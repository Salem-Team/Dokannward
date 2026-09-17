<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformBrandDomain extends Model
{
    protected $fillable = [
        'platform_brand_id',
        'host',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(PlatformBrand::class, 'platform_brand_id');
    }
}
