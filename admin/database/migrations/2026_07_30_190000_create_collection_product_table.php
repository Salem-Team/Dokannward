<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decouples Collections from Categories.
 *
 * A Category answers "what is this item" (bag, shoe, belt) while a Collection
 * is a merchandising group that may mix categories ("Summer 2026", "Gifts").
 * Membership therefore lives on its own pivot instead of being inferred from
 * `categories.collection_id`.
 *
 * Up:
 *  1. Create `collection_product` (safe if already present).
 *  2. Backfill every product into its category's current collection.
 *  3. Null every `categories.collection_id` so categories are truly independent.
 *     The column remains for rollback / schema compatibility only.
 *
 * Down:
 *  Best-effort restore of `categories.collection_id` from the pivot (a category
 *  gets the first collection that still has an active product in that category).
 *  Then drop the pivot. Categories that never had pivot members stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collection_product')) {
            Schema::create('collection_product', function (Blueprint $table) {
                $table->uuid('product_id');
                $table->uuid('collection_id');
                // Merchandising order inside the collection (0 = first).
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->primary(['collection_id', 'product_id']);
                $table->index('product_id');

                $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        $this->backfillFromCategories();
        $this->detachCategoriesFromCollections();
    }

    public function down(): void
    {
        $this->bestEffortRestoreCategoryCollections();
        Schema::dropIfExists('collection_product');
    }

    /**
     * Seeds membership from `products.category_id` -> `categories.collection_id`.
     * Safe to re-run: existing pairs are skipped.
     */
    private function backfillFromCategories(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('categories')) {
            return;
        }

        if (! Schema::hasColumn('categories', 'collection_id')) {
            return;
        }

        $now = now();
        $positions = [];

        DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereNotNull('categories.collection_id')
            ->orderBy('categories.collection_id')
            ->orderBy('products.created_at')
            ->orderBy('products.id')
            ->select([
                'products.id as product_id',
                'categories.collection_id as collection_id',
            ])
            ->chunk(500, function ($rows) use (&$positions, $now) {
                $insert = [];

                foreach ($rows as $row) {
                    $collectionId = (string) $row->collection_id;
                    $positions[$collectionId] = ($positions[$collectionId] ?? -1) + 1;

                    $insert[] = [
                        'product_id' => (string) $row->product_id,
                        'collection_id' => $collectionId,
                        'position' => $positions[$collectionId],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($insert !== []) {
                    DB::table('collection_product')->insertOrIgnore($insert);
                }
            });
    }

    /**
     * Categories are classification only — clear the legacy FK after membership
     * has been copied onto the pivot.
     */
    private function detachCategoriesFromCollections(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'collection_id')) {
            return;
        }

        DB::table('categories')->whereNotNull('collection_id')->update([
            'collection_id' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Best-effort: for each category that has pivot members, restore
     * `collection_id` to the first (lowest position) collection that still
     * holds one of its products. Ambiguous / empty cases stay null.
     */
    private function bestEffortRestoreCategoryCollections(): void
    {
        if (
            ! Schema::hasTable('collection_product')
            || ! Schema::hasTable('categories')
            || ! Schema::hasColumn('categories', 'collection_id')
        ) {
            return;
        }

        $pairs = DB::table('collection_product')
            ->join('products', 'products.id', '=', 'collection_product.product_id')
            ->whereNotNull('products.category_id')
            ->orderBy('collection_product.position')
            ->orderBy('collection_product.collection_id')
            ->select([
                'products.category_id',
                'collection_product.collection_id',
            ])
            ->get();

        $restored = [];
        foreach ($pairs as $pair) {
            $categoryId = (string) $pair->category_id;
            if (isset($restored[$categoryId])) {
                continue;
            }
            $restored[$categoryId] = (string) $pair->collection_id;
        }

        foreach ($restored as $categoryId => $collectionId) {
            DB::table('categories')->where('id', $categoryId)->update([
                'collection_id' => $collectionId,
                'updated_at' => now(),
            ]);
        }
    }
};
