<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    public const LIST_CACHE_KEY = 'api.banners.live';

    /** Live homepage CTA banners, ordered for the storefront. */
    public function index()
    {
        $generation = Cache::get('api.banners.list.generation') ?: 'v1';
        // Minute bucket so date-window banners (start_at / end_at) flip on time
        // without waiting for a full TTL when the generation key hasn't bumped.
        $bucket = now()->format('YmdHi');
        $cacheKey = self::LIST_CACHE_KEY.'.'.$generation.'.'.$bucket;

        $payload = Cache::remember($cacheKey, now()->addMinutes(2), function () {
            return Banner::query()
                ->live()
                ->orderBy('position')
                ->orderBy('created_at')
                ->get([
                    'id', 'title', 'subtitle', 'button_text', 'button_url',
                    'image_url', 'position',
                ])
                ->toArray();
        });

        return response()
            ->json(['data' => $payload])
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    public static function forgetListCache(): void
    {
        Cache::forever('api.banners.list.generation', (string) Str::uuid());
    }
}
