<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'user_id', 'session_token', 'expires_at'];

    public $incrementing = false;

    protected $keyType = 'string';

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function getSubtotalAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
    }

    public function getTotalAttribute()
    {
        return $this->subtotal;
    }
}
