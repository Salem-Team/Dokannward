<?php

use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

function ordersAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'orders-admin@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

function seedOrderWithStock(int $stock = 5, int $qty = 2): array
{
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'House', 'ar' => 'House'],
        'slug' => 'house-orders-'.Str::random(4),
        'description' => ['en' => '', 'ar' => ''],
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'Bags'],
        'slug' => ['en' => 'bags-orders-'.Str::random(4), 'ar' => 'bags'],
        'position' => 1,
    ]);
    $product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ORD-'.Str::upper(Str::random(5)),
        'slug' => 'order-product-'.Str::random(6),
        'name' => ['en' => 'Ops Tote', 'ar' => 'Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 400,
        'status' => 'active',
        'in_stock' => true,
    ]);
    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $product->id,
        'sku' => $product->sku.'-A',
        'price' => 400,
        'stock' => $stock,
        'is_active' => true,
    ]);
    $address = Address::create([
        'id' => (string) Str::uuid(),
        'recipient_name' => 'Nour Client',
        'phone' => '01001234567',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'postal_code' => '11511',
        'country' => 'EG',
    ]);
    $order = Order::create([
        'customer_id' => null,
        'customer_email' => 'nour@example.com',
        'status' => 'pending',
        'total_amount' => 800,
        'subtotal' => 800,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'shipping_address_id' => $address->id,
        'notes' => null,
        'placed_at' => now(),
    ]);
    OrderItem::create([
        'id' => (string) Str::uuid(),
        'order_id' => $order->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'sku' => $variant->sku,
        'name' => 'Ops Tote',
        'unit_price' => 400,
        'qty' => $qty,
        'line_total' => 800,
        'tax' => 0,
    ]);

    return compact('order', 'variant', 'product', 'address');
}

it('renders the orders desk with pipeline chips and export', function () {
    $admin = ordersAdmin();
    seedOrderWithStock();

    $this->actingAs($admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertSee('Commerce · Operations')
        ->assertSee('Export CSV')
        ->assertSee('Pending')
        ->assertSee('Refunded')
        ->assertSee('Select page')
        ->assertSee('View products')
        ->assertSee('Ops Tote')
        ->assertSee('Nour Client')
        ->assertSee(route('admin.orders.bulk-destroy'), false);
});

it('filters orders by status chip and search', function () {
    $admin = ordersAdmin();
    $seed = seedOrderWithStock();

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['status' => 'pending', 'search' => $seed['order']->order_number]))
        ->assertOk()
        ->assertSee($seed['order']->order_number)
        ->assertSee('Nour Client');
});

it('exports filtered orders as csv', function () {
    $admin = ordersAdmin();
    $seed = seedOrderWithStock();

    $response = $this->actingAs($admin)
        ->get(route('admin.orders.export', ['status' => 'pending']));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.csv');

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain($seed['order']->order_number)
        ->and($csv)->toContain('Nour Client');
});

it('updates status with milestones and audit history', function () {
    $admin = ordersAdmin();
    $seed = seedOrderWithStock();

    $this->actingAs($admin)
        ->post(route('admin.orders.updateStatus', $seed['order']->id), [
            'status' => 'shipped',
        ])
        ->assertRedirect();

    $order = $seed['order']->fresh();
    expect($order->status)->toBe('shipped');
    expect($order->confirmed_at)->not->toBeNull();
    expect($order->shipped_at)->not->toBeNull();
    expect(OrderStatusHistory::query()->where('order_id', $order->id)->where('status', 'shipped')->exists())->toBeTrue();
});

it('restocks inventory when an order is cancelled', function () {
    $admin = ordersAdmin();
    $seed = seedOrderWithStock(stock: 3, qty: 2);
    expect((int) $seed['variant']->fresh()->stock)->toBe(3);

    $this->actingAs($admin)
        ->put(route('admin.orders.update', $seed['order']->id), [
            'status' => 'cancelled',
            'notes' => 'Customer cancelled on WhatsApp',
            'restock' => '1',
        ])
        ->assertRedirect(route('admin.orders.show', $seed['order']->id));

    expect($seed['order']->fresh()->status)->toBe('cancelled');
    expect($seed['order']->fresh()->canceled_at)->not->toBeNull();
    expect((int) $seed['variant']->fresh()->stock)->toBe(5);
});

it('marks the product in stock again after cancel restock from zero', function () {
    $admin = ordersAdmin();
    $seed = seedOrderWithStock(stock: 0, qty: 2);
    $seed['product']->forceFill(['in_stock' => false])->saveQuietly();

    expect($seed['product']->fresh()->in_stock)->toBeFalse();

    $this->actingAs($admin)
        ->put(route('admin.orders.update', $seed['order']->id), [
            'status' => 'cancelled',
            'restock' => '1',
        ])
        ->assertRedirect(route('admin.orders.show', $seed['order']->id));

    expect((int) $seed['variant']->fresh()->stock)->toBe(2)
        ->and($seed['product']->fresh()->in_stock)->toBeTrue();
});

it('shows the brand-studio edit desk', function () {
    $admin = ordersAdmin();
    $seed = seedOrderWithStock();

    $this->actingAs($admin)
        ->get(route('admin.orders.edit', $seed['order']->id))
        ->assertOk()
        ->assertSee('Edit '.$seed['order']->order_number)
        ->assertSee('Restock on cancel')
        ->assertSee('Save order');
});

it('restores stock when deleting a non-cancelled order', function () {
    $admin = ordersAdmin();
    $seed = seedOrderWithStock(stock: 1, qty: 1);

    $this->actingAs($admin)
        ->delete(route('admin.orders.destroy', $seed['order']->id))
        ->assertRedirect(route('admin.orders.index'));

    expect(Order::query()->whereKey($seed['order']->id)->exists())->toBeFalse();
    expect((int) $seed['variant']->fresh()->stock)->toBe(2);
});

it('bulk deletes selected orders and restores stock', function () {
    $admin = ordersAdmin();
    $first = seedOrderWithStock(stock: 3, qty: 1);
    $second = seedOrderWithStock(stock: 5, qty: 2);

    $this->actingAs($admin)
        ->post(route('admin.orders.bulk-destroy'), [
            'ids' => [$first['order']->id, $second['order']->id],
        ])
        ->assertRedirect(route('admin.orders.index'))
        ->assertSessionHas('success');

    expect(Order::query()->whereKey($first['order']->id)->exists())->toBeFalse();
    expect(Order::query()->whereKey($second['order']->id)->exists())->toBeFalse();
    expect((int) $first['variant']->fresh()->stock)->toBe(4);
    expect((int) $second['variant']->fresh()->stock)->toBe(7);
});
