<?php

use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

function returnsAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'returns-admin-'.Str::random(5).'@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

function seedReturnableOrder(int $stock = 2, int $qty = 2, float $unit = 400, float $shipping = 33.50): array
{
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'House', 'ar' => 'House'],
        'slug' => 'house-returns-'.Str::random(4),
        'description' => ['en' => '', 'ar' => ''],
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'Bags'],
        'slug' => ['en' => 'bags-returns-'.Str::random(4), 'ar' => 'bags'],
        'position' => 1,
    ]);
    $product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'RET-'.Str::upper(Str::random(5)),
        'slug' => 'return-product-'.Str::random(6),
        'name' => ['en' => 'Return Tote', 'ar' => 'Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => $unit,
        'status' => 'active',
        'in_stock' => true,
    ]);
    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $product->id,
        'sku' => $product->sku.'-A',
        'price' => $unit,
        'stock' => $stock,
        'is_active' => true,
    ]);
    $address = Address::create([
        'id' => (string) Str::uuid(),
        'recipient_name' => 'Return Client',
        'phone' => '01009876543',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'postal_code' => '11511',
        'country' => 'EG',
    ]);

    $subtotal = $unit * $qty;
    $order = Order::create([
        'user_id' => null,
        'customer_email' => 'return-client@example.com',
        'status' => 'delivered',
        'total_amount' => $subtotal + $shipping,
        'subtotal' => $subtotal,
        'shipping_amount' => $shipping,
        'tax_amount' => 0,
        'shipping_address_id' => $address->id,
        'notes' => null,
        'placed_at' => now(),
    ]);
    $item = OrderItem::create([
        'id' => (string) Str::uuid(),
        'order_id' => $order->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'sku' => $variant->sku,
        'name' => 'Return Tote',
        'unit_price' => $unit,
        'qty' => $qty,
        'line_total' => $subtotal,
        'tax' => 0,
    ]);

    return compact('order', 'variant', 'product', 'address', 'item');
}

it('renders the return desk with refundable totals and alpine returnForm', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder();

    $this->actingAs($admin)
        ->get(route('admin.orders.returns.create', $seed['order']->id))
        ->assertOk()
        ->assertSee('Credit note for this order')
        ->assertSee('Full remaining')
        ->assertSee('Match items')
        ->assertSee('Still refundable')
        ->assertSee('returnForm(')
        ->assertSee((string) $seed['order']->order_number);
});

it('issues a full remaining return with restock and records credit note lines', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder(stock: 1, qty: 2, unit: 2750, shipping: 33.50);
    $order = $seed['order'];
    $remaining = (float) $order->total_amount;

    $this->actingAs($admin)
        ->post(route('admin.orders.returns.store', $order->id), [
            'return_all' => '1',
            'refund_amount' => $remaining,
            'reason' => 'changed_mind',
            'notes' => 'Customer returned both pieces',
            'restock' => '1',
        ])
        ->assertRedirect(route('admin.orders.show', $order->id));

    $return = OrderReturn::query()->where('order_id', $order->id)->first();
    expect($return)->not->toBeNull();
    expect((float) $return->refund_amount)->toBe($remaining);
    expect($return->restock)->toBeTrue();
    expect($return->items)->toHaveCount(1);
    expect((int) $return->items->first()->qty)->toBe(2);
    expect((int) $seed['variant']->fresh()->stock)->toBe(3);
    expect($order->fresh()->refundableRemaining())->toBe(0.0);
    expect($order->fresh()->status)->toBe('refunded');
});

it('issues a partial item return matching selected qty and amount', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder(stock: 4, qty: 2, unit: 400, shipping: 0);

    $this->actingAs($admin)
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'refund_amount' => 400,
            'reason' => 'damaged',
            'restock' => '1',
            'items' => [
                ['order_item_id' => $seed['item']->id, 'qty' => 1],
            ],
        ])
        ->assertRedirect(route('admin.orders.show', $seed['order']->id));

    $return = OrderReturn::query()->where('order_id', $seed['order']->id)->first();
    expect((float) $return->refund_amount)->toBe(400.0);
    expect((float) $return->suggested_amount)->toBe(400.0);
    expect((int) $return->items->first()->qty)->toBe(1);
    expect((int) $seed['variant']->fresh()->stock)->toBe(5);
    expect($seed['order']->fresh()->refundableRemaining())->toBe(400.0);
    expect($seed['order']->fresh()->status)->toBe('refunded');
});

it('marks the order status as refunded when a return is issued', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder(stock: 2, qty: 1, unit: 500, shipping: 0);

    $this->actingAs($admin)
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'return_all' => '1',
            'refund_amount' => 500,
            'restock' => '1',
            'reason' => 'changed_mind',
        ])
        ->assertRedirect(route('admin.orders.show', $seed['order']->id));

    expect($seed['order']->fresh()->status)->toBe('refunded');
});

it('marks the order refunded even on a partial credit note', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder(stock: 4, qty: 2, unit: 400, shipping: 0);

    $this->actingAs($admin)
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'refund_amount' => 400,
            'reason' => 'damaged',
            'restock' => '1',
            'items' => [
                ['order_item_id' => $seed['item']->id, 'qty' => 1],
            ],
        ])
        ->assertRedirect(route('admin.orders.show', $seed['order']->id));

    expect($seed['order']->fresh()->status)->toBe('refunded')
        ->and($seed['order']->fresh()->refundableRemaining())->toBe(400.0);
});

it('rejects refunds above the remaining balance', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder();

    $this->actingAs($admin)
        ->from(route('admin.orders.returns.create', $seed['order']->id))
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'return_all' => '1',
            'refund_amount' => 999999,
            'restock' => '1',
        ])
        ->assertRedirect(route('admin.orders.returns.create', $seed['order']->id))
        ->assertSessionHasErrors('refund_amount');
});

it('requires selected items when return_all is off', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder();

    $this->actingAs($admin)
        ->from(route('admin.orders.returns.create', $seed['order']->id))
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'refund_amount' => 100,
            'restock' => '0',
        ])
        ->assertRedirect(route('admin.orders.returns.create', $seed['order']->id))
        ->assertSessionHasErrors('items');
});

it('reverses restock when a credit note is deleted', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder(stock: 1, qty: 1, unit: 500, shipping: 0);

    $this->actingAs($admin)
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'return_all' => '1',
            'refund_amount' => 500,
            'restock' => '1',
            'reason' => 'wrong_item',
        ])
        ->assertRedirect();

    expect((int) $seed['variant']->fresh()->stock)->toBe(2);

    $return = OrderReturn::query()->where('order_id', $seed['order']->id)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('admin.orders.returns.destroy', [$seed['order']->id, $return->id]))
        ->assertRedirect(route('admin.orders.show', $seed['order']->id));

    expect(OrderReturn::query()->whereKey($return->id)->exists())->toBeFalse();
    expect(OrderReturnItem::query()->where('order_return_id', $return->id)->exists())->toBeFalse();
    expect((int) $seed['variant']->fresh()->stock)->toBe(1);
});

it('blocks a second full return after the balance is exhausted', function () {
    $admin = returnsAdmin();
    $seed = seedReturnableOrder(stock: 3, qty: 1, unit: 200, shipping: 0);

    $this->actingAs($admin)
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'return_all' => '1',
            'refund_amount' => 200,
            'restock' => '0',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->from(route('admin.orders.returns.create', $seed['order']->id))
        ->post(route('admin.orders.returns.store', $seed['order']->id), [
            'return_all' => '1',
            'refund_amount' => 200,
            'restock' => '0',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('refund_amount');
});

it('syncs legacy orders with returns to refunded status via artisan', function () {
    $seed = seedReturnableOrder(stock: 2, qty: 1, unit: 300, shipping: 0);
    $order = $seed['order'];

    \App\Models\OrderReturn::create([
        'order_id' => $order->id,
        'status' => 'refunded',
        'refund_amount' => 100,
        'suggested_amount' => 100,
        'restock' => false,
        'refunded_at' => now(),
    ]);

    expect($order->fresh()->status)->toBe('delivered');

    $this->artisan('orders:sync-refunded-status')
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe('refunded');
});
