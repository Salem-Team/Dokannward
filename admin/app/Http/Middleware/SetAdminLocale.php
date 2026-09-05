<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    /**
     * Handle admin locale independently from public site
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'ar'];

        // Use separate session key for admin locale
        $adminLocale = session('admin_locale', 'en');

        // Validate
        if (!in_array($adminLocale, $supported)) {
            $adminLocale = 'en';
            session(['admin_locale' => $adminLocale]);
        }

        // Set application locale for admin
        app()->setLocale($adminLocale);

        return $next($request);
    }
}
