<?php

namespace App\Services\Branding;

use App\Models\PlatformBrand;
use App\Models\PlatformBrandDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/**
 * Resolves active platform branding: config/branding.php + branding/*.json defaults,
 * overridden by platform_brands DB row, then ROOTK runtime (.rootk/* + ROOTK_TENANT_* env).
 */
final class BrandingService
{
    private const CACHE_PREFIX = 'platform.branding.resolved.';

    /** @var array<string, string> */
    private array $resolvedSlugs = [];

    /** @var array<string, array<string, mixed>> */
    private array $resolvedBranding = [];

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** Public fallbacks when env/DB/ROOTK leave asset paths empty or missing on disk. */
    private const FALLBACK_LOGO_LIGHT = '/images/brand-logo.png';

    private const FALLBACK_LOGO_MARK = '/favicon.ico';

    private const FALLBACK_FAVICON = '/favicon.ico';

    public function __construct(
        private readonly RootkBrandingLoader $rootk = new RootkBrandingLoader,
    ) {}

    public function resolveSlug(?Request $request = null): string
    {
        $rootkSlug = $this->rootk->tenantSlug();
        if (is_string($rootkSlug) && $rootkSlug !== '') {
            return $rootkSlug;
        }

        if ($request && $this->hasTable('platform_brand_domains')) {
            $host = strtolower($request->getHost());
            if (isset($this->resolvedSlugs[$host])) {
                return $this->resolvedSlugs[$host];
            }

            $mapped = Cache::remember(
                'platform.branding.host.'.$host,
                config('branding.cache_ttl', 600),
                function () use ($host) {
                    $domain = PlatformBrandDomain::query()->where('host', $host)->first();

                    return $domain?->brand?->slug;
                }
            );
            if (is_string($mapped) && $mapped !== '') {
                return $this->resolvedSlugs[$host] = $mapped;
            }
        }

        return $request
            ? ($this->resolvedSlugs[strtolower($request->getHost())] = (string) config('branding.slug', 'default'))
            : (string) config('branding.slug', 'default');
    }

    /**
     * @return array<string, mixed>
     */
    public function all(?Request $request = null): array
    {
        $slug = $this->resolveSlug($request);
        $localCacheKey = $this->resolvedBrandingCacheKey($slug);

        if (isset($this->resolvedBranding[$localCacheKey])) {
            return $this->resolvedBranding[$localCacheKey];
        }

        if ($this->rootk->isManaged()) {
            return $this->resolvedBranding[$localCacheKey] = $this->buildResolved($slug);
        }

        return $this->resolvedBranding[$localCacheKey] = Cache::remember(
            self::CACHE_PREFIX.$slug,
            config('branding.cache_ttl', 600),
            fn () => $this->buildResolved($slug)
        );
    }

    public function get(string $key, mixed $default = null, ?Request $request = null): mixed
    {
        return Arr::get($this->all($request), $key, $default);
    }

    public function bustCache(?string $slug = null): void
    {
        $this->resolvedSlugs = [];
        $this->resolvedBranding = [];
        $this->tableExistsCache = [];
        $this->rootk->forgetCache();

        if ($slug !== null) {
            Cache::forget(self::CACHE_PREFIX.$slug);

            return;
        }

        if (! $this->hasTable('platform_brands')) {
            Cache::forget(self::CACHE_PREFIX.config('branding.slug', 'default'));

            return;
        }

        $slugs = PlatformBrand::query()->pluck('slug');
        if ($slugs->isEmpty()) {
            Cache::forget(self::CACHE_PREFIX.config('branding.slug', 'default'));

            return;
        }

        $slugs->each(
            fn (string $s) => Cache::forget(self::CACHE_PREFIX.$s)
        );
        PlatformBrandDomain::query()->pluck('host')->each(
            fn (string $h) => Cache::forget('platform.branding.host.'.$h)
        );
    }

    /**
     * Payload for Inertia / frontend BrandProvider.
     *
     * @return array<string, mixed>
     */
    public function forFrontend(?Request $request = null): array
    {
        $b = $this->all($request);

        return [
            'slug' => $b['slug'],
            'company_name' => $b['company_name'],
            'company_name_ar' => $b['company_name_ar'],
            'company_short_name' => $b['company_short_name'],
            'company_short_name_ar' => $b['company_short_name_ar'],
            'portal_name' => $b['portal_name'],
            'portal_name_ar' => $b['portal_name_ar'],
            'logo_light_url' => $b['logo_light_url'],
            'logo_dark_url' => $b['logo_dark_url'],
            'logo_mark_url' => $b['logo_mark_url'],
            'favicon_url' => $b['favicon_url'],
            'og_image_url' => $b['og_image_url'],
            'primary_color' => $b['primary_color'],
            'secondary_color' => $b['secondary_color'],
            'accent_color' => $b['accent_color'],
            'primary_hover' => $b['primary_hover'],
            'theme_color_light' => $b['theme_color_light'],
            'theme_color_dark' => $b['theme_color_dark'],
            'website_title' => $b['website_title'],
            'website_title_ar' => $b['website_title_ar'],
            'website_description' => $b['website_description'],
            'website_description_ar' => $b['website_description_ar'],
            'properties_title_suffix' => $b['properties_title_suffix'],
            'properties_title_suffix_ar' => $b['properties_title_suffix_ar'],
            'export_prefix' => $b['export_prefix'],
            'pdf_footer' => $b['pdf_footer'],
            'show_developer_credit' => $b['show_developer_credit'],
            'mobile_app_name' => $b['mobile_app_name'],
            'domain' => $b['domain'] ?? null,
        ];
    }

    /**
     * Unified tenant branding view (ROOTK / white-label contract).
     *
     * @return array<string, mixed>
     */
    public function tenantBranding(?Request $request = null): array
    {
        return $this->rootk->tenantBranding($this->all($request));
    }

    /**
     * CSS custom properties for runtime color overrides (admin without rebuild).
     */
    public function cssVariables(?Request $request = null): string
    {
        $b = $this->all($request);
        $pairs = [
            '--brand-primary' => $b['primary_color'],
            '--brand-primary-hover' => $b['primary_hover'],
            '--brand-accent' => $b['accent_color'],
            '--brand-secondary' => $b['secondary_color'],
            '--theme-color-meta' => $b['theme_color_light'],
        ];

        $lightLines = [];
        foreach ($pairs as $var => $hex) {
            if (is_string($hex) && $hex !== '') {
                $lightLines[] = $var.': '.$hex.';';
            }
        }

        $primaryHex = is_string($b['primary_color'] ?? null) ? $b['primary_color'] : null;
        $secondaryHex = is_string($b['secondary_color'] ?? null) ? $b['secondary_color'] : null;
        if ($primaryHex !== null) {
            $hoverHex = is_string($b['primary_hover'] ?? null) && $b['primary_hover'] !== ''
                ? $b['primary_hover']
                : $primaryHex;
            $lightLines[] = '--brand-primary-active: '.$hoverHex.';';
            if ($secondaryHex !== null) {
                $lightLines[] = '--brand-primary-muted: color-mix(in srgb, '.$primaryHex.' 25%, '.$secondaryHex.');';
            }
        }

        // Derive the Tailwind primary token family (HSL) from the brand color so the
        // whole UI (buttons, active nav, focus rings, primary surfaces) follows the
        // tenant brand at runtime. For the default red brand these resolve to the
        // exact values baked in theme.css, so there is no regression.
        $primaryHsl = is_string($b['primary_color'] ?? null) ? $this->hexToHsl($b['primary_color']) : null;
        $darkLines = [];
        if ($primaryHsl !== null) {
            [$h, $s, $l] = $primaryHsl;
            $hoverHsl = is_string($b['primary_hover'] ?? null) ? $this->hexToHsl($b['primary_hover']) : null;
            [$hh, $hs, $hl] = $hoverHsl ?? [$h, $s, max($l - 8, 0)];

            $hsl = static fn (int $hue, int $sat, int $lig): string => sprintf('%d %d%% %d%%', $hue, $sat, $lig);
            $clamp = static fn (int $v, int $min = 0, int $max = 100): int => max($min, min($max, $v));

            $lightTokens = [
                '--primary' => $hsl($h, $s, $l),
                '--primary-hover' => $hsl($hh, $hs, $hl),
                '--primary-foreground' => '0 0% 98%',
                '--ring' => $hsl($h, $s, $l),
                '--sidebar-active-bg' => $hsl($h, 52, 91),
                '--sidebar-active-border' => $hsl($h, 48, 72),
                '--sidebar-active-fg' => $hsl($h, 62, 28),
                '--surface-primary' => $hsl($h, 55, 93),
                '--surface-primary-border' => $hsl($h, 50, 68),
                '--surface-primary-fg' => $hsl($h, 62, 26),
                '--surface-primary-icon' => $hsl($h, 52, 88),
                '--surface-primary-progress' => $hsl($h, 59, 44),
                // Charts + focus border follow tenant primary (not baked Sidra red).
                '--chart-1' => $hsl($h, $s, $l),
                '--chart-3' => $hsl($hh, $hs, $hl),
            ];
            $lightLines[] = '--chart-color-1: '.$primaryHex.';';
            $lightLines[] = '--chart-color-3: '.(is_string($b['primary_hover'] ?? null) && $b['primary_hover'] !== ''
                ? $b['primary_hover']
                : $primaryHex).';';
            $lightLines[] = '--border-focus: '.$primaryHex.';';
            $accentHsl = is_string($b['accent_color'] ?? null) ? $this->hexToHsl($b['accent_color']) : null;
            if ($accentHsl !== null) {
                [$ah, $as, $al] = $accentHsl;
                $lightLines[] = '--brand-accent-hsl: '.sprintf('%d %d%% %d%%', $ah, $as, $al).';';
                $lightLines[] = '--chart-2: '.sprintf('%d %d%% %d%%', $ah, $as, $al).';';
                $lightLines[] = '--chart-color-2: '.(string) $b['accent_color'].';';
                $darkLines[] = '--brand-accent-hsl: '.sprintf('%d %d%% %d%%', $ah, max(0, $as - 4), min(100, $al + 10)).';';
            }
            foreach ($lightTokens as $var => $value) {
                $lightLines[] = $var.': '.$value.';';
            }

            $darkTokens = [
                '--primary' => $hsl($h, $clamp($s + 5, 0, 80), $clamp($l + 14, 0, 62)),
                '--primary-hover' => $hsl($hh, $clamp($hs, 0, 80), $clamp($hl + 16, 0, 58)),
                '--primary-foreground' => '0 0% 100%',
                '--ring' => $hsl($h, $clamp($s + 14, 0, 80), $clamp($l + 23, 0, 70)),
                '--sidebar-active-bg' => $hsl($h, 38, 22),
                '--sidebar-active-border' => $hsl($h, 50, 48),
                '--sidebar-active-fg' => $hsl($h, 80, 92),
                '--surface-primary' => $hsl($h, 42, 20),
                '--surface-primary-border' => $hsl($h, 48, 44),
                '--surface-primary-fg' => $hsl($h, 75, 92),
                '--surface-primary-icon' => $hsl($h, 38, 26),
                '--surface-primary-progress' => $hsl($h, 62, 58),
            ];
            foreach ($darkTokens as $var => $value) {
                $darkLines[] = $var.': '.$value.';';
            }
        }

        $light = implode(' ', $lightLines);

        if ($darkLines !== []) {
            // Unquoted attribute selector: the Blade wrapper renders this via {{ }},
            // which would HTML-escape quotes and break the selector inside <style>.
            return $light.' } html.dark, .dark, [data-theme=dark] { '.implode(' ', $darkLines);
        }

        return $light;
    }

    /**
     * Convert a hex color (#RGB or #RRGGBB) to an HSL triple [h(0-360), s(0-100), l(0-100)].
     *
     * @return array{0:int,1:int,2:int}|null
     */
    private function hexToHsl(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return null;
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0.0) {
            return [0, 0, (int) round($l * 100)];
        }

        $s = $d / (1 - abs(2 * $l - 1));

        if ($max === $r) {
            $h = fmod((($g - $b) / $d), 6);
        } elseif ($max === $g) {
            $h = (($b - $r) / $d) + 2;
        } else {
            $h = (($r - $g) / $d) + 4;
        }

        $h *= 60;
        if ($h < 0) {
            $h += 360;
        }

        return [(int) round($h), (int) round($s * 100), (int) round($l * 100)];
    }

    public function exportPrefix(?Request $request = null): string
    {
        return (string) $this->get('export_prefix', config('app.name'), $request);
    }

    public function companyName(?Request $request = null): string
    {
        return (string) $this->get('company_name', config('app.name'), $request);
    }

    /**
     * Document / boot shell display name.
     * When $preferArabic is true, use a real Arabic tenant name and skip
     * white-label placeholders (same set as frontend useAuthBrandDisplayName).
     */
    public function bootAppName(bool $preferArabic = false, ?Request $request = null): string
    {
        $fallback = $this->companyName($request);
        if (! $preferArabic) {
            return $fallback;
        }

        $branding = $this->all($request);
        $arabicPlaceholders = ['الشركة الافتراضية', 'الشركة', 'CRM'];

        foreach (['company_name_ar', 'company_short_name_ar'] as $key) {
            $candidate = trim((string) ($branding[$key] ?? ''));
            if ($candidate !== '' && ! in_array($candidate, $arabicPlaceholders, true)) {
                return $candidate;
            }
        }

        return $fallback;
    }

    public function investorPortalSubjectPrefix(?Request $request = null): string
    {
        $custom = $this->get('investor_portal_subject_prefix', null, $request);
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        $portal = (string) $this->get('portal_name', 'Client Portal', $request);
        $company = (string) $this->get('company_short_name', $this->companyName($request), $request);

        return trim($company.' '.$portal);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResolved(string $slug): array
    {
        $base = $this->configDefaults();
        $db = $this->loadDbBrand($slug);

        if ($db !== null) {
            $base = $this->mergeDbOverrides($base, $db);
        }

        $base = $this->mergeRootkOverrides($base, $this->rootk->overrides());

        $base['slug'] = $slug;
        $base['domain'] = $this->rootk->tenantDomain();
        $base = $this->normalizeUrls($base);
        $base = $this->normalizeCompanyShortNames($base);

        // Jundy white-label: never surface “Developed by ROOTK” in footers.
        if ($this->shouldHideDeveloperCredit($slug, $base['domain'] ?? null)) {
            $base['show_developer_credit'] = false;
        }

        return $base;
    }

    /**
     * Hide ROOTK developer credit for the Jundy tenant (slug / domain / APP_URL host).
     */
    private function shouldHideDeveloperCredit(string $slug, ?string $domain): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === 'jundy' || str_starts_with($slug, 'jundy-') || str_starts_with($slug, 'jundy_')) {
            return true;
        }

        $hosts = array_filter([
            strtolower(trim((string) $domain)),
            strtolower(trim((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''))),
        ]);

        foreach ($hosts as $host) {
            if (
                $host === 'jundy'
                || str_starts_with($host, 'jundy.')
                || str_starts_with($host, 'jundycrm.')
                || str_contains($host, '.jundy.')
                || str_contains($host, 'jundyservices.')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeRootkOverrides(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '') {
                $base[$key] = $value;
            }
        }

        if (
            isset($overrides['logo_light'])
            && (! isset($overrides['logo_dark']) || $overrides['logo_dark'] === '')
        ) {
            $base['logo_dark'] = $overrides['logo_light'];
        }

        if (
            isset($overrides['company_name'])
            && (! isset($overrides['company_short_name']) || $overrides['company_short_name'] === '')
        ) {
            $base['company_short_name'] = $overrides['company_name'];
        }

        if (
            isset($overrides['company_name_ar'])
            && (! isset($overrides['company_short_name_ar']) || $overrides['company_short_name_ar'] === '')
        ) {
            $base['company_short_name_ar'] = $overrides['company_name_ar'];
        }

        // ROOTK tenants often ship primary only — derive hover/accent/theme from that
        // primary so Sidra baked defaults (red) do not bleed into hover/charts/meta.
        $base = $this->deriveMissingCompanionColors($base, $overrides);

        return $base;
    }

    /**
     * When a tenant overrides primary_color without companions, derive them from primary
     * instead of leaving config/DB Sidra reds (hover, accent, theme meta).
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $explicitOverrides  Keys the tenant actually provided
     * @return array<string, mixed>
     */
    private function deriveMissingCompanionColors(array $base, array $explicitOverrides): array
    {
        $primary = $explicitOverrides['primary_color'] ?? null;
        if (! is_string($primary) || $primary === '') {
            return $base;
        }

        if (! isset($explicitOverrides['primary_hover']) || $explicitOverrides['primary_hover'] === '') {
            $base['primary_hover'] = $this->shadeHex($primary, -0.12);
        }

        if (! isset($explicitOverrides['accent_color']) || $explicitOverrides['accent_color'] === '') {
            $base['accent_color'] = $this->tintHex($primary, 0.18);
        }

        if (! isset($explicitOverrides['theme_color_light']) || $explicitOverrides['theme_color_light'] === '') {
            $base['theme_color_light'] = $primary;
        }

        if (! isset($explicitOverrides['mobile_splash_color']) || $explicitOverrides['mobile_splash_color'] === '') {
            $base['mobile_splash_color'] = $primary;
        }

        return $base;
    }

    /** Darken (negative factor) or lighten (positive) a #RRGGBB color. */
    private function shadeHex(string $hex, float $factor): string
    {
        $factor = max(-1.0, min(1.0, $factor));
        [$r, $g, $b] = $this->hexToRgb($hex);
        if ($factor >= 0) {
            $r = (int) round($r + (255 - $r) * $factor);
            $g = (int) round($g + (255 - $g) * $factor);
            $b = (int) round($b + (255 - $b) * $factor);
        } else {
            $scale = 1 + $factor;
            $r = (int) round($r * $scale);
            $g = (int) round($g * $scale);
            $b = (int) round($b * $scale);
        }

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /** Mix a #RRGGBB color toward white (0 = unchanged, 1 = white). */
    private function tintHex(string $hex, float $mixTowardWhite): string
    {
        $mixTowardWhite = max(0.0, min(1.0, $mixTowardWhite));
        [$r, $g, $b] = $this->hexToRgb($hex);
        $r = (int) round($r + (255 - $r) * $mixTowardWhite);
        $g = (int) round($g + (255 - $g) * $mixTowardWhite);
        $b = (int) round($b + (255 - $b) * $mixTowardWhite);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $normalized = ltrim(trim($hex), '#');
        if (strlen($normalized) === 3) {
            $normalized = $normalized[0].$normalized[0].$normalized[1].$normalized[1].$normalized[2].$normalized[2];
        }

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }

    /**
     * When short name equals full name (common ROOTK misconfig), derive a readable short label
     * so {{BRAND_SHORT}} and mobile UI do not repeat suffixes like "العقارية" / "Real Estate".
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function normalizeCompanyShortNames(array $base): array
    {
        $base['company_short_name'] = $this->deriveShortNameWhenRedundant(
            (string) ($base['company_short_name'] ?? ''),
            (string) ($base['company_name'] ?? ''),
            [' Real Estate', ' Realty', ' Properties', ' CRM']
        );
        $base['company_short_name_ar'] = $this->deriveShortNameWhenRedundant(
            (string) ($base['company_short_name_ar'] ?? ''),
            (string) ($base['company_name_ar'] ?? ''),
            [' العقارية', ' للعقارات', ' العقاري', ' CRM']
        );

        return $base;
    }

    private function deriveShortNameWhenRedundant(string $short, string $full, array $suffixes): string
    {
        if ($short === '' && $full !== '') {
            $short = $full;
        }

        if ($short === '' || $short !== $full) {
            return $short;
        }

        foreach ($suffixes as $suffix) {
            if (! str_ends_with($full, $suffix)) {
                continue;
            }
            $candidate = rtrim(mb_substr($full, 0, -mb_strlen($suffix)));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $short;
    }

    /**
     * @return array<string, mixed>
     */
    private function configDefaults(): array
    {
        return [
            'company_name' => config('branding.company_name'),
            'company_name_ar' => config('branding.company_name_ar'),
            'company_short_name' => config('branding.company_short_name'),
            'company_short_name_ar' => config('branding.company_short_name_ar'),
            'portal_name' => config('branding.portal_name'),
            'portal_name_ar' => config('branding.portal_name_ar'),
            'logo_light' => config('branding.logo_light'),
            'logo_dark' => config('branding.logo_dark'),
            'logo_mark' => config('branding.logo_mark'),
            'favicon' => config('branding.favicon'),
            'favicon_ico' => config('branding.favicon_ico'),
            'favicon_svg' => config('branding.favicon_svg'),
            'og_image' => config('branding.og_image'),
            'primary_color' => config('branding.primary_color'),
            'secondary_color' => config('branding.secondary_color'),
            'accent_color' => config('branding.accent_color'),
            'primary_hover' => config('branding.primary_hover'),
            'theme_color_light' => config('branding.theme_color_light'),
            'theme_color_dark' => config('branding.theme_color_dark'),
            'mobile_splash_color' => config('branding.mobile_splash_color'),
            'website_title' => config('branding.website_title'),
            'website_title_ar' => config('branding.website_title_ar'),
            'website_description' => config('branding.website_description'),
            'website_description_ar' => config('branding.website_description_ar'),
            'properties_title_suffix' => config('branding.properties_title_suffix'),
            'properties_title_suffix_ar' => config('branding.properties_title_suffix_ar'),
            'email_from_name' => config('branding.email_from_name'),
            'email_from_address' => config('branding.email_from_address'),
            'push_title' => config('branding.push_title'),
            'investor_portal_subject_prefix' => config('branding.investor_portal_subject_prefix'),
            'pdf_logo' => config('branding.pdf_logo'),
            'pdf_footer' => config('branding.pdf_footer'),
            'export_prefix' => config('branding.export_prefix'),
            'mobile_app_name' => config('branding.mobile_app_name'),
            'mobile_package' => config('branding.mobile_package'),
            'show_developer_credit' => config('branding.show_developer_credit'),
            'font_sans' => config('branding.font_sans'),
            'font_arabic' => config('branding.font_arabic'),
        ];
    }

    private function loadDbBrand(string $slug): ?PlatformBrand
    {
        if (! $this->hasTable('platform_brands')) {
            return null;
        }

        $brand = PlatformBrand::query()->where('slug', $slug)->first();
        if ($brand) {
            return $brand;
        }

        return PlatformBrand::query()->where('is_default', true)->first();
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function mergeDbOverrides(array $base, PlatformBrand $db): array
    {
        $map = [
            'company_name' => 'company_name',
            'company_name_ar' => 'company_name_ar',
            'company_short_name' => 'company_short_name',
            'company_short_name_ar' => 'company_short_name_ar',
            'portal_name' => 'portal_name',
            'portal_name_ar' => 'portal_name_ar',
            'logo_light' => 'logo_light_path',
            'logo_dark' => 'logo_dark_path',
            'logo_mark' => 'logo_mark_path',
            'favicon' => 'favicon_path',
            'og_image' => 'og_image_path',
            'primary_color' => 'primary_color',
            'secondary_color' => 'secondary_color',
            'accent_color' => 'accent_color',
            'primary_hover' => 'primary_hover',
            'theme_color_light' => 'theme_color_light',
            'theme_color_dark' => 'theme_color_dark',
            'mobile_splash_color' => 'mobile_splash_color',
            'website_title' => 'website_title',
            'website_title_ar' => 'website_title_ar',
            'website_description' => 'website_description',
            'website_description_ar' => 'website_description_ar',
            'properties_title_suffix' => 'properties_title_suffix',
            'properties_title_suffix_ar' => 'properties_title_suffix_ar',
            'email_from_name' => 'email_from_name',
            'email_from_address' => 'email_from_address',
            'push_title' => 'push_title',
            'investor_portal_subject_prefix' => 'investor_portal_subject_prefix',
            'pdf_logo' => 'pdf_logo_path',
            'pdf_footer' => 'pdf_footer',
            'export_prefix' => 'export_prefix',
            'mobile_app_name' => 'mobile_app_name',
            'mobile_package' => 'mobile_package',
            'show_developer_credit' => 'show_developer_credit',
        ];

        foreach ($map as $configKey => $dbKey) {
            $storedValue = $db->{$dbKey};
            if ($storedValue !== null && $storedValue !== '') {
                $base[$configKey] = $storedValue;
            }
        }

        return $base;
    }

    private function hasTable(string $table): bool
    {
        return $this->tableExistsCache[$table] ??= Schema::hasTable($table);
    }

    private function resolvedBrandingCacheKey(string $slug): string
    {
        if (! $this->rootk->isManaged()) {
            return $slug;
        }

        return $slug.'|rootk|'.md5(json_encode($this->rootk->overrides(), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function normalizeUrls(array $base): array
    {
        $logoLight = $this->resolveBrandingAsset($base['logo_light'] ?? '', self::FALLBACK_LOGO_LIGHT);
        $logoDark = $this->resolveBrandingAsset($base['logo_dark'] ?? '', $logoLight);
        $logoMark = $this->resolveBrandingAsset($base['logo_mark'] ?? '', self::FALLBACK_LOGO_MARK);
        $favicon = $this->resolveBrandingAsset($base['favicon'] ?? '', self::FALLBACK_FAVICON);
        $ogImage = $this->resolveBrandingAsset($base['og_image'] ?? '', $logoLight);
        $pdfLogo = $this->resolveBrandingAsset($base['pdf_logo'] ?? '', $logoLight);

        $base['logo_light'] = $logoLight;
        $base['logo_dark'] = $logoDark;
        $base['logo_mark'] = $logoMark;
        $base['favicon'] = $favicon;
        $base['og_image'] = $ogImage;
        $base['pdf_logo'] = $pdfLogo;
        $base['logo_light_url'] = $this->assetUrl($logoLight);
        $base['logo_dark_url'] = $this->assetUrl($logoDark);
        $base['logo_mark_url'] = $this->assetUrl($logoMark);
        $base['favicon_url'] = $this->assetUrl($favicon);
        $base['favicon_ico_url'] = $this->assetUrl($this->resolveBrandingAsset($base['favicon_ico'] ?? '', '/favicon.ico'));
        $base['favicon_svg_url'] = $this->assetUrl($this->resolveBrandingAsset($base['favicon_svg'] ?? '', '/favicon.svg'));
        $base['og_image_url'] = $this->assetUrl($ogImage);
        $base['pdf_logo_url'] = $this->assetUrl($pdfLogo);

        return $base;
    }

    /** Prefer configured path; fall back when empty or file is missing under public/. */
    private function resolveBrandingAsset(string $path, string $fallback): string
    {
        $path = trim($path);
        if ($path === '') {
            return $fallback;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relative = ltrim($path, '/');
        // ROOTK tenant.env often keeps .jpg while a transparent PNG sibling is on disk.
        $pngRelative = $this->transparentPngSibling($relative);
        if ($pngRelative !== null && is_file(public_path($pngRelative))) {
            return '/'.$pngRelative;
        }

        if ($relative !== '' && is_file(public_path($relative))) {
            return str_starts_with($path, '/') ? $path : '/'.$relative;
        }

        return $fallback;
    }

    private function transparentPngSibling(string $relative): ?string
    {
        if ($relative === '') {
            return null;
        }

        $lower = strtolower($relative);
        if (! str_ends_with($lower, '.jpg') && ! str_ends_with($lower, '.jpeg')) {
            return null;
        }

        $pngRelative = preg_replace('/\.jpe?g$/i', '.png', $relative);

        return is_string($pngRelative) && $pngRelative !== '' ? $pngRelative : null;
    }

    private function assetUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            return URL::to($path);
        }

        return URL::to('/'.ltrim($path, '/'));
    }
}
