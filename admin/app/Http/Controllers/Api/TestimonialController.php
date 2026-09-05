<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TestimonialController extends Controller
{
    public const LIST_CACHE_KEY = 'api.testimonials.list';

    public function index(Request $request)
    {
        $featured = $request->boolean('featured');
        $generation = Cache::get('api.testimonials.list.generation') ?: 'v1';
        $cacheKey = self::LIST_CACHE_KEY.'.'.$generation.'.'.($featured ? 'featured' : 'all');

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($featured) {
            $q = Testimonial::query()->active()->ordered();

            if ($featured) {
                $q->featured();
            }

            return $q->get()
                ->map(function (Testimonial $t) {
                    $row = $t->toArray();
                    // Prefer monogram initials on the storefront when no custom photo.
                    if (! filled($t->avatar)) {
                        $row['avatar_url'] = null;
                    }

                    return $row;
                })
                ->values()
                ->all();
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    public static function forgetListCache(): void
    {
        Cache::forever('api.testimonials.list.generation', (string) Str::uuid());
    }
}
