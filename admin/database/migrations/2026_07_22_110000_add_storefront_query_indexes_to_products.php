<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speed up the storefront catalog filters — status + featured / created_at
 * are the two hottest WHERE/ORDER patterns on /api/products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'products_status_created_at_index');
            $table->index(['status', 'featured'], 'products_status_featured_index');
            $table->index(['status', 'brand_id'], 'products_status_brand_id_index');
            $table->index(['status', 'category_id'], 'products_status_category_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_created_at_index');
            $table->dropIndex('products_status_featured_index');
            $table->dropIndex('products_status_brand_id_index');
            $table->dropIndex('products_status_category_id_index');
        });
    }
};
