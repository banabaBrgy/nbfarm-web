<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_items', 'daily_stock')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->decimal('daily_stock', 12, 2)->nullable()->after('min_level');
            });
        }

        DB::table('inventory_items')
            ->where('item_name', 'Egg Trays')
            ->whereNull('daily_stock')
            ->update([
                'daily_stock' => DB::raw('GREATEST(500, current_stock)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'daily_stock')) {
                $table->dropColumn('daily_stock');
            }
        });
    }
};
