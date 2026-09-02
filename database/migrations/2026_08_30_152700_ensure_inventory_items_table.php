<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name')->unique();
            $table->string('unit');
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('min_level', 12, 2)->default(0);
            $table->decimal('daily_stock', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Production already had this table; do not drop it on rollback.
    }
};
