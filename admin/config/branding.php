<?php

/**
 * Platform branding — single source of truth for white-label deployments.
 *
 * Defaults: branding/*.json (clone-friendly file bundle).
 * Overrides: .env BRAND_* keys and/or platform_brands DB rows (admin panel).
 * Active brand: BRAND_SLUG env (supports multiple brands per codebase).
 */

use Illuminate\Support\Arr;

$brandingDir = base_path('branding');

/** @param string $file */
$readJson = static function (string $file): array {
    $path = base_path('branding/'.$file);
    if (! is_readable($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
};

$brand = $readJson('brand.json');
$assets = $readJson('assets.json');
$colors = $readJson('colors.json');
$seo = $readJson('seo.json');
$notifications = $readJson('notifications.json');
$typography = $readJson('typography.json');

$appName = env('APP_NAME', $brand['company_name'] ?? 'Default Company');

return [
    /** Active brand profile slug — set per deployment (brand_a, brand_b, default). */
    'slug' => env('BRAND_SLUG', $brand['slug'] ?? 'default'),

    'company_name' => env('BRAND_COMPANY_NAME', $brand['company_name'] ?? $appName),
    'company_name_ar' => env('BRAND_COMPANY_NAME_AR', $brand['company_name_ar'] ?? 'الشركة الافتراضية'),
    'company_short_name' => env('BRAND_COMPANY_SHORT_NAME', $brand['company_short_name'] ?? 'Company'),
    'company_short_name_ar' => env('BRAND_COMPANY_SHORT_NAME_AR', $brand['company_short_name_ar'] ?? 'الشركة'),
    'portal_name' => env('BRAND_PORTAL_NAME', $brand['portal_name'] ?? 'Client Portal'),
    'portal_name_ar' => env('BRAND_PORTAL_NAME_AR', $brand['portal_name_ar'] ?? 'بوابة العملاء'),

    'logo_light' => env('BRAND_LOGO_LIGHT') ?: ($assets['logo_light'] ?? '/platform-logo-light.png'),
    'logo_dark' => env('BRAND_LOGO_DARK') ?: ($assets['logo_dark'] ?? '/platform-logo-light.png'),
    'logo_mark' => env('BRAND_LOGO_MARK', $assets['logo_mark'] ?? '/favicon.png'),

    'favicon' => env('BRAND_FAVICON', $assets['favicon'] ?? '/favicon.png'),
    'favicon_ico' => env('BRAND_FAVICON_ICO', $assets['favicon_ico'] ?? '/favicon.ico'),
    'favicon_svg' => env('BRAND_FAVICON_SVG', $assets['favicon_svg'] ?? '/favicon.svg'),

    'primary_color' => env('BRAND_PRIMARY_COLOR', $colors['primary_color'] ?? '#B43A2E'),
    'secondary_color' => env('BRAND_SECONDARY_COLOR', $colors['secondary_color'] ?? '#F7F7F8'),
    'accent_color' => env('BRAND_ACCENT_COLOR', $colors['accent_color'] ?? '#D85A4E'),
    'primary_hover' => env('BRAND_PRIMARY_HOVER', $colors['primary_hover'] ?? '#922F25'),
    'theme_color_light' => env('BRAND_THEME_COLOR_LIGHT', $colors['theme_color_light'] ?? '#B43A2E'),
    'theme_color_dark' => env('BRAND_THEME_COLOR_DARK', $colors['theme_color_dark'] ?? '#0F0F11'),
    'mobile_splash_color' => env('BRAND_MOBILE_SPLASH_COLOR', $colors['mobile_splash_color'] ?? '#A64129'),

    'website_title' => env('BRAND_WEBSITE_TITLE', $seo['website_title'] ?? $appName),
    'website_title_ar' => env('BRAND_WEBSITE_TITLE_AR', $seo['website_title_ar'] ?? 'الشركة الافتراضية'),
    'website_description' => env('BRAND_WEBSITE_DESCRIPTION', $seo['website_description'] ?? ''),
    'website_description_ar' => env('BRAND_WEBSITE_DESCRIPTION_AR', $seo['website_description_ar'] ?? ''),
    'properties_title_suffix' => env('BRAND_PROPERTIES_TITLE_SUFFIX', $seo['properties_title_suffix'] ?? $appName),
    'properties_title_suffix_ar' => env('BRAND_PROPERTIES_TITLE_SUFFIX_AR', $seo['properties_title_suffix_ar'] ?? 'الشركة الافتراضية'),
    'og_image' => env('BRAND_OG_IMAGE', $assets['og_image'] ?? '/platform-logo-light.png'),

    'email_from_name' => env('BRAND_EMAIL_FROM_NAME', $notifications['email_from_name'] ?? env('MAIL_FROM_NAME', $appName)),
    'email_from_address' => env('BRAND_EMAIL_FROM_ADDRESS', $notifications['email_from_address'] ?? env('MAIL_FROM_ADDRESS')),
    'push_title' => env('BRAND_PUSH_TITLE', $notifications['push_title'] ?? $appName),
    'investor_portal_subject_prefix' => env(
        'BRAND_INVESTOR_PORTAL_SUBJECT_PREFIX',
        $notifications['investor_portal_subject_prefix'] ?? null
    ),

    'pdf_logo' => env('BRAND_PDF_LOGO', $assets['pdf_logo'] ?? '/images/brand/brand-logo.png'),
    'pdf_footer' => env('BRAND_PDF_FOOTER', ''),
    'export_prefix' => env('BRAND_EXPORT_PREFIX', $brand['export_prefix'] ?? $brand['company_short_name'] ?? $brand['company_name'] ?? 'Store'),

    'mobile_app_name' => env('BRAND_MOBILE_APP_NAME', env('CAPACITOR_APP_NAME', $brand['company_name'] ?? 'Default Company')),
    'mobile_package' => env('BRAND_MOBILE_PACKAGE', env('CAPACITOR_APP_ID', 'com.example.store')),

    'show_developer_credit' => filter_var(
        env('BRAND_SHOW_DEVELOPER_CREDIT', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'font_sans' => $typography['font_sans'] ?? "'DM Sans', ui-sans-serif, system-ui, sans-serif",
    'font_arabic' => $typography['font_arabic'] ?? "'Cairo', ui-sans-serif, system-ui, sans-serif",

    /** Storage subdirectory for uploaded brand assets (public disk). */
    'upload_disk' => 'public',
    'upload_path' => 'branding',

    /** Cache resolved branding payload (seconds). */
    'cache_ttl' => (int) env('BRAND_CACHE_TTL', 600),

    /** Raw JSON defaults (for seeders / admin preview diff). */
    'defaults' => [
        'brand' => Arr::except($brand, ['$schema']),
        'assets' => Arr::except($assets, ['$schema']),
        'colors' => Arr::except($colors, ['$schema']),
        'seo' => Arr::except($seo, ['$schema']),
        'notifications' => Arr::except($notifications, ['$schema']),
        'typography' => Arr::except($typography, ['$schema']),
    ],
];
