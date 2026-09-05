<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

function crudGuardAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'crud-guard-'.Str::random(5).'@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

function crudCatalogSeed(): array
{
    $collection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'Bags'],
        'slug' => ['en' => 'bags-crud-'.Str::random(4), 'ar' => 'bags'],
        'position' => 1,
        'is_published' => true,
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Tote', 'ar' => 'Tote'],
        'slug' => ['en' => 'tote-crud-'.Str::random(4), 'ar' => 'tote'],
        'position' => 1,
    ]);
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'House', 'ar' => 'House'],
        'slug' => 'house-crud-'.Str::random(4),
        'description' => ['en' => '', 'ar' => ''],
    ]);
    $product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'CRUD-'.Str::upper(Str::random(5)),
        'slug' => 'crud-product-'.Str::random(6),
        'name' => ['en' => 'Studio Bag', 'ar' => 'Bag'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 1200,
        'status' => 'active',
        'featured' => false,
        'in_stock' => true,
    ]);
    $product->collections()->sync([$collection->id => ['position' => 0]]);

    return compact('collection', 'category', 'brand', 'product');
}

it('blocks deleting a brand that still has products', function () {
    $admin = crudGuardAdmin();
    $seed = crudCatalogSeed();

    $this->actingAs($admin)
        ->from(route('admin.brands.index'))
        ->delete(route('admin.brands.destroy', $seed['brand']->id))
        ->assertRedirect(route('admin.brands.index'))
        ->assertSessionHas('error');

    expect(Brand::find($seed['brand']->id))->not->toBeNull();
});

it('deletes an empty brand cleanly', function () {
    $admin = crudGuardAdmin();
    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Empty House', 'ar' => 'Empty'],
        'slug' => 'empty-house-'.Str::random(4),
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.brands.destroy', $brand->id))
        ->assertRedirect(route('admin.brands.index'))
        ->assertSessionHas('success');

    expect(Brand::find($brand->id))->toBeNull();
});

it('allows creating an independent category', function () {
    $admin = crudGuardAdmin();

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name_en' => 'Standalone Edit',
            'name_ar' => 'تعديل مستقل',
            'slug_en' => 'standalone-edit-'.Str::lower(Str::random(4)),
            'position' => 0,
            'is_featured' => '0',
        ])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('success');

    $category = Category::query()->where('name->en', 'Standalone Edit')->first();

    expect($category)->not->toBeNull()
        ->and($category->collection_id)->toBeNull();
});

it('defaults new categories to featured on the homepage', function () {
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Default Featured', 'ar' => 'افتراضي'],
        'slug' => ['en' => 'default-featured-'.Str::lower(Str::random(4)), 'ar' => 'default'],
        'position' => 0,
    ]);

    expect($category->fresh()->is_featured)->toBeTrue();
});

it('keeps featured on when the admin form omits the toggle field', function () {
    $admin = crudGuardAdmin();

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name_en' => 'Implied Homepage',
            'name_ar' => 'هوم',
            'slug_en' => 'implied-homepage-'.Str::lower(Str::random(4)),
            'position' => 0,
        ])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('success');

    expect(
        Category::query()
            ->where('name->en', 'Implied Homepage')
            ->value('is_featured')
    )->toBeTrue();
});

it('still exposes logo_url on the API when Featured is explicitly off', function () {
    $slug = 'logo-only-'.Str::lower(Str::random(4));
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Logo Only', 'ar' => 'لوجو'],
        'slug' => ['en' => $slug, 'ar' => $slug],
        'position' => 9,
        'is_featured' => false,
        'logo_url' => 'https://cdn.example/logo-only.jpg',
        'path' => 'https://cdn.example/logo-only-banner.jpg',
    ]);

    expect($category->fresh()->is_featured)->toBeFalse();

    \App\Http\Controllers\Api\CategoryController::forgetListCache();

    $response = $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonFragment([
            'slug' => $slug,
            'is_featured' => false,
        ]);

    $payload = collect($response->json('data'))->firstWhere('slug', $slug);
    expect($payload['logo_url'])
        ->toStartWith('https://cdn.example/logo-only.jpg?v=');
});

it('persists Featured off when the admin form sends the hidden zero', function () {
    $admin = crudGuardAdmin();
    $slug = 'unfeatured-'.Str::lower(Str::random(4));

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name_en' => 'Unfeatured Logo',
            'name_ar' => 'بدون فيتشر',
            'slug_en' => $slug,
            'position' => 0,
            'is_featured' => '0',
            'logo_url' => 'https://cdn.example/unfeatured.jpg',
            'logo_source' => 'url',
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect(
        Category::query()->where('slug->en', $slug)->value('is_featured')
    )->toBeFalse();
});

it('requires uncategorize confirmation before deleting a category with products', function () {
    $admin = crudGuardAdmin();
    $seed = crudCatalogSeed();

    $this->actingAs($admin)
        ->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $seed['category']->id))
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('error');

    expect(Category::find($seed['category']->id))->not->toBeNull();
    expect(Product::find($seed['product']->id)?->category_id)->toBe($seed['category']->id);
});

it('deletes a category and leaves its products uncategorized when confirmed', function () {
    $admin = crudGuardAdmin();
    $seed = crudCatalogSeed();

    $this->actingAs($admin)
        ->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $seed['category']->id), [
            'uncategorize_products' => '1',
        ])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('success');

    expect(Category::find($seed['category']->id))->toBeNull();
    expect(Product::find($seed['product']->id)?->category_id)->toBeNull();
});

it('deletes an empty category from the categories desk', function () {
    $admin = crudGuardAdmin();
    $seed = crudCatalogSeed();
    $seed['product']->delete();

    $this->actingAs($admin)
        ->from(route('admin.categories.index'))
        ->delete(route('admin.categories.destroy', $seed['category']->id))
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHas('success');

    expect(Category::find($seed['category']->id))->toBeNull();
});

it('deletes a collection while keeping its products and categories', function () {
    $admin = crudGuardAdmin();
    $seed = crudCatalogSeed();

    $this->actingAs($admin)
        ->from(route('admin.collections.index'))
        ->delete(route('admin.collections.destroy', $seed['collection']->id))
        ->assertRedirect(route('admin.collections.index'))
        ->assertSessionHas('success');

    expect(Collection::find($seed['collection']->id))->toBeNull()
        ->and(Category::find($seed['category']->id))->not->toBeNull()
        ->and(Product::find($seed['product']->id))->not->toBeNull()
        ->and($seed['product']->fresh()->collections)->toHaveCount(0);
});

it('surfaces page validation errors on the form', function () {
    $admin = crudGuardAdmin();

    $this->actingAs($admin)
        ->from(route('admin.pages.create'))
        ->post(route('admin.pages.store'), [
            'title' => '',
            'published' => '1',
        ])
        ->assertRedirect(route('admin.pages.create'))
        ->assertSessionHasErrors(['title']);
});
