<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'parent_id',
        // Legacy column — kept nullable for schema/rollback compatibility.
        // Product collection membership uses `collection_product`, not this FK.
        'collection_id',
        'path',
        'logo_url',
        'position',
        'is_featured',
    ];

    /**
     * New categories appear on the homepage banner stack unless explicitly turned off.
     * Logo rail visibility is driven by `logo_url` alone (see storefront catalog).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_featured' => true,
    ];

    protected $casts = [
        'name' => 'array',
        'slug' => 'array',
        'is_featured' => 'boolean',
    ];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute()
    {
        $name = $this->name;
        if (! is_array($name)) {
            return $name;
        }
        $locale = app()->getLocale();

        return $name[$locale] ?? $name['en'] ?? reset($name) ?? 'Unnamed';
    }

    /**
     * @deprecated Categories are independent classification. The column may
     *             remain null forever; do not use for membership or visibility.
     */
    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(\App\Models\Product::class);
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
