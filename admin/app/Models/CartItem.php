<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = ['id', 'cart_id', 'variant_id', 'quantity', 'price', 'added_at'];

    public $incrementing = false;

    protected $keyType = 'string';

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function product()
    {
        return $this->hasOneThrough(
            Product::class,
            ProductVariant::class,
            'id',
            'id',
            'variant_id',
            'product_id'
        );
    }

    // Helper to get variant attributes
    public function getVariantAttributesAttribute()
    {
        if ($this->variant && $this->variant->attributes) {
            return $this->variant->attributes;
        }
        return [];
    }

    // Helper to display variant info
    public function getVariantDisplayAttribute()
    {
        if (!$this->variant) return null;

        $attributes = $this->variant->attributes ?? [];
        $display = [];

        if (!empty($attributes['color'])) {
            $display[] = 'Color: ' . $attributes['color'];
        }
        if (!empty($attributes['material'])) {
            $display[] = 'Material: ' . $attributes['material'];
        }

        return !empty($display) ? implode(', ', $display) : null;
    }
}
