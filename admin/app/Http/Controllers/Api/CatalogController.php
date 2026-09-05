<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight catalog index for the Next.js middleware — one round-trip
 * to validate /collections, /products, and /brands handles with real HTTP 404s.
 */
class CatalogController extends Controller
{
    public function handles()
    {
        $generation = implode(':', [
            Cache::get('api.products.list.generation', 'v1'),
            Cache::get('api.categories.list.generation', 'v1'),
            Cache::get('api.collections.list.generation', 'v1'),
            Cache::get('api.brands.list.generation', 'v1'),
        ]);

        $payload = Cache::remember(
            'api.catalog.handles.'.$generation,
            now()->addMinutes(30),
            fn () => [
                'collections' => $this->collectionHandles(),
                'products' => $this->productHandles(),
                'brands' => $this->brandHandles(),
            ],
        );

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    /** @return list<string> */
    private function collectionHandles(): array
    {
        $handles = collect(['all']);

        foreach (Category::query()->get(['slug']) as $category) {
            $handles = $handles->merge($this->slugValues($category->slug));
        }

        foreach (Collection::query()->published()->get(['slug']) as $collection) {
            $handles = $handles->merge($this->slugValues($collection->slug));
        }

        // Legacy storefront aliases kept in src/lib/catalog.ts
        $handles->push('handbags-and-accessories-example-products', 'frontpage');

        return $handles->filter()->unique()->values()->all();
    }

    /** @return list<string> */
    private function productHandles(): array
    {
        $query = Product::query()->where('status', 'active');
        $query->where(function ($q) {
            $q->whereDoesntHave('collections')
                ->orWhereHas('collections', fn ($col) => $col->where('is_published', true));
        });

        return $query->pluck('slug')->filter()->values()->all();
    }

    /** @return list<string> */
    private function brandHandles(): array
    {
        return Brand::query()->pluck('slug')->filter()->values()->all();
    }

    /** @return list<string> */
    private function slugValues(mixed $slug): array
    {
        if (is_string($slug) && $slug !== '') {
            return [$slug];
        }

        if (! is_array($slug)) {
            return [];
        }

        return array_values(array_filter([
            $slug['en'] ?? null,
            $slug['ar'] ?? null,
        ]));
    }
}
