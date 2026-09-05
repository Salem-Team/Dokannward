<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'title',
        'company',
        'content',
        'avatar',
        'source',
        'rating',
        'is_featured',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected $appends = ['avatar_url'];

    /**
     * Scope for active testimonials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured testimonials
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for ordered testimonials
     */
    public function scopeOrdered($query)
    {
        return $query
            ->orderByDesc('is_featured')
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get avatar URL
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        // Admin list fallback only — storefront API omits this when empty.
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=0a0a0a&color=fff&size=200';
    }

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Http\Controllers\Api\TestimonialController::forgetListCache();
            \App\Services\StorefrontRevalidator::purge(['/'], ['testimonials']);
        });
        static::deleted(function () {
            \App\Http\Controllers\Api\TestimonialController::forgetListCache();
            \App\Services\StorefrontRevalidator::purge(['/'], ['testimonials']);
        });
    }
}
