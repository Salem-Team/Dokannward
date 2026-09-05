<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront API locale — driven by X-Locale / ?locale from Next.js,
 * so translated_name / description resolve to ar or en correctly.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header('X-Locale')
            ?? $request->query('locale')
            ?? $request->header('Accept-Language');

        $locale = 'en';
        if (is_string($raw) && $raw !== '') {
            $candidate = strtolower(substr(trim(explode(',', $raw)[0]), 0, 2));
            if (in_array($candidate, ['ar', 'en'], true)) {
                $locale = $candidate;
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
