<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->constrained('inventory_items')
                ->nullOnDelete();
            $table->string('item_name');
            $table->string('action');
            $table->decimal('quantity_change', 12, 2)->default(0);
            $table->decimal('stock_before', 12, 2)->nullable();
            $table->decimal('stock_after', 12, 2)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_histories');
    }
};
