<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing products to convert string short_description to JSON format
        // We need to do this BEFORE changing the column type
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            $shortDesc = $product->short_description;

            // If it's null, set default empty JSON
            if (is_null($shortDesc) || $shortDesc === '') {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'short_description' => json_encode(['en' => '', 'ar' => ''])
                    ]);
                continue;
            }

            // Try to decode if it's already JSON
            $decoded = json_decode($shortDesc, true);

            // If it's not valid JSON, convert string to JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'short_description' => json_encode([
                            'en' => $shortDesc,
                            'ar' => $shortDesc // Use same text as fallback
                        ])
                    ]);
            }
        }

        // Now change the column type to JSON
        Schema::table('products', function (Blueprint $table) {
            $table->json('short_description')->nullable()->change();
        });
    }    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert JSON back to string (taking English version)
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            $shortDesc = json_decode($product->short_description, true);

            if (is_array($shortDesc)) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'short_description' => $shortDesc['en'] ?? ''
                    ]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->text('short_description')->nullable()->change();
        });
    }
};
