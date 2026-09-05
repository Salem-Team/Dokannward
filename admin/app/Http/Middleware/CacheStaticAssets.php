<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Long-cache fingerprinted static assets when they are served through
 * Laravel (php artisan serve / some hosts). Hashed Vite filenames are
 * safe to cache forever — a new build gets a new hash.
 */
class CacheStaticAssets
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = $request->path();
        if (
            str_starts_with($path, 'build/')
            || str_starts_with($path, 'images/')
            || str_starts_with($path, 'fonts/')
        ) {
            $response->headers->set(
                'Cache-Control',
                'public, max-age=31536000, immutable'
            );
        }

        return $response;
    }
}
