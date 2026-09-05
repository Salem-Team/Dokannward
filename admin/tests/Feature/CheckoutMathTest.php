<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Models\SiteSetting;
use App\Services\OrderPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('computes shipping and tax identically to the storefront defaults', function () {
    Cache::flush();
    $service = app(OrderPlacementService::class);

    // Defaults: fixed fee 10, tax 8.5%
    [$shipping, $tax] = $service->computeShippingAndTax(250.0);
    expect($shipping)->toBe(10.0);
    expect($tax)->toBe(21.25);

    [$shippingBelow, $taxBelow] = $service->computeShippingAndTax(50.0);
    expect($shippingBelow)->toBe(10.0);
    expect($taxBelow)->toBe(4.25);
});

it('honors custom site settings for shipping and tax math', function () {
    Cache::flush();

    SiteSetting::create([
        'id' => (string) Str::uuid(),
        'key' => 'shipping',
        'value' => [
            'standard_shipping_fee' => 25,
            'shipping_company' => 'Bosta',
        ],
    ]);
    SiteSetting::create([
        'id' => (string) Str::uuid(),
        'key' => 'tax',
        'value' => [
            'tax_rate' => 10,
            'tax_enabled' => true,
        ],
    ]);

    $service = app(OrderPlacementService::class);

    [$shipping, $tax] = $service->computeShippingAndTax(250.0);
    expect($shipping)->toBe(25.0);
    expect($tax)->toBe(25.0);
});

it('keeps SettingsController defaults aligned with the storefront', function () {
    expect(SettingsController::DEFAULTS['currency'])->toMatchArray([
        'code' => 'EGP',
        'symbol' => 'LE',
        'position' => 'before',
    ]);
    expect(SettingsController::DEFAULTS['shipping'])->toBe([
        'standard_shipping_fee' => 10,
        'shipping_company' => 'Dokan Ward Delivery',
    ]);
    expect(SettingsController::DEFAULTS['tax']['tax_rate'])->toBe(8.5);
});
