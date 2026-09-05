<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product/variant deletes must never 500 because of cart or order history FKs.
 * - Order lines keep a snapshot (sku/name/price) → null product/variant refs.
 * - Cart lines are disposable → cascade when the variant disappears.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->dropForeignKeys('order_items', ['product_id', 'variant_id']);
        $this->dropForeignKeys('cart_items', ['variant_id']);

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->uuid('product_id')->nullable()->change();
            });

            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            });
        }

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->dropForeignKeys('cart_items', ['variant_id']);
        $this->dropForeignKeys('order_items', ['product_id', 'variant_id']);

        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete();
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
                $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete();
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignKeys(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $foreignKeys = Schema::getForeignKeys($table);
        foreach ($foreignKeys as $foreignKey) {
            $fkColumns = $foreignKey['columns'] ?? [];
            if (count(array_intersect($fkColumns, $columns)) === 0) {
                continue;
            }

            $name = $foreignKey['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropForeign($name);
            });
        }
    }
};
