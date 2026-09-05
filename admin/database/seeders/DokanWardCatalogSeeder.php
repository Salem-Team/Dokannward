<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\ProductController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Collection;
use App\Models\Photo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Imports the live Dokan Ward WooCommerce catalog (scraped from dokannward.com)
 * into the admin DB: categories, brand, products, variants, and local photos.
 *
 * Source: scrape/dokannward/data/catalog.json
 * Images: storage/app/public/products/dokannward/{id}-{n}.ext
 *
 * Safe to re-run — keyed by stable product slug / SKU.
 */
class DokanWardCatalogSeeder extends Seeder
{
    /** @var array<string, string> */
    private array $categoryAr = [
        'bakhoor-burners' => 'بخور ومباخر',
        'boho-style' => 'ستايل بوهيمي',
        'candle-holder' => 'حامل شموع',
        'decoration-products' => 'منتجات ديكور',
        'diffusers' => 'فواحات',
        'flowers' => 'ورود',
        'lamps' => 'إضاءة',
        'ramadan-products' => 'منتجات رمضان',
        'sculpture' => 'منحوتات',
        'small-artificial-plants' => 'نباتات صناعية',
        'tissue-boxes' => 'علب مناديل',
        'trays' => 'صواني',
        'trees' => 'أشجار',
        'vases' => 'فازات',
        'wall-art-clocks' => 'لوحات وساعات',
    ];

    public function run(): void
    {
        $catalogPath = $this->resolveCatalogPath();
        if ($catalogPath === null) {
            $this->command?->error('catalog.json not found. Run the scrape first.');

            return;
        }

        $payload = json_decode((string) File::get($catalogPath), true);
        $products = $payload['products'] ?? [];
        $categories = $payload['categories'] ?? [];

        if ($products === []) {
            $this->command?->error('No products in catalog.json');

            return;
        }

        $this->ensureProductImages();

        $brand = $this->seedBrand();
        $color = Color::firstOrCreate(
            ['name' => 'Default'],
            ['id' => (string) Str::uuid(), 'hex' => '#B49480', 'is_active' => true]
        );
        $collection = $this->seedHomeCollection();
        $categoryMap = $this->seedCategories($categories, $collection);

        $featured = 0;
        foreach ($products as $index => $item) {
            $this->seedProduct($item, $brand, $color, $categoryMap, $featured < 12);
            $featured++;
        }

        ProductController::forgetListCache();
        $this->command?->info('Dokan Ward catalog seeded: '.count($products).' products, '.count($categoryMap).' categories.');
    }

    private function resolveCatalogPath(): ?string
    {
        $candidates = [
            base_path('../scrape/dokannward/data/catalog.json'),
            base_path('scrape/dokannward/data/catalog.json'),
            dirname(base_path()).'/scrape/dokannward/data/catalog.json',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function ensureProductImages(): void
    {
        $pairs = [
            [base_path('../public/images/products'), storage_path('app/public/products/dokannward')],
            [dirname(base_path()).'/public/images/products', storage_path('app/public/products/dokannward')],
            [base_path('../public/images/categories'), storage_path('app/public/categories/dokannward')],
            [dirname(base_path()).'/public/images/categories', storage_path('app/public/categories/dokannward')],
        ];

        foreach ($pairs as [$src, $dest]) {
            if (! is_dir($src)) {
                continue;
            }
            File::ensureDirectoryExists($dest);
            foreach (File::files($src) as $file) {
                $target = $dest.DIRECTORY_SEPARATOR.$file->getFilename();
                if (! is_file($target) || filesize($target) !== $file->getSize()) {
                    File::copy($file->getPathname(), $target);
                }
            }
        }
    }

    private function seedBrand(): Brand
    {
        return Brand::firstOrCreate(
            ['slug' => 'dokan-ward'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Dokan Ward', 'ar' => 'دكان ورد'],
                'description' => [
                    'en' => 'Premium home décor curated by Dokan Ward since 2018.',
                    'ar' => 'ديكور منزلي راقٍ من دكان ورد منذ ٢٠١٨.',
                ],
                'country' => 'Egypt',
                'logo_url' => '/images/dokan-ward-logo.png',
            ]
        );
    }

    private function seedHomeCollection(): Collection
    {
        return Collection::firstOrCreate(
            ['slug->en' => 'home-decor'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Home Decor', 'ar' => 'ديكور المنزل'],
                'slug' => ['en' => 'home-decor', 'ar' => 'ديكور-المنزل'],
                'description' => 'Timeless home décor pieces from Dokan Ward.',
                'position' => 0,
            ]
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<string, Category>
     */
    private function seedCategories(array $categories, Collection $collection): array
    {
        $map = [];
        foreach ($categories as $position => $cat) {
            $slug = (string) ($cat['slug'] ?? Str::slug((string) ($cat['name'] ?? 'category')));
            $nameEn = (string) ($cat['name'] ?? $slug);
            $nameAr = $this->categoryAr[$slug] ?? $nameEn;
            $coverUrl = $this->resolveCategoryCoverUrl($cat);

            $model = Category::firstOrCreate(
                ['slug->en' => $slug],
                [
                    'id' => (string) Str::uuid(),
                    'name' => ['en' => $nameEn, 'ar' => $nameAr],
                    'slug' => ['en' => $slug, 'ar' => $slug],
                    'collection_id' => $collection->id,
                    'parent_id' => null,
                    'position' => (int) $position,
                    'is_featured' => true,
                    'path' => $coverUrl,
                    'logo_url' => $coverUrl,
                ]
            );

            $model->update([
                'collection_id' => $collection->id,
                'name' => ['en' => $nameEn, 'ar' => $nameAr],
                'is_featured' => true,
                'position' => (int) $position,
                'path' => $coverUrl ?: $model->path,
                'logo_url' => $coverUrl ?: $model->logo_url,
            ]);

            $map[$slug] = $model;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $cat
     */
    private function resolveCategoryCoverUrl(array $cat): ?string
    {
        $slug = (string) ($cat['slug'] ?? '');
        $candidates = [];
        $image = (string) ($cat['image'] ?? '');
        if ($image !== '') {
            $candidates[] = basename(parse_url($image, PHP_URL_PATH) ?: $image);
        }
        if ($slug !== '') {
            foreach (['.png', '.jpg', '.jpeg', '.webp'] as $ext) {
                $candidates[] = $slug.$ext;
            }
        }

        foreach (array_unique(array_filter($candidates)) as $name) {
            $rel = 'categories/dokannward/'.$name;
            if (is_file(storage_path('app/public/'.$rel))) {
                return \App\Support\PublicUrl::storage($rel);
            }
            $public = dirname(base_path()).'/public/images/categories/'.$name;
            if (is_file($public)) {
                File::ensureDirectoryExists(dirname(storage_path('app/public/'.$rel)));
                File::copy($public, storage_path('app/public/'.$rel));

                return \App\Support\PublicUrl::storage($rel);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, Category>  $categoryMap
     */
    private function seedProduct(array $item, Brand $brand, Color $color, array $categoryMap, bool $featured): void
    {
        $slug = (string) ($item['slug'] ?? Str::slug((string) $item['name']));
        $title = trim((string) ($item['name'] ?? 'Product'));
        $sku = (string) ($item['sku'] ?: 'DW-'.$item['id']);
        $price = (float) ($item['price'] ?? 0);
        $compare = isset($item['compare_at_price']) && $item['compare_at_price'] !== null
            ? (float) $item['compare_at_price']
            : null;
        if ($compare !== null && $compare <= $price) {
            $compare = null;
        }
        // Storefront ProductResource exposes compare_at when price_max > price.
        $priceMax = $compare ?? $price;

        $primaryCategory = null;
        foreach ($item['categories'] ?? [] as $c) {
            $cSlug = (string) ($c['slug'] ?? '');
            if ($cSlug !== '' && isset($categoryMap[$cSlug])) {
                $primaryCategory = $categoryMap[$cSlug];
                break;
            }
        }
        if ($primaryCategory === null) {
            $primaryCategory = reset($categoryMap) ?: null;
        }

        $short = trim((string) ($item['short_description'] ?? ''));
        $desc = trim((string) ($item['description'] ?? ''));
        if ($desc === '') {
            $desc = $short;
        }
        $short = $this->normalizeCopy($short);
        $desc = $this->normalizeCopy($desc);

        $sourceSku = trim((string) ($item['sku'] ?? ''));
        $productSku = $sourceSku !== ''
            ? 'DW-'.$sourceSku
            : 'DW-'.Str::upper(Str::substr(md5($slug), 0, 8));

        $product = Product::updateOrCreate(
            ['slug' => $slug],
            [
                'id' => Product::where('slug', $slug)->value('id') ?: (string) Str::uuid(),
                'sku' => $productSku,
                'name' => ['en' => $title, 'ar' => $title],
                'brand_id' => $brand->id,
                'category_id' => $primaryCategory?->id,
                'short_description' => [
                    'en' => $short !== '' ? $short : "From Dokan Ward — {$title}.",
                    'ar' => $short !== '' ? $short : "من دكان ورد — {$title}.",
                ],
                'description' => [
                    'en' => $desc !== '' ? $desc : "Home décor piece from Dokan Ward: {$title}.",
                    'ar' => $desc !== '' ? $desc : "قطعة ديكور من دكان ورد: {$title}.",
                ],
                'price' => $price,
                'price_min' => $price,
                'price_max' => $priceMax,
                'material' => $this->extractMaterial($short."\n".$desc),
                'status' => 'active',
                'visibility' => true,
                'featured' => $featured,
                'in_stock' => (bool) ($item['in_stock'] ?? true),
            ]
        );

        $variantSku = $sourceSku !== ''
            ? 'DW-VAR-'.$sourceSku
            : 'DW-'.Str::upper(Str::slug($slug));

        $variant = ProductVariant::where('product_id', $product->id)->orderBy('created_at')->first();
        if ($variant) {
            $variant->update([
                'sku' => $variantSku,
                'color_id' => $color->id,
                'price' => $price,
                'compare_at_price' => $compare,
                'stock' => max(1, (int) ($variant->stock ?? 15)),
                'is_active' => true,
            ]);
        } else {
            $variant = ProductVariant::create([
                'id' => (string) Str::uuid(),
                'sku' => $variantSku,
                'product_id' => $product->id,
                'color_id' => $color->id,
                'price' => $price,
                'compare_at_price' => $compare,
                'stock' => 15,
                'is_active' => true,
            ]);
        }

        // Drop leftover variants from earlier seed runs (SKU scheme changed).
        ProductVariant::where('product_id', $product->id)
            ->where('id', '!=', $variant->id)
            ->delete();

        $photoIds = [];
        foreach (array_values($item['images'] ?? []) as $position => $imagePath) {
            $storageRel = $this->resolveStorageImage((string) $imagePath, (int) $item['id'], (int) $position);
            if ($storageRel === null) {
                continue;
            }

            $photo = Photo::updateOrCreate(
                [
                    'imageable_type' => Product::class,
                    'imageable_id' => $product->id,
                    'position' => $position,
                ],
                [
                    'id' => Photo::where('imageable_type', Product::class)
                        ->where('imageable_id', $product->id)
                        ->where('position', $position)
                        ->value('id') ?: (string) Str::uuid(),
                    'storage_path' => $storageRel,
                    'file_name' => basename($storageRel),
                    'mime_type' => str_ends_with(strtolower($storageRel), '.png') ? 'image/png' : 'image/jpeg',
                    'alt_text' => $title,
                    'is_primary' => $position === 0,
                ]
            );
            $photoIds[$photo->id] = ['position' => $position];
        }

        if ($photoIds !== []) {
            $variant->photos()->sync($photoIds);
        }

        if (method_exists($product, 'syncAvailabilityFromVariants')) {
            $product->syncAvailabilityFromVariants();
        }
    }

    /** Keep paragraph breaks from the live WooCommerce short description. */
    private function normalizeCopy(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace("/[ \t]+\n/", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }

    private function extractMaterial(string $copy): ?string
    {
        if (preg_match('/(?:^|\n)\s*Material\s*:\s*(.+?)(?:\n|$)/iu', $copy, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/ماتيريال\s*([^\n]+)/u', $copy, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/خامه\s+([^\n]+)/u', $copy, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function resolveStorageImage(string $imagePath, int $productId, int $index): ?string
    {
        // Prefer scraped local files: /images/products/2474-0.jpeg → products/dokannward/2474-0.jpeg
        $basename = basename(parse_url($imagePath, PHP_URL_PATH) ?: $imagePath);
        if ($basename === '' || $basename === '/' || $basename === '.') {
            $basename = "{$productId}-{$index}.jpg";
        }

        $rel = 'products/dokannward/'.$basename;
        $absolute = storage_path('app/public/'.$rel);
        if (is_file($absolute)) {
            return $rel;
        }

        // Fallback: public storefront copy
        $public = dirname(base_path()).'/public/images/products/'.$basename;
        if (is_file($public)) {
            File::ensureDirectoryExists(dirname($absolute));
            File::copy($public, $absolute);

            return $rel;
        }

        return null;
    }
}
