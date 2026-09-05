<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for admin order / inventory list + search paths. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
            $table->index('created_at', 'orders_created_at_index');
            $table->index('customer_email', 'orders_customer_email_index');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->index('phone', 'addresses_phone_index');
            $table->index('recipient_name', 'addresses_recipient_name_index');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('stock', 'product_variants_stock_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_at_index');
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_customer_email_index');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_phone_index');
            $table->dropIndex('addresses_recipient_name_index');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_stock_index');
        });
    }
};
