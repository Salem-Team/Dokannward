<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'sku', 'name', 'slug', 'brand_id', 'category_id', 'short_description', 'description',
        'status', 'visibility', 'price', 'price_min', 'price_max', 'weight_kg', 'images',
        'color', 'material', 'metadata', 'featured', 'in_stock', 'is_limited_edition',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'short_description' => 'array',
        'images' => 'array',
        'metadata' => 'array',
        'featured' => 'boolean',
        'in_stock' => 'boolean',
        'is_limited_edition' => 'boolean',
        'visibility' => 'boolean',
        'price' => 'decimal:2',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'weight_kg' => 'decimal:3',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $appends = ['translated_name', 'translated_description', 'translated_short_description'];

    public function getTranslatedNameAttribute()
    {
        $name = $this->name;
        $locale = app()->getLocale();

        return $name[$locale] ?? $name['en'] ?? null;
    }

    public function getTranslatedDescriptionAttribute()
    {
        $desc = $this->description;
        $locale = app()->getLocale();

        return $desc[$locale] ?? $desc['en'] ?? null;
    }

    public function getTranslatedShortDescriptionAttribute()
    {
        $shortDesc = $this->short_description;
        $locale = app()->getLocale();

        return $shortDesc[$locale] ?? $shortDesc['en'] ?? null;
    }

    public function getMetaTitleAttribute(): ?string
    {
        return ($this->metadata ?? [])['meta_title'] ?? null;
    }

    public function getMetaDescriptionAttribute(): ?string
    {
        return ($this->metadata ?? [])['meta_description'] ?? null;
    }

    public function getMetaKeywordsAttribute(): ?string
    {
        return ($this->metadata ?? [])['meta_keywords'] ?? null;
    }

    protected static function booted(): void
    {
        static::saved(function (self $product) {
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeProduct($product);
        });
        static::deleted(function (self $product) {
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeProduct($product);
        });
    }

    // Relationships
    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Merchandising groups this product is sold in. Independent of `category`:
     * a collection ("Summer 2026") can mix bags, shoes and belts, and a product
     * can appear in several collections at once.
     */
    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_product')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('collection_product.position');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Offered sizes (EU shoe, clothing letters, or custom) — empty means color-only. */
    public function sizes()
    {
        return $this->belongsToMany(Size::class, 'product_sizes', 'product_id', 'size_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'imageable')
            ->orderByDesc('is_primary')
            ->orderBy('position');
    }

    /**
     * Storefront / admin thumbnail cover — the single is_primary photo when
     * present, otherwise the first gallery photo by position.
     */
    public function coverPhoto(): ?Photo
    {
        $photos = $this->relationLoaded('photos')
            ? $this->photos
            : $this->photos()->get();

        return $photos->firstWhere('is_primary', true) ?? $photos->first();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /** Only reviews an admin has approved — what the storefront is allowed to show. */
    public function approvedReviews()
    {
        return $this->reviews()->approved()->latest();
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->relationLoaded('approvedReviews')
            ? $this->approvedReviews->count()
            : $this->reviews()->approved()->count();
    }

    public function getReviewsAverageAttribute(): ?float
    {
        $avg = $this->relationLoaded('approvedReviews')
            ? $this->approvedReviews->avg('rating')
            : $this->reviews()->approved()->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * Canonical public URL encoded into this product's QR code.
     * Prefer slug so the code stays stable and human-readable when scanned.
     */
    public function scanUrl(): string
    {
        $base = rtrim((string) config('app.storefront_public_url'), '/');
        $slug = trim((string) $this->slug);

        if ($slug === '') {
            $slug = (string) $this->id;
        }

        return $base.'/scan/'.rawurlencode($slug);
    }

    public function storefrontUrl(): string
    {
        $base = rtrim((string) config('app.storefront_public_url'), '/');
        $slug = trim((string) $this->slug);

        if ($slug === '') {
            return $base;
        }

        return $base.'/products/'.rawurlencode($slug);
    }

    /**
     * Keep the product-level `in_stock` flag aligned with active variant qty
     * so listing cards (which read the flag, not every variant) stay truthful
     * after Inventory / checkout stock changes.
     */
    public function syncAvailabilityFromVariants(): void
    {
        if (! $this->variants()->exists()) {
            return;
        }

        $available = $this->variants()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->exists();

        if ((bool) $this->in_stock === $available) {
            return;
        }

        $this->forceFill(['in_stock' => $available])->saveQuietly();
        \App\Http\Controllers\Api\ProductController::forgetListCache();
    }
}
