<?php

use App\Models\Address;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

function productDeleteAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'product-del-'.Str::random(5).'@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

function productDeleteSeed(): array
{
    $collection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'Bags'],
        'slug' => ['en' => 'bags-del-'.Str::random(4), 'ar' => 'bags'],
        'position' => 1,
        'is_published' => true,
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Tote', 'ar' => 'Tote'],
        'slug' => ['en' => 'tote-del-'.Str::random(4), 'ar' => 'tote'],
        'collection_id' => $collection->id,
        'position' => 1,
    ]);
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'House', 'ar' => 'House'],
        'slug' => 'house-del-'.Str::random(4),
        'description' => ['en' => '', 'ar' => ''],
    ]);
    $product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'DEL-'.Str::upper(Str::random(5)),
        'slug' => 'del-product-'.Str::random(6),
        'name' => ['en' => 'Delete Me', 'ar' => 'Delete'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 1500,
        'status' => 'active',
        'featured' => false,
        'in_stock' => true,
    ]);
    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $product->id,
        'sku' => 'DELV-'.Str::upper(Str::random(5)),
        'price' => 1500,
        'stock' => 3,
        'is_active' => true,
    ]);

    return compact('product', 'variant');
}

it('deletes a product even when cart and order history reference its variant', function () {
    $admin = productDeleteAdmin();
    $seed = productDeleteSeed();

    $cart = Cart::create([
        'id' => (string) Str::uuid(),
        'session_token' => 'sess-'.Str::random(8),
    ]);
    CartItem::create([
        'id' => (string) Str::uuid(),
        'cart_id' => $cart->id,
        'variant_id' => $seed['variant']->id,
        'quantity' => 1,
        'price' => 1500,
    ]);

    $address = Address::create([
        'id' => (string) Str::uuid(),
        'recipient_name' => 'Buyer',
        'phone' => '01001234567',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'postal_code' => '11511',
        'country' => 'EG',
    ]);
    $order = Order::create([
        'id' => (string) Str::uuid(),
        'order_number' => 'Z-'.Str::upper(Str::random(6)),
        'status' => 'paid',
        'customer_email' => 'buyer@example.com',
        'subtotal' => 1500,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1500,
        'shipping_address_id' => $address->id,
        'placed_at' => now(),
    ]);
    OrderItem::create([
        'id' => (string) Str::uuid(),
        'order_id' => $order->id,
        'product_id' => $seed['product']->id,
        'variant_id' => $seed['variant']->id,
        'sku' => $seed['variant']->sku,
        'name' => 'Delete Me',
        'unit_price' => 1500,
        'qty' => 1,
        'line_total' => 1500,
        'tax' => 0,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.products.index'))
        ->delete(route('admin.products.destroy', $seed['product']->id))
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success')
        ->assertSessionMissing('error');

    expect(Product::find($seed['product']->id))->toBeNull();
    expect(ProductVariant::find($seed['variant']->id))->toBeNull();
    expect(CartItem::query()->where('cart_id', $cart->id)->count())->toBe(0);

    $history = OrderItem::find(
        OrderItem::query()->where('order_id', $order->id)->value('id')
    );
    expect($history)->not->toBeNull();
    expect($history->product_id)->toBeNull();
    expect($history->variant_id)->toBeNull();
    expect($history->name)->toBe('Delete Me');
});
