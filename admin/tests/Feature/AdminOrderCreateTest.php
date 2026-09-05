<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

function orderAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'orders-admin@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

function seedSellableProduct(): Product
{
    $collection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
        'slug' => ['en' => 'bags', 'ar' => 'bags'],
        'position' => 1,
        'is_published' => true,
    ]);

    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Tote', 'ar' => 'Tote'],
        'slug' => ['en' => 'tote', 'ar' => 'tote'],
        'collection_id' => $collection->id,
        'position' => 1,
    ]);

    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Dokan Ward', 'ar' => 'Dokan Ward'],
        'slug' => 'dokan-ward-orders',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    return Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ZBR-ORD-01',
        'slug' => 'order-desk-tote',
        'name' => ['en' => 'Order Desk Tote', 'ar' => 'Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 400,
        'status' => 'active',
        'in_stock' => true,
        'featured' => false,
    ]);
}

it('shows the create-order desk from the orders index', function () {
    $admin = orderAdmin();

    $this->actingAs($admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertSee('Create order')
        ->assertSee(route('admin.orders.create'), false);

    $this->actingAs($admin)
        ->get(route('admin.orders.create'))
        ->assertOk()
        ->assertSee('Create order')
        ->assertSee('Line items')
        ->assertSee('Place order');
});

it('searches the catalog for the order desk picker', function () {
    $admin = orderAdmin();
    $product = seedSellableProduct();

    $this->actingAs($admin)
        ->getJson(route('admin.orders.catalogSearch', ['q' => 'Desk']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.sku', 'ZBR-ORD-01');
});

it('creates a manual order with inventory-safe pricing', function () {
    $admin = orderAdmin();
    $product = seedSellableProduct();

    $response = $this->actingAs($admin)
        ->post(route('admin.orders.store'), [
            'recipient_name' => 'Hajar Client',
            'phone' => '01034212422',
            'email' => 'client@example.com',
            'line_1' => 'New Cairo',
            'city' => 'Cairo',
            'country' => 'Egypt',
            'status' => 'pending',
            'notes' => 'WhatsApp order',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'name' => 'Order Desk Tote',
                    'qty' => 2,
                    'sku' => $product->sku,
                ],
            ],
        ]);

    $order = Order::query()->first();
    expect($order)->not->toBeNull();
    $response->assertRedirect(route('admin.orders.show', $order->id));

    expect($order->order_number)->toStartWith('ZBR-');
    expect((float) $order->subtotal)->toBe(800.0);
    expect($order->customer_email)->toBe('client@example.com');
    expect($order->items)->toHaveCount(1);
    expect((int) $order->items->first()->qty)->toBe(2);
});

it('records the payment method the desk picked for a manual order', function () {
    $admin = orderAdmin();
    $product = seedSellableProduct();
    \Illuminate\Support\Facades\Cache::forget(\App\Http\Controllers\Api\SiteSettingController::CHECKOUT_CACHE_KEY);
    \App\Models\SiteSetting::updateOrCreate(['key' => 'payments'], [
        'id' => (string) Str::uuid(),
        'value' => [
            'default_method' => 'cash',
            'cash_enabled' => true,
            'instapay_enabled' => true,
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.create'))
        ->assertOk()
        ->assertSee('Payment method', false)
        ->assertSee('value="instapay"', false);

    $this->actingAs($admin)->post(route('admin.orders.store'), [
        'recipient_name' => 'Hajar Client',
        'phone' => '01034212422',
        'line_1' => 'New Cairo',
        'city' => 'Cairo',
        'status' => 'pending',
        'payment_method' => 'instapay',
        'items' => [[
            'product_id' => $product->id,
            'name' => 'Order Desk Tote',
            'qty' => 1,
            'sku' => $product->sku,
        ]],
    ]);

    expect(Order::query()->first()->payment_method)->toBe('instapay');
});

it('creates a variant line and decrements stock', function () {
    $admin = orderAdmin();
    $product = seedSellableProduct();

    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $product->id,
        'sku' => 'ZBR-ORD-01-BLK',
        'price' => 450,
        'stock' => 3,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.orders.store'), [
            'recipient_name' => 'Phone Buyer',
            'phone' => '01111111111',
            'line_1' => 'Maadi',
            'city' => 'Cairo',
            'status' => 'processing',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'name' => 'Order Desk Tote · Black',
                    'qty' => 2,
                    'sku' => $variant->sku,
                ],
            ],
        ])
        ->assertRedirect();

    expect((int) $variant->fresh()->stock)->toBe(1);
    expect(Order::query()->value('status'))->toBe('processing');
    expect((float) Order::query()->value('subtotal'))->toBe(900.0);
});

it('rejects create when required client fields are missing', function () {
    $admin = orderAdmin();

    $this->actingAs($admin)
        ->from(route('admin.orders.create'))
        ->post(route('admin.orders.store'), [
            'status' => 'pending',
            'items' => [],
        ])
        ->assertRedirect(route('admin.orders.create'))
        ->assertSessionHasErrors(['recipient_name', 'phone', 'line_1', 'city', 'items']);
});
