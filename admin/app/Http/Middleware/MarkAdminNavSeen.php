<?php

namespace App\Http\Middleware;

use App\Services\NavBadge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When an admin opens Orders / Reviews / Contact, clear that sidebar badge
 * and share the previous count so the UI can animate it down to zero.
 */
class MarkAdminNavSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only GET page views — never clear on POSTs (approve, status, etc.).
        if ($request->isMethod('GET')) {
        // Don't clear badges while downloading PDFs / credit notes.
            $section = match (true) {
                $request->routeIs('admin.orders.invoice') => null,
                $request->routeIs('admin.orders.returns.creditNote') => null,
                $request->routeIs('admin.orders.*') => 'orders',
                $request->routeIs('admin.reviews.*') => 'reviews',
                $request->routeIs('admin.contact-messages.*') => 'contact',
                default => null,
            };

            if ($section) {
                $cleared = NavBadge::markSeen($section);

                if ($cleared > 0) {
                    view()->share('navBadgeClear', [$section => $cleared]);
                }
            }
        }

        return $next($request);
    }
}
