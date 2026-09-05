<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('requires authentication for desk search', function () {
    getJson(route('admin.desk-search', ['q' => 'test']))
        ->assertUnauthorized();
});

it('returns grouped desk search results', function () {
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Desk', 'ar' => 'Desk'],
        'slug' => ['en' => 'desk-cat', 'ar' => 'desk-cat'],
    ]);
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Desk Brand', 'ar' => 'Desk'],
        'slug' => 'desk-brand',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'DESK-SEARCH-SKU',
        'slug' => 'desk-search-product',
        'name' => ['en' => 'Desk Search Product', 'ar' => 'منتج'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 100,
        'status' => 'active',
        'featured' => false,
    ]);

    actingAs($this->admin)
        ->getJson(route('admin.desk-search', ['q' => 'DESK-SEARCH']))
        ->assertOk()
        ->assertJsonPath('products.0.id', $product->id)
        ->assertJsonPath('products.0.label', 'Desk Search Product');
});

it('returns empty groups for short queries', function () {
    actingAs($this->admin)
        ->getJson(route('admin.desk-search', ['q' => 'a']))
        ->assertOk()
        ->assertJson([
            'products' => [],
            'orders' => [],
            'customers' => [],
        ]);
});
