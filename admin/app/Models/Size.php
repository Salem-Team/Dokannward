<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public const GROUP_SHOE = 'shoe';

    public const GROUP_APPAREL = 'apparel';

    public const GROUP_CUSTOM = 'custom';

    /** Display order + labels for the admin size picker sections. */
    public const GROUPS = [
        self::GROUP_SHOE => 'EU shoe',
        self::GROUP_APPAREL => 'Clothing',
        self::GROUP_CUSTOM => 'Custom',
    ];

    protected $fillable = ['id', 'name', 'size_group', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function groupLabel(): string
    {
        return self::GROUPS[$this->size_group] ?? self::GROUPS[self::GROUP_CUSTOM];
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_sizes', 'size_id', 'product_id');
    }
}
