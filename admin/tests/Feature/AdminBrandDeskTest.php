<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

function brandDeskAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'brand-desk-'.Str::random(5).'@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

it('renders the brand desk with inline product gallery markup', function () {
    $admin = brandDeskAdmin();

    $collection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'Bags'],
        'slug' => ['en' => 'bags-brand-desk-'.Str::random(4), 'ar' => 'bags'],
        'position' => 1,
        'is_published' => true,
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Tote', 'ar' => 'Tote'],
        'slug' => ['en' => 'tote-brand-desk-'.Str::random(4), 'ar' => 'tote'],
        'collection_id' => $collection->id,
        'position' => 1,
    ]);
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Polène', 'ar' => 'Polène'],
        'slug' => 'polene-desk-'.Str::random(4),
        'description' => ['en' => 'Parisian house', 'ar' => ''],
        'country' => 'France',
    ]);
    Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'BRD-'.Str::upper(Str::random(5)),
        'slug' => 'brand-desk-product-'.Str::random(6),
        'name' => ['en' => 'Cyme Mini', 'ar' => 'Cyme'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 8900,
        'status' => 'active',
        'featured' => true,
        'in_stock' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.brands.index', ['open' => $brand->id]))
        ->assertOk()
        ->assertSee('brand-desk', false)
        ->assertSee('The house · Catalog')
        ->assertSee('View products')
        ->assertSee('Brand gallery')
        ->assertSee('Polène')
        ->assertSee('Cyme Mini')
        ->assertSee('Products nested');
});
