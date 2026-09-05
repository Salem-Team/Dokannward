<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\SiteSettingController as ApiSiteSettingController;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\StorefrontRevalidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every site-wide setting (store info, currency, shipping, tax) is stored as
 * a single row per key in `site_settings` (key/value JSON). This controller
 * is the one place that reads/writes them, so the storefront's public
 * `/api/settings/*` endpoints and this admin screen never drift apart.
 */
class SettingsController extends Controller
{
    /** Sensible defaults so the page (and the storefront) never break on a fresh install. */
    public const DEFAULTS = [
        'general' => [
            'store_name' => 'Dokan Ward',
            'store_email' => 'hello@dokannward.com',
            'store_phone' => '+201069503631',
            'store_whatsapp' => '+201069503631',
            'store_description' => 'Premium home décor — artificial plants, vases, bakhoor, candles, lamps, and boho pieces.',
            'store_announcement' => 'Bring nature indoors — shop home decor, plants & more',
            'store_address_label' => 'Serving Egypt',
            'store_address_label_ar' => 'نخدم كل مصر',
            'store_address' => 'Egypt',
            'store_address_ar' => 'مصر',
            'store_maps_url' => '',
            'store_logo' => '/images/dokan-ward-logo.png',
            'store_logo_on_dark' => '/images/dokan-ward-logo.png',
            'seo_description' => 'Dokan Ward is Egypt’s home décor destination — premium artificial plants, vases, bakhoor, candles, lamps, and boho pieces curated since 2018.',
            'seo_og_image' => '/images/og-share.jpg',
            'social_instagram' => 'https://www.instagram.com/dokan_ward_96/',
            'social_tiktok' => '',
            'social_facebook' => '',
        ],
        'currency' => [
            'code' => 'EGP',
            'symbol' => 'LE',
            'position' => 'before', // before|after the amount
        ],
        'shipping' => [
            'standard_shipping_fee' => 10,
            'shipping_company' => 'Dokan Ward Delivery',
        ],
        'inventory' => [
            'show_stock_status' => true,
            'show_stock_quantity' => false,
        ],
        'tax' => [
            'tax_rate' => 8.5,
            'tax_enabled' => true,
            'tax_enabled_message' => 'Tax {rate}% added to this order',
            'tax_disabled_message' => 'Prices shown without tax',
        ],
        'payments' => [
            'default_method' => 'cash',
            'cash_enabled' => true,
            'cash_label' => 'Cash on delivery',
            'cash_instructions' => 'Pay the courier in cash when your order arrives.',
            'instapay_enabled' => false,
            'instapay_label' => 'InstaPay',
            'instapay_instructions' => 'Send the total on InstaPay, then share the receipt on WhatsApp.',
            'visa_enabled' => false,
            'visa_label' => 'Visa / Mastercard',
            'visa_instructions' => 'Our team sends a secure card payment link once the order is confirmed.',
            'wallet_enabled' => false,
            'wallet_label' => 'Vodafone Cash',
            'wallet_instructions' => 'Transfer the total to our wallet, then share the receipt on WhatsApp.',
        ],
    ];

    /**
     * Payment methods the store can offer. Adding a key here is all it takes
     * to surface a new option in Settings, the storefront and the order desk.
     *
     * @var list<string>
     */
    public const PAYMENT_METHODS = ['cash', 'instapay', 'visa', 'wallet'];

    private const SECTIONS = ['general', 'currency', 'shipping', 'inventory', 'tax', 'payments'];

    public function index()
    {
        $settings = self::DEFAULTS;

        foreach (SiteSetting::whereIn('key', array_keys(self::DEFAULTS))->get() as $row) {
            $settings[$row->key] = array_merge($settings[$row->key] ?? [], $row->value ?? []);
        }

        $storefrontBase = rtrim((string) config('app.frontend_url'), '/');

        return view('admin.settings.index', compact('settings', 'storefrontBase'));
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $section = (string) $request->input('section', '');

        if (! in_array($section, self::SECTIONS, true)) {
            throw ValidationException::withMessages([
                'section' => 'Unknown settings section.',
            ]);
        }

        match ($section) {
            'general' => $this->saveGeneral($request),
            'currency' => $this->saveCurrency($request),
            'shipping' => $this->saveShipping($request),
            'inventory' => $this->saveInventory($request),
            'tax' => $this->saveTax($request),
            'payments' => $this->savePayments($request),
        };

        StorefrontRevalidator::purgeChrome();

        if ($request->expectsJson() || $request->ajax()) {
            $payload = [
                'ok' => true,
                'section' => $section,
                'message' => 'Saved — live on the website.',
            ];

            if (in_array($section, ['shipping', 'tax', 'payments'], true)) {
                $payload['checkout'] = self::checkoutPayload();
            }

            return response()->json($payload);
        }

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Settings saved — live on the website.');
    }

    /**
     * Canonical checkout settings payload shared with /api/settings/checkout.
     *
     * @return array{
     *     standard_shipping_fee: float,
     *     shipping_company: string,
     *     tax_rate: float,
     *     tax_enabled: bool,
     *     tax_enabled_message: string,
     *     tax_disabled_message: string,
     *     payment_methods: list<array{key: string, label: string, instructions: string}>,
     *     default_payment_method: string,
     *     updated_at: string|null
     * }
     */
    public static function checkoutPayload(): array
    {
        $shippingRow = SiteSetting::where('key', 'shipping')->first();
        $taxRow = SiteSetting::where('key', 'tax')->first();
        $paymentsRow = SiteSetting::where('key', 'payments')->first();

        $shipping = array_merge(self::DEFAULTS['shipping'], $shippingRow?->value ?? []);
        $tax = array_merge(self::DEFAULTS['tax'], $taxRow?->value ?? []);
        $payments = self::paymentMethods($paymentsRow?->value ?? []);

        $updatedAt = collect([
            $shippingRow?->updated_at,
            $taxRow?->updated_at,
            $paymentsRow?->updated_at,
        ])
            ->filter()
            ->max();

        return [
            'standard_shipping_fee' => (float) ($shipping['standard_shipping_fee'] ?? 0),
            'shipping_company' => trim((string) ($shipping['shipping_company'] ?? '')),
            'tax_rate' => (float) ($tax['tax_rate'] ?? 0),
            'tax_enabled' => (bool) ($tax['tax_enabled'] ?? false),
            'tax_enabled_message' => (string) ($tax['tax_enabled_message'] ?? ''),
            'tax_disabled_message' => (string) ($tax['tax_disabled_message'] ?? ''),
            'payment_methods' => $payments['methods'],
            'default_payment_method' => $payments['default_method'],
            'updated_at' => $updatedAt?->toIso8601String(),
        ];
    }

    /**
     * Resolves the enabled payment methods and the default the shopper lands on.
     *
     * A store must always be able to take an order, so an empty or corrupt
     * selection falls back to cash rather than leaving checkout unusable.
     *
     * @param  array<string, mixed>  $saved
     * @return array{
     *     methods: list<array{key: string, label: string, instructions: string}>,
     *     default_method: string
     * }
     */
    public static function paymentMethods(array $saved = []): array
    {
        $config = array_merge(self::DEFAULTS['payments'], $saved);
        $methods = [];

        foreach (self::PAYMENT_METHODS as $key) {
            if (! filter_var($config[$key.'_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $label = trim((string) ($config[$key.'_label'] ?? ''));
            $instructions = trim((string) ($config[$key.'_instructions'] ?? ''));

            $methods[] = [
                'key' => $key,
                'label' => $label !== '' ? $label : self::DEFAULTS['payments'][$key.'_label'],
                'instructions' => $instructions,
            ];
        }

        if ($methods === []) {
            $methods[] = [
                'key' => 'cash',
                'label' => self::DEFAULTS['payments']['cash_label'],
                'instructions' => self::DEFAULTS['payments']['cash_instructions'],
            ];
        }

        $keys = array_column($methods, 'key');
        $default = (string) ($config['default_method'] ?? '');

        return [
            'methods' => $methods,
            'default_method' => in_array($default, $keys, true) ? $default : $keys[0],
        ];
    }

    private function saveGeneral(Request $request): void
    {
        foreach ([
            'store_phone',
            'store_whatsapp',
            'store_description',
            'store_announcement',
            'store_address_label',
            'store_address_label_ar',
            'store_address',
            'store_address_ar',
            'store_maps_url',
            'store_logo',
            'store_logo_on_dark',
            'seo_description',
            'seo_og_image',
            'social_instagram',
            'social_tiktok',
            'social_facebook',
        ] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }

        foreach (['social_instagram', 'social_tiktok', 'social_facebook', 'store_maps_url'] as $key) {
            $request->merge([$key => $this->normalizeUrl($request->input($key))]);
        }

        $data = $request->validate([
            'store_name' => 'required|string|max:255',
            'store_email' => 'required|email|max:255',
            'store_phone' => 'nullable|string|max:50',
            'store_whatsapp' => 'nullable|string|max:50',
            'store_description' => 'nullable|string|max:1000',
            'store_announcement' => 'nullable|string|max:255',
            'store_address_label' => 'nullable|string|max:120',
            'store_address_label_ar' => 'nullable|string|max:120',
            'store_address' => 'nullable|string|max:500',
            'store_address_ar' => 'nullable|string|max:500',
            'store_maps_url' => 'nullable|url|max:700',
            'store_logo' => 'nullable|string|max:500',
            'store_logo_on_dark' => 'nullable|string|max:500',
            'seo_description' => 'nullable|string|max:320',
            'seo_og_image' => 'nullable|string|max:500',
            'social_instagram' => 'nullable|url|max:500',
            'social_tiktok' => 'nullable|url|max:500',
            'social_facebook' => 'nullable|url|max:500',
        ]);

        $defaults = self::DEFAULTS['general'];
        $data['store_whatsapp'] = $data['store_whatsapp'] ?? '';
        $data['social_instagram'] = $data['social_instagram'] ?? '';
        $data['social_tiktok'] = $data['social_tiktok'] ?? '';
        $data['social_facebook'] = $data['social_facebook'] ?? '';
        $data['store_address_label'] = $data['store_address_label'] ?? '';
        $data['store_address_label_ar'] = $data['store_address_label_ar'] ?? '';
        $data['store_address'] = $data['store_address'] ?? '';
        $data['store_address_ar'] = $data['store_address_ar'] ?? '';
        $data['store_maps_url'] = $data['store_maps_url'] ?? '';
        $data['store_description'] = $data['store_description'] ?? '';
        $data['store_announcement'] = $data['store_announcement'] ?? '';
        $data['store_phone'] = $data['store_phone'] ?? '';
        $data['store_logo'] = $data['store_logo'] ?? $defaults['store_logo'];
        $data['store_logo_on_dark'] = $data['store_logo_on_dark'] ?? $defaults['store_logo_on_dark'];
        $data['seo_description'] = $data['seo_description'] ?? $defaults['seo_description'];
        $data['seo_og_image'] = $data['seo_og_image'] ?? $defaults['seo_og_image'];

        $this->put('general', $data);
        Cache::forget(ApiSiteSettingController::STORE_CACHE_KEY);
    }

    private function saveCurrency(Request $request): void
    {
        $data = $request->validate([
            'code' => 'required|string|max:10',
            'symbol' => 'required|string|max:10',
            'position' => 'required|in:before,after',
        ]);

        $data['code'] = Str::upper(trim($data['code']));

        $this->put('currency', $data);
        Cache::forget(ApiSiteSettingController::CURRENCY_CACHE_KEY);
    }

    private function saveShipping(Request $request): void
    {
        $data = $request->validate([
            'standard_shipping_fee' => 'required|numeric|min:0|max:999999.99',
            'shipping_company' => 'nullable|string|max:120',
        ]);

        $data['standard_shipping_fee'] = (float) $data['standard_shipping_fee'];
        $data['shipping_company'] = trim((string) ($data['shipping_company'] ?? ''));

        // Replace the section so retired free-shipping fields can never affect
        // checkout again after this simpler fixed-price model is saved.
        $this->put('shipping', $data);
        Cache::forget(ApiSiteSettingController::CHECKOUT_CACHE_KEY);
    }

    private function saveTax(Request $request): void
    {
        $data = $request->validate([
            'tax_rate' => 'required|numeric|min:0|max:100',
            'tax_enabled_message' => 'sometimes|required|string|max:255',
            'tax_disabled_message' => 'sometimes|required|string|max:255',
        ]);
        $data['tax_rate'] = (float) $data['tax_rate'];
        $data['tax_enabled'] = $request->boolean('tax_enabled');

        $this->put('tax', $this->mergeSection('tax', $data));
        Cache::forget(ApiSiteSettingController::CHECKOUT_CACHE_KEY);
    }

    private function saveInventory(Request $request): void
    {
        $showStatus = $request->boolean('show_stock_status');

        $this->put('inventory', [
            'show_stock_status' => $showStatus,
            // Exact quantities only make sense while stock visibility is on.
            'show_stock_quantity' => $showStatus && $request->boolean('show_stock_quantity'),
        ]);
        Cache::forget(ApiSiteSettingController::INVENTORY_CACHE_KEY);
    }

    private function savePayments(Request $request): void
    {
        $rules = ['default_method' => 'required|in:'.implode(',', self::PAYMENT_METHODS)];

        foreach (self::PAYMENT_METHODS as $key) {
            $rules[$key.'_label'] = 'nullable|string|max:120';
            $rules[$key.'_instructions'] = 'nullable|string|max:500';
        }

        $data = $request->validate($rules);
        $enabled = [];

        foreach (self::PAYMENT_METHODS as $key) {
            $isOn = $request->boolean($key.'_enabled');
            $data[$key.'_enabled'] = $isOn;
            $data[$key.'_label'] = trim((string) ($data[$key.'_label'] ?? '')) ?: self::DEFAULTS['payments'][$key.'_label'];
            $data[$key.'_instructions'] = trim((string) ($data[$key.'_instructions'] ?? ''));

            if ($isOn) {
                $enabled[] = $key;
            }
        }

        if ($enabled === []) {
            throw ValidationException::withMessages([
                'default_method' => 'Keep at least one payment method switched on so shoppers can check out.',
            ]);
        }

        if (! in_array($data['default_method'], $enabled, true)) {
            throw ValidationException::withMessages([
                'default_method' => 'The default payment method has to be one of the enabled methods.',
            ]);
        }

        $this->put('payments', $this->mergeSection('payments', $data));
        Cache::forget(ApiSiteSettingController::CHECKOUT_CACHE_KEY);
    }

    private function mergeSection(string $key, array $data): array
    {
        $saved = SiteSetting::where('key', $key)->first()?->value ?? [];

        return array_merge(self::DEFAULTS[$key], $saved, $data);
    }

    private function put(string $key, array $value): void
    {
        $setting = SiteSetting::firstOrNew(['key' => $key]);
        if (! $setting->exists) {
            $setting->id = (string) Str::uuid();
        }
        $setting->value = $value;
        $setting->save();
    }

    private function normalizeUrl(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        return $url;
    }
}
