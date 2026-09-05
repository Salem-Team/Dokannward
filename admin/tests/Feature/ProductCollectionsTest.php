<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Collections are merchandising groups that may mix categories, so membership
 * lives on `collection_product` instead of being inferred from the category.
 */
beforeEach(function () {
    Cache::flush();

    $this->summer = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Summer 2026', 'ar' => 'صيف'],
        'slug' => ['en' => 'summer-2026', 'ar' => 'summer-2026'],
        'position' => 1,
        'is_published' => true,
    ]);

    $this->brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Dokan Ward', 'ar' => 'Dokan Ward'],
        'slug' => 'dokan-ward-collections',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $this->bags = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
        'slug' => ['en' => 'bags-cat', 'ar' => 'bags-cat'],
        'position' => 1,
    ]);

    $this->shoes = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Shoes', 'ar' => 'أحذية'],
        'slug' => ['en' => 'shoes-cat', 'ar' => 'shoes-cat'],
        'position' => 2,
    ]);

    $this->makeProduct = function (string $sku, string $slug, Category $category): Product {
        return Product::create([
            'id' => (string) Str::uuid(),
            'sku' => $sku,
            'slug' => $slug,
            'name' => ['en' => Str::headline($slug), 'ar' => Str::headline($slug)],
            'description' => ['en' => 'Test', 'ar' => ''],
            'brand_id' => $this->brand->id,
            'category_id' => $category->id,
            'price' => 400,
            'status' => 'active',
            'in_stock' => true,
        ]);
    };
});

it('creates the pivot with a unique pair and cascading deletes', function () {
    expect(Schema::hasColumns('collection_product', ['product_id', 'collection_id', 'position', 'created_at', 'updated_at']))->toBeTrue();

    $bag = ($this->makeProduct)('ZBR-PIV1', 'pivot-bag', $this->bags);
    $bag->collections()->attach($this->summer->id, ['position' => 0]);

    // Same pair twice must not create a second row.
    DB::table('collection_product')->insertOrIgnore([
        'product_id' => $bag->id,
        'collection_id' => $this->summer->id,
        'position' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('collection_product')->count())->toBe(1);

    $bag->delete();

    expect(DB::table('collection_product')->count())->toBe(0);
});

it('backfills every product into its category collection then detaches categories', function () {
    // Legacy shape: the category still points at a collection.
    $this->bags->update(['collection_id' => $this->summer->id]);
    $legacy = ($this->makeProduct)('ZBR-BF01', 'backfill-bag', $this->bags);
    $standalone = ($this->makeProduct)('ZBR-BF02', 'backfill-shoe', $this->shoes);

    $migration = require database_path('migrations/2026_07_30_190000_create_collection_product_table.php');
    $migration->down();
    $migration->up();

    expect($legacy->fresh()->collections->pluck('id')->all())->toBe([$this->summer->id])
        // Categories without a collection leave the product unattached.
        ->and($standalone->fresh()->collections)->toHaveCount(0)
        // After backfill, every category is independent again.
        ->and($this->bags->fresh()->collection_id)->toBeNull()
        ->and($this->shoes->fresh()->collection_id)->toBeNull();
});

it('returns collection members from several categories for the collection filter', function () {
    $bag = ($this->makeProduct)('ZBR-MIX1', 'mixed-bag', $this->bags);
    $shoe = ($this->makeProduct)('ZBR-MIX2', 'mixed-shoe', $this->shoes);
    $unlisted = ($this->makeProduct)('ZBR-MIX3', 'unlisted-bag', $this->bags);

    $bag->collections()->attach($this->summer->id, ['position' => 0]);
    $shoe->collections()->attach($this->summer->id, ['position' => 1]);

    $slugs = collect($this->getJson('/api/products?collection=summer-2026')
        ->assertOk()
        ->json('data'))
        ->pluck('slug')
        ->sort()
        ->values()
        ->all();

    expect($slugs)->toBe(['mixed-bag', 'mixed-shoe'])
        ->and($slugs)->not->toContain($unlisted->slug);

    // UUIDs work the same way.
    $this->getJson('/api/products?collection='.$this->summer->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns nothing for an unknown or unpublished collection filter', function () {
    $bag = ($this->makeProduct)('ZBR-UNP1', 'hidden-bag', $this->bags);
    $bag->collections()->attach($this->summer->id, ['position' => 0]);

    $this->getJson('/api/products?collection=does-not-exist')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->summer->update(['is_published' => false]);
    Cache::flush();

    $this->getJson('/api/products?collection=summer-2026')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('exposes a product collections on list and detail payloads', function () {
    $bag = ($this->makeProduct)('ZBR-RES1', 'resource-bag', $this->bags);
    $bag->collections()->attach($this->summer->id, ['position' => 0]);

    $this->getJson('/api/products/resource-bag')
        ->assertOk()
        ->assertJsonPath('data.collections.0.id', $this->summer->id)
        ->assertJsonPath('data.collections.0.name', 'Summer 2026')
        ->assertJsonPath('data.collections.0.slug', 'summer-2026')
        // Category stays an independent classification.
        ->assertJsonPath('data.category.slug', 'bags-cat');

    $this->getJson('/api/products?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.collections.0.slug', 'summer-2026');
});

it('keeps products without any collection public', function () {
    ($this->makeProduct)('ZBR-FREE1', 'no-collection-bag', $this->bags);

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/products/no-collection-bag')->assertOk();
});

it('hides a product whose only collection is unpublished', function () {
    $bag = ($this->makeProduct)('ZBR-HID1', 'hidden-only-bag', $this->bags);
    $bag->collections()->attach($this->summer->id, ['position' => 0]);

    $this->summer->update(['is_published' => false]);
    Cache::flush();

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/products/hidden-only-bag')->assertNotFound();
});

it('keeps a product visible when at least one of its collections is published', function () {
    $draft = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Preview Drop', 'ar' => 'معاينة'],
        'slug' => ['en' => 'preview-drop', 'ar' => 'preview-drop'],
        'position' => 2,
        'is_published' => false,
    ]);

    $bag = ($this->makeProduct)('ZBR-BOTH1', 'both-collections-bag', $this->bags);
    $bag->collections()->attach([
        $this->summer->id => ['position' => 0],
        $draft->id => ['position' => 1],
    ]);

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // The hidden collection never leaks into the payload.
    $this->getJson('/api/products/both-collections-bag')
        ->assertOk()
        ->assertJsonCount(1, 'data.collections')
        ->assertJsonPath('data.collections.0.slug', 'summer-2026');
});

it('counts direct members on the public collections endpoint', function () {
    $bag = ($this->makeProduct)('ZBR-CNT1', 'count-bag', $this->bags);
    $shoe = ($this->makeProduct)('ZBR-CNT2', 'count-shoe', $this->shoes);
    $bag->collections()->attach($this->summer->id, ['position' => 0]);
    $shoe->collections()->attach($this->summer->id, ['position' => 1]);

    $this->getJson('/api/collections')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'summer-2026')
        ->assertJsonPath('data.0.products_count', 2);

    $children = collect($this->getJson('/api/collections/summer-2026')->assertOk()->json('data.categories'))
        ->pluck('slug')
        ->sort()
        ->values()
        ->all();

    expect($children)->toBe(['bags-cat', 'shoes-cat']);
});

it('derives category-tree collection children from member products across categories', function () {
    $bag = ($this->makeProduct)('ZBR-TREE1', 'tree-bag', $this->bags);
    $shoe = ($this->makeProduct)('ZBR-TREE2', 'tree-shoe', $this->shoes);
    $bag->collections()->attach($this->summer->id, ['position' => 0]);
    $shoe->collections()->attach($this->summer->id, ['position' => 1]);

    $roots = collect($this->getJson('/api/categories')->assertOk()->json('data'));
    $collectionRoot = $roots->firstWhere('slug', 'summer-2026');
    $childSlugs = collect($collectionRoot['children'] ?? [])->pluck('slug')->sort()->values()->all();

    expect($childSlugs)->toBe(['bags-cat', 'shoes-cat'])
        ->and($roots->firstWhere('slug', 'bags-cat'))->not->toBeNull()
        ->and($roots->firstWhere('slug', 'shoes-cat'))->not->toBeNull();
});

it('never hides a category show based on a legacy collection link', function () {
    $this->bags->update(['collection_id' => $this->summer->id]);
    $this->summer->update(['is_published' => false]);
    \App\Http\Controllers\Api\CategoryController::forgetListCache();

    $this->getJson('/api/categories/bags-cat')
        ->assertOk()
        ->assertJsonPath('data.slug', 'bags-cat');
});
