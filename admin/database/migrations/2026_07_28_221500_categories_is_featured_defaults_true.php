<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categories belong on the homepage by default.
 * Logo rail no longer requires Featured; Featured only gates the banner stack.
 * Backfill any rows that lost Featured (e.g. Homewear) before the logo-rail fix.
 *
 * Avoids Schema::change() so we do not need doctrine/dbal — model $attributes
 * + controller defaults keep new rows Featured=true going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'is_featured')) {
            return;
        }

        DB::table('categories')->where('is_featured', false)->update(['is_featured' => true]);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE categories MODIFY is_featured TINYINT(1) NOT NULL DEFAULT 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE categories ALTER COLUMN is_featured SET DEFAULT true');
        }
        // sqlite: column default cannot be altered cheaply; Eloquent $attributes covers creates.
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'is_featured')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE categories MODIFY is_featured TINYINT(1) NOT NULL DEFAULT 0');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE categories ALTER COLUMN is_featured SET DEFAULT false');
        }
    }
};
