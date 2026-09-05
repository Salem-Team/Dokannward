<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->index()->after('is_active');
        });

        // Preserve today's effective behavior for existing products by making
        // the oldest live color variant the explicit default.
        DB::table('product_variants')
            ->whereNotNull('color_id')
            ->whereNull('deleted_at')
            ->select('product_id')
            ->distinct()
            ->orderBy('product_id')
            ->each(function ($row): void {
                $variantId = DB::table('product_variants')
                    ->where('product_id', $row->product_id)
                    ->whereNotNull('color_id')
                    ->whereNull('deleted_at')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->value('id');

                if ($variantId) {
                    DB::table('product_variants')
                        ->where('id', $variantId)
                        ->update(['is_default' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
