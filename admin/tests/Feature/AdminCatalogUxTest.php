<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Color;
use App\Models\Photo;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function catalogAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'catalog-admin@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

function seedCatalogTree(): array
{
    $collection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
        'slug' => ['en' => 'bags', 'ar' => 'bags'],
        'description' => 'Authenticated bags.',
        'position' => 1,
        'is_published' => true,
        'image_url' => 'https://cdn.example.com/bags.jpg',
    ]);

    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Tote', 'ar' => 'Tote'],
        'slug' => ['en' => 'tote', 'ar' => 'tote'],
        'parent_id' => null,
        'position' => 1,
    ]);

    $otherCollection = Collection::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Accessories', 'ar' => 'إكسسوارات'],
        'slug' => ['en' => 'accessories', 'ar' => 'accessories'],
        'position' => 2,
        'is_published' => true,
    ]);

    $otherCategory = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Belts', 'ar' => 'أحزمة'],
        'slug' => ['en' => 'belts', 'ar' => 'belts'],
        'position' => 1,
    ]);

    $brand = Brand::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'Dokan Ward Test', 'ar' => 'Dokan Ward'],
        'slug' => 'dokan-ward-test-catalog',
        'description' => ['en' => '', 'ar' => ''],
    ]);

    $product = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ZBR-CAT01',
        'slug' => 'catalog-test-tote',
        'name' => ['en' => 'Catalog Tote', 'ar' => 'Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => 300,
        'status' => 'active',
        'featured' => false,
    ]);

    // Membership is explicit now — mirrors what the backfill migration does.
    $product->collections()->sync([$collection->id => ['position' => 0]]);

    return compact('collection', 'category', 'otherCollection', 'otherCategory', 'brand', 'product');
}

it('shows a roadmap on every collection in the admin index', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->get(route('admin.collections.index'))
        ->assertOk()
        ->assertSee('collection-desk', false)
        ->assertSee('crm crm--compact', false)
        ->assertSee('Roadmap')
        ->assertSee('Products')
        ->assertSee('Live on site')
        ->assertSee($tree['collection']->translated_name);
});

it('shows the full collection roadmap with member products on edit', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->get(route('admin.collections.edit', $tree['collection']->id))
        ->assertOk()
        ->assertSee('crm crm--panel crm--full', false)
        ->assertSee('Merchandising roadmap', false)
        ->assertSee('Catalog Tote')
        ->assertSee('Tote') // product's category column
        ->assertSee('Products in this collection')
        ->assertSee('Add product')
        ->assertDontSee('Add another category')
        ->assertDontSee('Add category')
        ->assertSee('image-progress', false)
        ->assertSee('Drop image here, or browse');
});

it('shows the setup roadmap on collection create', function () {
    $admin = catalogAdmin();

    $this->actingAs($admin)
        ->get(route('admin.collections.create'))
        ->assertOk()
        ->assertSee('crm crm--panel crm--setup', false)
        ->assertSee('Build in two clear stages')
        ->assertSee('image-progress', false);
});

it('counts direct collection members regardless of their category', function () {
    $tree = seedCatalogTree();

    // A belt (different category) merchandised in the Bags collection.
    $belt = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ZBR-CAT02',
        'slug' => 'catalog-test-belt',
        'name' => ['en' => 'Catalog Belt', 'ar' => 'Belt'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $tree['brand']->id,
        'category_id' => $tree['otherCategory']->id,
        'price' => 120,
        'status' => 'active',
    ]);
    $belt->collections()->attach($tree['collection']->id, ['position' => 1]);

    $collection = Collection::query()
        ->withCount('products')
        ->findOrFail($tree['collection']->id);

    expect($collection->products_count)->toBe(2)
        ->and($collection->categoriesFromMembers()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$tree['category']->id, $tree['otherCategory']->id])->sort()->values()->all());
});

it('renders product create with independent category and collection pickers', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $response = $this->actingAs($admin)
        ->get(route('admin.products.create'))
        ->assertOk();

    $response->assertSee('name="collection_ids[]"', false)
        ->assertSee('id="category_id"', false)
        ->assertSee('product-images-dropzone', false)
        ->assertSee('accept="image/*', false)
        ->assertSee('data-product-save-form', false)
        ->assertSee($tree['collection']->id, false)
        ->assertSee($tree['otherCollection']->id, false)
        ->assertSee($tree['category']->id, false)
        ->assertSee('Select a category')
        ->assertSee('Collections');
});

it('accepts common image formats beyond jpeg and png for product photos', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Format Mix Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 150,
            'stock_quantity' => 3,
            'is_active' => 1,
            'images' => [
                UploadedFile::fake()->image('cover.webp'),
                UploadedFile::fake()->create('extra.bmp', 40, 'image/bmp'),
                UploadedFile::fake()->create('mark.svg', 20, 'image/svg+xml'),
            ],
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'format-mix-bag')->firstOrFail();

    expect($product->photos()->count())->toBe(3);
});

it('accepts heic and camera raw extensions on the product gallery', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Raw Mix Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 150,
            'stock_quantity' => 3,
            'is_active' => 1,
            'images' => [
                UploadedFile::fake()->create('iphone.heic', 80, 'image/heic'),
                UploadedFile::fake()->create('canon.cr3', 120, 'application/octet-stream'),
                UploadedFile::fake()->create('photo.jxl', 90, 'image/jxl'),
            ],
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'raw-mix-bag')->firstOrFail();

    expect($product->photos()->count())->toBe(3);
});

it('accepts WhatsApp-style jpeg filenames on the product gallery', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $whatsapp = UploadedFile::fake()->create(
        'WhatsApp Image 2026-08-03 at 9.17.53 PM (1).jpeg',
        120,
        'application/octet-stream',
    );

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'WhatsApp Upload Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 180,
            'stock_quantity' => 2,
            'is_active' => 1,
            'images' => [$whatsapp],
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'whatsapp-upload-bag')->firstOrFail();
    expect($product->photos()->count())->toBe(1)
        ->and($product->photos()->first()->file_name)
        ->toBe('WhatsApp Image 2026-08-03 at 9.17.53 PM (1).jpeg');
});

it('accepts WhatsApp JPEG bytes saved with a misleading .png extension', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    // Minimal valid JPEG — WhatsApp often wraps these bytes in a .png filename.
    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
        true,
    );

    $images = [];
    foreach (['WhatsApp Image 2026-08-27 at 4.38.27 PM.png', 'WhatsApp Image 2026-08-27 at 4.38.27 PM (1).png'] as $name) {
        $path = tempnam(sys_get_temp_dir(), 'wa-png-');
        file_put_contents($path, $jpeg);
        $images[] = new UploadedFile($path, $name, 'image/png', UPLOAD_ERR_OK, true);
    }

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'WhatsApp PNG Name JPEG Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 190,
            'stock_quantity' => 2,
            'is_active' => 1,
            'images' => $images,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'whatsapp-png-name-jpeg-bag')->firstOrFail();

    expect($product->photos()->count())->toBe(2);

    foreach ($product->photos as $photo) {
        expect($photo->file_name)->toMatch('/\.jpe?g$/i')
            ->and($photo->storage_path)->toMatch('/\.jpe?g$/i')
            ->and($photo->mime_type)->toBe('image/jpeg');
    }
});

it('rejects non-image uploads on the product gallery', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->from(route('admin.products.create'))
        ->post(route('admin.products.store'), [
            'name' => 'Bad File Product',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 150,
            'stock_quantity' => 1,
            'is_active' => 1,
            'images' => [
                UploadedFile::fake()->create('notes.pdf', 30, 'application/pdf'),
            ],
        ])
        ->assertRedirect(route('admin.products.create'))
        ->assertSessionHasErrors('images.0');
});

it('preselects a collection only when asked, never from the category', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $fromCollection = $this->actingAs($admin)
        ->get(route('admin.products.create', ['collection_id' => $tree['collection']->id]))
        ->assertOk();

    $fromCategory = $this->actingAs($admin)
        ->get(route('admin.products.create', ['category_id' => $tree['category']->id]))
        ->assertOk();

    expect($fromCollection->viewData('selectedCollectionIds'))->toBe([$tree['collection']->id])
        ->and($fromCategory->viewData('selectedCollectionIds'))->toBe([]);

    $fromCategory->assertSee('data-selected="'.$tree['category']->id.'"', false);
});

it('files a product in collections that do not match its category', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Cross Category Bag',
            // A belt filed in both the Bags and Accessories collections.
            'collection_ids' => [$tree['collection']->id, $tree['otherCollection']->id],
            'category_id' => $tree['otherCategory']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 100,
            'stock_quantity' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'cross-category-bag')->firstOrFail();

    expect($product->category_id)->toBe($tree['otherCategory']->id)
        ->and($product->collections->pluck('id')->sort()->values()->all())
        ->toBe(collect([$tree['collection']->id, $tree['otherCollection']->id])->sort()->values()->all())
        ->and((int) $product->collections->firstWhere('id', $tree['collection']->id)->pivot->position)->toBe(0)
        ->and((int) $product->collections->firstWhere('id', $tree['otherCollection']->id)->pivot->position)->toBe(1);
});

it('replaces collection membership when editing a product', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'collection_ids' => [$tree['otherCollection']->id],
            'brand_id' => $tree['brand']->id,
            'base_price' => 300,
            'stock_quantity' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    expect($tree['product']->fresh()->collections->pluck('id')->all())->toBe([$tree['otherCollection']->id]);
});

it('clears collection membership when none are submitted', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 300,
            'stock_quantity' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'));

    expect($tree['product']->fresh()->collections)->toHaveCount(0);
});

it('rejects an unknown collection id on the product form', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Bad Collection Bag',
            'collection_ids' => [(string) Str::uuid()],
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 100,
            'stock_quantity' => 1,
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('collection_ids.0');
});

it('creates a product under a standalone category without a collection', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $standalone = Category::create([
        'id' => (string) Str::uuid(),
        'name' => ['en' => 'New In', 'ar' => 'جديد'],
        'slug' => ['en' => 'new-in', 'ar' => 'new-in'],
        'collection_id' => null,
        'position' => 9,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Standalone Piece',
            'category_id' => $standalone->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 220,
            'stock_quantity' => 2,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('products', [
        'category_id' => $standalone->id,
        'brand_id' => $tree['brand']->id,
    ]);
});

it('creates a product in a single collection', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Matched Bag',
            'collection_ids' => [$tree['collection']->id],
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 180,
            'stock_quantity' => 3,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('products', [
        'category_id' => $tree['category']->id,
        'brand_id' => $tree['brand']->id,
    ]);
});

it('lets an admin choose the primary color while creating a product', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Primary Color Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 450,
            'stock_quantity' => 7,
            'is_active' => 1,
            'colors' => [
                ['name' => 'Black', 'hex' => '#111111', 'stock' => 3],
                ['name' => 'Red', 'hex' => '#a6192e', 'stock' => 4],
            ],
            'default_color' => 'new:1',
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'primary-color-bag')->firstOrFail();
    $variants = ProductVariant::query()
        ->with('color')
        ->where('product_id', $product->id)
        ->whereNotNull('color_id')
        ->get();

    expect($variants)->toHaveCount(2)
        ->and($variants->where('is_default', true))->toHaveCount(1)
        ->and($variants->firstWhere('is_default', true)?->color?->name)->toBe('Red');
});

it('lets an admin change the primary color while editing a product', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();
    $black = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Black',
        'hex' => '#111111',
        'is_active' => true,
    ]);
    $red = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Red',
        'hex' => '#a6192e',
        'is_active' => true,
    ]);
    $blackVariant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $tree['product']->id,
        'color_id' => $black->id,
        'sku' => 'ZBR-CAT01-BLACK',
        'price' => 300,
        'stock' => 3,
        'is_active' => true,
        'is_default' => true,
    ]);
    $redVariant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $tree['product']->id,
        'color_id' => $red->id,
        'sku' => 'ZBR-CAT01-RED',
        'price' => 300,
        'stock' => 4,
        'is_active' => true,
        'is_default' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 300,
            'stock_quantity' => 7,
            'is_active' => 1,
            'existing_variants' => [
                $blackVariant->id => ['stock' => 3],
                $redVariant->id => ['stock' => 4],
            ],
            'default_color' => 'existing:'.$red->id,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    expect($blackVariant->fresh()->is_default)->toBeFalse()
        ->and($redVariant->fresh()->is_default)->toBeTrue()
        ->and(ProductVariant::where('product_id', $tree['product']->id)
            ->where('is_default', true)
            ->count())->toBe(1);
});

it('lets an admin rename and recolor an existing product color', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();
    $olive = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Olive',
        'hex' => '#556b2f',
        'is_active' => true,
    ]);
    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $tree['product']->id,
        'color_id' => $olive->id,
        'sku' => 'ZBR-CAT01-OLIVE',
        'price' => 300,
        'stock' => 5,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 300,
            'stock_quantity' => 5,
            'is_active' => 1,
            'existing_variants' => [
                $variant->id => ['stock' => 5],
            ],
            'color_groups' => [
                $olive->id => [
                    'name' => 'Forest Green',
                    'hex' => '#0b3d0b',
                ],
            ],
            'default_color' => 'existing:'.$olive->id,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $fresh = $variant->fresh()->load('color');
    expect($fresh->color->name)->toBe('Forest Green')
        ->and($fresh->color->hex)->toBe('#0b3d0b')
        ->and($fresh->stock)->toBe(5)
        ->and($fresh->is_default)->toBeTrue();
});

it('clones a shared color when renaming it on one product only', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();
    $shared = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Shared Black',
        'hex' => '#111111',
        'is_active' => true,
    ]);

    $other = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ZBR-OTHER01',
        'slug' => 'other-shared-color-tote',
        'name' => ['en' => 'Other Tote', 'ar' => 'Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $tree['brand']->id,
        'category_id' => $tree['category']->id,
        'price' => 200,
        'status' => 'active',
        'featured' => false,
    ]);

    $variant = ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $tree['product']->id,
        'color_id' => $shared->id,
        'sku' => 'ZBR-CAT01-SHARED',
        'price' => 300,
        'stock' => 2,
        'is_active' => true,
        'is_default' => true,
    ]);
    ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $other->id,
        'color_id' => $shared->id,
        'sku' => 'ZBR-OTHER01-SHARED',
        'price' => 200,
        'stock' => 1,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 300,
            'stock_quantity' => 2,
            'is_active' => 1,
            'existing_variants' => [
                $variant->id => ['stock' => 2],
            ],
            'color_groups' => [
                $shared->id => [
                    'name' => 'Charcoal',
                    'hex' => '#36454f',
                ],
            ],
            'default_color' => 'existing:'.$shared->id,
        ])
        ->assertRedirect(route('admin.products.index'));

    expect($shared->fresh()->name)->toBe('Shared Black')
        ->and($variant->fresh()->color->name)->toBe('Charcoal')
        ->and($variant->fresh()->color_id)->not->toBe($shared->id)
        ->and($other->variants()->first()->color_id)->toBe($shared->id);
});

it('shows editable color name and swatch fields on product edit', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();
    $navy = Color::create([
        'id' => (string) Str::uuid(),
        'name' => 'Navy',
        'hex' => '#1f2a44',
        'is_active' => true,
    ]);
    ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $tree['product']->id,
        'color_id' => $navy->id,
        'sku' => 'ZBR-CAT01-NAVY',
        'price' => 300,
        'stock' => 1,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.edit', $tree['product']->id))
        ->assertOk()
        ->assertSee('name="color_groups['.$navy->id.'][name]"', false)
        ->assertSee('name="color_groups['.$navy->id.'][hex]"', false)
        ->assertSee('value="Navy"', false);
});

it('creates one variant per color and size, keeping zero-stock sizes offered', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();
    $size37 = Size::where('name', '37')->firstOrFail();
    $size38 = Size::where('name', '38')->firstOrFail();
    $size39 = Size::where('name', '39')->firstOrFail();
    $size40 = Size::where('name', '40')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Sized Sneaker',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 500,
            'stock_quantity' => 0,
            'is_active' => 1,
            'size_ids' => [$size37->id, $size38->id, $size39->id, $size40->id],
            'colors' => [
                [
                    'name' => 'Black',
                    'hex' => '#111111',
                    'size_stocks' => [
                        $size37->id => 0,
                        $size38->id => 5,
                        $size39->id => 3,
                        $size40->id => 0,
                    ],
                ],
            ],
            'default_color' => 'new:0',
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'sized-sneaker')->firstOrFail();

    expect($product->sizes()->pluck('name')->sort()->values()->all())->toBe(['37', '38', '39', '40']);

    $variants = ProductVariant::where('product_id', $product->id)->whereNotNull('color_id')->get();

    expect($variants)->toHaveCount(4)
        // Every size of the (sole, default) color is marked default together.
        ->and($variants->where('is_default', true))->toHaveCount(4)
        ->and($variants->firstWhere('size_id', $size38->id)?->stock)->toBe(5)
        ->and($variants->firstWhere('size_id', $size39->id)?->stock)->toBe(3)
        ->and($variants->firstWhere('size_id', $size37->id)?->stock)->toBe(0)
        ->and($variants->firstWhere('size_id', $size40->id)?->stock)->toBe(0);
});

it('edits per-color, per-size stock and lets an admin remove an entire color', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();
    $size38 = Size::where('name', '38')->firstOrFail();
    $size39 = Size::where('name', '39')->firstOrFail();

    $tree['product']->sizes()->sync([$size38->id, $size39->id]);

    $black = Color::create(['id' => (string) Str::uuid(), 'name' => 'Black', 'hex' => '#111111', 'is_active' => true]);
    $red = Color::create(['id' => (string) Str::uuid(), 'name' => 'Red', 'hex' => '#a6192e', 'is_active' => true]);

    ProductVariant::create(['id' => (string) Str::uuid(), 'product_id' => $tree['product']->id, 'color_id' => $black->id, 'size_id' => $size38->id, 'sku' => 'ZBR-CAT01-BLACK-38', 'price' => 300, 'stock' => 2, 'is_active' => true, 'is_default' => true]);
    ProductVariant::create(['id' => (string) Str::uuid(), 'product_id' => $tree['product']->id, 'color_id' => $black->id, 'size_id' => $size39->id, 'sku' => 'ZBR-CAT01-BLACK-39', 'price' => 300, 'stock' => 1, 'is_active' => true, 'is_default' => true]);
    ProductVariant::create(['id' => (string) Str::uuid(), 'product_id' => $tree['product']->id, 'color_id' => $red->id, 'size_id' => $size38->id, 'sku' => 'ZBR-CAT01-RED-38', 'price' => 300, 'stock' => 0, 'is_active' => true, 'is_default' => false]);
    ProductVariant::create(['id' => (string) Str::uuid(), 'product_id' => $tree['product']->id, 'color_id' => $red->id, 'size_id' => $size39->id, 'sku' => 'ZBR-CAT01-RED-39', 'price' => 300, 'stock' => 0, 'is_active' => true, 'is_default' => false]);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 300,
            'stock_quantity' => 0,
            'is_active' => 1,
            'size_ids' => [$size38->id, $size39->id],
            'color_groups' => [
                $black->id => ['size_stocks' => [$size38->id => 6, $size39->id => 4]],
            ],
            'remove_colors' => [$red->id],
            'default_color' => 'existing:'.$black->id,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $remaining = ProductVariant::where('product_id', $tree['product']->id)->whereNotNull('color_id')->get();

    expect($remaining->where('color_id', $red->id))->toHaveCount(0)
        ->and($remaining)->toHaveCount(2)
        ->and($remaining->firstWhere('size_id', $size38->id)?->stock)->toBe(6)
        ->and($remaining->firstWhere('size_id', $size39->id)?->stock)->toBe(4)
        ->and($remaining->where('is_default', true))->toHaveCount(2);
});

it('renders category create without a collection selector', function () {
    $admin = catalogAdmin();
    seedCatalogTree();

    $this->actingAs($admin)
        ->get(route('admin.categories.create'))
        ->assertOk()
        ->assertSee('image-progress', false)
        ->assertSee('image-progress-bar', false)
        ->assertSee('Drop banner here, or browse')
        ->assertSee('category-tile-loading', false)
        ->assertDontSee('name="collection_id"', false)
        ->assertSee('classification only', false);
});

it('deleting a collection keeps products and categories', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->delete(route('admin.collections.destroy', $tree['collection']->id))
        ->assertRedirect(route('admin.collections.index'))
        ->assertSessionHas('success');

    expect(Collection::find($tree['collection']->id))->toBeNull()
        ->and(Category::find($tree['category']->id))->not->toBeNull()
        ->and(Product::find($tree['product']->id))->not->toBeNull()
        ->and(Product::find($tree['product']->id)?->category_id)->toBe($tree['category']->id);
});

it('renders product edit with image uploader and saved collection membership', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $response = $this->actingAs($admin)
        ->get(route('admin.products.edit', $tree['product']->id))
        ->assertOk()
        ->assertSee('product-images-dropzone', false)
        ->assertSee('name="collection_ids[]"', false)
        ->assertSee('id="category_id"', false)
        ->assertSee('data-selected="'.$tree['category']->id.'"', false)
        ->assertSee('data-product-qr', false)
        ->assertSee('/scan/', false);

    expect($response->viewData('selectedCollectionIds'))->toBe([$tree['collection']->id])
        ->and($tree['product']->fresh()->scanUrl())->toContain('/scan/'.$tree['product']->slug);
});

it('shows catalog totals and bulk selection controls on the products index', function () {
    $admin = catalogAdmin();
    seedCatalogTree();

    $this->actingAs($admin)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertSee('Total catalog')
        ->assertSee('Matching filters')
        ->assertSee('Most ordered products')
        ->assertSee('Delete selected')
        ->assertSee('name="ids[]"', false)
        ->assertSee(route('admin.products.bulk-destroy'), false);
});

it('stores every photo picked for a color and shares them across its sizes', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();
    $size38 = Size::where('name', '38')->firstOrFail();
    $size39 = Size::where('name', '39')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Multi Photo Sneaker',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 400,
            'stock_quantity' => 0,
            'is_active' => 1,
            'size_ids' => [$size38->id, $size39->id],
            'colors' => [
                ['name' => 'Burgundy', 'hex' => '#5c0f1c', 'size_stocks' => [$size38->id => 2, $size39->id => 1]],
            ],
            'color_images' => [
                [
                    UploadedFile::fake()->image('burgundy-front.jpg'),
                    UploadedFile::fake()->image('burgundy-side.jpg'),
                    UploadedFile::fake()->image('burgundy-back.jpg'),
                ],
            ],
            'default_color' => 'new:0',
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'multi-photo-sneaker')->firstOrFail();
    $variants = ProductVariant::with('photos')->where('product_id', $product->id)->get();

    expect($variants)->toHaveCount(2)
        ->and($product->photos()->count())->toBe(3);

    foreach ($variants as $variant) {
        expect($variant->photos)->toHaveCount(3);
    }
});

it('removes only the color photos an admin unticked', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Two Shot Belt',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 200,
            'stock_quantity' => 0,
            'is_active' => 1,
            'colors' => [['name' => 'Black', 'hex' => '#111111', 'stock' => 4]],
            'color_images' => [[
                UploadedFile::fake()->image('black-a.jpg'),
                UploadedFile::fake()->image('black-b.jpg'),
            ]],
            'default_color' => 'new:0',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'two-shot-belt')->firstOrFail();
    $variant = ProductVariant::with('photos')->where('product_id', $product->id)->firstOrFail();
    $dropped = $variant->photos->first();

    $this->actingAs($admin)
        ->put(route('admin.products.update', $product), [
            'name' => 'Two Shot Belt',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 200,
            'stock_quantity' => 0,
            'is_active' => 1,
            'existing_variants' => [$variant->id => ['stock' => 4]],
            'remove_color_photos' => [$dropped->id],
            'default_color' => 'existing:'.$variant->color_id,
        ])
        ->assertRedirect(route('admin.products.index'));

    expect($product->photos()->count())->toBe(1)
        ->and($variant->fresh()->photos)->toHaveCount(1)
        ->and($variant->fresh()->photos->first()->id)->not->toBe($dropped->id);
});

it('bulk deletes selected products', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $extra = Product::create([
        'id' => (string) Str::uuid(),
        'sku' => 'ZBR-BULK02',
        'slug' => 'bulk-delete-tote',
        'name' => ['en' => 'Bulk Delete Tote', 'ar' => 'Tote'],
        'description' => ['en' => 'Test', 'ar' => ''],
        'brand_id' => $tree['brand']->id,
        'category_id' => $tree['category']->id,
        'price' => 150,
        'status' => 'active',
        'featured' => false,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.bulk-destroy'), [
            'ids' => [$tree['product']->id, $extra->id],
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('products', ['id' => $tree['product']->id]);
    $this->assertDatabaseMissing('products', ['id' => $extra->id]);
});

it('saves original and sale prices for before/after discount display', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Sale Sandals',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 1000,
            'sale_price' => 750,
            'stock_quantity' => 4,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('slug', 'sale-sandals')->firstOrFail();
    $variant = $product->variants()->firstOrFail();

    expect((float) $product->price)->toBe(750.0)
        ->and((float) $product->price_min)->toBe(750.0)
        ->and((float) $product->price_max)->toBe(1000.0)
        ->and((float) $variant->price)->toBe(750.0)
        ->and((float) $variant->compare_at_price)->toBe(1000.0);

    $this->getJson('/api/products/'.$product->slug)
        ->assertOk()
        ->assertJsonPath('data.price', '750.00')
        ->assertJsonPath('data.compare_at_price', '1000.00');
});

it('rejects a sale price that is not lower than the original', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->from(route('admin.products.create'))
        ->post(route('admin.products.store'), [
            'name' => 'Invalid Sale Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 400,
            'sale_price' => 400,
            'stock_quantity' => 2,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.create'))
        ->assertSessionHasErrors('sale_price');

    expect(Product::where('slug', 'invalid-sale-bag')->exists())->toBeFalse();
});

it('updates sale pricing and clears compare-at when sale is removed', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    ProductVariant::create([
        'id' => (string) Str::uuid(),
        'product_id' => $tree['product']->id,
        'sku' => $tree['product']->sku.'-DEFAULT',
        'price' => 300,
        'stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 500,
            'sale_price' => 399,
            'stock_quantity' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'));

    $onSale = $tree['product']->fresh();
    expect((float) $onSale->price)->toBe(399.0)
        ->and((float) $onSale->price_max)->toBe(500.0)
        ->and((float) $onSale->variants()->first()->compare_at_price)->toBe(500.0);

    $this->actingAs($admin)
        ->put(route('admin.products.update', $tree['product']), [
            'name' => 'Catalog Tote',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 500,
            'stock_quantity' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.products.index'));

    $cleared = $tree['product']->fresh();
    expect((float) $cleared->price)->toBe(500.0)
        ->and((float) $cleared->price_max)->toBe(500.0)
        ->and($cleared->variants()->first()->compare_at_price)->toBeNull();
});

it('lets an admin set the product gallery main image', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $cover = Photo::create([
        'id' => (string) Str::uuid(),
        'imageable_type' => Product::class,
        'imageable_id' => $tree['product']->id,
        'storage_path' => 'products/cover.jpg',
        'file_name' => 'cover.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 100,
        'alt_text' => 'Cover',
        'is_primary' => true,
        'position' => 0,
    ]);
    $alt = Photo::create([
        'id' => (string) Str::uuid(),
        'imageable_type' => Product::class,
        'imageable_id' => $tree['product']->id,
        'storage_path' => 'products/alt.jpg',
        'file_name' => 'alt.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 100,
        'alt_text' => 'Alt',
        'is_primary' => false,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.images.primary', $alt->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($alt->fresh()->is_primary)->toBeTrue()
        ->and($alt->fresh()->position)->toBe(0)
        ->and($cover->fresh()->is_primary)->toBeFalse()
        ->and($cover->fresh()->position)->toBe(1);
});

it('clears is_primary on variant-linked photos when setting gallery main', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Shared Primary Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 200,
            'stock_quantity' => 0,
            'is_active' => 1,
            'colors' => [['name' => 'Navy', 'hex' => '#001f3f', 'stock' => 2]],
            'color_images' => [[
                UploadedFile::fake()->image('navy-a.jpg'),
            ]],
            'default_color' => 'new:0',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'shared-primary-bag')->firstOrFail();
    $variantPhoto = $product->variants()->first()->photos()->first();
    expect($variantPhoto)->not->toBeNull();
    $variantPhoto->update(['is_primary' => true]);

    $gallery = Photo::create([
        'id' => (string) Str::uuid(),
        'imageable_type' => Product::class,
        'imageable_id' => $product->id,
        'storage_path' => 'products/gallery-cover.jpg',
        'file_name' => 'gallery-cover.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 100,
        'alt_text' => 'Gallery',
        'is_primary' => false,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.images.primary', $gallery->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($gallery->fresh()->is_primary)->toBeTrue()
        ->and($variantPhoto->fresh()->is_primary)->toBeFalse()
        ->and($product->fresh()->photos()->where('is_primary', true)->count())->toBe(1);
});

it('lets an admin set the main photo for a color', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Main Color Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 250,
            'stock_quantity' => 0,
            'is_active' => 1,
            'colors' => [['name' => 'Olive', 'hex' => '#556b2f', 'stock' => 3]],
            'color_images' => [[
                UploadedFile::fake()->image('olive-a.jpg'),
                UploadedFile::fake()->image('olive-b.jpg'),
            ]],
            'default_color' => 'new:0',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'main-color-bag')->firstOrFail();
    $variant = ProductVariant::with('photos')->where('product_id', $product->id)->firstOrFail();
    $first = $variant->photos->get(0);
    $second = $variant->photos->get(1);
    expect($second)->not->toBeNull();
    $first->update(['is_primary' => true]);

    $this->actingAs($admin)
        ->post(route('admin.products.color-photos.primary', [$product->id, $second->id]))
        ->assertOk()
        ->assertJson(['success' => true]);

    $ordered = $variant->fresh()->photos()->orderBy('product_variant_photos.position')->get();
    expect($ordered->first()->id)->toBe($second->id)
        ->and((int) $ordered->first()->pivot->position)->toBe(0)
        ->and($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and($product->fresh()->photos()->where('is_primary', true)->count())->toBe(1);
});

it('lets an admin set a non-primary color photo as the website main', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Two Tone Cover Bag',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 300,
            'stock_quantity' => 0,
            'is_active' => 1,
            'colors' => [
                ['name' => 'Black', 'hex' => '#111111', 'stock' => 2],
                ['name' => 'Ivory', 'hex' => '#fffff0', 'stock' => 2],
            ],
            'color_images' => [
                [UploadedFile::fake()->image('black-a.jpg')],
                [UploadedFile::fake()->image('ivory-a.jpg'), UploadedFile::fake()->image('ivory-b.jpg')],
            ],
            'default_color' => 'new:0',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'two-tone-cover-bag')->firstOrFail();
    $ivory = $product->variants()->whereHas('color', fn ($q) => $q->where('name', 'Ivory'))->with('photos')->firstOrFail();
    $ivorySecond = $ivory->photos->sortBy(fn ($p) => $p->pivot->position ?? 0)->values()->get(1);
    expect($ivorySecond)->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.products.color-photos.primary', [$product->id, $ivorySecond->id]))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($ivorySecond->fresh()->is_primary)->toBeTrue()
        ->and($product->fresh()->photos()->where('is_primary', true)->count())->toBe(1)
        ->and($product->fresh()->coverPhoto()?->id)->toBe($ivorySecond->id);
});

it('lets an admin set a color-linked photo as main from the gallery endpoint', function () {
    Storage::fake('public');
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    $this->actingAs($admin)
        ->post(route('admin.products.store'), [
            'name' => 'Gallery Color Cover',
            'category_id' => $tree['category']->id,
            'brand_id' => $tree['brand']->id,
            'base_price' => 220,
            'stock_quantity' => 0,
            'is_active' => 1,
            'colors' => [['name' => 'Wine', 'hex' => '#722f37', 'stock' => 2]],
            'color_images' => [[
                UploadedFile::fake()->image('wine-a.jpg'),
            ]],
            'default_color' => 'new:0',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product = Product::where('slug', 'gallery-color-cover')->firstOrFail();
    $colorPhoto = $product->variants()->first()->photos()->first();
    expect($colorPhoto)->not->toBeNull();

    $gallery = Photo::create([
        'id' => (string) Str::uuid(),
        'imageable_type' => Product::class,
        'imageable_id' => $product->id,
        'storage_path' => 'products/gallery-only.jpg',
        'file_name' => 'gallery-only.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 100,
        'alt_text' => 'Gallery',
        'is_primary' => true,
        'position' => 0,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.images.primary', $colorPhoto->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($colorPhoto->fresh()->is_primary)->toBeTrue()
        ->and($gallery->fresh()->is_primary)->toBeFalse()
        ->and($product->fresh()->coverPhoto()?->id)->toBe($colorPhoto->id);
});

it('shows set-as-main controls on the product edit gallery', function () {
    $admin = catalogAdmin();
    $tree = seedCatalogTree();

    Photo::create([
        'id' => (string) Str::uuid(),
        'imageable_type' => Product::class,
        'imageable_id' => $tree['product']->id,
        'storage_path' => 'products/hero.jpg',
        'file_name' => 'hero.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 100,
        'alt_text' => 'Hero',
        'is_primary' => true,
        'position' => 0,
    ]);
    Photo::create([
        'id' => (string) Str::uuid(),
        'imageable_type' => Product::class,
        'imageable_id' => $tree['product']->id,
        'storage_path' => 'products/side.jpg',
        'file_name' => 'side.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 100,
        'alt_text' => 'Side',
        'is_primary' => false,
        'position' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.edit', $tree['product']->id))
        ->assertOk()
        ->assertSee('data-saved-product-gallery', false)
        ->assertSee('Set as Main')
        ->assertSee('Website cover');
});
