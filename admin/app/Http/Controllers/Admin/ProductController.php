<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Color;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Rules\ProductImageFile;
use App\Support\UniqueSlug;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * WhatsApp JPEGs are sometimes saved with a .png extension. Laravel stores
     * with the real extension — keep file_name aligned for admin + exports.
     */
    private function uploadDisplayName(\Illuminate\Http\UploadedFile $file, string $storedPath): string
    {
        $original = (string) $file->getClientOriginalName();
        $storedExt = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        if ($storedExt === '' || ! ProductImageFile::isImageExtension($storedExt)) {
            return $original !== '' ? $original : basename($storedPath);
        }

        $clientExt = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($clientExt === $storedExt) {
            return $original !== '' ? $original : basename($storedPath);
        }

        if ($original !== '' && preg_match('/\.[^.]+$/', $original)) {
            return preg_replace('/\.[^.]+$/', '.'.$storedExt, $original) ?? $original;
        }

        $stem = $original !== '' ? pathinfo($original, PATHINFO_FILENAME) : 'image';

        return $stem.'.'.$storedExt;
    }

    private function normalizeWeight(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Strip all non-digit/non-dot characters (e.g., "500g" -> "500")
        $clean = preg_replace('/[^0-9.]/', '', $value);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    /**
     * Turns original + optional sale into product/variant price fields.
     * Sale only applies when it is strictly below the original price.
     *
     * @return array{price: float, price_min: float, price_max: float, compare_at_price: ?float}
     */
    private function resolveSalePricing(float $basePrice, mixed $salePrice): array
    {
        $sale = ($salePrice === null || $salePrice === '')
            ? null
            : (float) $salePrice;

        if ($sale === null || $sale >= $basePrice) {
            return [
                'price' => $basePrice,
                'price_min' => $basePrice,
                'price_max' => $basePrice,
                'compare_at_price' => null,
            ];
        }

        return [
            'price' => $sale,
            'price_min' => $sale,
            'price_max' => $basePrice,
            'compare_at_price' => $basePrice,
        ];
    }

    /**
     * Merges SEO fields into product metadata without wiping unrelated keys.
     *
     * @param  array{meta_title?: ?string, meta_description?: ?string, meta_keywords?: ?string}  $seo
     */
    private function seoMetadata(array $existing, array $seo): array
    {
        $meta = is_array($existing) ? $existing : [];

        foreach (['meta_title', 'meta_description', 'meta_keywords'] as $key) {
            $value = isset($seo[$key]) ? trim((string) $seo[$key]) : '';
            if ($value === '') {
                unset($meta[$key]);
            } else {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    /**
     * Guaranteed-unique product SKU in the existing catalog format: ZBR-XXXXXX.
     */
    private function generateUniqueSku(): string
    {
        do {
            $sku = 'ZBR-'.Str::upper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * Guaranteed-unique product slug derived from the product name.
     */
    private function uniqueProductSlug(string $name, ?string $ignoreId = null): string
    {
        return UniqueSlug::make(Product::class, $name, 'slug', $ignoreId, null, 'product');
    }

    /**
     * @return array<string, string> id => plain label (never raw JSON)
     */
    private function optionLabels($query): array
    {
        return $query->get()->mapWithKeys(fn ($row) => [
            $row->id => $row->translated_name,
        ])->all();
    }

    /** Category dropdown: plain translated labels (classification only). */
    private function categoryOptionLabels(): array
    {
        return Cache::remember(
            'admin.options.categories',
            now()->addMinutes(15),
            function () {
                return Category::query()
                    ->orderBy('name->en')
                    ->get()
                    ->mapWithKeys(fn (Category $category) => [
                        $category->id => $category->translated_name,
                    ])
                    ->all();
            }
        );
    }

    /**
     * Every collection, for the product form's multi-select. Collections are
     * merchandising groups, so the list is never filtered by the chosen
     * category — one collection may hold bags, shoes and belts together.
     *
     * @return list<array{id: string, label: string, is_published: bool}>
     */
    private function collectionChoices(): array
    {
        return Cache::remember(
            'admin.options.collection_choices',
            now()->addMinutes(15),
            fn () => Collection::query()
                ->orderBy('position')
                ->orderBy('name->en')
                ->get()
                ->map(fn (Collection $collection) => [
                    'id' => $collection->id,
                    'label' => $collection->translated_name,
                    'is_published' => (bool) $collection->is_published,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * Persists collection membership and busts every cache that lists the
     * product — pivot writes fire no model events, so nothing else would.
     *
     * @param  list<string>  $collectionIds
     */
    private function syncProductCollections(Product $product, array $collectionIds): void
    {
        $ids = array_values(array_unique(array_filter($collectionIds)));

        $payload = [];
        foreach ($ids as $position => $id) {
            $payload[$id] = ['position' => $position];
        }

        $changes = $product->collections()->sync($payload);

        if ($changes['attached'] === [] && $changes['detached'] === [] && $changes['updated'] === []) {
            return;
        }

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Http\Controllers\Api\CollectionController::forgetListCache();
        \App\Http\Controllers\Api\CategoryController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);
        \App\Services\StorefrontRevalidator::purgeCatalog();
    }

    /**
     * Collection ids to pre-check on the form: submitted values first, then
     * whatever is already saved (edit) or passed in the query string (create).
     *
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private function selectedCollectionIds(array $fallback): array
    {
        $selected = old('collection_ids', $fallback);

        return array_values(array_filter(array_map(
            fn ($id) => is_scalar($id) ? (string) $id : null,
            is_array($selected) ? $selected : [$selected],
        )));
    }

    /** Cached brand dropdowns for admin forms/filters. */
    private function cachedOptionLabels(string $cacheKey, $query): array
    {
        return Cache::remember(
            $cacheKey,
            now()->addMinutes(15),
            fn () => $this->optionLabels($query),
        );
    }

    /** Active sizes for the create/edit "Sizes" chip picker, in display order. */
    private function activeSizes()
    {
        $groupOrder = [
            Size::GROUP_SHOE => 1,
            Size::GROUP_APPAREL => 2,
            Size::GROUP_CUSTOM => 3,
        ];

        return Size::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Size $size) => [
                $groupOrder[$size->size_group] ?? 9,
                $size->sort_order,
                $size->name,
            ])
            ->values();
    }

    /**
     * Stores one or more photos and attaches them to every active variant of
     * a color — with sizes enabled a color spans several variants (one per
     * size), and they must all share the same photo set so the storefront
     * swaps the gallery no matter which size the shopper is viewing.
     *
     * New uploads are appended after whatever the color already has, so an
     * admin can build a multi-angle set across several saves.
     *
     * @param  iterable<ProductVariant>  $variants
     * @param  iterable<\Illuminate\Http\UploadedFile>  $files
     */
    private function attachColorPhotos(Product $product, iterable $variants, iterable $files, string $colorName): void
    {
        $photoIds = [];

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('products', 'public');
            $photo = Photo::create([
                'id' => (string) Str::uuid(),
                'imageable_type' => Product::class,
                'imageable_id' => $product->id,
                'storage_path' => $path,
                'file_name' => $this->uploadDisplayName($file, $path),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'alt_text' => ($product->name['en'] ?? 'Product').' - '.$colorName,
                'is_primary' => false,
            ]);

            $photoIds[] = $photo->id;
        }

        if (! $photoIds) {
            return;
        }

        foreach ($variants as $variant) {
            $position = (int) $variant->photos()->max('product_variant_photos.position');
            $attach = [];
            foreach ($photoIds as $photoId) {
                $attach[$photoId] = ['position' => ++$position];
            }
            $variant->photos()->syncWithoutDetaching($attach);
        }

        // Pivot attach alone does not fire model events — bump caches so the
        // storefront picks up the new color images immediately.
        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);
    }

    /**
     * Deletes color photos the admin unchecked on the edit page. Only photos
     * that actually belong to this product can be removed, so a crafted id
     * can never reach another product's media.
     *
     * @param  list<string>  $photoIds
     */
    private function removeColorPhotos(Product $product, array $photoIds): void
    {
        if (! $photoIds) {
            return;
        }

        $photos = Photo::query()
            ->whereIn('id', $photoIds)
            ->where('imageable_type', Product::class)
            ->where('imageable_id', $product->id)
            ->get();

        if ($photos->isEmpty()) {
            return;
        }

        DB::table('product_variant_photos')
            ->whereIn('photo_id', $photos->pluck('id'))
            ->delete();

        foreach ($photos as $photo) {
            Storage::disk('public')->delete((string) $photo->storage_path);
            $photo->delete();
        }

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);
    }

    /**
     * Normalizes a `color_images[key]` entry into a list of uploaded files.
     * The pickers post arrays, but a legacy single-file input may still
     * arrive from a cached page.
     *
     * @return list<\Illuminate\Http\UploadedFile>
     */
    private function uploadedFileList(mixed $entry): array
    {
        if (! $entry) {
            return [];
        }

        $files = is_array($entry) ? $entry : [$entry];

        return array_values(array_filter(
            $files,
            fn ($file) => $file instanceof \Illuminate\Http\UploadedFile && $file->isValid(),
        ));
    }

    /** Guaranteed-unique product_variants.sku, bumping a numeric suffix on collision. */
    private function uniqueVariantSku(string $base): string
    {
        $sku = $base;
        $n = 2;
        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$n;
            $n++;
        }

        return $sku;
    }

    /** Color-only variant (no size): today's default behavior, one variant per color. */
    private function syncColorOnlyVariant(Product $product, Color $color, int $stock, float $price, ?float $compareAtPrice = null): ProductVariant
    {
        $variant = ProductVariant::firstOrCreate(
            ['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => null],
            [
                'id' => (string) Str::uuid(),
                'sku' => $this->uniqueVariantSku($product->sku.'-'.Str::upper(Str::slug($color->name) ?: Str::random(4))),
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
                'stock' => $stock,
                'is_active' => true,
            ]
        );

        $variant->update([
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'stock' => $stock,
        ]);

        return $variant;
    }

    /**
     * One variant per (color, size) so stock is tracked per size — sizes with
     * no stock are still created (stock 0) so the storefront can offer and
     * cross them out rather than hiding them entirely.
     *
     * @param  \Illuminate\Support\Collection<int, Size>  $sizes
     * @param  array<string, string>  $sizeStocks  size id => stock
     * @return list<ProductVariant>
     */
    private function syncSizedVariantsForColor(Product $product, Color $color, $sizes, array $sizeStocks, float $price, ?float $compareAtPrice = null): array
    {
        $variants = [];

        foreach ($sizes as $size) {
            $stock = (int) ($sizeStocks[$size->id] ?? 0);
            $sku = $this->uniqueVariantSku(
                $product->sku.'-'.Str::upper(Str::slug($color->name) ?: Str::random(4)).'-'.Str::upper($size->name)
            );

            $variant = ProductVariant::firstOrCreate(
                ['product_id' => $product->id, 'color_id' => $color->id, 'size_id' => $size->id],
                [
                    'id' => (string) Str::uuid(),
                    'sku' => $sku,
                    'price' => $price,
                    'compare_at_price' => $compareAtPrice,
                    'stock' => $stock,
                    'is_active' => true,
                ]
            );

            $variant->update([
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
                'stock' => $stock,
            ]);
            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * Removes whichever variant "shape" no longer matches the product's
     * current size selection, so switching a product between color-only and
     * sized never leaves stale, unreachable stock rows behind. Uses the
     * model's soft delete, so past order lines still resolve their variant.
     */
    private function pruneVariantsForSizeMode(Product $product, bool $hasSizes): void
    {
        ProductVariant::where('product_id', $product->id)
            // Never touch the color-less legacy variant (no colors at all) —
            // sizes only ever apply to color variants.
            ->whereNotNull('color_id')
            ->when($hasSizes, fn ($q) => $q->whereNull('size_id'), fn ($q) => $q->whereNotNull('size_id'))
            ->delete();
    }

    /**
     * Creates/updates variants for one new color row from the "Color
     * Variants" repeater, each with its own optional photo — this is what
     * lets the storefront show real, professional color swatches instead
     * of a single free-text `color` field.
     *
     * @param  array<int, array{name?: string, hex?: string, stock?: string, size_stocks?: array<string,string>}>  $rows
     * @param  \Illuminate\Http\UploadedFile[]  $colorImages  keyed by the same row index
     * @param  \Illuminate\Support\Collection<int, Size>  $sizes  product's selected sizes (empty = color-only)
     * @return array<int, string> submitted row index => color ID
     */
    private function syncColorVariants(Product $product, array $rows, array $colorImages, float $price, $sizes, ?float $compareAtPrice = null): array
    {
        $colorIdsByRow = [];

        foreach ($rows as $index => $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $color = Color::firstOrCreate(
                ['name' => $name],
                ['id' => (string) Str::uuid(), 'hex' => $row['hex'] ?? '#000000', 'is_active' => true]
            );
            if (! empty($row['hex']) && $color->hex !== $row['hex']) {
                $color->update(['hex' => $row['hex']]);
            }
            $colorIdsByRow[(int) $index] = $color->id;

            $variants = $sizes->isNotEmpty()
                ? $this->syncSizedVariantsForColor($product, $color, $sizes, $row['size_stocks'] ?? [], $price, $compareAtPrice)
                : [$this->syncColorOnlyVariant($product, $color, (int) ($row['stock'] ?? 0), $price, $compareAtPrice)];

            $files = $this->uploadedFileList($colorImages[$index] ?? null);
            if ($files) {
                $this->attachColorPhotos($product, $variants, $files, $name);
            }
        }

        return $colorIdsByRow;
    }

    /**
     * Updates existing colors submitted from the edit page's "grouped by
     * color" rows — name/hex, stock, and photos. Shared Color records are
     * cloned on rename so other products keep their original swatch.
     *
     * @param  array<string, array{name?: string, hex?: string, stock?: string, size_stocks?: array<string,string>}>  $groups  color id => row
     * @param  \Illuminate\Http\UploadedFile[]  $colorImages  keyed by color id
     * @param  \Illuminate\Support\Collection<int, Size>  $sizes
     * @return array<string, string> submitted color id => resolved color id
     */
    private function syncColorGroups(Product $product, array $groups, array $colorImages, float $price, $sizes, ?float $compareAtPrice = null): array
    {
        $colorIdMap = [];

        foreach ($groups as $colorId => $group) {
            $color = $this->resolveEditableColor($product, (string) $colorId, is_array($group) ? $group : []);
            if (! $color) {
                continue;
            }

            $colorIdMap[(string) $colorId] = $color->id;

            if ($sizes->isNotEmpty()) {
                $variants = $this->syncSizedVariantsForColor(
                    $product,
                    $color,
                    $sizes,
                    $group['size_stocks'] ?? [],
                    $price,
                    $compareAtPrice,
                );
            } elseif (array_key_exists('stock', $group)) {
                $variants = [$this->syncColorOnlyVariant(
                    $product,
                    $color,
                    (int) ($group['stock'] ?? 0),
                    $price,
                    $compareAtPrice,
                )];
            } else {
                // Color-only products keep stock on existing_variants[*]; here we
                // only need the live variants so new photos can attach.
                $variants = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('color_id', $color->id)
                    ->get()
                    ->all();
            }

            $files = $this->uploadedFileList($colorImages[$colorId] ?? null);
            if ($files) {
                $this->attachColorPhotos($product, $variants, $files, $color->name);
            }
        }

        return $colorIdMap;
    }

    /**
     * Apply name/hex edits for one product color without breaking other
     * products that share the same Color row.
     *
     * @param  array{name?: string, hex?: string}  $group
     */
    private function resolveEditableColor(Product $product, string $colorId, array $group): ?Color
    {
        $color = Color::find($colorId);
        if (! $color) {
            return null;
        }

        $newName = trim((string) ($group['name'] ?? $color->name));
        if ($newName === '') {
            $newName = $color->name;
        }

        $newHex = (string) ($group['hex'] ?? $color->hex ?? '#000000');
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $newHex)) {
            $newHex = $color->hex ?: '#000000';
        }

        if ($newName === $color->name) {
            if ($newHex !== $color->hex) {
                $color->update(['hex' => $newHex]);
            }

            return $color->fresh();
        }

        $existingByName = Color::query()
            ->where('name', $newName)
            ->where('id', '!=', $color->id)
            ->first();

        if ($existingByName) {
            ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('color_id', $color->id)
                ->update(['color_id' => $existingByName->id]);

            if ($existingByName->hex !== $newHex) {
                $existingByName->update(['hex' => $newHex]);
            }

            return $existingByName->fresh();
        }

        $shared = ProductVariant::query()
            ->where('color_id', $color->id)
            ->where('product_id', '!=', $product->id)
            ->exists();

        if ($shared) {
            $cloned = Color::create([
                'id' => (string) Str::uuid(),
                'name' => $newName,
                'hex' => $newHex,
                'is_active' => true,
            ]);

            ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('color_id', $color->id)
                ->update(['color_id' => $cloned->id]);

            return $cloned;
        }

        $color->update([
            'name' => $newName,
            'hex' => $newHex,
        ]);

        return $color->fresh();
    }

    /**
     * Guarantees exactly one default *color* among a product's live variants.
     * With sizes enabled a color spans several variants, so "default" is
     * stored on every size variant of the winning color — the API resource
     * only needs to check "any variant of this color is_default".
     *
     * The admin radio submits either "existing:{colorId}" or "new:{rowIndex}".
     * Invalid/stale values (for example, a selected row removed in the browser)
     * safely fall back to the current default, then the oldest remaining color.
     *
     * @param  array<int, string>  $newColorIdsByRow  row index => color ID, from syncColorVariants()
     */
    private function setDefaultColorVariant(Product $product, ?string $selection, array $newColorIdsByRow = []): void
    {
        DB::transaction(function () use ($product, $selection, $newColorIdsByRow): void {
            $variants = ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereNotNull('color_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($variants->isEmpty()) {
                return;
            }

            $colorIdsInOrder = $variants->pluck('color_id')->unique()->values();
            $selectedColorId = null;

            if ($selection && str_starts_with($selection, 'new:')) {
                $rowIndex = filter_var(substr($selection, 4), FILTER_VALIDATE_INT);
                if ($rowIndex !== false) {
                    $selectedColorId = $newColorIdsByRow[$rowIndex] ?? null;
                }
            } elseif ($selection && str_starts_with($selection, 'existing:')) {
                $selectedColorId = substr($selection, 9);
            }

            if ($selectedColorId && ! $colorIdsInOrder->contains($selectedColorId)) {
                $selectedColorId = null;
            }

            $selectedColorId ??= $variants->firstWhere('is_default', true)?->color_id;
            $selectedColorId ??= $colorIdsInOrder->first();

            ProductVariant::where('product_id', $product->id)
                ->whereNotNull('color_id')
                ->update(['is_default' => false]);
            ProductVariant::where('product_id', $product->id)
                ->where('color_id', $selectedColorId)
                ->update(['is_default' => true]);
        });

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);
    }

    public function index(Request $request)
    {
        // Skip full variants/gallery trees — index only needs a thumbnail,
        // list price, and aggregate stock (withSum subquery).
        $query = Product::query()
            ->with(['category:id,name', 'brand:id,name', 'photos'])
            ->withSum('variants as variants_stock_sum', 'stock');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Brand filter
        if ($request->filled('brand')) {
            $query->where('brand_id', $request->brand);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $catalogTotal = Product::query()->count();
        $filteredTotal = $products->total();
        $activeOnPage = $products->where('status', 'active')->count();

        $activeTotal = Product::query()->where('status', 'active')->count();
        $inactiveTotal = max(0, $catalogTotal - $activeTotal);

        $stockHealth = DB::query()
            ->fromSub(
                Product::query()
                    ->select('products.id')
                    ->selectRaw('COALESCE(SUM(product_variants.stock), 0) as units')
                    ->leftJoin('product_variants', 'product_variants.product_id', '=', 'products.id')
                    ->groupBy('products.id'),
                'product_units',
            )
            ->selectRaw('COUNT(*) as products')
            ->selectRaw('COALESCE(SUM(units), 0) as total_units')
            ->selectRaw('SUM(CASE WHEN units = 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->selectRaw('SUM(CASE WHEN units > 0 AND units <= 10 THEN 1 ELSE 0 END) as low_stock')
            ->selectRaw('SUM(CASE WHEN units > 10 THEN 1 ELSE 0 END) as healthy_stock')
            ->first();

        $dashboard = [
            'active' => $activeTotal,
            'inactive' => $inactiveTotal,
            'out_of_stock' => (int) ($stockHealth->out_of_stock ?? 0),
            'low_stock' => (int) ($stockHealth->low_stock ?? 0),
            'healthy_stock' => (int) ($stockHealth->healthy_stock ?? 0),
            'total_units' => (int) ($stockHealth->total_units ?? 0),
        ];

        $topOrderedRows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('product_variants', 'order_items.variant_id', '=', 'product_variants.id')
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->whereRaw('COALESCE(order_items.product_id, product_variants.product_id) IS NOT NULL')
            ->selectRaw('COALESCE(order_items.product_id, product_variants.product_id) as product_id')
            ->selectRaw('SUM(order_items.qty) as units_ordered')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders_count')
            ->selectRaw('COALESCE(SUM(order_items.line_total), 0) as revenue')
            ->groupByRaw('COALESCE(order_items.product_id, product_variants.product_id)')
            ->orderByDesc('units_ordered')
            ->limit(8)
            ->get();

        $topOrderedProducts = collect();
        if ($topOrderedRows->isNotEmpty()) {
            $rankedProducts = Product::query()
                ->with(['photos', 'brand:id,name', 'category:id,name'])
                ->whereIn('id', $topOrderedRows->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $topOrderedProducts = $topOrderedRows
                ->map(function ($row) use ($rankedProducts) {
                    $product = $rankedProducts->get($row->product_id);
                    if (! $product) {
                        return null;
                    }

                    $product->units_ordered = (int) $row->units_ordered;
                    $product->orders_count = (int) $row->orders_count;
                    $product->order_revenue = (float) $row->revenue;

                    return $product;
                })
                ->filter()
                ->values();
        }

        $categories = $this->categoryOptionLabels();
        $brands = $this->cachedOptionLabels('admin.options.brands', Brand::query());

        return view('admin.products.index', compact(
            'products',
            'categories',
            'brands',
            'catalogTotal',
            'filteredTotal',
            'activeOnPage',
            'dashboard',
            'topOrderedProducts',
        ));
    }

    public function create(Request $request)
    {
        $collections = $this->collectionChoices();
        $categories = $this->categoryOptionLabels();
        $brands = $this->cachedOptionLabels('admin.options.brands', Brand::query());
        $sizes = $this->activeSizes();
        $autoSku = $this->generateUniqueSku();
        $selectedCategoryId = old('category_id', $request->query('category_id'));
        $selectedBrandId = old('brand_id', $request->query('brand_id'));
        // Only an explicit ?collection_id= preselects a collection — never the
        // category, which no longer implies membership.
        $selectedCollectionIds = $this->selectedCollectionIds(
            array_filter([$request->query('collection_id')]),
        );

        return view('admin.products.create', compact(
            'collections',
            'categories',
            'brands',
            'sizes',
            'autoSku',
            'selectedCategoryId',
            'selectedBrandId',
            'selectedCollectionIds',
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_description_en' => 'nullable|string',
            'short_description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'exists:collections,id',
            'brand_id' => 'required|exists:brands,id',
            'base_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0|lt:base_price',
            'material' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'weight' => 'nullable|string|max:100', // accept string input like "500g"
            'stock_quantity' => 'required|integer|min:0',
            'images.*' => ['nullable', 'file', 'max:2097152', new ProductImageFile],
            'colors' => 'nullable|array',
            'colors.*.name' => 'nullable|string|max:50',
            'colors.*.hex' => 'nullable|string|max:7',
            'colors.*.stock' => 'nullable|integer|min:0',
            'colors.*.size_stocks' => 'nullable|array',
            'colors.*.size_stocks.*' => 'nullable|integer|min:0',
            'color_images' => 'nullable|array',
            'color_images.*' => 'nullable',
            'color_images.*.*' => ['nullable', 'file', 'max:2097152', new ProductImageFile],
            'default_color' => 'nullable|string|max:80',
            'size_ids' => 'nullable|array',
            'size_ids.*' => 'exists:sizes,id',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:180',
            'meta_keywords' => 'nullable|string|max:255',
        ], [
            'sale_price.lt' => 'Sale price must be lower than the original price.',
            'images.*.max' => 'Each product image may be at most 2 GB.',
            'images.*.uploaded' => 'A product image failed to upload. Try a smaller image (under 2 GB).',
            'color_images.*.*.max' => 'Each color photo may be at most 2 GB.',
            'color_images.*.*.uploaded' => 'A color photo failed to upload. Try a smaller image (under 2 GB).',
        ]);

        // SKU + slug are system-owned: never taken from the form.
        $sku = $this->generateUniqueSku();
        $slug = $this->uniqueProductSlug($validated['name']);
        $pricing = $this->resolveSalePricing(
            (float) $validated['base_price'],
            $validated['sale_price'] ?? null,
        );

        // Prepare data
        $productData = [
            'id' => (string) Str::uuid(),
            'sku' => $sku,
            'name' => [
                'en' => $validated['name'],
                'ar' => $validated['name'],
            ],
            'slug' => $slug,
            'short_description' => [
                'en' => $validated['short_description_en'] ?? '',
                'ar' => $validated['short_description_ar'] ?? '',
            ],
            'description' => [
                'en' => $validated['description_en'] ?? '',
                'ar' => $validated['description_ar'] ?? '',
            ],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'],
            'price' => $pricing['price'],
            'price_min' => $pricing['price_min'],
            'price_max' => $pricing['price_max'],
            'material' => $validated['material'] ?? null,
            'color' => $validated['color'] ?? null,
            'weight_kg' => $this->normalizeWeight($validated['weight'] ?? null),
            'metadata' => $this->seoMetadata([], $validated),
            // "Active" toggle defaults to on so a product created without
            // touching it is immediately visible, matching what the toggle's
            // own help text promises.
            'status' => $request->boolean('is_active', true) ? 'active' : 'draft',
            'visibility' => true,
            'featured' => $request->boolean('is_featured'),
            'in_stock' => true,
        ];

        // Create product
        $product = Product::create($productData);

        $this->syncProductCollections($product, $validated['collection_ids'] ?? []);

        $product->sizes()->sync($validated['size_ids'] ?? []);
        $sizes = $product->sizes()->get();

        $colorRows = array_filter($validated['colors'] ?? [], fn ($row) => trim($row['name'] ?? '') !== '');

        if (! empty($colorRows)) {
            // Colors were provided via the repeater: each one becomes its
            // own purchasable variant (one per size when sizes are selected),
            // so skip the single generic variant.
            $newColorIds = $this->syncColorVariants(
                $product,
                $colorRows,
                $request->file('color_images', []),
                $pricing['price'],
                $sizes,
                $pricing['compare_at_price'],
            );
            $this->setDefaultColorVariant($product, $validated['default_color'] ?? null, $newColorIds);
        } else {
            ProductVariant::create([
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'sku' => $sku.'-DEFAULT',
                'price' => $pricing['price'],
                'compare_at_price' => $pricing['compare_at_price'],
                'weight_kg' => $this->normalizeWeight($validated['weight'] ?? null),
                'stock' => $request->input('stock_quantity', 0),
                'stock_reserved' => 0,
                'is_active' => true,
                'attributes' => [
                    'color' => $validated['color'] ?? null,
                    'material' => $validated['material'] ?? null,
                ],
            ]);
        }

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                if (! $image || ! $image->isValid()) {
                    continue;
                }
                $path = $image->store('products', 'public');

                Photo::create([
                    'id' => (string) Str::uuid(),
                    'imageable_type' => Product::class,
                    'imageable_id' => $product->id,
                    'storage_path' => $path,
                    'file_name' => $this->uploadDisplayName($image, $path),
                    'mime_type' => $image->getMimeType(),
                    'file_size' => $image->getSize(),
                    'alt_text' => $product->name['en'] ?? 'Product image',
                    'is_primary' => $index === 0,
                    'position' => $index,
                ]);
            }
        }

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);
        \App\Services\StorefrontRevalidator::purgeCatalog();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product created successfully!');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'collections', 'brand', 'photos', 'variants.color', 'variants.size', 'reviews.user']);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $collections = $this->collectionChoices();
        $categories = $this->categoryOptionLabels();
        $brands = $this->cachedOptionLabels('admin.options.brands', Brand::query());
        $sizes = $this->activeSizes();
        $product->load(['variants.color', 'variants.size', 'variants.photos', 'category', 'sizes', 'collections']);
        $selectedCategoryId = old('category_id', $product->category_id);
        $selectedCollectionIds = $this->selectedCollectionIds($product->collections->pluck('id')->all());

        return view('admin.products.edit', compact(
            'product',
            'collections',
            'categories',
            'brands',
            'sizes',
            'selectedCategoryId',
            'selectedCollectionIds',
        ));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_description_en' => 'nullable|string',
            'short_description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'exists:collections,id',
            'brand_id' => 'required|exists:brands,id',
            'base_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0|lt:base_price',
            'material' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'weight' => 'nullable|string|max:100',
            'stock_quantity' => 'required|integer|min:0',
            'images.*' => ['nullable', 'file', 'max:2097152', new ProductImageFile],
            'colors' => 'nullable|array',
            'colors.*.name' => 'nullable|string|max:50',
            'colors.*.hex' => 'nullable|string|max:7',
            'colors.*.stock' => 'nullable|integer|min:0',
            'colors.*.size_stocks' => 'nullable|array',
            'colors.*.size_stocks.*' => 'nullable|integer|min:0',
            'color_images' => 'nullable|array',
            'color_images.*' => 'nullable',
            'color_images.*.*' => ['nullable', 'file', 'max:2097152', new ProductImageFile],
            'variant_images' => 'nullable|array',
            'variant_images.*' => 'nullable',
            'variant_images.*.*' => ['nullable', 'file', 'max:2097152', new ProductImageFile],
            'remove_color_photos' => 'nullable|array',
            'remove_color_photos.*' => 'string',
            'existing_variants' => 'nullable|array',
            'existing_variants.*.stock' => 'nullable|integer|min:0',
            'color_groups' => 'nullable|array',
            'color_groups.*.name' => 'nullable|string|max:50',
            'color_groups.*.hex' => 'nullable|string|max:7',
            'color_groups.*.stock' => 'nullable|integer|min:0',
            'color_groups.*.size_stocks' => 'nullable|array',
            'color_groups.*.size_stocks.*' => 'nullable|integer|min:0',
            'remove_variants' => 'nullable|array',
            'remove_variants.*' => 'string|exists:product_variants,id',
            'remove_colors' => 'nullable|array',
            'remove_colors.*' => 'string|exists:colors,id',
            'default_color' => 'nullable|string|max:80',
            'size_ids' => 'nullable|array',
            'size_ids.*' => 'exists:sizes,id',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:180',
            'meta_keywords' => 'nullable|string|max:255',
        ], [
            'sale_price.lt' => 'Sale price must be lower than the original price.',
            'images.*.max' => 'Each product image may be at most 2 GB.',
            'images.*.uploaded' => 'A product image failed to upload. Try a smaller image (under 2 GB).',
            'color_images.*.*.max' => 'Each color photo may be at most 2 GB.',
            'color_images.*.*.uploaded' => 'A color photo failed to upload. Try a smaller image (under 2 GB).',
            'variant_images.*.*.max' => 'Each variant photo may be at most 2 GB.',
            'variant_images.*.*.uploaded' => 'A variant photo failed to upload. Try a smaller image (under 2 GB).',
        ]);

        $pricing = $this->resolveSalePricing(
            (float) $validated['base_price'],
            $validated['sale_price'] ?? null,
        );

        // SKU + slug stay locked after create — never overwrite from the form.
        $product->update([
            'name' => [
                'en' => $validated['name'],
                'ar' => $validated['name'],
            ],
            'short_description' => [
                'en' => $validated['short_description_en'] ?? '',
                'ar' => $validated['short_description_ar'] ?? '',
            ],
            'description' => [
                'en' => $validated['description_en'] ?? '',
                'ar' => $validated['description_ar'] ?? '',
            ],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'],
            'price' => $pricing['price'],
            'price_min' => $pricing['price_min'],
            'price_max' => $pricing['price_max'],
            'material' => $validated['material'] ?? null,
            'color' => $validated['color'] ?? null,
            'weight_kg' => $this->normalizeWeight($validated['weight'] ?? null),
            'metadata' => $this->seoMetadata($product->metadata ?? [], $validated),
            'status' => $request->boolean('is_active') ? 'active' : 'draft',
            'featured' => $request->boolean('is_featured'),
        ]);

        $this->syncProductCollections($product, $validated['collection_ids'] ?? []);

        // Sync selected sizes, then drop whichever variant "shape" no longer
        // applies (color-only rows if sizes were just enabled, or leftover
        // per-size rows if sizes were just cleared) before rebuilding below.
        $product->sizes()->sync($validated['size_ids'] ?? []);
        $sizes = $product->sizes()->get();
        $this->pruneVariantsForSizeMode($product, $sizes->isNotEmpty());

        // Remove any colors (all their size variants) or single legacy
        // variants the admin explicitly deleted.
        if (! empty($validated['remove_colors'])) {
            ProductVariant::where('product_id', $product->id)
                ->whereIn('color_id', $validated['remove_colors'])
                ->delete();
        }
        if (! empty($validated['remove_variants'])) {
            ProductVariant::whereIn('id', $validated['remove_variants'])
                ->where('product_id', $product->id)
                ->delete();
        }

        $price = $pricing['price'];
        $compareAtPrice = $pricing['compare_at_price'];
        $colorImages = $request->file('color_images', []) ?? [];

        // Drop unchecked color photos first so a replace (remove + upload in
        // the same save) ends with only the newly chosen set.
        $this->removeColorPhotos($product, $validated['remove_color_photos'] ?? []);

        // Update name / hex / stock / photos on existing colors grouped by color id
        // (sized products — one row covers every size of that color).
        $colorIdMap = [];
        if (! empty($validated['color_groups'])) {
            $colorIdMap = $this->syncColorGroups($product, $validated['color_groups'], $colorImages, $price, $sizes, $compareAtPrice);
        }

        // Update stock / photos on existing color-only variants (no sizes).
        $variantImages = $request->file('variant_images', []) ?? [];
        foreach ($validated['existing_variants'] ?? [] as $variantId => $row) {
            $variant = ProductVariant::with('color')
                ->where('product_id', $product->id)
                ->whereNotNull('color_id')
                ->find($variantId);

            if (! $variant) {
                continue;
            }

            $variant->update([
                'stock' => (int) ($row['stock'] ?? $variant->stock),
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
            ]);

            $files = $this->uploadedFileList(
                $variantImages[$variantId] ?? $colorImages[$variant->color_id] ?? null,
            );
            if ($files) {
                $this->attachColorPhotos($product, [$variant], $files, $variant->color->name ?? 'Color');
            }
        }

        $colorRows = array_filter($validated['colors'] ?? [], fn ($row) => trim($row['name'] ?? '') !== '');
        $newColorIds = [];

        if (! empty($colorRows)) {
            $newColorIds = $this->syncColorVariants($product, $colorRows, $colorImages, $price, $sizes, $compareAtPrice);
        }

        $defaultColor = $validated['default_color'] ?? null;
        if (is_string($defaultColor) && str_starts_with($defaultColor, 'existing:')) {
            $oldDefaultId = substr($defaultColor, strlen('existing:'));
            if (isset($colorIdMap[$oldDefaultId])) {
                $defaultColor = 'existing:'.$colorIdMap[$oldDefaultId];
            }
        }
        $this->setDefaultColorVariant($product, $defaultColor, $newColorIds);

        // Legacy single-variant path: only touches the first variant, and
        // only when the product isn't using color variants at all.
        $variant = $product->variants()->whereNull('color_id')->first();
        if ($variant && isset($validated['stock_quantity']) && empty($colorRows)) {
            $variant->update([
                'stock' => $validated['stock_quantity'],
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
                'weight_kg' => $this->normalizeWeight($validated['weight'] ?? null),
                'attributes' => [
                    'color' => $validated['color'] ?? null,
                    'material' => $validated['material'] ?? null,
                ],
            ]);
        }

        // Handle new image uploads
        if ($request->hasFile('images')) {
            $this->attachProductGalleryImages($product, $request->file('images'));
        }

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);
        \App\Services\StorefrontRevalidator::purgeCatalog();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product updated successfully!');
    }

    public function destroy(Product $product)
    {
        try {
            $message = DB::transaction(function () use ($product) {
                return $this->deleteProductRecord($product);
            });
        } catch (QueryException $e) {
            report($e);

            return redirect()
                ->route('admin.products.index')
                ->with('error', 'Could not delete this product because related records still depend on it. Remove cart/order blockers and try again.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.products.index')
                ->with('error', 'Could not delete this product. Please try again.');
        }

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeCatalog();

        return redirect()
            ->route('admin.products.index')
            ->with('success', $message);
    }

    /**
     * Delete many products from the index selection.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'required|uuid|exists:products,id',
        ]);

        $ids = array_values(array_unique($validated['ids']));
        $deleted = 0;
        $orderLinesKept = 0;

        try {
            DB::transaction(function () use ($ids, &$deleted, &$orderLinesKept) {
                $products = Product::query()
                    ->with('photos')
                    ->whereIn('id', $ids)
                    ->get();

                foreach ($products as $product) {
                    $result = $this->deleteProductRecord($product, returnMeta: true);
                    $deleted++;
                    $orderLinesKept += $result['order_lines'];
                }
            });
        } catch (QueryException $e) {
            report($e);

            return redirect()
                ->route('admin.products.index')
                ->with('error', 'Could not delete the selected products because related records still depend on them.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.products.index')
                ->with('error', 'Could not delete the selected products. Please try again.');
        }

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeCatalog();

        $message = $deleted === 1
            ? '1 product deleted successfully.'
            : "{$deleted} products deleted successfully.";

        if ($orderLinesKept > 0) {
            $message .= ' '.$orderLinesKept.' past order line'.($orderLinesKept === 1 ? '' : 's').' kept for history.';
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', $message);
    }

    /**
     * Shared catalog delete used by single + bulk destroy.
     *
     * @return ($returnMeta is true ? array{message: string, order_lines: int} : string)
     */
    private function deleteProductRecord(Product $product, bool $returnMeta = false): array|string
    {
        $product->loadMissing('photos');
        $variantIds = $product->variants()->pluck('id');
        $orderLines = OrderItem::query()
            ->where(function ($query) use ($product, $variantIds) {
                $query->where('product_id', $product->id);
                if ($variantIds->isNotEmpty()) {
                    $query->orWhereIn('variant_id', $variantIds);
                }
            })
            ->count();

        if ($variantIds->isNotEmpty()) {
            CartItem::query()->whereIn('variant_id', $variantIds)->delete();
        }

        OrderItem::query()->where('product_id', $product->id)->update([
            'product_id' => null,
        ]);
        if ($variantIds->isNotEmpty()) {
            OrderItem::query()->whereIn('variant_id', $variantIds)->update([
                'variant_id' => null,
            ]);
        }

        foreach ($product->photos as $photo) {
            if ($photo->storage_path) {
                Storage::disk('public')->delete($photo->storage_path);
            }
            $photo->delete();
        }

        $product->delete();

        $message = 'Product deleted successfully.';
        if ($orderLines > 0) {
            $message .= ' '.$orderLines.' past order line'.($orderLines === 1 ? '' : 's').' kept for history.';
        }

        if ($returnMeta) {
            return [
                'message' => $message,
                'order_lines' => $orderLines,
            ];
        }

        return $message;
    }

    public function deleteImage(string $photoId)
    {
        $photo = Photo::findOrFail($photoId);

        // Color-variant photos are managed from Color Variants — deleting them
        // here would silently break storefront swatch image swaps.
        if (\Illuminate\Support\Facades\DB::table('product_variant_photos')
            ->where('photo_id', $photo->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This photo belongs to a color variant. Replace or remove it from Color Variants instead.',
            ], 422);
        }

        $product = $photo->imageable_type === Product::class
            ? Product::find($photo->imageable_id)
            : null;
        $wasPrimary = (bool) $photo->is_primary;

        Storage::disk('public')->delete($photo->storage_path);
        $photo->delete();

        if ($product && $wasPrimary) {
            $next = $product->photos()
                ->whereNotIn('id', $this->variantPhotoIdsFor($product))
                ->orderBy('position')
                ->first();

            if ($next) {
                $next->update(['is_primary' => true, 'position' => 0]);
            }
        }

        if ($product) {
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeProduct($product);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Append gallery images on edit without resubmitting the whole product form.
     * Used by the admin uploader to stage files one-by-one (faster + resumable).
     */
    public function storeImages(Request $request, Product $product)
    {
        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => ['required', 'file', 'max:2097152', new ProductImageFile],
        ], [
            'images.*.max' => 'Each product image may be at most 2 GB.',
            'images.*.uploaded' => 'A product image failed to upload. Try a smaller image (under 2 GB).',
        ]);

        $created = $this->attachProductGalleryImages($product, $request->file('images'));

        return response()->json([
            'success' => true,
            'photos' => $created,
            'count' => count($created),
        ]);
    }

    /**
     * Mark any product photo as the storefront hero (cards + default PDP),
     * including photos that also belong to a color variant.
     */
    public function setPrimaryImage(string $photoId)
    {
        $photo = Photo::findOrFail($photoId);

        if ($photo->imageable_type !== Product::class) {
            return response()->json([
                'success' => false,
                'message' => 'This photo is not attached to a product.',
            ], 422);
        }

        $product = Product::findOrFail($photo->imageable_id);
        $variantPhotoIds = $this->variantPhotoIdsFor($product);
        $isColorPhoto = in_array($photo->id, $variantPhotoIds, true);

        DB::transaction(function () use ($product, $photo, $variantPhotoIds, $isColorPhoto) {
            // Always clear every product photo — shared/variant-linked rows can
            // still carry is_primary from seeders/repairs and would leave two Mains.
            $product->photos()->update(['is_primary' => false]);

            $photo->update(['is_primary' => true, 'position' => 0]);

            // Keep gallery ordering tidy for non-color siblings only.
            if (! $isColorPhoto) {
                $siblings = $product->photos()
                    ->where('id', '!=', $photo->id)
                    ->whereNotIn('id', $variantPhotoIds)
                    ->orderBy('position')
                    ->get();

                foreach ($siblings as $index => $sibling) {
                    $sibling->update(['position' => $index + 1]);
                }
            }
        });

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);

        return response()->json(['success' => true]);
    }

    /**
     * Promote a color photo to the first slot for every size variant of that color.
     * Storefront color.image / color.images[0] follow pivot position.
     * Always promotes the photo to product is_primary so listing cards and the
     * website cover use this image, regardless of which color is Primary.
     */
    public function setColorPhotoPrimary(Product $product, string $photoId)
    {
        $photo = Photo::findOrFail($photoId);

        $linkedVariantIds = \Illuminate\Support\Facades\DB::table('product_variant_photos')
            ->where('photo_id', $photo->id)
            ->pluck('variant_id');

        $linked = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $linkedVariantIds)
            ->get();

        if ($linked->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'This photo is not linked to a color on this product.',
            ], 422);
        }

        $colorId = $linked->first()->color_id;
        $colorVariants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('color_id', $colorId)
            ->with('photos')
            ->get();

        DB::transaction(function () use ($colorVariants, $photo, $product) {
            foreach ($colorVariants as $variant) {
                $photos = $variant->photos;
                if ($photos->isEmpty()) {
                    continue;
                }

                if (! $photos->contains('id', $photo->id)) {
                    continue;
                }

                $rest = $photos->reject(fn (Photo $p) => $p->id === $photo->id)->values();
                $sync = [$photo->id => ['position' => 0]];
                foreach ($rest as $index => $sibling) {
                    $sync[$sibling->id] = ['position' => $index + 1];
                }

                $variant->photos()->sync($sync);
            }

            $product->photos()->update(['is_primary' => false]);
            $photo->update(['is_primary' => true, 'position' => 0]);
        });

        \App\Http\Controllers\Api\ProductController::forgetListCache();
        \App\Services\StorefrontRevalidator::purgeProduct($product);

        return response()->json(['success' => true]);
    }

    /**
     * @return list<string>
     */
    private function variantPhotoIdsFor(Product $product): array
    {
        return \Illuminate\Support\Facades\DB::table('product_variant_photos')
            ->join('product_variants', 'product_variants.id', '=', 'product_variant_photos.variant_id')
            ->where('product_variants.product_id', $product->id)
            ->pluck('product_variant_photos.photo_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<\Illuminate\Http\UploadedFile|null>  $images
     * @return list<array{id: string, url: string, file_name: string}>
     */
    private function attachProductGalleryImages(Product $product, iterable $images): array
    {
        $variantPhotoIds = $this->variantPhotoIdsFor($product);
        $existingPhotosCount = $product->photos()
            ->whereNotIn('id', $variantPhotoIds)
            ->count();
        $nextPosition = (int) $product->photos()
            ->whereNotIn('id', $variantPhotoIds)
            ->max('position');

        $created = [];
        foreach ($images as $index => $image) {
            if (! $image || ! $image->isValid()) {
                continue;
            }
            $path = $image->store('products', 'public');

            $photo = Photo::create([
                'id' => (string) Str::uuid(),
                'imageable_type' => Product::class,
                'imageable_id' => $product->id,
                'storage_path' => $path,
                'file_name' => $this->uploadDisplayName($image, $path),
                'mime_type' => $image->getMimeType(),
                'file_size' => $image->getSize(),
                'alt_text' => $product->name['en'] ?? 'Product image',
                'is_primary' => ($existingPhotosCount === 0 && $index === 0),
                'position' => $existingPhotosCount === 0
                    ? $index
                    : ($nextPosition + $index + 1),
            ]);

            $created[] = [
                'id' => $photo->id,
                'url' => $photo->url,
                'file_name' => $photo->file_name,
            ];
        }

        if ($created !== []) {
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeProduct($product);
        }

        return $created;
    }
}
