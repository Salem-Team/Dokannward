<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a Product (+ eager-loaded brand/category/variants/photos) into the
 * flat, storefront-friendly payload the Next.js site consumes — most notably
 * `colors`, which groups variants by color+size so the client can render
 * swatches, swap the hero image, and (for sized products) offer a size grid,
 * all without extra requests.
 *
 * Expects the product to be loaded with:
 * with(['brand', 'category', 'collections', 'photos', 'variants.color', 'variants.size', 'variants.photos'])
 *
 * List responses (no `approvedReviews` relation) stay slim: primary image only,
 * no SEO block, no long description, no review bodies.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantPhotoIds = $this->variantPhotoIdSet();
        $galleryPhotos = $this->photos
            ->reject(fn ($photo) => $variantPhotoIds->contains($photo->id))
            ->values();

        // Website cover: any is_primary photo wins (gallery or color), then
        // fall back to gallery / first available plate.
        $primaryPhoto = $this->photos->firstWhere('is_primary', true)
            ?? $galleryPhotos->first()
            ?? $this->photos->first();

        $detailGallery = $galleryPhotos->isNotEmpty()
            ? $galleryPhotos
            : $this->photos;

        $isDetail = $this->relationLoaded('approvedReviews');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'name' => $this->translated_name,
            'short_description' => $this->when(
                $isDetail,
                fn () => $this->translated_short_description,
            ),
            'description' => $this->when(
                $isDetail,
                fn () => $this->translated_description,
            ),
            'price' => $this->price,
            'compare_at_price' => $this->price_max > $this->price ? $this->price_max : null,
            'material' => $this->when($isDetail, fn () => $this->material),
            'featured' => (bool) $this->featured,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'in_stock' => $this->relationLoaded('variants') && $this->variants->isNotEmpty()
                ? $this->variants->contains(fn ($v) => $v->is_active && $v->stock > 0)
                : (bool) $this->in_stock,
            'stock' => $this->when(
                $isDetail && $this->relationLoaded('variants') && $this->variants->isNotEmpty(),
                fn () => (int) $this->variants->where('is_active', true)->sum('stock'),
            ),
            'seo' => $this->when($isDetail, fn () => [
                'title' => $this->meta_title,
                'description' => $this->meta_description,
                'keywords' => $this->meta_keywords,
            ]),
            'brand' => $this->when($this->relationLoaded('brand') && $this->brand, fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->translated_name,
                'slug' => $this->brand->slug,
                'logo_url' => $this->brand->logo_url,
            ]),
            'category' => $this->when($this->relationLoaded('category') && $this->category, fn () => [
                'id' => $this->category->id,
                'name' => $this->category->translated_name,
                'slug' => is_array($this->category->slug) ? ($this->category->slug['en'] ?? null) : $this->category->slug,
            ]),
            // Merchandising groups, independent of the category — the PDP shows
            // "more from this collection" before falling back to the category.
            // Unpublished collections never leak to the storefront.
            'collections' => $this->whenLoaded('collections', fn () => $this->collections
                ->filter(fn ($collection) => (bool) $collection->is_published)
                ->map(fn ($collection) => [
                    'id' => $collection->id,
                    'name' => $collection->translated_name,
                    'slug' => is_array($collection->slug) ? ($collection->slug['en'] ?? null) : $collection->slug,
                ])
                ->values()),
            'image' => $primaryPhoto?->url,
            // Listing: one URL is enough for cards. Detail: product gallery only
            // (color shots live under colors[].images so they do not duplicate).
            'images' => $isDetail
                ? $detailGallery->pluck('url')->values()
                : ($primaryPhoto?->url ? [$primaryPhoto->url] : []),
            'rating' => [
                'average' => $isDetail
                    ? ($this->approvedReviews->count() > 0
                        ? round((float) $this->approvedReviews->avg('rating'), 1)
                        : null)
                    : ($this->approved_reviews_avg_rating !== null
                        ? round((float) $this->approved_reviews_avg_rating, 1)
                        : null),
                'count' => $isDetail
                    ? $this->approvedReviews->count()
                    : (int) ($this->approved_reviews_count ?? 0),
            ],
            'reviews' => $this->whenLoaded('approvedReviews', fn () => $this->approvedReviews->map(fn ($r) => [
                'id' => $r->id,
                'author' => $r->author_name,
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'recommended' => $r->recommended,
                'created_at' => $r->created_at,
            ])->values()),
            'colors' => $this->whenLoaded('variants', function () use ($isDetail) {
                return $this->variants
                    ->filter(fn ($v) => $v->color !== null)
                    ->groupBy('color_id')
                    ->map(fn ($variants) => $this->shapeColor($variants, $isDetail))
                    ->sortByDesc(fn ($color) => (int) $color['is_default'])
                    ->values();
            }),
            // Unique offered size names across every color, low to high — the
            // storefront uses this to render the size picker before a color is
            // even chosen. Color-only products (no sized variants) get [].
            'sizes' => $this->when($isDetail, function () {
                if ($this->relationLoaded('variants')) {
                    $fromVariants = $this->variants
                        ->pluck('size')
                        ->filter()
                        ->unique('id')
                        ->sortBy('sort_order')
                        ->pluck('name')
                        ->values();

                    if ($fromVariants->isNotEmpty()) {
                        return $fromVariants;
                    }
                }

                return $this->relationLoaded('sizes') ? $this->sizes->pluck('name')->values() : [];
            }),
        ];
    }

    /**
     * Photo IDs attached to any variant of this product (color galleries).
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function variantPhotoIdSet()
    {
        if (! $this->relationLoaded('variants')) {
            return collect();
        }

        return $this->variants
            ->flatMap(function ($variant) {
                if (! $variant->relationLoaded('photos')) {
                    return [];
                }

                return $variant->photos->pluck('id');
            })
            ->unique()
            ->values();
    }

    /**
     * One storefront color entry from every variant sharing a color_id —
     * whether that is a single color-only variant or a full set of size
     * variants for that color.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\ProductVariant>  $variants
     */
    private function shapeColor($variants, bool $isDetail): array
    {
        $color = $variants->first()->color;
        $bySize = $variants->sortBy(fn ($v) => $v->size?->sort_order ?? 0)->values();
        $activeVariants = $bySize->where('is_active', true);
        $inStockVariants = $activeVariants->where('stock', '>', 0);

        // Preferred purchasable variant: smallest in-stock size, else the
        // smallest active size, else whatever variant exists at all.
        $preferred = $inStockVariants->first() ?? $activeVariants->first() ?? $bySize->first();

        $photoVariant = $bySize->first(fn ($v) => $v->relationLoaded('photos') && $v->photos->isNotEmpty());
        $hasSizes = $bySize->contains(fn ($v) => $v->size !== null);

        return [
            'variant_id' => $preferred->id,
            'sku' => $preferred->sku,
            'name' => $color->name,
            'hex' => $color->hex,
            'price' => $preferred->price,
            'compare_at_price' => $preferred->compare_at_price,
            'in_stock' => $inStockVariants->isNotEmpty(),
            ...($isDetail ? ['stock' => (int) $activeVariants->sum('stock')] : []),
            'is_default' => (bool) $bySize->contains(fn ($v) => $v->is_default),
            // Only the color's own photos — never silently fall back to the
            // product hero here. Storefront uses `color.image || product.image`.
            'image' => $photoVariant?->photos->first()?->url,
            // Full per-color gallery so picking a swatch can swap every slide.
            'images' => $photoVariant
                ? $photoVariant->photos->pluck('url')->values()
                : [],
            'sizes' => $hasSizes
                ? $bySize
                    ->filter(fn ($v) => $v->size !== null)
                    ->map(fn ($v) => [
                        'name' => $v->size->name,
                        'variant_id' => $v->id,
                        'sku' => $v->sku,
                        'price' => $v->price,
                        'in_stock' => $v->is_active && $v->stock > 0,
                        ...($isDetail ? ['stock' => max(0, (int) $v->stock)] : []),
                    ])
                    ->values()
                : [],
        ];
    }
}
