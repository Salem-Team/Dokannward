<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    use HasFactory;

    protected $fillable = ['id','user_id','action','object_type','object_id','payload'];

    protected $casts = ['payload' => 'array'];

    public $incrementing = false;
    protected $keyType = 'string';
}
