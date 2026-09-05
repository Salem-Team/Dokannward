<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public const LIST_CACHE_KEY = 'api.pages.list';

    public const SHOW_CACHE_PREFIX = 'api.pages.show.';

    /** Published pages for storefront CMS (about, policies, etc.). */
    public function index()
    {
        $generation = Cache::get('api.pages.list.generation') ?: 'v1';
        $payload = Cache::remember(self::LIST_CACHE_KEY.'.'.$generation, now()->addMinutes(15), function () {
            return Page::query()
                ->published()
                ->orderBy('position')
                ->orderBy('title')
                ->get(['id', 'slug', 'title', 'meta_title', 'meta_description', 'position'])
                ->toArray();
        });

        return response()
            ->json(['data' => $payload])
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    /**
     * Resolve by UUID or slug — only published pages are public.
     */
    public function show(string $id)
    {
        $generation = Cache::get('api.pages.list.generation') ?: 'v1';
        $cacheKey = self::SHOW_CACHE_PREFIX.$generation.'.'.md5($id);

        $page = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($id) {
            return Page::query()
                ->published()
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('slug', $id);
                })
                ->firstOrFail()
                ->toArray();
        });

        return response()
            ->json($page)
            ->header('Cache-Control', 'private, no-cache, must-revalidate');
    }

    public static function forgetListCache(): void
    {
        Cache::forever('api.pages.list.generation', (string) Str::uuid());
    }
}
