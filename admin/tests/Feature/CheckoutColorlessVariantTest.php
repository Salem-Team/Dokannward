<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Dokan Ward', 'ar' => 'Dokan Ward'],
        'slug' => 'dokan-ward',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $this->category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
        'slug' => ['en' => 'bags', 'ar' => 'bags'],
        'position' => 1,
    ]);

    $this->product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'LEGACY-01',
        'slug' => 'legacy-tote',
        'name' => ['en' => 'Legacy Tote', 'ar' => 'Legacy Tote'],
        'description' => ['en' => 'No color swatches', 'ar' => ''],
        'brand_id' => $this->brand->id,
        'category_id' => $this->category->id,
        'price' => 8050,
        'status' => 'active',
        'in_stock' => true,
    ]);
});

function colorlessCheckoutPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'recipient_name' => 'Sara Buyer',
        'phone' => '01012345678',
        'email' => 'sara@example.com',
        'line_1' => '10 Test St',
        'city' => 'Cairo',
        'country' => 'Egypt',
        'payment_method' => 'cod',
        'items' => [[
            'product_id' => $product->id,
            'name' => 'Legacy Tote',
            'price' => 8050,
            'qty' => 1,
            'sku' => $product->sku,
        ]],
    ], $overrides);
}

it('checks out products with a legacy colorless variant without variant_id', function () {
    $variantId = (string) Str::uuid();

    ProductVariant::create([
        'id' => $variantId,
        'product_id' => $this->product->id,
        'color_id' => null,
        'sku' => 'LEGACY-01-V',
        'price' => 8050,
        'stock' => 5,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->postJson('/api/checkout', colorlessCheckoutPayload($this->product))
        ->assertCreated();

    expect(ProductVariant::find($variantId)->stock)->toBe(4);

    $this->assertDatabaseHas('order_items', [
        'variant_id' => $variantId,
        'product_id' => $this->product->id,
        'qty' => 1,
    ]);
});

it('still requires a color when the product has color variants', function () {
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
        'sku' => 'LEGACY-01-BLACK',
        'price' => 8050,
        'stock' => 3,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->postJson('/api/checkout', colorlessCheckoutPayload($this->product))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.variant_id']);
});
