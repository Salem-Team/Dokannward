<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 64)->nullable();
            // multilingual fields stored as JSON
            $table->json('name');
            $table->string('slug')->nullable()->unique();
            $table->uuid('brand_id')->nullable()->index();
            $table->uuid('category_id')->nullable()->index();
            $table->text('short_description')->nullable();
            $table->json('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('visibility')->default(true)->index();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('price_min', 10, 2)->nullable();
            $table->decimal('price_max', 10, 2)->nullable();
            $table->decimal('weight_kg', 6, 3)->nullable();
            $table->json('images')->nullable();
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('in_stock')->default(true)->index();
            $table->boolean('is_limited_edition')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // fulltext handled in DB-specific index (migration can create it for MySQL)
        });

        // add foreign key
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        // Fulltext indexes on JSON fields need generated columns; create them later if needed.
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
