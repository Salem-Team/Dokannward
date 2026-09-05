<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id', 'product_id', 'sku', 'upc', 'gtin', 'color_id', 'size_id', 'price', 'compare_at_price',
        'weight_kg', 'attributes', 'stock', 'stock_reserved', 'is_active', 'is_default',
    ];

    protected $casts = [
        'attributes' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function photos()
    {
        return $this->belongsToMany(Photo::class, 'product_variant_photos', 'variant_id', 'photo_id')
            ->withPivot('position')
            ->orderBy('product_variant_photos.position');
    }

    protected static function booted(): void
    {
        $sync = function (self $variant) {
            \App\Http\Controllers\Api\ProductController::forgetListCache();
            $product = $variant->product;
            $product?->syncAvailabilityFromVariants();
            \App\Services\StorefrontRevalidator::purgeProduct($product);
        };

        static::saved($sync);
        static::deleted($sync);
    }
}
