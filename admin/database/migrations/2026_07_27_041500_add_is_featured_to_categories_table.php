<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage "Shop by category" curation.
 * Existing categories stay featured so the live edit does not empty overnight;
 * new categories opt in via the admin toggle (DB default false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('position');
            $table->index(['is_featured', 'position']);
        });

        DB::table('categories')->update(['is_featured' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'position']);
            $table->dropColumn('is_featured');
        });
    }
};
