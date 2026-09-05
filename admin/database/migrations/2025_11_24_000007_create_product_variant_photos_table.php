<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variant_photos', function (Blueprint $table) {
            $table->uuid('variant_id');
            $table->uuid('photo_id');
            $table->integer('position')->default(0);

            $table->primary(['variant_id', 'photo_id']);
            $table->index('photo_id');

            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('photo_id')->references('id')->on('photos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_photos');
    }
};
