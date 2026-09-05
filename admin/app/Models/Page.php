<?php

namespace App\Models;

use App\Http\Controllers\Api\PageController;
use App\Services\StorefrontRevalidator;
use App\Support\StorePolicies;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'slug', 'title', 'content', 'meta_title', 'meta_description', 'published', 'position',
    ];

    protected $casts = [
        'published' => 'boolean',
        'position' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    protected static function booted(): void
    {
        $purge = function (Page $page): void {
            PageController::forgetListCache();

            $slug = (string) $page->slug;
            $paths = ['/', '/pages/about', ...StorePolicies::storefrontPaths()];
            $tags = ['pages'];

            if ($slug !== '') {
                $tags[] = 'page:'.$slug;
                if (StorePolicies::isPolicySlug($slug)) {
                    $paths[] = "/policies/{$slug}";
                } elseif ($slug !== 'about') {
                    $paths[] = "/pages/{$slug}";
                }
            }

            StorefrontRevalidator::purge(
                array_values(array_unique($paths)),
                array_values(array_unique($tags)),
            );
        };

        static::saved($purge);
        static::deleted($purge);
    }
}
