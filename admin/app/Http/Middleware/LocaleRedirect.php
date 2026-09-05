<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocaleRedirect
{
    /**
     * Redirect root path to locale-prefixed URL based on user preference
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // No redirect needed - English is default (no prefix), only Arabic uses /ar
        return $next($request);
    }
}
