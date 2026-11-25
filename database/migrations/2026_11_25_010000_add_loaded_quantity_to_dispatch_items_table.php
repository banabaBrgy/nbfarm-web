<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dispatch_items')) {
            return;
        }

        if (! Schema::hasColumn('dispatch_items', 'loaded_quantity')) {
            Schema::table('dispatch_items', function (Blueprint $table) {
                $table->unsignedInteger('loaded_quantity')->default(0)->after('quantity');
            });
        }

        // Already shipped/delivered orders count as fully loaded so stock stays reserved.
        if (Schema::hasTable('dispatches')) {
            $shippedIds = DB::table('dispatches')
                ->whereIn('status', ['On Transit', 'Delivered'])
                ->pluck('id');

            if ($shippedIds->isNotEmpty()) {
                DB::table('dispatch_items')
                    ->whereIn('dispatch_id', $shippedIds)
                    ->update([
                        'loaded_quantity' => DB::raw('quantity'),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispatch_items') && Schema::hasColumn('dispatch_items', 'loaded_quantity')) {
            Schema::table('dispatch_items', function (Blueprint $table) {
                $table->dropColumn('loaded_quantity');
            });
        }
    }
};
