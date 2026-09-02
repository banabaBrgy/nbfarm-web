<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        if (! Schema::hasColumn('inventory_items', 'daily_stock')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->decimal('daily_stock', 12, 2)->nullable()->after('min_level');
            });
        }

        $trays = DB::table('inventory_items')
            ->where('item_name', 'Egg Trays')
            ->whereNull('daily_stock')
            ->get(['id', 'current_stock']);

        foreach ($trays as $tray) {
            $daily = max(500, (float) $tray->current_stock);
            DB::table('inventory_items')->where('id', $tray->id)->update([
                'daily_stock' => $daily,
            ]);
        }
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
