<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
            $table->unique('name');
        });

        Schema::create('product_taggings', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('tag_id');
            $table->primary(['product_id', 'tag_id']);
            $table->index('tag_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('product_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_taggings');
        Schema::dropIfExists('product_tags');
    }
};
