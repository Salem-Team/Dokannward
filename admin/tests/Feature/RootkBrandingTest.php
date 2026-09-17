<?php

use App\Support\Branding;
use Illuminate\Support\Facades\Artisan;

it('exposes packaged tenant branding by default', function () {
    $tenant = Branding::tenantBranding();

    expect($tenant['displayName'])->toBe('Dokan Ward')
        ->and($tenant['displayNameAr'])->toBe('دكان ورد')
        ->and($tenant['colors']['primary_color'])->toStartWith('#');
});

it('overrides display name from ROOTK_TENANT_DISPLAY_NAME env', function () {
    putenv('ROOTK_TENANT_DISPLAY_NAME=Acme Atelier');
    $_ENV['ROOTK_TENANT_DISPLAY_NAME'] = 'Acme Atelier';
    Branding::bustCache();

    expect(Branding::tenantBranding()['displayName'])->toBe('Acme Atelier');

    putenv('ROOTK_TENANT_DISPLAY_NAME');
    unset($_ENV['ROOTK_TENANT_DISPLAY_NAME']);
    Branding::bustCache();
});

it('rootk:install clears config cache and does not require config:cache', function () {
    $exit = Artisan::call('rootk:install', [
        '--skip-migrate' => true,
        '--skip-seed' => true,
        '--skip-brand-sync' => true,
    ]);

    expect($exit)->toBe(0);
});

it('overrides logo url from ROOTK_TENANT_LOGO_URL env', function () {
    putenv('ROOTK_TENANT_LOGO_URL=https://cdn.example/logo-light.png');
    $_ENV['ROOTK_TENANT_LOGO_URL'] = 'https://cdn.example/logo-light.png';
    Branding::bustCache();

    expect(Branding::tenantBranding()['logoLightUrl'])->toBe('https://cdn.example/logo-light.png');

    putenv('ROOTK_TENANT_LOGO_URL');
    unset($_ENV['ROOTK_TENANT_LOGO_URL']);
    Branding::bustCache();
});

it('resolves order number prefix from export_prefix branding', function () {
    expect(\App\Models\Order::normalizeNumberPrefix('DW'))->toBe('DW')
        ->and(\App\Models\Order::normalizeNumberPrefix('Dokan Ward'))->toBe('DW')
        ->and(\App\Models\Order::normalizeNumberPrefix('Acme'))->toBe('ACME');
});
