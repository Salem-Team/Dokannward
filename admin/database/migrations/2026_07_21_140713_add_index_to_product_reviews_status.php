<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A single-column index on `approved` already exists; this adds the
        // compound index that actually matches how the storefront queries
        // reviews (scoped to one product, filtered to approved only).
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->index(['product_id', 'approved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'approved']);
        });
    }
};
