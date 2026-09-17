<?php

namespace App\Services\Branding;

use Illuminate\Support\Arr;

/**
 * Reads ROOTK-managed branding at runtime (bypasses config:cache for tenant overrides).
 *
 * Sources (merged, later wins within this layer):
 * - .rootk/branding.json
 * - .rootk/tenant.json
 * - ROOTK_TENANT_* / BRAND_* env (live env(), not config())
 */
final class RootkBrandingLoader
{
    private const BRANDING_JSON = '.rootk/branding.json';

    private const TENANT_JSON = '.rootk/tenant.json';

    /** @var array<string, mixed>|null */
    private ?array $brandingJson = null;

    /** @var array<string, mixed>|null */
    private ?array $tenantJson = null;

    public function isManaged(): bool
    {
        if ($this->readBrandingJson() !== null || $this->readTenantJson() !== null) {
            return true;
        }

        foreach ([
            'ROOTK_TENANT_ID',
            'ROOTK_TENANT_DISPLAY_NAME',
            'ROOTK_TENANT_SLUG',
            'ROOTK_TENANT_DOMAIN',
            'ROOTK_TENANT_LOGO_URL',
            'ROOTK_TENANT_PRIMARY_COLOR',
            'ROOTK_TENANT_SECONDARY_COLOR',
            'ROOTK_TENANT_BRAND_COLORS',
        ] as $key) {
            if ($this->envString($key) !== null) {
                return true;
            }
        }

        return false;
    }

    public function tenantSlug(): ?string
    {
        return $this->envString('ROOTK_TENANT_SLUG')
            ?? Arr::get($this->readTenantJson() ?? [], 'slug');
    }

    public function tenantDomain(): ?string
    {
        return $this->envString('ROOTK_TENANT_DOMAIN')
            ?? Arr::get($this->readBrandingJson() ?? [], 'domain')
            ?? Arr::get($this->readTenantJson() ?? [], 'domain');
    }

    /**
     * Drop in-memory JSON caches so the next read picks up filesystem / env changes.
     * Called from BrandingService::bustCache() after tenant branding updates or in tests.
     */
    public function forgetCache(): void
    {
        $this->brandingJson = null;
        $this->tenantJson = null;
    }

    /**
     * Overrides keyed to BrandingService internal fields.
     *
     * @return array<string, mixed>
     */
    public function overrides(): array
    {
        $merged = [];

        $fromJson = $this->mapBrandingJson($this->readBrandingJson() ?? []);
        $fromTenant = $this->mapTenantJson($this->readTenantJson() ?? []);
        $fromEnv = $this->mapEnvOverrides();

        foreach ([$fromJson, $fromTenant, $fromEnv] as $chunk) {
            $merged = $this->mergeNonEmpty($merged, $chunk);
        }

        // ROOTK sometimes sets ROOTK_TENANT_DISPLAY_NAME to the Arabic label for both
        // EN and AR. Prefer branding.json / short English name so language toggling works.
        return $this->repairArabicScriptInEnglishNames($merged, $fromJson, $fromTenant);
    }

    /**
     * When an "English" company field contains Arabic script, restore Latin text from
     * branding.json / tenant.json / short name.
     *
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $fromJson
     * @param  array<string, mixed>  $fromTenant
     * @return array<string, mixed>
     */
    private function repairArabicScriptInEnglishNames(array $merged, array $fromJson, array $fromTenant): array
    {
        $merged['company_name'] = $this->preferLatinCompanyLabel(
            (string) ($merged['company_name'] ?? ''),
            [
                (string) ($fromJson['company_name'] ?? ''),
                (string) ($fromTenant['company_name'] ?? ''),
                (string) ($merged['company_short_name'] ?? ''),
                (string) ($fromJson['company_short_name'] ?? ''),
            ]
        );

        $merged['company_short_name'] = $this->preferLatinCompanyLabel(
            (string) ($merged['company_short_name'] ?? ''),
            [
                (string) ($fromJson['company_short_name'] ?? ''),
                (string) ($fromJson['company_name'] ?? ''),
                (string) ($fromTenant['company_name'] ?? ''),
            ]
        );

        return $this->filterNonEmpty($merged);
    }

    /**
     * @param  list<string>  $fallbacks
     */
    private function preferLatinCompanyLabel(string $candidate, array $fallbacks): string
    {
        $candidate = trim($candidate);
        if ($candidate === '' || ! $this->containsArabicScript($candidate)) {
            return $candidate;
        }

        foreach ($fallbacks as $fallback) {
            $fallback = trim($fallback);
            if ($fallback !== '' && ! $this->containsArabicScript($fallback)) {
                return $fallback;
            }
        }

        return $candidate;
    }

    private function containsArabicScript(string $value): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $value);
    }

    /**
     * Unified tenant branding view for docs / API consumers.
     *
     * @param  array<string, mixed>  $resolved  Fully resolved branding from BrandingService
     * @return array{
     *   displayName: string,
     *   displayNameAr: string,
     *   shortName: string,
     *   logoLightUrl: string,
     *   logoDarkUrl: string,
     *   faviconUrl: string,
     *   colors: array<string, string>,
     *   websiteTitle: string,
     *   websiteDescription: string,
     *   exportPrefix: string,
     *   mobileAppName: string,
     *   domain: string|null
     * }
     */
    public function tenantBranding(array $resolved): array
    {
        return [
            'displayName' => (string) ($resolved['company_name'] ?? ''),
            'displayNameAr' => (string) ($resolved['company_name_ar'] ?? ''),
            'shortName' => (string) ($resolved['company_short_name'] ?? $resolved['company_name'] ?? ''),
            'logoLightUrl' => (string) ($resolved['logo_light_url'] ?? ''),
            'logoDarkUrl' => (string) ($resolved['logo_dark_url'] ?? ''),
            'faviconUrl' => (string) ($resolved['favicon_url'] ?? ''),
            'colors' => [
                'primary_color' => (string) ($resolved['primary_color'] ?? ''),
                'secondary_color' => (string) ($resolved['secondary_color'] ?? ''),
                'accent_color' => (string) ($resolved['accent_color'] ?? ''),
                'primary_hover' => (string) ($resolved['primary_hover'] ?? ''),
                'theme_color_light' => (string) ($resolved['theme_color_light'] ?? ''),
                'theme_color_dark' => (string) ($resolved['theme_color_dark'] ?? ''),
            ],
            'websiteTitle' => (string) ($resolved['website_title'] ?? $resolved['company_name'] ?? ''),
            'websiteDescription' => (string) ($resolved['website_description'] ?? ''),
            'exportPrefix' => (string) ($resolved['export_prefix'] ?? ''),
            'mobileAppName' => (string) ($resolved['mobile_app_name'] ?? ''),
            'domain' => $this->tenantDomain(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readBrandingJson(): ?array
    {
        if ($this->brandingJson !== null) {
            return $this->brandingJson ?: null;
        }

        $this->brandingJson = $this->readRootkJson(self::BRANDING_JSON) ?? [];

        return $this->brandingJson !== [] ? $this->brandingJson : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readTenantJson(): ?array
    {
        if ($this->tenantJson !== null) {
            return $this->tenantJson ?: null;
        }

        $this->tenantJson = $this->readRootkJson(self::TENANT_JSON) ?? [];

        return $this->tenantJson !== [] ? $this->tenantJson : null;
    }

    /**
     * Prefer admin/.rootk then repo-root/.rootk (Next.js + Laravel hybrid).
     *
     * @return array<string, mixed>|null
     */
    private function readRootkJson(string $relative): ?array
    {
        foreach ([
            base_path($relative),
            dirname(base_path()).DIRECTORY_SEPARATOR.$relative,
        ] as $path) {
            $decoded = $this->readJsonFile($path);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonFile(string $path): ?array
    {
        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function mapBrandingJson(array $json): array
    {
        $company = is_array($json['company'] ?? null) ? $json['company'] : [];
        $logos = is_array($json['logos'] ?? null) ? $json['logos'] : [];
        $colors = is_array($json['colors'] ?? null) ? $json['colors'] : [];
        $seo = is_array($json['seo'] ?? null) ? $json['seo'] : [];
        $email = is_array($json['email'] ?? null) ? $json['email'] : [];
        $pdf = is_array($json['pdf'] ?? null) ? $json['pdf'] : [];
        $mobile = is_array($json['mobile'] ?? null) ? $json['mobile'] : [];

        $out = [
            'company_name' => $company['name'] ?? null,
            'company_name_ar' => $company['nameAr'] ?? $company['name_ar'] ?? null,
            'company_short_name' => $company['shortName'] ?? $company['short_name'] ?? null,
            'company_short_name_ar' => $company['shortNameAr'] ?? $company['short_name_ar'] ?? null,
            'logo_light' => $logos['light'] ?? null,
            'logo_dark' => $logos['dark'] ?? null,
            'logo_mark' => $logos['mark'] ?? $logos['favicon'] ?? null,
            'favicon' => $logos['favicon'] ?? null,
            'primary_color' => $colors['primary'] ?? null,
            'secondary_color' => $colors['secondary'] ?? null,
            'accent_color' => $colors['accent'] ?? null,
            'primary_hover' => $colors['primaryHover'] ?? $colors['primary_hover'] ?? $colors['hover'] ?? null,
            'theme_color_light' => $colors['themeLight'] ?? $colors['theme_color_light'] ?? $colors['theme'] ?? null,
            'theme_color_dark' => $colors['themeDark'] ?? $colors['theme_color_dark'] ?? null,
            'website_title' => $seo['title'] ?? null,
            'website_title_ar' => $seo['titleAr'] ?? $seo['title_ar'] ?? null,
            'website_description' => $seo['description'] ?? null,
            'website_description_ar' => $seo['descriptionAr'] ?? $seo['description_ar'] ?? null,
            'email_from_name' => $email['fromName'] ?? $email['from_name'] ?? null,
            'email_from_address' => $email['fromAddress'] ?? $email['from_address'] ?? null,
            'pdf_logo' => $pdf['logo'] ?? null,
            'pdf_footer' => $pdf['footer'] ?? null,
            'export_prefix' => $pdf['exportPrefix'] ?? $pdf['export_prefix'] ?? null,
            'mobile_app_name' => $mobile['appName'] ?? $mobile['app_name'] ?? null,
            'mobile_package' => $mobile['package'] ?? null,
            'mobile_splash_color' => $mobile['splashColor'] ?? $mobile['splash_color'] ?? $colors['mobile_splash'] ?? null,
        ];

        $variables = $json['variables'] ?? null;
        if (is_array($variables)) {
            $out = $this->mergeNonEmpty($out, $this->mapColorVariables($variables));
        }

        return $this->filterNonEmpty($out);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function mapTenantJson(array $json): array
    {
        return $this->filterNonEmpty([
            'company_name' => $json['displayName'] ?? $json['display_name'] ?? null,
            'company_name_ar' => $json['displayNameAr'] ?? $json['display_name_ar'] ?? null,
            'logo_light' => $json['logoUrl'] ?? $json['logo_url'] ?? null,
            'primary_color' => $json['primaryColor'] ?? $json['primary_color'] ?? null,
            'secondary_color' => $json['secondaryColor'] ?? $json['secondary_color'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEnvOverrides(): array
    {
        $out = [
            'company_name' => $this->envString('ROOTK_TENANT_DISPLAY_NAME') ?? $this->envString('BRAND_COMPANY_NAME'),
            'company_name_ar' => $this->envString('ROOTK_TENANT_DISPLAY_NAME_AR') ?? $this->envString('BRAND_COMPANY_NAME_AR'),
            'company_short_name' => $this->envString('BRAND_COMPANY_SHORT_NAME'),
            'company_short_name_ar' => $this->envString('BRAND_COMPANY_SHORT_NAME_AR'),
            'logo_light' => $this->envString('ROOTK_TENANT_LOGO_URL') ?? $this->envString('BRAND_LOGO_LIGHT'),
            'logo_dark' => $this->envString('BRAND_LOGO_DARK'),
            'favicon' => $this->envString('BRAND_FAVICON'),
            'primary_color' => $this->envString('ROOTK_TENANT_PRIMARY_COLOR') ?? $this->envString('BRAND_PRIMARY_COLOR'),
            'secondary_color' => $this->envString('ROOTK_TENANT_SECONDARY_COLOR') ?? $this->envString('BRAND_SECONDARY_COLOR'),
            'accent_color' => $this->envString('BRAND_ACCENT_COLOR'),
            'website_title' => $this->envString('BRAND_WEBSITE_TITLE'),
            'website_title_ar' => $this->envString('BRAND_WEBSITE_TITLE_AR'),
            'website_description' => $this->envString('BRAND_WEBSITE_DESCRIPTION'),
            'export_prefix' => $this->envString('BRAND_EXPORT_PREFIX'),
            'mobile_app_name' => $this->envString('BRAND_MOBILE_APP_NAME')
                ?? $this->envString('NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME')
                ?? $this->envString('ROOTK_TENANT_DISPLAY_NAME'),
            'mobile_package' => $this->envString('ROOTK_TENANT_MOBILE_PACKAGE')
                ?? $this->envString('BRAND_MOBILE_PACKAGE')
                ?? $this->envString('CAPACITOR_APP_ID')
                ?? $this->envString('MOBILE_ANDROID_PACKAGE'),
            'mobile_splash_color' => $this->envString('BRAND_MOBILE_SPLASH_COLOR'),
            'pdf_logo' => $this->envString('BRAND_PDF_LOGO'),
            'pdf_footer' => $this->envString('BRAND_PDF_FOOTER'),
            'email_from_name' => $this->envString('BRAND_EMAIL_FROM_NAME'),
            'email_from_address' => $this->envString('BRAND_EMAIL_FROM_ADDRESS'),
        ];

        $brandColors = $this->envString('ROOTK_TENANT_BRAND_COLORS');
        if ($brandColors !== null) {
            $decoded = json_decode($brandColors, true);
            if (is_array($decoded)) {
                $out = $this->mergeNonEmpty($out, $this->mapColorVariables($decoded));
            }
        }

        return $this->filterNonEmpty($out);
    }

    /**
     * @param  array<int|string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function mapColorVariables(array $variables): array
    {
        $aliases = [
            'primary' => 'primary_color',
            'primary_color' => 'primary_color',
            'secondary' => 'secondary_color',
            'secondary_color' => 'secondary_color',
            'accent' => 'accent_color',
            'accent_color' => 'accent_color',
            'primary_hover' => 'primary_hover',
            'theme_color_light' => 'theme_color_light',
            'theme_color_dark' => 'theme_color_dark',
            'mobile_splash_color' => 'mobile_splash_color',
        ];

        $out = [];

        foreach ($variables as $key => $item) {
            if (is_array($item)) {
                $name = (string) ($item['name'] ?? '');
                $value = $item['value'] ?? null;
            } else {
                $name = (string) $key;
                $value = $item;
            }

            $name = strtolower(trim($name));
            if ($name === '' || ! is_string($value) || trim($value) === '') {
                continue;
            }

            $target = $aliases[$name] ?? null;
            if ($target !== null) {
                $out[$target] = trim($value);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeNonEmpty(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterNonEmpty(array $data): array
    {
        return array_filter($data, static fn ($v) => $v !== null && $v !== '');
    }

    private function envString(string $key): ?string
    {
        // Prefer live process env (ROOTK sync) over Laravel's immutable Dotenv repo.
        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? false;
        if ($raw === false || $raw === null) {
            $raw = getenv($key);
        }
        if ($raw === false || $raw === null) {
            $raw = env($key);
        }
        if (! is_string($raw)) {
            return null;
        }

        $value = trim($raw);

        return $value !== '' ? $value : null;
    }
}
