<?php

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Hermes', 'ar' => 'Hermes'],
        'slug' => 'hermes',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $this->category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
        'slug' => ['en' => 'bags', 'ar' => 'bags'],
        'parent_id' => null,
        'position' => 1,
    ]);

    $this->product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'PERF-001',
        'slug' => 'perf-birkin',
        'name' => ['en' => 'Perf Birkin', 'ar' => 'بيركن'],
        'description' => ['en' => 'A long description that must not ship on list payloads.', 'ar' => ''],
        'brand_id' => $this->brand->id,
        'category_id' => $this->category->id,
        'price' => 1500,
        'status' => 'active',
        'featured' => true,
        'in_stock' => true,
        'metadata' => [
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description',
            'meta_keywords' => 'seo,keywords',
        ],
    ]);
});

it('keeps product list payloads slim for storefront cards', function () {
    $response = $this->getJson('/api/products?per_page=10');

    $response->assertOk()
        ->assertHeader('Cache-Control')
        ->assertJsonMissingPath('data.0.seo')
        ->assertJsonMissingPath('data.0.description')
        ->assertJsonMissingPath('data.0.short_description')
        ->assertJsonPath('data.0.slug', 'perf-birkin')
        ->assertJsonStructure([
            'data' => [[
                'id',
                'sku',
                'slug',
                'name',
                'price',
                'image',
                'images',
                'colors',
                'rating' => ['average', 'count'],
            ]],
        ]);

    $images = $response->json('data.0.images');
    expect($images)->toBeArray()->and(count($images))->toBeLessThanOrEqual(1);
});

it('includes full detail fields only on product show', function () {
    $response = $this->getJson('/api/products/perf-birkin');

    $response->assertOk()
        ->assertHeader('Cache-Control')
        ->assertJsonPath('data.slug', 'perf-birkin')
        ->assertJsonPath('data.description', 'A long description that must not ship on list payloads.')
        ->assertJsonPath('data.seo.title', 'SEO Title')
        ->assertJsonStructure([
            'data' => [
                'seo' => ['title', 'description', 'keywords'],
                'description',
                'short_description',
            ],
        ]);
});

it('serves product list from cache on the second hit', function () {
    $this->getJson('/api/products?per_page=10')->assertOk();

    // Bypass model observers so generation keys stay warm.
    \Illuminate\Support\Facades\DB::table('products')->where('id', $this->product->id)->delete();

    $this->getJson('/api/products?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'perf-birkin');
});

it('serves product show from cache on the second hit', function () {
    $this->getJson('/api/products/perf-birkin')
        ->assertOk()
        ->assertJsonPath('data.name', 'Perf Birkin');

    \Illuminate\Support\Facades\DB::table('products')
        ->where('id', $this->product->id)
        ->update(['name' => json_encode(['en' => 'Changed Name', 'ar' => 'Changed'])]);

    // Without bumping generation, show cache still returns the first snapshot.
    $this->getJson('/api/products/perf-birkin')
        ->assertOk()
        ->assertJsonPath('data.name', 'Perf Birkin');
});

it('invalidates product caches when the catalog generation bumps', function () {
    $this->getJson('/api/products?per_page=10')->assertOk();
    $this->getJson('/api/products/perf-birkin')->assertOk();

    \App\Http\Controllers\Api\ProductController::forgetListCache();
    \Illuminate\Support\Facades\DB::table('products')->where('id', $this->product->id)->delete();

    $this->getJson('/api/products?per_page=10')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/products/perf-birkin')->assertNotFound();
});

it('caches live banners and returns cache headers', function () {
    Banner::create([
        'id' => (string) Str::uuid(),
        'title' => 'Speed Banner',
        'subtitle' => null,
        'button_text' => 'Shop',
        'button_url' => '/collections/all',
        'image_url' => '/images/og-share.jpg',
        'is_active' => true,
        'position' => 1,
    ]);

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertHeader('Cache-Control')
        ->assertJsonPath('data.0.title', 'Speed Banner');

    \Illuminate\Support\Facades\DB::table('banners')->delete();

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Speed Banner');
});

it('caches settings endpoints used by every storefront layout', function () {
    foreach (['/api/settings/currency', '/api/settings/store', '/api/settings/checkout', '/api/settings/inventory'] as $path) {
        $this->getJson($path)
            ->assertOk()
            ->assertHeader('Cache-Control');
    }
});

it('exposes rating aggregates on list without loading review bodies', function () {
    ProductReview::create([
        'id' => (string) Str::uuid(),
        'product_id' => $this->product->id,
        'reviewer_name' => 'Speed Shopper',
        'rating' => 5,
        'title' => 'Great',
        'body' => 'Should never appear on the list payload.',
        'approved' => true,
    ]);

    $response = $this->getJson('/api/products?per_page=10');

    $response->assertOk()
        ->assertJsonPath('data.0.rating.average', 5)
        ->assertJsonPath('data.0.rating.count', 1)
        ->assertJsonMissingPath('data.0.reviews');
});
