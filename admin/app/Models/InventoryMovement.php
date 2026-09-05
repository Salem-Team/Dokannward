<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    public $timestamps = false; // created_at stored manually

    protected $fillable = ['id','variant_id','change','source_type','source_id','reason','current_stock','created_by','created_at'];

    public $incrementing = false;
    protected $keyType = 'string';
}
