<?php

/**
 * Apply Dokan Ward identity (name, logo, hotline, social, SEO) from the live scrape.
 *
 * Usage: php scripts/apply-dokannward-branding.php
 */

use App\Http\Controllers\Api\SiteSettingController as ApiSiteSettingController;
use App\Http\Controllers\Admin\SettingsController;
use App\Models\Brand;
use App\Models\SiteSetting;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

require __DIR__.'/../admin/vendor/autoload.php';
$app = require __DIR__.'/../admin/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$catalogPath = dirname(__DIR__).'/scrape/dokannward/data/catalog.json';
$brandMeta = [
    'name' => 'Dokan Ward',
    'name_ar' => 'دكان ورد',
    'hotline' => '01069503631',
    'phone' => '+201069503631',
    'instagram' => 'https://www.instagram.com/dokan_ward_96/',
    'logo' => '/images/dokan-ward-logo.png',
    'url' => 'https://dokannward.com',
];

if (is_file($catalogPath)) {
    $payload = json_decode((string) File::get($catalogPath), true);
    if (is_array($payload['brand'] ?? null)) {
        $brandMeta = array_merge($brandMeta, $payload['brand']);
    }
}

$logo = (string) ($brandMeta['logo'] ?? '/images/dokan-ward-logo.png');
$phone = (string) ($brandMeta['phone'] ?? '+201069503631');
$hotline = (string) ($brandMeta['hotline'] ?? '01069503631');
if ($phone === '' && $hotline !== '') {
    $phone = str_starts_with($hotline, '0') ? '+2'.$hotline : $hotline;
}

$saveSetting = static function (string $key, array $value): void {
    $setting = SiteSetting::firstOrNew(['key' => $key]);
    if (! $setting->exists) {
        $setting->id = (string) Str::uuid();
    }
    $setting->value = $value;
    $setting->save();
};

$general = array_merge(SettingsController::DEFAULTS['general'], [
    'store_name' => 'Dokan Ward',
    'store_email' => 'hello@dokannward.com',
    'store_phone' => $phone,
    'store_whatsapp' => $phone,
    'store_description' => 'Premium home décor — artificial plants, vases, bakhoor, candles, lamps, and boho pieces.',
    'store_announcement' => 'Bring nature indoors — shop home decor, plants & more',
    'store_address_label' => 'Serving Egypt',
    'store_address_label_ar' => 'نخدم كل مصر',
    'store_address' => 'Egypt',
    'store_address_ar' => 'مصر',
    'store_logo' => $logo,
    'store_logo_on_dark' => $logo,
    'seo_description' => 'Dokan Ward is Egypt’s home décor destination — premium artificial plants, vases, bakhoor, candles, lamps, and boho pieces curated since 2018.',
    'seo_og_image' => '/images/og-share.jpg',
    'social_instagram' => (string) ($brandMeta['instagram'] ?? 'https://www.instagram.com/dokan_ward_96/'),
    'social_tiktok' => '',
    'social_facebook' => '',
]);
$saveSetting('general', $general);

foreach (['currency', 'shipping', 'inventory', 'tax', 'payments'] as $section) {
    $row = SiteSetting::where('key', $section)->first();
    if (! $row || ! is_array($row->value) || $row->value === []) {
        $value = SettingsController::DEFAULTS[$section];
        if ($section === 'shipping') {
            $value['shipping_company'] = 'Dokan Ward Delivery';
        }
        $saveSetting($section, $value);
    } elseif ($section === 'shipping') {
        $saveSetting($section, array_merge($row->value, [
            'shipping_company' => 'Dokan Ward Delivery',
        ]));
    }
}

Brand::query()->updateOrCreate(
    ['slug' => 'dokan-ward'],
    [
        'id' => Brand::where('slug', 'dokan-ward')->value('id') ?: (string) Str::uuid(),
        'name' => ['en' => 'Dokan Ward', 'ar' => 'دكان ورد'],
        'description' => [
            'en' => 'Premium home décor curated by Dokan Ward since 2018.',
            'ar' => 'ديكور منزلي راقٍ من دكان ورد منذ ٢٠١٨.',
        ],
        'logo_url' => $logo,
        'country' => 'Egypt',
    ]
);

$defaults = SiteContent::defaults();
$scrubZibra = static function (array $merged, array $fallback, string $logoPath): array {
    foreach ($merged as $k => $v) {
        if (is_string($v) && str_contains(strtolower($v), 'zibra')) {
            $merged[$k] = $fallback[$k] ?? $logoPath;
        }
    }

    return $merged;
};

$homeKey = SiteContent::KEYS['home'];
$homeRow = SiteSetting::where('key', $homeKey)->first();
$homeCurrent = is_array($homeRow?->value) ? $homeRow->value : [];
$homeMerged = $scrubZibra(
    array_merge($defaults['home'] ?? [], $homeCurrent, [
        'hero_wordmark' => $logo,
        'testimonials_title' => 'What people say about Dokan Ward',
    ]),
    $defaults['home'] ?? [],
    $logo
);
$saveSetting($homeKey, $homeMerged);

$aboutKey = SiteContent::KEYS['about'];
$aboutRow = SiteSetting::where('key', $aboutKey)->first();
$aboutCurrent = is_array($aboutRow?->value) ? $aboutRow->value : [];
$aboutMerged = $scrubZibra(
    array_merge($defaults['about'] ?? [], $aboutCurrent, [
        'story_image' => $logo,
        'hero_eyebrow' => 'About Dokan Ward',
        'story_title' => 'The Story Behind Dokan Ward',
        'quote_attribution' => '— Dokan Ward',
    ]),
    $defaults['about'] ?? [],
    $logo
);
$saveSetting($aboutKey, $aboutMerged);

$contactKey = SiteContent::KEYS['contact'];
$contactRow = SiteSetting::where('key', $contactKey)->first();
$contactCurrent = is_array($contactRow?->value) ? $contactRow->value : [];
$contactMerged = $scrubZibra(
    array_merge($defaults['contact'] ?? [], $contactCurrent, [
        'lede' => "Questions about an order, a piece, or styling advice? Message us — we reply with care. Hotline: {$hotline}",
    ]),
    $defaults['contact'] ?? [],
    $logo
);
$saveSetting($contactKey, $contactMerged);

Cache::forget(ApiSiteSettingController::STORE_CACHE_KEY);
Cache::forget(ApiSiteSettingController::CURRENCY_CACHE_KEY);
Cache::forget(ApiSiteSettingController::CHECKOUT_CACHE_KEY);
Cache::forget(ApiSiteSettingController::INVENTORY_CACHE_KEY);

echo "Branding applied: Dokan Ward / {$phone} / {$logo}\n";
