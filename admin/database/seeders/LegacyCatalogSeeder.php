<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Photo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Populates the catalog that the Dokan Ward storefront actually needs: every
 * brand shown in the site's navigation, the category tree, a reusable color
 * palette, and a curated set of real products — each with several color
 * variants — so the "does this bag come in another color?" experience has
 * real data behind it.
 *
 * Safe to re-run: every lookup is keyed by a stable slug/sku, so nothing is
 * duplicated on subsequent runs.
 */
class LegacyCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->removeSampleProducts();

        $brands = $this->seedBrands();
        $categories = $this->seedCategories();
        $colors = $this->seedColors();
        $images = $this->catalogImages();

        $this->seedProducts($brands, $categories, $colors, $images);
    }

    /**
     * The two "anti-theft sling bag" rows come from an unrelated demo seed
     * and don't belong in a luxury resale catalog.
     */
    private function removeSampleProducts(): void
    {
        Product::where('slug', 'like', 'mens-anti-theft-sling-bag%')->get()->each(function (Product $p) {
            $variantIds = $p->variants()->withTrashed()->pluck('id');
            \Illuminate\Support\Facades\DB::table('cart_items')->whereIn('variant_id', $variantIds)->delete();
            \Illuminate\Support\Facades\DB::table('order_items')->whereIn('variant_id', $variantIds)->update(['variant_id' => null]);
            ProductVariant::withTrashed()->whereIn('id', $variantIds)->forceDelete();
            $p->delete();
        });
    }

    /** @return array<string, Brand> keyed by slug */
    private function seedBrands(): array
    {
        $brands = [
            'gucci' => ['name' => 'Gucci', 'name_ar' => 'غوتشي', 'country' => 'Italy', 'logo' => 'gucci.com'],
            'prada' => ['name' => 'Prada', 'name_ar' => 'برادا', 'country' => 'Italy', 'logo' => 'prada.com'],
            'louis-vuitton' => ['name' => 'Louis Vuitton', 'name_ar' => 'لويس فيتون', 'country' => 'France', 'logo' => 'louisvuitton.com'],
            'chloe' => ['name' => 'Chloé', 'name_ar' => 'كلوي', 'country' => 'France', 'logo' => 'chloe.com'],
            'fendi' => ['name' => 'Fendi', 'name_ar' => 'فيندي', 'country' => 'Italy', 'logo' => 'fendi.com'],
            'dior' => ['name' => 'Dior', 'name_ar' => 'ديور', 'country' => 'France', 'logo' => 'dior.com'],
            'hermes' => ['name' => 'Hermès', 'name_ar' => 'هيرميس', 'country' => 'France', 'logo' => 'hermes.com'],
            'jacquemus' => ['name' => 'Jacquemus', 'name_ar' => 'جاكموس', 'country' => 'France', 'logo' => 'jacquemus.com'],
            'lacoste' => ['name' => 'Lacoste', 'name_ar' => 'لاكوست', 'country' => 'France', 'logo' => 'lacoste.com'],
            'loro-piana' => ['name' => 'Loro Piana', 'name_ar' => 'لورو بيانا', 'country' => 'Italy', 'logo' => 'loropiana.com'],
            'miu-miu' => ['name' => 'Miu Miu', 'name_ar' => 'ميو ميو', 'country' => 'Italy', 'logo' => 'miumiu.com'],
            'nike' => ['name' => 'Nike', 'name_ar' => 'نايك', 'country' => 'USA', 'logo' => 'nike.com'],
            'polene' => ['name' => 'Polène', 'name_ar' => 'بولين', 'country' => 'France', 'logo' => 'polene-paris.com'],
            'ysl' => ['name' => 'Saint Laurent', 'name_ar' => 'سان لوران', 'country' => 'France', 'logo' => 'ysl.com'],
            'alo' => ['name' => 'Alo Yoga', 'name_ar' => 'ألو يوجا', 'country' => 'USA', 'logo' => 'aloyoga.com'],
            'd-g' => ['name' => 'Dolce & Gabbana', 'name_ar' => 'دولتشي آند غابانا', 'country' => 'Italy', 'logo' => 'dolcegabbana.com'],
        ];

        // The 5 brands seeded by DatabaseSeeder before slugs existed: back-fill
        // their slug in place first, so the firstOrCreate lookups below find
        // them instead of creating duplicates.
        $existingByName = ['Gucci' => 'gucci', 'Prada' => 'prada', 'Fendi' => 'fendi', 'Louis Vuitton' => 'louis-vuitton', 'Chloé' => 'chloe'];
        foreach ($existingByName as $nameEn => $slug) {
            Brand::where('name->en', $nameEn)->whereNull('slug')->update(['slug' => $slug]);
        }

        $result = [];
        foreach ($brands as $slug => $b) {
            $result[$slug] = Brand::firstOrCreate(
                ['slug' => $slug],
                [
                    'id' => (string) Str::uuid(),
                    'name' => ['en' => $b['name'], 'ar' => $b['name_ar']],
                    'description' => [
                        'en' => "Authenticated {$b['name']} pieces, curated for the store.",
                        'ar' => "قطع {$b['name_ar']} أصلية موثقة، مختارة من دكان ورد.",
                    ],
                    'country' => $b['country'],
                    'logo_url' => "https://logo.clearbit.com/{$b['logo']}",
                ]
            );
        }

        return $result;
    }

    /** @return array<string, Category> keyed by slug */
    private function seedCategories(): array
    {
        $bags = \App\Models\Collection::firstOrCreate(
            ['slug->en' => 'bags'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Bags', 'ar' => 'حقائب'],
                'slug' => ['en' => 'bags', 'ar' => 'حقائب'],
                'description' => 'Authenticated handbags and accessories, curated for the store for craft and rarity.',
                'position' => 0,
            ]
        );

        $eyewear = \App\Models\Collection::firstOrCreate(
            ['slug->en' => 'eyewear'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Eyewear', 'ar' => 'نظارات'],
                'slug' => ['en' => 'eyewear', 'ar' => 'نظارات'],
                'description' => 'Designer eyewear, authenticated and ready to wear.',
                'position' => 1,
            ]
        );

        $accessories = \App\Models\Collection::firstOrCreate(
            ['slug->en' => 'accessories'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Accessories', 'ar' => 'إكسسوارات'],
                'slug' => ['en' => 'accessories', 'ar' => 'إكسسوارات'],
                'description' => 'Finishing pieces for the Dokan Ward wardrobe.',
                'position' => 2,
            ]
        );

        $bagTypes = [
            'tote' => ['en' => 'Tote', 'ar' => 'tote'],
            'crossbody' => ['en' => 'Crossbody', 'ar' => 'crossbody'],
            'backpack' => ['en' => 'Backpack', 'ar' => 'backpack'],
            'mini-bag' => ['en' => 'Mini Bag', 'ar' => 'mini-bag'],
            'clutch' => ['en' => 'Clutch', 'ar' => 'clutch'],
        ];

        foreach ($bagTypes as $slug => $names) {
            $existing = Category::where('slug->en', $slug)->first();
            if ($existing) {
                $existing->update([
                    'collection_id' => $bags->id,
                    'parent_id' => null,
                    'name' => ['en' => $names['en'], 'ar' => $names['ar']],
                ]);
            } else {
                Category::create([
                    'id' => (string) Str::uuid(),
                    'name' => ['en' => $names['en'], 'ar' => $names['ar']],
                    'slug' => ['en' => $slug, 'ar' => $slug],
                    'collection_id' => $bags->id,
                    'parent_id' => null,
                    'position' => 0,
                    'is_featured' => true,
                ]);
            }
        }

        $sunglasses = Category::where('slug->en', 'sunglasses')->first();
        if ($sunglasses) {
            $sunglasses->update([
                'collection_id' => $eyewear->id,
                'parent_id' => null,
                'name' => ['en' => 'Sunglasses', 'ar' => 'نظارات شمسية'],
            ]);
        } else {
            Category::create([
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'Sunglasses', 'ar' => 'نظارات شمسية'],
                'slug' => ['en' => 'sunglasses', 'ar' => 'نظارات-شمسية'],
                'collection_id' => $eyewear->id,
                'parent_id' => null,
                'position' => 0,
                'is_featured' => true,
            ]);
        }

        Category::firstOrCreate(
            ['slug->en' => 'accessories-all'],
            [
                'id' => (string) Str::uuid(),
                'name' => ['en' => 'All Accessories', 'ar' => 'كل الإكسسوارات'],
                'slug' => ['en' => 'accessories-all', 'ar' => 'اكسسوارات'],
                'collection_id' => $accessories->id,
                'parent_id' => null,
                'position' => 0,
                'is_featured' => true,
            ]
        );

        return Category::query()->get()->keyBy(fn (Category $c) => $c->slug['en'] ?? $c->id)->all();
    }

    /** @return array<string, Color> keyed by name */
    private function seedColors(): array
    {
        $palette = [
            'Black' => '#111111',
            'White' => '#f5f5f0',
            'Beige' => '#e3d5b8',
            'Camel' => '#c19a6b',
            'Brown' => '#6b4226',
            'Tan' => '#d2b48c',
            'Red' => '#a6192e',
            'Navy' => '#1f2a44',
            'Gold' => '#c9a227',
            'Grey' => '#8c8c8c',
        ];

        $colors = [];
        foreach ($palette as $name => $hex) {
            $colors[$name] = Color::firstOrCreate(
                ['name' => $name],
                ['id' => (string) Str::uuid(), 'hex' => $hex, 'is_active' => true]
            );
        }

        return $colors;
    }

    /**
     * Product-only plates for seed photos.
     * Never mix lifestyle/collection covers (IMG-055x sunglasses etc.) — that
     * caused tote titles to show eyewear art on the storefront.
     *
     * @return string[] storage-relative paths, already copied into storage/app/public
     */
    private function catalogImages(): array
    {
        return [
            'products/catalog/handbags-and-accessories-products-1.jpg',
            'products/catalog/handbags-and-accessories-products-2.jpg',
            'products/catalog/handbags-and-accessories-products-3.jpg',
            'products/catalog/handbags-and-accessories-products-4.jpg',
        ];
    }

    /**
     * @param array<string, Brand> $brands
     * @param array<string, Category> $categories
     * @param array<string, Color> $colors
     * @param string[] $images
     */
    private function seedProducts(array $brands, array $categories, array $colors, array $images): void
    {
        $catalog = [
            ['brand' => 'fendi', 'category' => 'crossbody', 'name' => 'Fendi Baguette Shoulder Bag', 'price' => 28500, 'colors' => ['Black', 'Camel', 'Red']],
            ['brand' => 'fendi', 'category' => 'mini-bag', 'name' => 'Fendi Peekaboo Mini Tote', 'price' => 42000, 'colors' => ['Black', 'Beige', 'Grey']],
            ['brand' => 'louis-vuitton', 'category' => 'tote', 'name' => 'Louis Vuitton Neverfull Tote', 'price' => 35500, 'colors' => ['Brown', 'Black', 'Beige']],
            ['brand' => 'louis-vuitton', 'category' => 'crossbody', 'name' => 'Louis Vuitton Pochette Crossbody', 'price' => 19800, 'colors' => ['Brown', 'Black']],
            ['brand' => 'chloe', 'category' => 'crossbody', 'name' => 'Chloé Marcie Saddle Bag', 'price' => 31000, 'colors' => ['Tan', 'Black', 'Brown']],
            ['brand' => 'chloe', 'category' => 'tote', 'name' => 'Chloé Woody Tote', 'price' => 22500, 'colors' => ['Beige', 'Black']],
            ['brand' => 'gucci', 'category' => 'crossbody', 'name' => 'Gucci Marmont Shoulder Bag', 'price' => 38900, 'colors' => ['Black', 'Red', 'Beige']],
            ['brand' => 'gucci', 'category' => 'mini-bag', 'name' => 'Gucci Dionysus Mini Bag', 'price' => 33500, 'colors' => ['Black', 'Gold', 'Grey']],
            ['brand' => 'prada', 'category' => 'tote', 'name' => 'Prada Re-Edition Tote', 'price' => 27000, 'colors' => ['Black', 'Camel']],
            ['brand' => 'prada', 'category' => 'crossbody', 'name' => 'Prada Cleo Shoulder Bag', 'price' => 36000, 'colors' => ['Beige', 'Black', 'Navy']],
            ['brand' => 'dior', 'category' => 'tote', 'name' => 'Dior Lady Dior Bag', 'price' => 52000, 'colors' => ['Black', 'Red', 'Navy']],
            ['brand' => 'dior', 'category' => 'crossbody', 'name' => 'Dior Saddle Bag', 'price' => 45500, 'colors' => ['Tan', 'Black', 'Grey']],
            ['brand' => 'hermes', 'category' => 'tote', 'name' => 'Hermès Garden Party Tote', 'price' => 68000, 'colors' => ['Camel', 'Black']],
            ['brand' => 'hermes', 'category' => 'crossbody', 'name' => 'Hermès Evelyne Crossbody', 'price' => 59500, 'colors' => ['Black', 'Tan', 'Gold']],
            ['brand' => 'miu-miu', 'category' => 'backpack', 'name' => 'Miu Miu Wander Matelassé Bag', 'price' => 41000, 'colors' => ['Black', 'Beige', 'Grey']],
            ['brand' => 'miu-miu', 'category' => 'clutch', 'name' => 'Miu Miu Coffer Top Handle', 'price' => 39500, 'colors' => ['Grey', 'Black']],
            ['brand' => 'ysl', 'category' => 'crossbody', 'name' => 'Saint Laurent Loulou Bag', 'price' => 47000, 'colors' => ['Black', 'Navy', 'Beige']],
            ['brand' => 'ysl', 'category' => 'tote', 'name' => 'Saint Laurent Sac de Jour', 'price' => 51500, 'colors' => ['Black', 'Camel']],

            // Every brand shown in the storefront's navigation needs at least
            // one listing — otherwise clicking through to that brand's page
            // shows an empty, unfinished-looking catalog.
            ['brand' => 'loro-piana', 'category' => 'tote', 'name' => 'Loro Piana Extra Pocket Tote', 'price' => 58000, 'colors' => ['Camel', 'Black', 'Grey']],
            ['brand' => 'loro-piana', 'category' => 'crossbody', 'name' => 'Loro Piana L19 Shoulder Bag', 'price' => 49500, 'colors' => ['Tan', 'Navy']],
            ['brand' => 'd-g', 'category' => 'tote', 'name' => 'Dolce & Gabbana Sicily Bag', 'price' => 41500, 'colors' => ['Black', 'Red', 'Beige']],
            ['brand' => 'd-g', 'category' => 'crossbody', 'name' => 'Dolce & Gabbana Devotion Crossbody', 'price' => 36500, 'colors' => ['Black', 'Gold']],
            ['brand' => 'jacquemus', 'category' => 'mini-bag', 'name' => 'Jacquemus Le Chiquito Mini Bag', 'price' => 21500, 'colors' => ['Black', 'Beige', 'Camel']],
            ['brand' => 'jacquemus', 'category' => 'tote', 'name' => 'Jacquemus Le Bambino Tote', 'price' => 18500, 'colors' => ['White', 'Tan']],
            ['brand' => 'polene', 'category' => 'crossbody', 'name' => 'Polène Numéro Un Bag', 'price' => 12500, 'colors' => ['Camel', 'Black', 'Grey']],
            ['brand' => 'polene', 'category' => 'mini-bag', 'name' => 'Polène Cyme Mini Bag', 'price' => 9500, 'colors' => ['Beige', 'Brown']],
            ['brand' => 'lacoste', 'category' => 'crossbody', 'name' => 'Lacoste Chantaco Crossbody', 'price' => 6200, 'colors' => ['Black', 'Navy', 'White'], 'material' => 'Canvas'],
            ['brand' => 'lacoste', 'category' => 'tote', 'name' => 'Lacoste L.12.12 Canvas Tote', 'price' => 4500, 'colors' => ['White', 'Navy'], 'material' => 'Canvas'],
            ['brand' => 'nike', 'category' => 'backpack', 'name' => 'Nike Heritage Backpack', 'price' => 3800, 'colors' => ['Black', 'Grey'], 'material' => 'Nylon'],
            ['brand' => 'nike', 'category' => 'crossbody', 'name' => 'Nike Heritage Crossbody Bag', 'price' => 2900, 'colors' => ['Black', 'White'], 'material' => 'Nylon'],
            ['brand' => 'alo', 'category' => 'tote', 'name' => 'Alo Yoga Micro Tote', 'price' => 4200, 'colors' => ['Black', 'Beige'], 'material' => 'Canvas'],
            ['brand' => 'alo', 'category' => 'backpack', 'name' => 'Alo Yoga Studio Duffle Bag', 'price' => 5100, 'colors' => ['Black', 'Grey', 'White'], 'material' => 'Nylon'],
        ];

        foreach ($catalog as $i => $entry) {
            $brand = $brands[$entry['brand']];
            $category = $categories[$entry['category']];
            $slug = Str::slug($entry['name']);
            $material = $entry['material'] ?? 'Leather';

            $product = Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'id' => (string) Str::uuid(),
                    'sku' => 'ZBR-'.Str::upper(Str::random(6)),
                    'name' => ['en' => $entry['name'], 'ar' => $entry['name']],
                    'brand_id' => $brand->id,
                    'category_id' => $category->id,
                    'short_description' => [
                        'en' => "Authenticated {$entry['name']}, available in ".count($entry['colors']).' colors.',
                        'ar' => "قطعة أصلية موثقة: {$entry['name']}، متوفرة بـ".count($entry['colors']).' ألوان.',
                    ],
                    'description' => [
                        'en' => "A signature piece from {$brand->translated_name}, carefully inspected and authenticated by the Dokan Ward team before listing. Comes with original packaging where available.",
                        'ar' => 'قطعة أصلية تم فحصها وتوثيقها من فريق دكان ورد قبل عرضها. تأتي مع التغليف الأصلي عند توفره.',
                    ],
                    'price' => $entry['price'],
                    'price_min' => $entry['price'],
                    'price_max' => $entry['price'],
                    'material' => $material,
                    'status' => 'active',
                    'visibility' => true,
                    'featured' => $i < 6,
                    'in_stock' => true,
                ]
            );

            $image = $images[$i % count($images)];
            $photo = Photo::firstOrCreate(
                ['imageable_type' => Product::class, 'imageable_id' => $product->id, 'position' => 0],
                [
                    'id' => (string) Str::uuid(),
                    'storage_path' => $image,
                    'file_name' => basename($image),
                    'mime_type' => 'image/jpeg',
                    'alt_text' => $entry['name'],
                    'is_primary' => true,
                ]
            );

            foreach ($entry['colors'] as $position => $colorName) {
                $color = $colors[$colorName];
                $sku = Str::upper(Str::slug($entry['name'])).'-'.Str::upper($colorName);

                $variant = ProductVariant::firstOrCreate(
                    ['sku' => $sku],
                    [
                        'id' => (string) Str::uuid(),
                        'product_id' => $product->id,
                        'color_id' => $color->id,
                        'price' => $entry['price'],
                        'stock' => 8 + $position * 3,
                        'is_active' => true,
                    ]
                );

                // Variants share the product hero until real color shots are uploaded.
                // Never invent per-color lifestyle/collection plates — that made the
                // storefront card disagree with the admin primary thumbnail.
                $variant->photos()->sync([$photo->id => ['position' => 0]]);
            }
        }
    }
}
