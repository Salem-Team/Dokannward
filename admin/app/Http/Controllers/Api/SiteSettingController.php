<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SiteSettingController extends Controller
{
    /** Bumped by the admin Settings controller whenever currency is saved. */
    public const CURRENCY_CACHE_KEY = 'site-settings.currency';

    public const STORE_CACHE_KEY = 'site-settings.store';

    public const CHECKOUT_CACHE_KEY = 'site-settings.checkout';

    public const INVENTORY_CACHE_KEY = 'site-settings.inventory';

    public const CONTENT_CACHE_KEY = 'site-settings.content';

    public function index()
    {
        return response()->json(SiteSetting::all());
    }

    /**
     * The only setting the storefront actually needs at runtime: what
     * currency symbol/code/position to render prices with. Admin-controlled
     * from Settings → Currency, with safe defaults if nothing was saved yet.
     *
     * Cached for a few minutes — this is hit on every ISR revalidation of
     * every storefront page, but currency changes are rare, so there's no
     * reason to pay a database round trip for it every time.
     */
    public function currency()
    {
        $payload = Cache::remember(self::CURRENCY_CACHE_KEY, now()->addMinutes(30), function () {
            $value = SiteSetting::where('key', 'currency')->first()?->value ?? [];

            return array_merge(SettingsController::DEFAULTS['currency'], $value);
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    /**
     * Store identity for header/footer/contact — Admin → Settings → General.
     */
    public function storefront()
    {
        $payload = Cache::remember(self::STORE_CACHE_KEY, now()->addMinutes(30), function () {
            $value = SiteSetting::where('key', 'general')->first()?->value ?? [];

            return array_merge(SettingsController::DEFAULTS['general'], $value);
        });

        return response()
            ->json([
                'name' => $payload['store_name'],
                'email' => $payload['store_email'] ?? '',
                'phone' => $payload['store_phone'] ?? '',
                'whatsapp' => $payload['store_whatsapp'] ?? $payload['store_phone'] ?? '',
                'description' => $payload['store_description'] ?? '',
                'announcement' => $payload['store_announcement'] ?? '',
                'address_label' => $payload['store_address_label'] ?? '',
                'address_label_ar' => $payload['store_address_label_ar'] ?? '',
                'address' => $payload['store_address'] ?? '',
                'address_ar' => $payload['store_address_ar'] ?? '',
                'maps_url' => $payload['store_maps_url'] ?? '',
                'logo' => $payload['store_logo'] ?? '',
                'logo_on_dark' => $payload['store_logo_on_dark'] ?? '',
                'seo_description' => $payload['seo_description'] ?? '',
                'seo_og_image' => $payload['seo_og_image'] ?? '',
                'social' => [
                    'instagram' => $payload['social_instagram'] ?? '',
                    'tiktok' => $payload['social_tiktok'] ?? '',
                    'facebook' => $payload['social_facebook'] ?? '',
                ],
            ])
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    /**
     * Shipping + tax rules for the checkout summary — mirrors what
     * CheckoutController charges when an order is placed.
     */
    public function checkout()
    {
        $payload = Cache::remember(
            self::CHECKOUT_CACHE_KEY,
            now()->addMinutes(30),
            fn () => SettingsController::checkoutPayload()
        );

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    /** Customer-facing stock visibility — availability checks remain enforced server-side. */
    public function inventory()
    {
        $payload = Cache::remember(self::INVENTORY_CACHE_KEY, now()->addMinutes(30), function () {
            $value = SiteSetting::where('key', 'inventory')->first()?->value ?? [];

            return array_merge(SettingsController::DEFAULTS['inventory'], $value);
        });

        return response()
            ->json([
                'show_stock_status' => (bool) ($payload['show_stock_status'] ?? true),
                'show_stock_quantity' => (bool) ($payload['show_stock_quantity'] ?? false),
            ])
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    /**
     * Structured website copy — Admin → Website.
     * Powers nav, about, contact, FAQ, footer trust rows, and homepage section titles.
     */
    public function content()
    {
        $payload = Cache::remember(self::CONTENT_CACHE_KEY, now()->addMinutes(30), function () {
            return SiteContent::all();
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string',
            'value' => 'nullable|array',
        ]);

        $entry = SiteSetting::create(array_merge(['id' => (string) \Illuminate\Support\Str::uuid()], $data));

        return response()->json($entry, 201);
    }

    public function update(Request $request, string $id)
    {
        $entry = SiteSetting::findOrFail($id);
        $data = $request->validate(['value' => 'nullable|array']);
        $entry->update($data);

        return response()->json($entry);
    }

    public function destroy(string $id)
    {
        SiteSetting::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
