<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The storefront has no customer accounts, so guest shoppers need a way to
 * sign their review — these columns hold the name/email they typed in the
 * review form when `user_id` is null.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->string('reviewer_name', 100)->nullable()->after('user_id');
            $table->string('reviewer_email', 255)->nullable()->after('reviewer_name');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn(['reviewer_name', 'reviewer_email']);
        });
    }
};
