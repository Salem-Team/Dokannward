<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A tiny single-row counter table used to hand out guaranteed-unique,
 * sequential order numbers (see Order::nextSequence()). A dedicated table
 * (rather than e.g. COUNT(*) on `orders`) means the number never repeats or
 * goes backwards even if orders are later deleted, and MySQL's row lock on
 * the UPDATE makes the increment safe under concurrent checkouts.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        // Seed with a single row so subsequent generation is a plain
        // atomic UPDATE (no INSERT race to worry about). Starts right after
        // the highest existing order count so numbering continues smoothly
        // for stores that already have historical orders.
        $existingOrders = Schema::hasTable('orders') ? DB::table('orders')->count() : 0;

        DB::table('order_number_sequences')->insert([
            'id' => 1,
            'next_number' => $existingOrders + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
