<?php

use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
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
        'position' => 1,
        'is_featured' => true,
    ]);

    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Dokan Ward', 'ar' => 'Dokan Ward'],
        'slug' => 'dokan-ward-pin',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $this->product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ZT-PIN-01',
        'slug' => 'pin-tote',
        'name' => ['en' => 'Pin Tote', 'ar' => 'Pin Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 250,
        'status' => 'active',
        'in_stock' => true,
        'featured' => false,
    ]);

    $this->product->collections()->sync([$collection->id => ['position' => 0]]);
});

function checkoutPinPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'recipient_name' => 'Nadia Buyer',
        'phone' => '01034212422',
        'email' => 'nadia.pin@example.com',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'country' => 'Egypt',
        'latitude' => 30.0626321,
        'longitude' => 31.2197123,
        'place_name' => 'Zamalek, Cairo, Egypt',
        'location_source' => 'gps',
        'items' => [[
            'product_id' => $product->id,
            'name' => 'Pin Tote',
            'price' => 250,
            'qty' => 1,
            'sku' => $product->sku,
        ]],
    ], $overrides);
}

it('allows a typed address without a map pin', function () {
    $this->postJson('/api/checkout', checkoutPinPayload($this->product, [
        'latitude' => null,
        'longitude' => null,
        'place_name' => null,
        'location_source' => 'typed',
    ]))->assertCreated();

    $address = Address::query()->first();
    expect($address)->not->toBeNull()
        ->and($address->latitude)->toBeNull()
        ->and($address->longitude)->toBeNull()
        ->and($address->line_1)->toBe('12 Nile St')
        ->and($address->location_source)->toBe('typed');
});

it('rejects a pin that has only one coordinate', function () {
    $this->postJson('/api/checkout', checkoutPinPayload($this->product, [
        'longitude' => null,
    ]))->assertStatus(422)
        ->assertJsonValidationErrors(['longitude']);
});

it('stores the pin on the address and attaches a guest customer', function () {
    $this->postJson('/api/checkout', checkoutPinPayload($this->product))
        ->assertCreated();

    $order = Order::query()->with(['shippingAddress', 'user'])->first();
    expect($order)->not->toBeNull()
        ->and($order->user)->not->toBeNull()
        ->and($order->user->is_guest)->toBeTrue()
        ->and($order->user->email)->toBe('nadia.pin@example.com')
        ->and($order->user->phone)->toBe('01034212422');

    $address = $order->shippingAddress;
    expect($address)->not->toBeNull()
        ->and($address->user_id)->toBe($order->user_id)
        ->and((float) $address->latitude)->toBe(30.0626321)
        ->and((float) $address->longitude)->toBe(31.2197123)
        ->and($address->place_name)->toBe('Zamalek, Cairo, Egypt')
        ->and($address->location_source)->toBe('gps')
        ->and($address->hasCoordinates())->toBeTrue()
        ->and($address->googleMapsUrl())->toContain('google.com/maps?q=');
});

it('reuses the same customer when the same phone checks out again', function () {
    $this->postJson('/api/checkout', checkoutPinPayload($this->product))->assertCreated();

    $this->product->update(['in_stock' => true]);

    $this->postJson('/api/checkout', checkoutPinPayload($this->product, [
        'email' => null,
        'recipient_name' => 'Nadia Again',
        'latitude' => 29.96,
        'longitude' => 31.25,
        'location_source' => 'map',
    ]))->assertCreated();

    expect(User::query()->where('is_admin', false)->count())->toBe(1);
    expect(Address::query()->count())->toBe(2);
    expect(Address::query()->where('user_id', User::query()->value('id'))->count())->toBe(2);
});
