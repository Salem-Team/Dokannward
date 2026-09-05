<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    /** @use HasFactory<\Database\Factories\BrandFactory> */
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'slug', 'description', 'country', 'logo_url'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute()
    {
        $name = $this->name;
        if (!is_array($name)) {
            return $name;
        }
        $locale = app()->getLocale();
        return $name[$locale] ?? $name['en'] ?? reset($name) ?? 'Unnamed';
    }

    public function products()
    {
        return $this->hasMany(\App\Models\Product::class);
    }

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Http\Controllers\Api\BrandController::forgetListCache();
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeCatalog();
        });
        static::deleted(function () {
            \App\Http\Controllers\Api\BrandController::forgetListCache();
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            \App\Services\StorefrontRevalidator::purgeCatalog();
        });
    }
}
