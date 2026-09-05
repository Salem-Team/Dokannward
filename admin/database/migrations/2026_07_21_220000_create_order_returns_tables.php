<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-issued returns / credit notes against completed purchases.
 * The admin picks what came back and types the refund amount (full or partial).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->string('return_number')->unique();
            $table->string('status', 50)->default('refunded')->index();
            $table->string('reason', 100)->nullable();
            $table->text('notes')->nullable();
            // Admin-authored refund — may differ from sum of line items.
            $table->decimal('refund_amount', 12, 2);
            $table->decimal('suggested_amount', 12, 2)->default(0);
            $table->uuid('processed_by')->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_return_id')->index();
            $table->uuid('order_item_id')->index();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_refund', 12, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_return_id')->references('id')->on('order_returns')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->restrictOnDelete();
        });

        // Reuse the order sequence table with a second counter row for return numbers.
        if (Schema::hasTable('order_number_sequences')) {
            $existing = Schema::hasTable('order_returns')
                ? DB::table('order_returns')->count()
                : 0;

            DB::table('order_number_sequences')->insertOrIgnore([
                'id' => 2,
                'next_number' => $existing + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');

        if (Schema::hasTable('order_number_sequences')) {
            DB::table('order_number_sequences')->where('id', 2)->delete();
        }
    }
};
