<?php

use App\Http\Controllers\Api\SiteSettingController;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function settingsAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'settings-'.Str::random(5).'@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

it('renders the settings studio without save buttons', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Settings', false)
        ->assertSee('data-settings-form', false)
        ->assertSee('Shipping price', false)
        ->assertSee('Shipping company', false)
        ->assertDontSee('Free shipping from', false)
        ->assertSee('Stock visibility', false)
        ->assertSee('Show exact quantities', false)
        ->assertSee('Store location', false)
        ->assertSee('Google Maps link', false)
        ->assertSee('Payment methods', false)
        ->assertSee('data-payment-method="instapay"', false)
        ->assertSee('Default at checkout', false)
        ->assertDontSee('Save Currency', false)
        ->assertDontSee('Save Contact', false)
        ->assertDontSee('Save Settings', false);
});

it('persists general settings and exposes them on the storefront API', function () {
    $admin = settingsAdmin();
    Cache::forget(SiteSettingController::STORE_CACHE_KEY);

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'general',
            'store_name' => 'Dokan Ward Atelier',
            'store_email' => 'hello@dokannward.test',
            'store_phone' => '+201099988877',
            'store_whatsapp' => '',
            'store_description' => 'Editorial luxury.',
            'store_announcement' => 'New season drop',
            'store_address_label' => 'Cairo studio',
            'store_address_label_ar' => 'أستوديو القاهرة',
            'store_address' => 'New Cairo',
            'store_address_ar' => 'القاهرة الجديدة، مصر',
            'store_maps_url' => 'maps.app.goo.gl/dokanward-atelier',
            'social_instagram' => 'instagram.com/dokan_ward_96',
            'social_tiktok' => 'https://www.instagram.com/dokan_ward_96',
            'social_facebook' => '',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('section', 'general');

    $row = SiteSetting::where('key', 'general')->first();
    expect($row)->not->toBeNull();
    expect($row->value['store_name'])->toBe('Dokan Ward Atelier');
    expect($row->value['store_announcement'])->toBe('New season drop');
    expect($row->value['social_instagram'])->toBe('https://instagram.com/dokan_ward_96');
    expect($row->value['store_address_label_ar'])->toBe('أستوديو القاهرة');
    expect($row->value['store_address_ar'])->toBe('القاهرة الجديدة، مصر');
    expect($row->value['store_maps_url'])->toBe('https://maps.app.goo.gl/dokanward-atelier');

    $this->getJson('/api/settings/store')
        ->assertOk()
        ->assertJsonPath('name', 'Dokan Ward Atelier')
        ->assertJsonPath('announcement', 'New season drop')
        ->assertJsonPath('phone', '+201099988877')
        ->assertJsonPath('address_label', 'Cairo studio')
        ->assertJsonPath('address_label_ar', 'أستوديو القاهرة')
        ->assertJsonPath('address', 'New Cairo')
        ->assertJsonPath('address_ar', 'القاهرة الجديدة، مصر')
        ->assertJsonPath('maps_url', 'https://maps.app.goo.gl/dokanward-atelier')
        ->assertJsonPath('social.instagram', 'https://instagram.com/dokan_ward_96');
});

it('persists shipping and tax for checkout settings', function () {
    $admin = settingsAdmin();
    Cache::forget(SiteSettingController::CHECKOUT_CACHE_KEY);

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'shipping',
            'standard_shipping_fee' => 55,
            'shipping_company' => 'Bosta',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('checkout.standard_shipping_fee', 55)
        ->assertJsonPath('checkout.shipping_company', 'Bosta');

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'tax',
            'tax_rate' => 14,
            'tax_enabled' => true,
            'tax_enabled_message' => 'Includes {rate}% VAT',
            'tax_disabled_message' => 'No VAT is charged',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('checkout.tax_rate', 14)
        ->assertJsonPath('checkout.tax_enabled', true);

    $this->getJson('/api/settings/checkout')
        ->assertOk()
        ->assertJsonPath('standard_shipping_fee', 55)
        ->assertJsonPath('shipping_company', 'Bosta')
        ->assertJsonPath('tax_rate', 14)
        ->assertJsonPath('tax_enabled', true)
        ->assertJsonPath('tax_enabled_message', 'Includes {rate}% VAT')
        ->assertJsonPath('tax_disabled_message', 'No VAT is charged')
        ->assertJsonStructure(['updated_at']);
});

it('allows the shipping company to be left blank', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'shipping',
            'standard_shipping_fee' => 55,
            'shipping_company' => '',
        ])
        ->assertOk()
        ->assertJsonPath('checkout.shipping_company', '');

    $this->getJson('/api/settings/checkout')
        ->assertOk()
        ->assertJsonPath('shipping_company', '');
});

it('removes retired free-shipping fields when the fixed shipping settings are saved', function () {
    $admin = settingsAdmin();

    SiteSetting::create([
        'id' => (string) Str::uuid(),
        'key' => 'shipping',
        'value' => [
            'free_shipping_threshold' => 500,
            'standard_shipping_fee' => 40,
            'free_shipping_applied_message' => 'Custom delivery message',
        ],
    ]);

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'shipping',
            'standard_shipping_fee' => 60,
            'shipping_company' => 'Aramex',
        ])
        ->assertOk();

    $shipping = SiteSetting::where('key', 'shipping')->firstOrFail()->value;
    expect($shipping)->toBe([
        'standard_shipping_fee' => 60,
        'shipping_company' => 'Aramex',
    ]);
});

it('controls storefront stock status and exact quantity visibility', function () {
    $admin = settingsAdmin();
    Cache::forget(SiteSettingController::INVENTORY_CACHE_KEY);

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'inventory',
            'show_stock_status' => '1',
            'show_stock_quantity' => '1',
        ])
        ->assertOk()
        ->assertJsonPath('section', 'inventory');

    $this->getJson('/api/settings/inventory')
        ->assertOk()
        ->assertJson([
            'show_stock_status' => true,
            'show_stock_quantity' => true,
        ]);
});

it('turns exact quantities off whenever stock status is hidden', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'inventory',
            'show_stock_quantity' => '1',
        ])
        ->assertOk();

    $this->getJson('/api/settings/inventory')
        ->assertOk()
        ->assertJson([
            'show_stock_status' => false,
            'show_stock_quantity' => false,
        ]);
});

it('persists currency settings', function () {
    $admin = settingsAdmin();
    Cache::forget(SiteSettingController::CURRENCY_CACHE_KEY);

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'currency',
            'code' => 'sar',
            'symbol' => 'SR',
            'position' => 'after',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->getJson('/api/settings/currency')
        ->assertOk()
        ->assertJsonPath('code', 'SAR')
        ->assertJsonPath('symbol', 'SR')
        ->assertJsonPath('position', 'after');
});

it('rejects unknown sections instead of faking success', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'mystery',
            'store_name' => 'Nope',
        ])
        ->assertStatus(422);
});

it('saves via browser-style form POST with method spoofing', function () {
    $admin = settingsAdmin();
    Cache::forget(SiteSettingController::STORE_CACHE_KEY);

    $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('admin.settings.update'), [
            '_method' => 'PUT',
            'section' => 'general',
            'store_name' => 'Dokan Ward Form Path',
            'store_email' => 'form@dokannward.test',
            'store_phone' => '+201012345678',
            'store_whatsapp' => '',
            'store_description' => 'Saved from FormData',
            'store_announcement' => 'Autosave path works',
            'store_address_label' => '',
            'store_address_label_ar' => '',
            'store_address' => '',
            'store_address_ar' => '',
            'store_maps_url' => '',
            'social_instagram' => '',
            'social_tiktok' => '',
            'social_facebook' => '',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('section', 'general');

    $this->getJson('/api/settings/store')
        ->assertOk()
        ->assertJsonPath('name', 'Dokan Ward Form Path')
        ->assertJsonPath('announcement', 'Autosave path works')
        ->assertJsonPath('email', 'form@dokannward.test');
});

it('publishes the enabled payment methods with cash as the shipped default', function () {
    Cache::forget(SiteSettingController::CHECKOUT_CACHE_KEY);

    $checkout = $this->getJson('/api/settings/checkout')->assertOk()->json();

    expect($checkout['default_payment_method'])->toBe('cash');
    expect(array_column($checkout['payment_methods'], 'key'))->toBe(['cash']);
    expect($checkout['payment_methods'][0]['label'])->toBe('Cash on delivery');
});

it('lets the admin switch on extra payment methods and pick the default', function () {
    $admin = settingsAdmin();
    Cache::forget(SiteSettingController::CHECKOUT_CACHE_KEY);

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'payments',
            'default_method' => 'instapay',
            'cash_enabled' => '1',
            'cash_label' => 'Cash on delivery',
            'cash_instructions' => 'Pay the courier.',
            'instapay_enabled' => '1',
            'instapay_label' => 'InstaPay transfer',
            'instapay_instructions' => 'Send to dokannward@instapay.',
            'visa_label' => '',
            'visa_instructions' => '',
            'wallet_label' => '',
            'wallet_instructions' => '',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('checkout.default_payment_method', 'instapay');

    $checkout = $this->getJson('/api/settings/checkout')->assertOk()->json();

    expect(array_column($checkout['payment_methods'], 'key'))->toBe(['cash', 'instapay']);
    expect($checkout['payment_methods'][1]['label'])->toBe('InstaPay transfer');
    expect($checkout['payment_methods'][1]['instructions'])->toBe('Send to dokannward@instapay.');
    expect($checkout['default_payment_method'])->toBe('instapay');
});

it('refuses a default payment method that is switched off', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'payments',
            'default_method' => 'visa',
            'cash_enabled' => '1',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['default_method']);
});

it('refuses to leave the store with no payment method at all', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'payments',
            'default_method' => 'cash',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['default_method']);
});

it('returns validation errors as json for invalid social urls', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), [
            'section' => 'general',
            'store_name' => 'Dokan Ward',
            'store_email' => 'hello@dokannward.test',
            'social_instagram' => 'not a url !!!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['social_instagram']);
});
