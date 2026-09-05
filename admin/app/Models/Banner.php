<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'title', 'subtitle', 'button_text', 'button_url', 'image_url',
        'is_active', 'start_at', 'end_at', 'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'position' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    /** Visible on the storefront right now (active + inside optional date window). */
    public function scopeLive($query)
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            });
    }

    /** Admin status chip: live | scheduled | expired | off */
    public function liveStatus(): string
    {
        if (! $this->is_active) {
            return 'off';
        }

        $now = now();
        if ($this->start_at && $this->start_at->gt($now)) {
            return 'scheduled';
        }
        if ($this->end_at && $this->end_at->lt($now)) {
            return 'expired';
        }

        return 'live';
    }

    /**
     * Homepage shows the first two live banners by position.
     * Lower position numbers fill earlier slots.
     */
    public function homepageSlotHint(): string
    {
        return match (true) {
            $this->position <= 0 => 'Homepage · Primary CTA',
            $this->position === 1 => 'Homepage · Secondary CTA',
            default => 'Extra · shows after primary slots if earlier ones are off',
        };
    }

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Http\Controllers\Api\BannerController::forgetListCache();
            \App\Services\StorefrontRevalidator::purge(['/'], ['catalog', 'banners']);
        });
        static::deleted(function () {
            \App\Http\Controllers\Api\BannerController::forgetListCache();
            \App\Services\StorefrontRevalidator::purge(['/'], ['catalog', 'banners']);
        });
    }
}
