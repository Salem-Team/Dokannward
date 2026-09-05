<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // multilingual name and description stored as JSON
            $table->json('name');
            $table->string('slug')->nullable()->index();
            $table->json('description')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('logo_url', 1024)->nullable();
            $table->uuid('logo_image_id')->nullable()->index();
            $table->timestamps();
            // name is JSON so uniqueness at DB level is not applied
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
