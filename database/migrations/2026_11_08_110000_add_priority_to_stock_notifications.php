<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_notifications', 'priority')) {
                $table->string('priority', 10)->default('low')->after('type');
            }
        });

        if (! Schema::hasTable('stock_notifications')) {
            return;
        }

        DB::table('stock_notifications')
            ->where('type', 'production_drop')
            ->update(['priority' => 'high']);

        DB::table('stock_notifications')
            ->where('type', 'low_stock')
            ->whereNotNull('current_stock')
            ->whereNotNull('min_level')
            ->whereColumn('current_stock', '<', 'min_level')
            ->update([
                'priority' => 'high',
                'title' => 'Critical low stock',
            ]);

        DB::table('stock_notifications')
            ->where('type', 'low_stock')
            ->whereNotNull('current_stock')
            ->whereNotNull('min_level')
            ->whereColumn('current_stock', '>=', 'min_level')
            ->whereRaw('current_stock < min_level * 1.2')
            ->update([
                'priority' => 'medium',
                'title' => 'Stock approaching minimum',
            ]);

        DB::table('stock_notifications')
            ->where('type', 'low_stock')
            ->where('priority', 'low')
            ->update([
                'priority' => 'high',
                'title' => 'Critical low stock',
            ]);
    }

    public function down(): void
    {
        Schema::table('stock_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('stock_notifications', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
