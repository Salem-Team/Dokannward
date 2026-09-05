<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'product_id', 'user_id', 'reviewer_name', 'reviewer_email',
        'rating', 'title', 'body', 'recommended', 'approved', 'helpful_count', 'ip_address',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'rating' => 'integer',
        'recommended' => 'boolean',
        'approved' => 'boolean',
        'helpful_count' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approved', true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approved', false);
    }

    /** Prefers the account name, falls back to whatever the guest typed in. */
    public function getAuthorNameAttribute(): string
    {
        return $this->user?->name ?: ($this->reviewer_name ?: 'Anonymous');
    }
}
