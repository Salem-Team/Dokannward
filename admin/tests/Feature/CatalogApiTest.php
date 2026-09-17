<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Notification;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Category classifies the product; the Collection it is merchandised in is
    // an explicit membership (`collection_product`).
    $this->collection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
        'slug' => ['en' => 'bags', 'ar' => 'bags'],
        'description' => 'Authenticated bags, curated by Dokan Ward.',
        'position' => 1,
        'is_published' => true,
    ]);

    $this->tote = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Tote', 'ar' => 'Tote'],
        'slug' => ['en' => 'tote', 'ar' => 'tote'],
        'parent_id' => null,
        'position' => 1,
        'is_featured' => true,
    ]);

    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Dokan Ward Test', 'ar' => 'Dokan Ward'],
        'slug' => 'dokan-ward-test',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $this->product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ZT-001',
        'slug' => 'dokan-ward-test-tote',
        'name' => ['en' => 'Test Tote', 'ar' => 'Test Tote'],
        'description' => ['en' => 'A test bag', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $this->tote->id,
        'price' => 250,
        'status' => 'active',
        'featured' => true,
    ]);

    $this->product->collections()->sync([$this->collection->id => ['position' => 0]]);
});

it('includes collection members when filtering by collection slug', function () {
    $this->getJson('/api/products?category=bags')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'dokan-ward-test-tote');
});

it('filters by leaf category slug', function () {
    $this->getJson('/api/products?category=tote')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('exposes published collections as the category tree roots', function () {
    $response = $this->getJson('/api/categories')->assertOk();

    $roots = collect($response->json('data'));
    $collectionRoot = $roots->firstWhere('slug', 'bags');
    $categoryRoot = $roots->firstWhere('slug', 'tote');

    expect($collectionRoot)->not->toBeNull()
        ->and($collectionRoot['children'][0]['slug'] ?? null)->toBe('tote')
        ->and($collectionRoot['children'][0]['is_featured'] ?? null)->toBeTrue()
        // Category also appears as an independent classification root.
        ->and($categoryRoot)->not->toBeNull()
        ->and($categoryRoot['children'] ?? [])->toBe([]);
});

it('hides unpublished collections from the category tree but keeps categories', function () {
    $this->collection->update(['is_published' => false]);
    \App\Http\Controllers\Api\CategoryController::forgetListCache();
    \App\Http\Controllers\Api\CollectionController::forgetListCache();
    \App\Http\Controllers\Api\ProductController::forgetListCache();

    $roots = collect($this->getJson('/api/categories')->assertOk()->json('data'));

    expect($roots->firstWhere('slug', 'bags'))->toBeNull()
        ->and($roots->firstWhere('slug', 'tote'))->not->toBeNull();

    $this->getJson('/api/collections')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('hides products when their collection is unpublished', function () {
    $this->collection->update(['is_published' => false]);
    \App\Http\Controllers\Api\ProductController::forgetListCache();

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/products?category=bags')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/products?category=tote')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/products/dokan-ward-test-tote')
        ->assertNotFound();
});

it('exposes storefront-ready brand list', function () {
    $this->getJson('/api/brands?per_page=50')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'dokan-ward-test')
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'name',
                    'slug',
                    'logo_url',
                ],
            ],
        ]);
});

it('applies shipping and tax from site settings at checkout', function () {
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

    $response = $this->postJson('/api/checkout', [
        'recipient_name' => 'Test Buyer',
        'phone' => '+201000000000',
        'email' => 'buyer@dokannward.test',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'country' => 'Egypt',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'place_name' => 'Tahrir Square, Cairo',
        'location_source' => 'map',
        'items' => [[
            'product_id' => $this->product->id,
            'name' => 'Test Tote',
            'price' => 250,
            'qty' => 1,
            'sku' => 'ZT-001',
        ]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'pending');

    expect($response->json('order_number'))->toStartWith('DW-');
    expect((float) $response->json('total_amount'))->toBe(300.0); // 250 + 25 ship + 25 tax

    expect(Notification::where('type', 'order')->count())->toBe(1);
});

it('creates a brand slug automatically from the admin form', function () {
    $controller = app(\App\Http\Controllers\Admin\BrandController::class);
    $request = \Illuminate\Http\Request::create('/admin/brands', 'POST', [
        'name_en' => 'Unique Brand Co',
        'name_ar' => 'براند',
        'country' => 'France',
    ]);

    $response = $controller->store($request);

    expect($response->getTargetUrl())->toContain('brands');
    $brand = \App\Models\Brand::where('slug', 'unique-brand-co')->first();
    expect($brand)->not->toBeNull();
    expect($brand->logo_url)->toContain('ui-avatars.com');
});

it('exposes general store settings for the storefront', function () {
    SiteSetting::create([
        'id' => (string) Str::uuid(),
        'key' => 'general',
        'value' => [
            'store_name' => 'Dokan Ward Live',
            'store_email' => 'hello@dokannward.test',
            'store_phone' => '+201111111111',
            'store_description' => 'Live description',
            'store_announcement' => 'Flash sale this week',
        ],
    ]);

    $this->getJson('/api/settings/store')
        ->assertOk()
        ->assertJsonPath('name', 'Dokan Ward Live')
        ->assertJsonPath('email', 'hello@dokannward.test')
        ->assertJsonPath('phone', '+201111111111')
        ->assertJsonPath('description', 'Live description')
        ->assertJsonPath('announcement', 'Flash sale this week')
        ->assertJsonPath('address_label', 'Serving Egypt')
        ->assertJsonPath('address_label_ar', 'نخدم كل مصر')
        ->assertJsonPath('address_ar', 'مصر')
        ->assertJsonPath('maps_url', '');
});

it('exposes checkout shipping and tax settings for the storefront', function () {
    SiteSetting::create([
        'id' => (string) Str::uuid(),
        'key' => 'shipping',
        'value' => [
            'standard_shipping_fee' => 40,
            'shipping_company' => 'Aramex',
        ],
    ]);
    SiteSetting::create([
        'id' => (string) Str::uuid(),
        'key' => 'tax',
        'value' => [
            'tax_rate' => 14,
            'tax_enabled' => true,
        ],
    ]);

    $this->getJson('/api/settings/checkout')
        ->assertOk()
        ->assertJsonPath('standard_shipping_fee', 40)
        ->assertJsonPath('shipping_company', 'Aramex')
        ->assertJsonPath('tax_rate', 14)
        ->assertJsonPath('tax_enabled', true);
});

it('decrements variant stock and refuses overselling at checkout', function () {
    $this->product->update(['in_stock' => true, 'status' => 'active']);

    $color = \App\Models\Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Noir',
        'hex' => '#111111',
        'is_active' => true,
    ]);

    $variant = \App\Models\ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'sku' => 'ZT-001-NOIR',
        'color_id' => $color->id,
        'price' => 250,
        'stock' => 1,
        'is_active' => true,
    ]);

    $payload = [
        'recipient_name' => 'Test Buyer',
        'phone' => '+201000000000',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'items' => [[
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'name' => 'Test Tote — Noir',
            'price' => 1, // client underpay — server must bill DB price
            'qty' => 1,
            'sku' => 'ZT-001-NOIR',
        ]],
    ];

    $first = $this->postJson('/api/checkout', $payload);
    $first->assertCreated();
    expect((float) $first->json('subtotal'))->toBe(250.0);

    expect((int) $variant->fresh()->stock)->toBe(0);
    expect((bool) $this->product->fresh()->in_stock)->toBeFalse();

    $this->postJson('/api/checkout', $payload)
        ->assertStatus(422);
});
