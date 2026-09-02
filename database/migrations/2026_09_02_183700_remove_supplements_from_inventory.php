<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        $names = ['Supplements', 'Supplement'];

        DB::table('inventory_items')->whereIn('item_name', $names)->delete();

        if (Schema::hasTable('inventory')) {
            $query = DB::table('inventory')->whereIn('item_name', $names);

            if (Schema::hasColumn('inventory', 'item_type')) {
                $query->orWhereIn('item_type', ['Supplement', 'Supplements']);
            }

            $query->delete();
        }
    }

    public function down(): void
    {
        // Supplements were removed by request; do not restore sample stock.
    }
};
