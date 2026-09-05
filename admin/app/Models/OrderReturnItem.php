<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrderReturnItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'order_return_id',
        'order_item_id',
        'qty',
        'unit_price',
        'line_refund',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_refund' => 'decimal:2',
        'qty' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderReturnItem $item) {
            if (empty($item->id)) {
                $item->id = (string) Str::uuid();
            }
        });
    }

    public function orderReturn()
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
