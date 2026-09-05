<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->segment(1) === 'ar' ? 'ar' : 'en';

        // Only dirty the session when the locale actually changes — writing
        // session(['locale' => …]) on every request forced a DB/file write
        // for every admin page view.
        if ($request->session()->get('locale') !== $locale) {
            $request->session()->put('locale', $locale);
        }

        app()->setLocale($locale);

        if ($locale === 'ar') {
            \Illuminate\Pagination\Paginator::useBootstrapFive();
        }

        return $next($request);
    }
}
