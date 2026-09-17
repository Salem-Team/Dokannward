<?php

namespace App\Support;

use App\Services\Branding\BrandingService;
use Illuminate\Http\Request;

/**
 * Global branding accessor — prefer over config('branding') for runtime DB overrides.
 */
final class Branding
{
    public static function all(?Request $request = null): array
    {
        return app(BrandingService::class)->all($request);
    }

    public static function get(string $key, mixed $default = null, ?Request $request = null): mixed
    {
        return app(BrandingService::class)->get($key, $default, $request);
    }

    public static function companyName(?Request $request = null): string
    {
        return app(BrandingService::class)->companyName($request);
    }

    public static function bootAppName(bool $preferArabic = false, ?Request $request = null): string
    {
        return app(BrandingService::class)->bootAppName($preferArabic, $request);
    }

    public static function exportPrefix(?Request $request = null): string
    {
        return app(BrandingService::class)->exportPrefix($request);
    }

    public static function investorPortalSubjectPrefix(?Request $request = null): string
    {
        return app(BrandingService::class)->investorPortalSubjectPrefix($request);
    }

    public static function forFrontend(?Request $request = null): array
    {
        return app(BrandingService::class)->forFrontend($request);
    }

    public static function bustCache(?string $slug = null): void
    {
        app(BrandingService::class)->bustCache($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public static function tenantBranding(?Request $request = null): array
    {
        return app(BrandingService::class)->tenantBranding($request);
    }
}
