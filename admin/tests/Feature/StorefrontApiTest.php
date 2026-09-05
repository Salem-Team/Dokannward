<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\ContactMessage;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\Size;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Hermes', 'ar' => 'Hermes'],
        'slug' => 'hermes',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $this->category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
        'slug' => ['en' => 'bags', 'ar' => 'bags'],
        'parent_id' => null,
        'position' => 1,
    ]);

    $this->product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'HM-001',
        'slug' => 'birkin-test',
        'name' => ['en' => 'Birkin Test', 'ar' => 'بيركن'],
        'description' => ['en' => 'Iconic tote', 'ar' => ''],
        'brand_id' => $this->brand->id,
        'category_id' => $this->category->id,
        'price' => 1200,
        'status' => 'active',
        'featured' => true,
        'in_stock' => true,
    ]);
});

it('returns product show payload shaped for the storefront', function () {
    $this->getJson('/api/products/birkin-test')
        ->assertOk()
        ->assertJsonPath('data.slug', 'birkin-test')
        ->assertJsonPath('data.name', 'Birkin Test')
        ->assertJsonPath('data.brand.slug', 'hermes')
        ->assertJsonStructure([
            'data' => [
                'id',
                'sku',
                'slug',
                'name',
                'price',
                'in_stock',
                'colors',
                'rating' => ['average', 'count'],
                'seo',
            ],
        ]);
});

it('resolves product show by sku for QR and inventory lookups', function () {
    $this->getJson('/api/products/HM-001')
        ->assertOk()
        ->assertJsonPath('data.slug', 'birkin-test')
        ->assertJsonPath('data.sku', 'HM-001');
});

it('returns the admin-selected primary color first', function () {
    $black = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Black',
        'hex' => '#111111',
        'is_active' => true,
    ]);
    $red = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Red',
        'hex' => '#a6192e',
        'is_active' => true,
    ]);

    ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'color_id' => $black->id,
        'sku' => 'HM-001-BLACK',
        'price' => 1200,
        'stock' => 2,
        'is_active' => true,
        'is_default' => false,
    ]);
    ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'color_id' => $red->id,
        'sku' => 'HM-001-RED',
        'price' => 1200,
        'stock' => 2,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->getJson('/api/products/birkin-test')
        ->assertOk()
        ->assertJsonPath('data.stock', 4)
        ->assertJsonPath('data.colors.0.name', 'Red')
        ->assertJsonPath('data.colors.0.stock', 2)
        ->assertJsonPath('data.colors.0.is_default', true)
        ->assertJsonPath('data.colors.1.name', 'Black')
        ->assertJsonPath('data.colors.1.is_default', false);
});

it('exposes sizes and per-size stock on a sized product', function () {
    $size37 = Size::where('name', '37')->firstOrFail();
    $size38 = Size::where('name', '38')->firstOrFail();
    $size39 = Size::where('name', '39')->firstOrFail();
    $size40 = Size::where('name', '40')->firstOrFail();

    $this->product->sizes()->sync([$size37->id, $size38->id, $size39->id, $size40->id]);

    $black = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Black',
        'hex' => '#111111',
        'is_active' => true,
    ]);

    // 38/39 in stock, 37/40 at zero — still offered so the storefront can cross them out.
    foreach ([$size37->id => 0, $size38->id => 5, $size39->id => 3, $size40->id => 0] as $sizeId => $stock) {
        ProductVariant::create([
            'id' => (string) Str::uuid(),
            'product_id' => $this->product->id,
            'color_id' => $black->id,
            'size_id' => $sizeId,
            'sku' => 'HM-001-BLACK-'.$sizeId,
            'price' => 1200,
            'stock' => $stock,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    $response = $this->getJson('/api/products/birkin-test')
        ->assertOk()
        ->assertJsonPath('data.colors.0.name', 'Black')
        ->assertJsonPath('data.colors.0.in_stock', true)
        ->assertJsonPath('data.colors.0.is_default', true)
        ->assertJsonCount(4, 'data.colors.0.sizes')
        ->assertJsonPath('data.sizes', ['37', '38', '39', '40']);

    $sizesByName = collect($response->json('data.colors.0.sizes'))->keyBy('name');

    expect($sizesByName['37']['in_stock'])->toBeFalse()
        ->and($sizesByName['37']['stock'])->toBe(0)
        ->and($sizesByName['38']['in_stock'])->toBeTrue()
        ->and($sizesByName['39']['in_stock'])->toBeTrue()
        ->and($sizesByName['39']['stock'])->toBe(3)
        ->and($sizesByName['40']['in_stock'])->toBeFalse();
});

it('keeps color-only products working without a sizes list', function () {
    $black = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Black',
        'hex' => '#111111',
        'is_active' => true,
    ]);
    ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'color_id' => $black->id,
        'sku' => 'HM-001-BLACK',
        'price' => 1200,
        'stock' => 4,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->getJson('/api/products/birkin-test')
        ->assertOk()
        ->assertJsonPath('data.colors.0.name', 'Black')
        ->assertJsonPath('data.colors.0.sizes', [])
        ->assertJsonPath('data.sizes', []);
});

it('exposes approved review averages on the product list', function () {
    ProductReview::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'reviewer_name' => 'Approved Shopper',
        'rating' => 5,
        'title' => 'Love it',
        'body' => 'Perfect',
        'approved' => true,
    ]);
    ProductReview::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'reviewer_name' => 'Pending Shopper',
        'rating' => 1,
        'approved' => false,
    ]);

    $this->getJson('/api/products?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'birkin-test')
        ->assertJsonPath('data.0.rating.average', 5)
        ->assertJsonPath('data.0.rating.count', 1);
});

it('lists only approved reviews and accepts pending submissions', function () {
    ProductReview::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'reviewer_name' => 'Visible',
        'rating' => 4,
        'title' => 'Nice',
        'body' => 'Great bag',
        'approved' => true,
    ]);
    ProductReview::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'reviewer_name' => 'Hidden',
        'rating' => 1,
        'approved' => false,
    ]);

    $this->getJson('/api/products/birkin-test/reviews')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.author', 'Visible')
        ->assertJsonPath('meta.average', 4)
        ->assertJsonPath('meta.count', 1);

    $this->postJson('/api/products/birkin-test/reviews', [
        'reviewer_name' => 'New Guest',
        'reviewer_email' => 'guest@dokannward.test',
        'rating' => 5,
        'title' => 'Wow',
        'body' => 'Amazing quality',
        'recommended' => true,
    ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'message']);

    expect(ProductReview::where('approved', false)->count())->toBe(2);
    expect(Notification::where('type', 'review')->count())->toBe(1);

    // Pending reviews must not appear on the public index yet.
    $this->getJson('/api/products/birkin-test/reviews')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects invalid review payloads', function () {
    $this->postJson('/api/products/birkin-test/reviews', [
        'reviewer_name' => '',
        'rating' => 9,
    ])->assertStatus(422);
});

it('accepts contact messages and notifies the admin inbox', function () {
    $this->postJson('/api/contact', [
        'name' => 'Sara',
        'email' => 'sara@dokannward.test',
        'phone' => '+201000000111',
        'message' => 'Do you ship to Alexandria?',
    ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'message']);

    expect(ContactMessage::count())->toBe(1);
    expect(Notification::where('type', 'contact_message')->count())->toBe(1);
});

it('rejects incomplete contact payloads', function () {
    $this->postJson('/api/contact', [
        'name' => 'Sara',
        'email' => 'not-an-email',
        'message' => '',
    ])->assertStatus(422);
});

it('exposes only active testimonials ordered for the homepage', function () {
    Testimonial::create([
        'name' => 'Second',
        'content' => 'Second review',
        'is_active' => true,
        'is_featured' => false,
        'display_order' => 2,
        'rating' => 5,
    ]);
    Testimonial::create([
        'name' => 'First',
        'content' => 'First review',
        'is_active' => true,
        'is_featured' => true,
        'display_order' => 1,
        'rating' => 4,
    ]);
    Testimonial::create([
        'name' => 'Off',
        'content' => 'Hidden',
        'is_active' => false,
        'display_order' => 0,
    ]);

    $this->getJson('/api/testimonials')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.name', 'First')
        ->assertJsonPath('1.name', 'Second');
});

it('exposes currency settings with safe defaults', function () {
    $this->getJson('/api/settings/currency')
        ->assertOk()
        ->assertJsonStructure(['code', 'symbol', 'position']);

    SiteSetting::create([
        'id' => (string) Str::uuid(),
        'key' => 'currency',
        'value' => [
            'code' => 'USD',
            'symbol' => '$',
            'position' => 'before',
        ],
    ]);

    // Bust the cache key used by the controller.
    \Illuminate\Support\Facades\Cache::forget(\App\Http\Controllers\Api\SiteSettingController::CURRENCY_CACHE_KEY);

    $this->getJson('/api/settings/currency')
        ->assertOk()
        ->assertJsonPath('code', 'USD')
        ->assertJsonPath('symbol', '$')
        ->assertJsonPath('position', 'before');
});

it('filters featured products for the homepage rail', function () {
    Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'HM-002',
        'slug' => 'plain-tote',
        'name' => ['en' => 'Plain Tote', 'ar' => ''],
        'description' => ['en' => '', 'ar' => ''],
        'brand_id' => $this->brand->id,
        'category_id' => $this->category->id,
        'price' => 200,
        'status' => 'active',
        'featured' => false,
        'in_stock' => true,
    ]);

    $this->getJson('/api/products?featured=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'birkin-test');
});

it('hides inactive products from the public catalog', function () {
    $this->product->update(['status' => 'draft']);

    $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/products/birkin-test')->assertNotFound();
});

it('validates checkout required fields', function () {
    $this->postJson('/api/checkout', [
        'recipient_name' => '',
        'items' => [],
    ])->assertStatus(422);
});

it('requires a color when the product has active variants', function () {
    $color = \App\Models\Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Noir',
        'hex' => '#111111',
        'is_active' => true,
    ]);

    \App\Models\ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'sku' => 'HM-001-NOIR',
        'color_id' => $color->id,
        'price' => 1200,
        'stock' => 3,
        'is_active' => true,
    ]);

    $this->postJson('/api/checkout', [
        'recipient_name' => 'Buyer',
        'phone' => '+201000000000',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'items' => [[
            'product_id' => $this->product->id,
            'name' => 'Birkin Test',
            'price' => 1200,
            'qty' => 1,
        ]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.variant_id']);
});

it('stores the chosen payment method and echoes its shopper-facing copy', function () {
    \Illuminate\Support\Facades\Cache::forget(\App\Http\Controllers\Api\SiteSettingController::CHECKOUT_CACHE_KEY);
    \App\Models\SiteSetting::updateOrCreate(['key' => 'payments'], ['id' => (string) Str::uuid(), 'value' => [
        'default_method' => 'cash',
        'cash_enabled' => true,
        'cash_label' => 'Cash on delivery',
        'cash_instructions' => 'Pay the courier.',
        'instapay_enabled' => true,
        'instapay_label' => 'InstaPay',
        'instapay_instructions' => 'Send to dokannward@instapay.',
    ]]);

    $this->postJson('/api/checkout', [
        'recipient_name' => 'Buyer',
        'phone' => '+201000000000',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'payment_method' => 'instapay',
        'items' => [[
            'product_id' => $this->product->id,
            'name' => 'Birkin Test',
            'price' => 1200,
            'qty' => 1,
        ]],
    ])
        ->assertStatus(201)
        ->assertJsonPath('payment_method', 'instapay')
        ->assertJsonPath('payment_label', 'InstaPay')
        ->assertJsonPath('payment_instructions', 'Send to dokannward@instapay.');

    expect(\App\Models\Order::latest('created_at')->first()->payment_method)->toBe('instapay');
});

it('falls back to the default when a shopper posts a disabled payment method', function () {
    \Illuminate\Support\Facades\Cache::forget(\App\Http\Controllers\Api\SiteSettingController::CHECKOUT_CACHE_KEY);

    $this->postJson('/api/checkout', [
        'recipient_name' => 'Buyer',
        'phone' => '+201000000000',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'payment_method' => 'visa',
        'items' => [[
            'product_id' => $this->product->id,
            'name' => 'Birkin Test',
            'price' => 1200,
            'qty' => 1,
        ]],
    ])
        ->assertStatus(201)
        ->assertJsonPath('payment_method', 'cash');
});

it('searches products by name across locales', function () {
    $this->getJson('/api/products?q=birkin')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'birkin-test');

    $this->getJson('/api/products?q=no-such-product')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
