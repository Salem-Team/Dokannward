<?php

namespace App\Services\Branding;

use App\Models\PlatformBrand;
use App\Models\PlatformBrandDomain;
use App\Support\Branding;
use Illuminate\Support\Facades\Schema;

/**
 * Persists ROOTK runtime branding (.rootk/* + env) into platform_brands with Sidra column names.
 *
 * Complements ROOTK's ephemeral `.platform-brand-sync.php` (which may use alias keys that
 * PlatformBrand remaps). Safe to run on every rootk:install / deploy.
 */
final class RootkPlatformBrandSyncService
{
    public function __construct(
        private readonly RootkBrandingLoader $rootk = new RootkBrandingLoader,
    ) {}

    /**
     * @return array{synced: bool, slug: string|null, domain: string|null}
     */
    public function sync(): array
    {
        if (! Schema::hasTable('platform_brands')) {
            return ['synced' => false, 'slug' => null, 'domain' => null];
        }

        if (! $this->rootk->isManaged()) {
            return ['synced' => false, 'slug' => null, 'domain' => null];
        }

        $overrides = $this->rootk->overrides();
        if ($overrides === []) {
            return ['synced' => false, 'slug' => null, 'domain' => null];
        }

        $slug = $this->resolveSlug();
        $companyName = (string) ($overrides['company_name'] ?? config('branding.company_name', 'Default Company'));

        $payload = [
            'name' => $companyName,
            'is_default' => true,
            'company_name' => $overrides['company_name'] ?? null,
            'company_name_ar' => $overrides['company_name_ar'] ?? null,
            'company_short_name' => $overrides['company_short_name'] ?? $overrides['company_name'] ?? null,
            'company_short_name_ar' => $overrides['company_short_name_ar'] ?? $overrides['company_name_ar'] ?? null,
            'logo_light_path' => $overrides['logo_light'] ?? null,
            'logo_dark_path' => $overrides['logo_dark'] ?? null,
            'logo_mark_path' => $overrides['logo_mark'] ?? null,
            'favicon_path' => $overrides['favicon'] ?? null,
            'primary_color' => $overrides['primary_color'] ?? null,
            'secondary_color' => $overrides['secondary_color'] ?? null,
            'accent_color' => $overrides['accent_color'] ?? null,
            'primary_hover' => $overrides['primary_hover'] ?? null,
            'theme_color_light' => $overrides['theme_color_light'] ?? null,
            'theme_color_dark' => $overrides['theme_color_dark'] ?? null,
            'mobile_splash_color' => $overrides['mobile_splash_color'] ?? null,
            'website_title' => $overrides['website_title'] ?? null,
            'website_title_ar' => $overrides['website_title_ar'] ?? null,
            'website_description' => $overrides['website_description'] ?? null,
            'website_description_ar' => $overrides['website_description_ar'] ?? null,
            'email_from_name' => $overrides['email_from_name'] ?? null,
            'email_from_address' => $overrides['email_from_address'] ?? null,
            'pdf_logo_path' => $overrides['pdf_logo'] ?? null,
            'pdf_footer' => $overrides['pdf_footer'] ?? null,
            'export_prefix' => $overrides['export_prefix'] ?? null,
            'mobile_app_name' => $overrides['mobile_app_name'] ?? null,
            'mobile_package' => $overrides['mobile_package'] ?? null,
        ];

        // Persist derived companions so platform_brands does not keep Sidra hover/accent
        // when ROOTK only supplied primary_color.
        $resolved = Branding::all();
        foreach ([
            'primary_hover' => 'primary_hover',
            'accent_color' => 'accent_color',
            'theme_color_light' => 'theme_color_light',
            'mobile_splash_color' => 'mobile_splash_color',
        ] as $payloadKey => $resolvedKey) {
            if (($payload[$payloadKey] ?? null) === null || ($payload[$payloadKey] ?? '') === '') {
                $value = $resolved[$resolvedKey] ?? null;
                if (is_string($value) && $value !== '') {
                    $payload[$payloadKey] = $value;
                }
            }
        }

        $payload = array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        $brand = PlatformBrand::query()->updateOrCreate(
            ['slug' => $slug],
            $payload
        );

        // Ensure only one default brand after sync.
        PlatformBrand::query()
            ->where('id', '!=', $brand->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $domain = $this->rootk->tenantDomain();
        if (is_string($domain) && $domain !== '' && Schema::hasTable('platform_brand_domains')) {
            PlatformBrandDomain::query()->updateOrCreate(
                ['host' => $domain],
                ['platform_brand_id' => $brand->id]
            );
        }

        Branding::bustCache($slug);

        return [
            'synced' => true,
            'slug' => $slug,
            'domain' => $domain,
        ];
    }

    private function resolveSlug(): string
    {
        $fromRootk = $this->rootk->tenantSlug();
        if (is_string($fromRootk) && $fromRootk !== '') {
            return $fromRootk;
        }

        $default = PlatformBrand::query()->where('is_default', true)->value('slug');
        if (is_string($default) && $default !== '') {
            return $default;
        }

        return (string) config('branding.slug', 'default');
    }
}
