<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = ['id','key','value'];

    protected $casts = ['value' => 'array'];

    public $incrementing = false;
    protected $keyType = 'string';
}
