<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ProductController;
use App\Models\Photo;
use App\Models\Product;
use App\Services\StorefrontRevalidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seed/import accidentally attached collection covers (IMG-055x) and lifestyle
 * plates to handbags. Admin then showed the product primary while the
 * storefront card preferred the first color's unrelated photo.
 *
 * This command:
 * 1. Drops junk plates from product galleries + variant pivots
 * 2. Ensures every product has a real product-plate primary photo
 * 3. Points every variant at that same primary (no invented per-color art)
 * 4. Purges catalog API + storefront caches
 */
class RepairProductCatalogPhotosCommand extends Command
{
    protected $signature = 'dokannward:repair-product-photos {--dry-run : Report only}';

    protected $description = 'Replace mismatched catalog/IMG seed photos with product plates and sync variant images';

    /** @var list<string> */
    private array $productPlates = [
        'products/catalog/handbags-and-accessories-products-1.jpg',
        'products/catalog/handbags-and-accessories-products-2.jpg',
        'products/catalog/handbags-and-accessories-products-3.jpg',
        'products/catalog/handbags-and-accessories-products-4.jpg',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $available = array_values(array_filter(
            $this->productPlates,
            fn (string $path) => Storage::disk('public')->exists($path)
        ));

        if ($available === []) {
            $this->error('No product plates found under storage/app/public/products/catalog.');

            return self::FAILURE;
        }

        $repaired = 0;
        $removed = 0;

        Product::query()
            ->with(['photos', 'variants.photos'])
            ->orderBy('created_at')
            ->each(function (Product $product) use ($dry, $available, &$repaired, &$removed) {
                $name = $product->translated_name;
                $junk = $product->photos->filter(fn (Photo $photo) => $this->isJunkPath((string) $photo->storage_path));
                $keepers = $product->photos->reject(fn (Photo $photo) => $this->isJunkPath((string) $photo->storage_path));

                $primary = $keepers->firstWhere('is_primary', true) ?? $keepers->sortBy('position')->first();

                if (! $primary) {
                    $plate = $available[crc32((string) $product->id) % count($available)];
                    if ($dry) {
                        $this->line("[dry] {$name}: would set primary → {$plate}");
                    } else {
                        $primary = Photo::create([
                            'id' => (string) Str::uuid(),
                            'imageable_type' => Product::class,
                            'imageable_id' => $product->id,
                            'storage_path' => $plate,
                            'file_name' => basename($plate),
                            'mime_type' => 'image/jpeg',
                            'alt_text' => $name,
                            'is_primary' => true,
                            'position' => 0,
                        ]);
                    }
                    $repaired++;
                } elseif (! $primary->is_primary || $primary->position !== 0) {
                    if ($dry) {
                        $this->line("[dry] {$name}: would promote {$primary->storage_path} to primary");
                    } else {
                        $product->photos()->update(['is_primary' => false]);
                        $primary->update(['is_primary' => true, 'position' => 0]);
                    }
                    $repaired++;
                }

                foreach ($junk as $photo) {
                    if ($dry) {
                        $this->line("[dry] {$name}: would remove junk {$photo->storage_path}");
                    } else {
                        DB::table('product_variant_photos')->where('photo_id', $photo->id)->delete();
                        $photo->delete();
                    }
                    $removed++;
                }

                if ($dry || ! $primary) {
                    return;
                }

                // Refresh primary after junk deletes.
                $primary = $product->photos()->where('is_primary', true)->first()
                    ?? $product->photos()->orderBy('position')->first();

                if (! $primary) {
                    return;
                }

                foreach ($product->variants as $variant) {
                    $variant->photos()->sync([$primary->id => ['position' => 0]]);
                }
            });

        if (! $dry) {
            ProductController::forgetListCache();
            StorefrontRevalidator::purgeCatalog();
        }

        $this->info(($dry ? 'Dry run — ' : '')."repaired={$repaired} removed_junk={$removed}");

        return self::SUCCESS;
    }

    private function isJunkPath(string $path): bool
    {
        $base = basename($path);

        // Collection / eyewear covers + lifestyle plates — not product SKUs.
        if (preg_match('/^IMG-055\d+\.jpe?g$/i', $base)) {
            return true;
        }

        if (str_contains($base, 'handbags-and-accessories-natural')) {
            return true;
        }

        return false;
    }
}
