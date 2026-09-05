<?php

namespace Database\Seeders;

use App\Models\Photo;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Attaches local bag product plates to products that still have no photos.
 * Skips lifestyle/collection covers so seed data cannot mislabel eyewear art
 * onto handbags again.
 */
class LocalProductPhotosSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::with(['variants', 'photos'])->get();
        if ($products->isEmpty()) {
            $this->command?->warn('No products found to attach photos.');

            return;
        }

        $dir = storage_path('app/public/products/catalog');
        $allowed = [
            'handbags-and-accessories-products-1.jpg',
            'handbags-and-accessories-products-2.jpg',
            'handbags-and-accessories-products-3.jpg',
            'handbags-and-accessories-products-4.jpg',
        ];

        $files = [];
        foreach ($allowed as $name) {
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        if ($files === []) {
            $this->command?->warn('No product catalog plates found under storage/app/public/products/catalog.');

            return;
        }

        $i = 0;
        foreach ($products as $product) {
            if ($product->photos()->exists()) {
                continue;
            }

            $file = $files[$i % count($files)];
            $fileName = basename($file);

            $photo = Photo::create([
                'id' => (string) Str::uuid(),
                'imageable_type' => Product::class,
                'imageable_id' => $product->id,
                'storage_path' => 'products/catalog/'.$fileName,
                'file_name' => $fileName,
                'file_size' => File::size($file) ?: null,
                'mime_type' => 'image/jpeg',
                'width' => null,
                'height' => null,
                'alt_text' => $product->translated_name ?? $product->name,
                'is_primary' => true,
                'position' => 0,
                'metadata' => null,
            ]);

            $variant = $product->variants->first();
            if ($variant) {
                $variant->photos()->syncWithoutDetaching([$photo->id => ['position' => 0]]);
            }

            $i++;
        }

        $this->command?->info("Local product photos attached ({$i}).");
    }
}
