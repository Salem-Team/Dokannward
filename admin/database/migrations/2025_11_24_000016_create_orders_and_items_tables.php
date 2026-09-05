<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->uuid('user_id')->nullable()->index();
            $table->string('status', 50)->default('pending')->index();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->uuid('shipping_address_id')->index();
            $table->uuid('billing_address_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('fulfillment_provider', 100)->nullable();
            $table->string('delivery_tracking_number', 100)->nullable();
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->uuid('canceled_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shipping_address_id')->references('id')->on('addresses')->restrictOnDelete();
            $table->foreign('billing_address_id')->references('id')->on('addresses')->restrictOnDelete();
            $table->foreign('canceled_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('product_id')->index();
            $table->uuid('variant_id')->nullable()->index();
            $table->string('sku', 64);
            $table->string('name', 255);
            $table->decimal('unit_price', 10, 2);
            $table->integer('qty')->default(1);
            $table->decimal('line_total', 12, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
