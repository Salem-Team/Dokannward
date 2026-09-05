<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

function categoryDeskAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'category-desk-'.Str::random(5).'@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

it('renders the category desk with inline product gallery markup', function () {
    $admin = categoryDeskAdmin();

    $collection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'Bags'],
        'slug' => ['en' => 'bags-desk-'.Str::random(4), 'ar' => 'bags'],
        'position' => 1,
        'is_published' => true,
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Tote', 'ar' => 'Tote'],
        'slug' => ['en' => 'tote-desk-'.Str::random(4), 'ar' => 'tote'],
        'collection_id' => $collection->id,
        'position' => 1,
    ]);
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'House', 'ar' => 'House'],
        'slug' => 'house-desk-'.Str::random(4),
        'description' => ['en' => '', 'ar' => ''],
    ]);
    Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'CAT-'.Str::upper(Str::random(5)),
        'slug' => 'category-desk-product-'.Str::random(6),
        'name' => ['en' => 'Studio Tote', 'ar' => 'Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 4200,
        'status' => 'active',
        'featured' => true,
        'in_stock' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.categories.index', ['open' => $category->id]))
        ->assertOk()
        ->assertSee('category-desk', false)
        ->assertSee('category-desk__card', false)
        ->assertSee('category-desk__cover', false)
        ->assertSee('View pieces')
        ->assertSee('Product gallery')
        ->assertSee('Studio Tote')
        ->assertSee('Tote')
        ->assertSee('Products classified')
        ->assertSee('Open studio');
});
