<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Restore classic homepage behavior: existing categories belong in The Edit.
        DB::table('categories')->where('is_featured', false)->update(['is_featured' => true]);
    }

    public function down(): void
    {
        // Irreversible backfill — leave featured flags as-is.
    }
};
