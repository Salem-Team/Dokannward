<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 100); // 'order', 'low_stock', 'payment', 'customer'
            $table->string('title', 255);
            $table->text('message');
            $table->string('icon', 50)->nullable(); // font awesome icon class
            $table->string('color', 50)->default('blue'); // blue, yellow, green, red
            $table->string('link')->nullable(); // URL to navigate to
            $table->uuid('related_id')->nullable(); // ID of related entity (order, product, etc)
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['is_read', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
