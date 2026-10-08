<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class WeeklyProductionSeeder extends Seeder
{
    /**
     * 2026 harvest is loaded from the client PDF by HistoricalProductionSeeder.
     * This only refreshes today's egg-tray stock from that harvest.
     */
    public function run(): void
    {
        $todayEggs = (int) Production::whereDate('production_date', Carbon::today())
            ->sum('eggs_produced');
        $traysUsedToday = intdiv($todayEggs, 30);
        $trayStock = max(0, 500 - $traysUsedToday);

        $trayUpdate = [
            'current_stock' => $trayStock,
            'min_level' => 100,
        ];
        if (Schema::hasColumn('inventory_items', 'daily_stock')) {
            $trayUpdate['daily_stock'] = 500;
        }

        InventoryItem::where('item_name', 'Egg Trays')->update($trayUpdate);

        Inventory::where('item_type', 'Egg Tray')->update([
            'quantity' => $trayStock,
            'minimum_stock' => 100,
        ]);

        $this->command?->info('Egg tray stock synced from today\'s client harvest.');
    }
}
