<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as SupportCollection;

class Collection extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'image_url',
        'position',
        'is_published',
    ];

    protected $casts = [
        'name' => 'array',
        'slug' => 'array',
        'is_published' => 'boolean',
    ];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute(): string
    {
        $name = $this->name;
        if (! is_array($name)) {
            return (string) $name;
        }

        $locale = app()->getLocale();

        return $name[$locale] ?? $name['en'] ?? reset($name) ?: 'Unnamed';
    }

    /**
     * Direct members of this collection, in merchandising order. Membership is
     * explicit — a collection can mix products from any number of categories.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('collection_product.position');
    }

    /**
     * Distinct categories of this collection's active direct members.
     * Used for storefront tree compatibility — never via categories.collection_id.
     *
     * @return SupportCollection<int, Category>
     */
    public function categoriesFromMembers(): SupportCollection
    {
        $categoryIds = $this->products()
            ->where('products.status', 'active')
            ->whereNotNull('products.category_id')
            ->pluck('products.category_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($categoryIds === []) {
            return collect();
        }

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('position')
            ->orderBy('name->en')
            ->get();
    }

    /** Storefront-visible collections only. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Http\Controllers\Api\CollectionController::forgetListCache();
            \App\Http\Controllers\Api\CategoryController::forgetListCache();
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeCatalog();
        });
        static::deleted(function () {
            \App\Http\Controllers\Api\CollectionController::forgetListCache();
            \App\Http\Controllers\Api\CategoryController::forgetListCache();
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeCatalog();
        });
    }
}
