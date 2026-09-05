<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Introduces first-class Collections. Existing root categories become
 * Collections; their children (and product-bearing roots) become Categories
 * under those Collections. Storefront /collections/{slug} keeps working via
 * collection slugs that match the former root category slugs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('name');
            $table->json('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->uuid('collection_id')->nullable()->after('parent_id')->index();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('collection_id')
                ->references('id')
                ->on('collections')
                ->nullOnDelete();
        });

        $this->migrateExistingTree();
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['collection_id']);
            $table->dropColumn('collection_id');
        });

        Schema::dropIfExists('collections');
    }

    private function migrateExistingTree(): void
    {
        $now = now();

        $roots = DB::table('categories')->whereNull('parent_id')->orderBy('position')->get();

        foreach ($roots as $root) {
            $collectionId = (string) Str::uuid();
            $name = $this->decodeJson($root->name);
            $slug = $this->decodeJson($root->slug);

            DB::table('collections')->insert([
                'id' => $collectionId,
                'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                'slug' => json_encode($slug, JSON_UNESCAPED_UNICODE),
                'description' => $root->description,
                'image_url' => $root->path,
                'position' => (int) $root->position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $children = DB::table('categories')->where('parent_id', $root->id)->get();

            foreach ($children as $child) {
                DB::table('categories')->where('id', $child->id)->update([
                    'collection_id' => $collectionId,
                    'parent_id' => null,
                    'updated_at' => $now,
                ]);
            }

            $hasProducts = DB::table('products')->where('category_id', $root->id)->exists();

            if ($hasProducts || $children->isEmpty()) {
                // Keep the former root as a category under the new collection.
                // Rename its slug so it does not collide with the collection URL.
                $slugEn = is_array($slug) ? (string) ($slug['en'] ?? 'category') : (string) $slug;
                $slugAr = is_array($slug) ? (string) ($slug['ar'] ?? $slugEn) : $slugEn;
                $nameEn = is_array($name) ? (string) ($name['en'] ?? 'All') : (string) $name;
                $nameAr = is_array($name) ? (string) ($name['ar'] ?? $nameEn) : $nameEn;

                DB::table('categories')->where('id', $root->id)->update([
                    'collection_id' => $collectionId,
                    'parent_id' => null,
                    'name' => json_encode([
                        'en' => $nameEn === '' ? 'All' : 'All '.$nameEn,
                        'ar' => $nameAr === '' ? 'الكل' : 'كل '.$nameAr,
                    ], JSON_UNESCAPED_UNICODE),
                    'slug' => json_encode([
                        'en' => $this->uniqueCategorySlug($slugEn.'-all', $root->id),
                        'ar' => $this->uniqueCategorySlug($slugAr.'-all', $root->id, 'ar'),
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('categories')->where('id', $root->id)->delete();
            }
        }

        // Any leftover nested rows (depth > 2) keep their parent but inherit
        // the nearest ancestor's collection when missing.
        $orphans = DB::table('categories')->whereNull('collection_id')->get();
        foreach ($orphans as $orphan) {
            $collectionId = $this->resolveCollectionFromParent($orphan->parent_id);
            if ($collectionId) {
                DB::table('categories')->where('id', $orphan->id)->update([
                    'collection_id' => $collectionId,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function decodeJson(mixed $value): array|string|null
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            return $value;
        }

        return null;
    }

    private function uniqueCategorySlug(string $base, string $ignoreId, string $locale = 'en'): string
    {
        $slug = $base !== '' ? $base : 'category';
        $i = 2;

        while (
            DB::table('categories')
                ->where("slug->{$locale}", $slug)
                ->where('id', '!=', $ignoreId)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function resolveCollectionFromParent(?string $parentId): ?string
    {
        $seen = [];
        while ($parentId && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = DB::table('categories')->where('id', $parentId)->first();
            if (! $parent) {
                return null;
            }
            if ($parent->collection_id) {
                return $parent->collection_id;
            }
            $parentId = $parent->parent_id;
        }

        return null;
    }
};
