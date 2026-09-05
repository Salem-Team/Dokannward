<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Photo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('repairs junk catalog covers so product and variant images match', function () {
    Storage::fake('public');
    foreach ([
        'products/catalog/handbags-and-accessories-products-1.jpg',
        'products/catalog/handbags-and-accessories-products-2.jpg',
        'products/catalog/handbags-and-accessories-products-3.jpg',
        'products/catalog/handbags-and-accessories-products-4.jpg',
    ] as $plate) {
        Storage::disk('public')->put($plate, 'fake-image');
    }

    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Repair House', 'ar' => 'Repair'],
        'slug' => 'repair-house-'.Str::random(4),
        'description' => ['en' => '', 'ar' => ''],
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Repair Tote Cat', 'ar' => 'Tote'],
        'slug' => ['en' => 'repair-tote-cat-'.Str::random(4), 'ar' => 'tote'],
        'position' => 0,
    ]);
    $product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'RPR-'.Str::upper(Str::random(5)),
        'slug' => 'repair-tote-'.Str::random(6),
        'name' => ['en' => 'Repair Tote', 'ar' => 'Repair Tote'],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 1200,
        'status' => 'active',
        'visibility' => true,
        'featured' => false,
        'in_stock' => true,
    ]);

    $junk = Photo::create([
        'id' => (string) Str::uuid(),
        'imageable_type' => Product::class,
        'imageable_id' => $product->id,
        'storage_path' => 'products/catalog/IMG-0552.jpg',
        'file_name' => 'IMG-0552.jpg',
        'mime_type' => 'image/jpeg',
        'is_primary' => true,
        'position' => 0,
    ]);

    $color = Color::firstOrCreate(
        ['name' => 'Repair Black'],
        ['id' => (string) Str::uuid(), 'hex' => '#111111', 'is_active' => true]
    );
    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $product->id,
        'sku' => 'RPR-VAR-'.Str::upper(Str::random(4)),
        'color_id' => $color->id,
        'price' => 1200,
        'stock' => 5,
        'is_active' => true,
    ]);
    $variant->photos()->sync([$junk->id => ['position' => 0]]);

    Artisan::call('dokannward:repair-product-photos');

    $product->refresh()->load(['photos', 'variants.photos']);
    $primary = $product->photos->firstWhere('is_primary', true);

    expect($product->photos)->not->toBeEmpty();
    expect($product->photos->contains(fn ($p) => str_contains((string) $p->storage_path, 'IMG-055')))->toBeFalse();
    expect($primary?->storage_path)->toContain('handbags-and-accessories-products-');
    expect($variant->fresh()->photos->first()?->id)->toBe($primary?->id);
});
