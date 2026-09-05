<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public const LIST_CACHE_PREFIX = 'api.products.list.';

    public const SHOW_CACHE_PREFIX = 'api.products.show.';

    public function index(Request $request)
    {
        // Only products the admin has marked "Active" are exposed.
        // List payloads stay slim (no review bodies / variant photo trees)
        // and are cached briefly — the remote DB round-trip is the main cost.
        $generation = Cache::get('api.products.list.generation') ?: 'v1';
        $cacheKey = self::LIST_CACHE_PREFIX.$generation.'.'.md5(json_encode([
            'brand' => $request->input('brand'),
            'category' => $request->input('category'),
            'collection' => $request->input('collection'),
            'color' => $request->input('color'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'featured' => $request->boolean('featured'),
            'q' => $request->input('q'),
            'per_page' => min(100, max(1, (int) $request->input('per_page', 24))),
            'page' => max(1, (int) $request->input('page', 1)),
            'locale' => app()->getLocale(),
        ]));

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($request) {
            $q = Product::query()
                ->where('status', 'active')
                // Include each variant's primary photo so catalog cards can
                // swap the hero when a shopper picks a color swatch.
                ->with([
                    'brand:id,name,slug,logo_url',
                    'category:id,name,slug',
                    'collections:id,name,slug,is_published',
                    'photos',
                    'variants:id,product_id,color_id,size_id,sku,price,stock,is_active,is_default',
                    'variants.color:id,name,hex',
                    'variants.size:id,name,sort_order',
                    'variants.photos',
                ])
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating');

            $this->constrainToPublishedCollections($q);

            if ($request->filled('brand')) {
                $this->filterByBrand($q, $request->input('brand'));
            }
            if ($request->filled('category')) {
                $this->filterByCategory($q, $request->input('category'));
            }
            if ($request->filled('collection')) {
                $this->filterByCollection($q, $request->input('collection'));
            }
            if ($request->filled('color')) {
                $q->whereHas('variants.color', fn ($sub) => $sub->where('name', $request->input('color')));
            }
            if ($request->filled('price_min')) {
                $q->where('price', '>=', (float) $request->input('price_min'));
            }
            if ($request->boolean('featured')) {
                $q->where('featured', true);
            }
            if ($request->filled('price_max')) {
                $q->where('price', '<=', (float) $request->input('price_max'));
            }
            if ($request->filled('q')) {
                // Laravel's `column->locale` JSON path works on MySQL and SQLite
                // alike — avoid MySQL-only JSON_UNQUOTE/JSON_EXTRACT helpers.
                $term = '%'.trim((string) $request->input('q')).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->orWhere('sku', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                    foreach (['en', 'ar'] as $locale) {
                        $sub->orWhere("name->{$locale}", 'like', $term)
                            ->orWhere("description->{$locale}", 'like', $term);
                    }
                });
            }

            $perPage = min(100, max(1, (int) $request->input('per_page', 24)));
            $products = $q->orderByDesc('created_at')->simplePaginate($perPage);

            return ProductResource::collection($products)->response()->getData(true);
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600')
            ->header('Vary', 'Accept-Encoding');
    }

    public function show(string $id)
    {
        $generation = Cache::get('api.products.show.generation') ?: 'v1';
        $cacheKey = self::SHOW_CACHE_PREFIX.$generation.'.'.md5($id.'|'.app()->getLocale());

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($id) {
            $product = Product::where('status', 'active')
                ->with(['brand', 'category', 'collections', 'photos', 'variants.color', 'variants.size', 'variants.photos', 'approvedReviews', 'sizes'])
                ->where(fn ($q) => $q->where('id', $id)->orWhere('slug', $id)->orWhere('sku', $id))
                ->firstOrFail();

            // Only collection membership gates visibility: a product with no
            // collection stays public, one whose every collection is hidden does not.
            if ($product->collections->isNotEmpty() && ! $product->collections->contains('is_published', true)) {
                abort(404);
            }

            return (new ProductResource($product))->response()->getData(true);
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600')
            ->header('Vary', 'Accept-Encoding');
    }

    /** Accepts either a brand UUID or its storefront slug (e.g. "dior"). */
    private function filterByBrand($query, string $value): void
    {
        if (Str::isUuid($value)) {
            $query->where('brand_id', $value);

            return;
        }

        $generation = Cache::get('api.brands.list.generation') ?: 'v1';
        $brandId = Cache::remember(
            'api.brand.id.by_slug.'.$generation.'.'.md5($value),
            now()->addMinutes(30),
            fn () => Brand::where('slug', $value)->value('id')
        );
        $query->where('brand_id', $brandId ?? '__none__');
    }

    /**
     * Accepts a category UUID/slug or a collection UUID/slug (e.g. "bags",
     * "tote") so existing storefront links keep working. Collection values are
     * resolved through direct membership, never through the category tree.
     */
    private function filterByCategory($query, string $value): void
    {
        $generation = Cache::get('api.categories.list.generation') ?: 'v1';

        if ($this->collectionExists($value, $generation)) {
            $this->filterByCollection($query, $value);

            return;
        }

        if (Str::isUuid($value)) {
            $query->whereIn('category_id', $this->categoryIdsWithDescendants($value, $generation));

            return;
        }

        $categoryId = Cache::remember(
            'api.category.id.by_slug.'.$generation.'.'.md5($value),
            now()->addMinutes(30),
            fn () => Category::where('slug->en', $value)->value('id')
        );

        if (! $categoryId) {
            $query->where('category_id', '__none__');

            return;
        }

        $query->whereIn('category_id', $this->categoryIdsWithDescendants($categoryId, $generation));
    }

    /**
     * Direct collection members — a collection mixes categories, so this only
     * ever looks at the `collection_product` pivot. Accepts a UUID or the
     * English slug; unpublished collections resolve to nothing.
     */
    private function filterByCollection($query, string $value): void
    {
        $collectionId = $this->publishedCollectionId($value);

        if (! $collectionId) {
            $query->where('id', '__none__');

            return;
        }

        $query->whereHas('collections', fn ($col) => $col->whereKey($collectionId));
    }

    /** Published collection id for a UUID or English slug, if any. */
    private function publishedCollectionId(string $value): ?string
    {
        $generation = Cache::get('api.collections.list.generation') ?: 'v1';

        if (Str::isUuid($value)) {
            return Cache::remember(
                'api.collection.id.by_uuid.'.$generation.'.'.$value,
                now()->addMinutes(30),
                fn () => \App\Models\Collection::published()->whereKey($value)->value('id')
            );
        }

        return Cache::remember(
            'api.collection.id.by_slug.'.$generation.'.'.md5($value),
            now()->addMinutes(30),
            fn () => \App\Models\Collection::published()->where('slug->en', $value)->value('id')
        );
    }

    /**
     * True when the value names a collection, published or not — an unpublished
     * collection must resolve to nothing instead of falling through to a
     * same-named category.
     */
    private function collectionExists(string $value, string $generation): bool
    {
        $isUuid = Str::isUuid($value);
        $key = $isUuid
            ? 'api.collection.exists.by_uuid.'.$generation.'.'.$value
            : 'api.collection.exists.by_slug.'.$generation.'.'.md5($value);

        return (bool) Cache::remember(
            $key,
            now()->addMinutes(30),
            fn () => \App\Models\Collection::query()
                ->when(
                    $isUuid,
                    fn ($q) => $q->whereKey($value),
                    fn ($q) => $q->where('slug->en', $value),
                )
                ->exists()
        );
    }

    /**
     * A product is public when it belongs to no collection at all, or to at
     * least one published collection. Categories never gate visibility.
     */
    private function constrainToPublishedCollections($query): void
    {
        $query->where(function ($q) {
            $q->whereDoesntHave('collections')
                ->orWhereHas('collections', fn ($col) => $col->where('is_published', true));
        });
    }

    /** @return list<string> */
    private function categoryIdsWithDescendants(string $rootId, string $generation): array
    {
        return Cache::remember(
            'api.category.descendants.'.$generation.'.'.$rootId,
            now()->addMinutes(30),
            function () use ($rootId) {
                $ids = [$rootId];
                $frontier = [$rootId];

                while ($frontier !== []) {
                    $children = Category::whereIn('parent_id', $frontier)->pluck('id')->all();
                    $frontier = array_values(array_diff($children, $ids));
                    foreach ($frontier as $id) {
                        $ids[] = $id;
                    }
                }

                return $ids;
            }
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|array',
            'description' => 'nullable|array',
            'brand_id' => 'nullable|uuid',
            'category_id' => 'nullable|uuid',
            'price' => 'nullable|numeric',
            'images' => 'nullable|array',
            'color' => 'nullable|string',
            'material' => 'nullable|string',
            'featured' => 'boolean',
            'in_stock' => 'boolean',
        ]);

        $product = Product::create(array_merge(['id' => (string) Str::uuid()], $data));
        self::forgetListCache();

        return response()->json($product, 201);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|array',
            'description' => 'sometimes|array',
            'brand_id' => 'nullable|uuid',
            'category_id' => 'nullable|uuid',
            'price' => 'nullable|numeric',
            'images' => 'nullable|array',
            'color' => 'nullable|string',
            'material' => 'nullable|string',
            'featured' => 'boolean',
            'in_stock' => 'boolean',
        ]);

        $product->update($data);
        self::forgetListCache();

        return response()->json($product);
    }

    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        self::forgetListCache();

        return response()->json(null, 204);
    }

    public static function forgetListCache(): void
    {
        // File/array stores don't support wildcard deletes reliably across
        // drivers, so we keep a generation key that all list keys include.
        Cache::forever('api.products.list.generation', (string) Str::uuid());
        Cache::forever('api.products.show.generation', (string) Str::uuid());
    }
}
