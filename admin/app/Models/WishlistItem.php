<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = ['wishlist_id','variant_id','added_at'];

    public $incrementing = false;
    protected $keyType = 'string';
}
