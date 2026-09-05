<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ProductController;
use App\Models\Photo;
use App\Models\Product;
use App\Services\StorefrontRevalidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ensures exactly one is_primary photo per product so admin thumbnails,
 * catalog cards, and PDP covers stay in sync after Set Main.
 */
class NormalizeProductPrimaryPhotosCommand extends Command
{
    protected $signature = 'dokannward:normalize-primary-photos {--dry-run : Report only}';

    protected $description = 'Keep a single primary photo per product and purge catalog caches';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fixed = 0;

        Product::query()
            ->with(['photos', 'variants.photos'])
            ->orderBy('created_at')
            ->each(function (Product $product) use ($dry, &$fixed) {
                $variantPhotoIds = $product->variants
                    ->flatMap(fn ($v) => $v->photos->pluck('id'))
                    ->unique();

                $gallery = $product->photos
                    ->reject(fn (Photo $photo) => $variantPhotoIds->contains($photo->id))
                    ->values();

                $pool = $gallery->isNotEmpty() ? $gallery : $product->photos;
                if ($pool->isEmpty()) {
                    return;
                }

                $chosen = $pool->firstWhere('is_primary', true) ?? $pool->sortBy('position')->first();
                $primaries = $product->photos->where('is_primary', true);

                $needsFix = $primaries->count() !== 1
                    || ($primaries->count() === 1 && $primaries->first()->id !== $chosen->id)
                    || ((int) $chosen->position !== 0);

                if (! $needsFix) {
                    return;
                }

                $name = $product->translated_name;
                if ($dry) {
                    $this->line("[dry] {$name}: primary → {$chosen->file_name} ({$chosen->id})");
                    $fixed++;

                    return;
                }

                DB::transaction(function () use ($product, $chosen) {
                    $product->photos()->update(['is_primary' => false]);
                    $chosen->update(['is_primary' => true, 'position' => 0]);
                });

                $this->line("fixed {$name}");
                $fixed++;
            });

        if (! $dry && $fixed > 0) {
            ProductController::forgetListCache();
            StorefrontRevalidator::purgeCatalog();
        }

        $this->info(($dry ? 'Dry run — ' : '')."normalized={$fixed}");

        return self::SUCCESS;
    }
}
