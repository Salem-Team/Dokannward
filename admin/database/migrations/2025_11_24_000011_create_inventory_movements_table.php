<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('variant_id')->index();
            $table->integer('change');
            $table->string('source_type', 50);
            $table->uuid('source_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->integer('current_stock')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
