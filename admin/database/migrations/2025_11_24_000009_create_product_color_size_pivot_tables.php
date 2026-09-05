<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_colors', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('color_id');
            $table->primary(['product_id', 'color_id']);
            $table->index('color_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('color_id')->references('id')->on('colors')->cascadeOnDelete();
        });

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('size_id');
            $table->primary(['product_id', 'size_id']);
            $table->index('size_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('size_id')->references('id')->on('sizes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('product_colors');
    }
};
