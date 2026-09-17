<?php

namespace App\Providers;

use App\Models\ContactMessage;
use App\Models\Notification;
use App\Models\Order;
use App\Models\ProductReview;
use App\Services\Branding\BrandingService;
use App\Services\NavBadge;
use App\Support\Branding;
use App\Support\PublicUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BrandingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->guardProductionUrls();
        $this->configurePasswordDefaults();
        $this->shareBrandingWithViews();
        $this->applyBrandingMailFrom();

        // Admin "Keep me signed in" — long enough for daily staff use,
        // short enough that a stolen device does not stay privileged forever.
        Auth::guard('web')->setRememberDuration(60 * 24 * 30);

        // Every admin page renders the topbar + sidebar chrome, which used to
        // run 4 fresh count/list queries per request no matter which page
        // was being viewed. Cached briefly (and busted the moment the
        // underlying model changes) so that overhead disappears without the
        // badges ever looking stale to an admin actively working.
        View::composer('admin.partials.topbar', function ($view) {
            $view->with([
                'notifications' => Cache::remember('chrome.notifications.recent', 30, fn () => Notification::recent(12)->get()),
                'unreadCount' => Cache::remember('chrome.notifications.unread_count', 30, fn () => Notification::unread()->count()),
            ]);
        });

        // Sidebar badges: unseen since last visit (orders/reviews) or unread (contact).
        View::composer('admin.partials.sidebar', function ($view) {
            $view->with([
                'pendingOrders' => NavBadge::unseenOrders(),
                'pendingReviews' => NavBadge::unseenReviews(),
                'unreadContactMessages' => NavBadge::unseenContact(),
            ]);
        });

        foreach ([Notification::class, ProductReview::class, ContactMessage::class, Order::class] as $model) {
            $model::saved(fn () => self::forgetChromeCache());
            $model::deleted(fn () => self::forgetChromeCache());
        }
    }

    /**
     * Share resolved branding with every admin Blade view (live — no config:cache).
     */
    private function shareBrandingWithViews(): void
    {
        View::composer(['admin.*', 'errors.*'], function ($view) {
            $branding = Branding::all();
            $tenant = Branding::tenantBranding();
            // Brand logos + icons are served with a 1-year immutable Cache-Control.
            // Bump this stamp whenever seal / favicon assets are replaced in place.
            $logoStamp = 'dw3';

            $view->with([
                'branding' => $branding,
                'tenantBranding' => $tenant,
                'brandDisplayName' => $tenant['displayName'] ?: ($branding['company_name'] ?? 'Default Company'),
                'brandLogoUrl' => PublicUrl::versioned(
                    $tenant['logoLightUrl'] ?: ($branding['logo_light_url'] ?? asset('images/brand-logo.png')),
                    $logoStamp,
                ),
                'brandLogoOnDarkUrl' => PublicUrl::versioned(
                    $tenant['logoDarkUrl']
                        ?: ($branding['logo_dark_url'] ?? asset('images/brand-logo-on-dark.png')),
                    $logoStamp,
                ),
                'brandFaviconUrl' => PublicUrl::versioned(
                    $tenant['faviconUrl'] ?: ($branding['favicon_url'] ?? asset('favicon.ico')),
                    $logoStamp,
                ),
                'brandCssVariables' => app(BrandingService::class)->cssVariables(),
            ]);
        });
    }

    /** Keep outbound mail "from" name aligned with live tenant branding. */
    private function applyBrandingMailFrom(): void
    {
        try {
            if (! $this->app->runningInConsole() && request()->is('api/*')) {
                return;
            }

            $name = Branding::companyName();
            $address = (string) (Branding::get('email_from_address') ?: config('mail.from.address'));
            if ($name !== '' && $address !== '') {
                config([
                    'mail.from.name' => (string) (Branding::get('email_from_name') ?: $name),
                    'mail.from.address' => $address,
                ]);
            }
        } catch (\Throwable) {
            // Branding unavailable during early boot / migrate.
        }
    }

    /** Named limiters for API + admin login (used by route middleware). */
    private function configureRateLimiting(): void
    {
        // Checkout map lookups are bursty (pan/zoom/search) — keep them off the general API bucket.
        RateLimiter::for('geo', function (Request $request) {
            return Limit::perMinute(360)->by($request->ip());
        });

        // Storefront browsing + post-deploy warm scripts share this bucket per IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(480)->by($request->ip());
        });

        // Soft ceiling only — LoginAttemptLimiter (5 / 15 min) is the real lockout.
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(40)->by($request->ip());
        });
    }

    /**
     * Length ≥ 12, mixed complexity, and reject known-breached passwords
     * when the app can reach the Have I Been Pwned API.
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return app()->environment('production')
                ? $rule->uncompromised()
                : $rule;
        });
    }

    /**
     * Hard-fail in production if any public URL still points at a local/dev host.
     * Prevents a bad deploy or cached env() fallback from shipping localhost links.
     */
    private function guardProductionUrls(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (empty(config('app.key'))) {
            throw new \RuntimeException('Production misconfigured: APP_KEY is not set. Run php artisan key:generate on the server and redeploy.');
        }

        $keys = [
            'app.url' => config('app.url'),
            'app.frontend_url' => config('app.frontend_url'),
            'app.storefront_url' => config('app.storefront_url'),
            'app.storefront_public_url' => config('app.storefront_public_url'),
        ];

        foreach ($keys as $name => $value) {
            $value = strtolower((string) $value);
            if ($value === '' || str_contains($value, 'localhost') || str_contains($value, '127.0.0.1') || str_contains($value, ':3000') || str_contains($value, ':8000')) {
                throw new \RuntimeException("Production misconfigured: {$name} must be a public HTTPS URL (got: {$value}).");
            }
            if (! str_starts_with($value, 'https://')) {
                throw new \RuntimeException("Production misconfigured: {$name} must use https://");
            }
        }
    }

    /** Invalidated on any write to the models the admin chrome badges/lists depend on. */
    public static function forgetChromeCache(): void
    {
        Cache::forget('chrome.notifications.recent');
        Cache::forget('chrome.notifications.unread_count');
        Cache::forget('chrome.orders.unseen_count');
        Cache::forget('chrome.reviews.pending_count');
        Cache::forget('chrome.contact_messages.unread_count');
    }
}
