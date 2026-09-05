<?php

use App\Http\Middleware\CacheStaticAssets;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Nginx terminates TLS in front of PHP-FPM / the storefront proxy.
        $middleware->trustProxies(at: '*');

        // Protect the public storefront API from abusive scrapers without
        // constraining normal browsing + post-deploy warm (generous per IP).
        $middleware->throttleApi('api');

        $middleware->api(prepend: [
            SetApiLocale::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            CacheStaticAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.locale' => SetAdminLocale::class,
            'admin.nav.seen' => \App\Http\Middleware\MarkAdminNavSeen::class,
        ]);

        // Authenticated visitors hitting guest-only pages (e.g. /admin/login)
        // go to the dashboard — never to a public "home" storefront.
        $middleware->redirectUsersTo('/admin/dashboard');
        $middleware->redirectGuestsTo('/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Oversized multipart bodies never reach the controller — surface a
        // clear admin message instead of a bare 413/500.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'Upload is too large. Use images up to 2 GB each, or fewer photos at once.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message], 413);
            }

            if ($request->is('admin') || $request->is('admin/*')) {
                return redirect()
                    ->back()
                    ->with('error', $message)
                    ->withInput();
            }

            return null;
        });

        // Never strand staff on a bare "429 | TOO MANY REQUESTS" page.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $retry = max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60));
            $message = "Too many requests. Please wait about {$retry} second(s) and try again.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()
                    ->json(['message' => $message], 429)
                    ->withHeaders($e->getHeaders());
            }

            if ($request->is('admin') || $request->is('admin/*')) {
                $target = $request->is('admin/login') || $request->routeIs('admin.login*')
                    ? url('/admin/login')
                    : (url()->previous() ?: url('/admin/login'));

                return redirect()
                    ->to($target)
                    ->with('error', $message)
                    ->withInput();
            }

            return null;
        });

        // Admin CRUD must never dump a bare 500 page — redirect with a flash
        // so staff always land back in the desk with a clear message.
        $exceptions->render(function (Throwable $e, Request $request) {
            $isAdmin = $request->is('admin') || $request->is('admin/*');
            if (! $isAdmin) {
                return null;
            }

            if (
                $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof TokenMismatchException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500)
            ) {
                return null;
            }

            report($e);

            $message = match (true) {
                $e instanceof ModelNotFoundException => 'That record was not found or was already removed.',
                $e instanceof QueryException => 'This action could not be completed because related records still depend on it.',
                default => config('app.debug')
                    ? $e->getMessage()
                    : 'Something went wrong while saving. Please try again.',
            };

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 500);
            }

            $fallback = url('/admin/dashboard');
            $target = url()->previous();
            if (! is_string($target) || $target === '' || $target === url()->current()) {
                $target = $fallback;
            }

            return redirect()
                ->to($target)
                ->with('error', $message)
                ->withInput();
        });

        // Keep the public JSON API's error contract clean and safe: never
        // leak stack traces or absolute file paths to API clients, even
        // while APP_DEBUG=true for local development of the admin dashboard.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $e instanceof ModelNotFoundException => 404,
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $payload = [
                'message' => $status === 500 && ! config('app.debug')
                    ? 'Something went wrong. Please try again.'
                    : $e->getMessage(),
            ];

            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors();
            }

            return response()->json($payload, $status);
        });
    })->create();
