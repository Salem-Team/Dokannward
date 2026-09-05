<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('published')->default(false)->index();
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('button_text', 100)->nullable();
            $table->string('button_url', 1024)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('sliders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('slider_items', function (Blueprint $table) {
            $table->uuid('slider_id');
            $table->uuid('photo_id');
            $table->text('caption')->nullable();
            $table->string('link_url', 1024)->nullable();
            $table->integer('position')->default(0);
            $table->primary(['slider_id','photo_id']);
            $table->index('photo_id');
            $table->foreign('slider_id')->references('id')->on('sliders')->cascadeOnDelete();
            $table->foreign('photo_id')->references('id')->on('photos')->cascadeOnDelete();
        });

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alias')->unique();
            $table->string('type', 50);
            $table->json('config')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('slider_items');
        Schema::dropIfExists('sliders');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('pages');
    }
};
