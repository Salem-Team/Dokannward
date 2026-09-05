<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->boolean('recommended')->nullable();
            $table->boolean('approved')->default(false)->index();
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
            $table->string('ip_address', 45)->nullable();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // MySQL supports CHECK from 8.0.16+, add constraint explicitly where available.
        // If the database doesn't support CHECK constraints, this statement will be ignored.
        try {
            DB::statement("ALTER TABLE product_reviews ADD CONSTRAINT chk_product_reviews_rating CHECK (rating BETWEEN 1 AND 5)");
        } catch (\Exception $e) {
            // Some MySQL / older servers may ignore CHECK constraints, swallow errors to keep migrations portable.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
