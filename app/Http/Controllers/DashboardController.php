<?php

namespace App\Http\Controllers;

use App\Models\DailyInventoryLog;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Services\Forecasting\GenerateEggForecast;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Daily Inventory Deduction
        |--------------------------------------------------------------------------
        */

        if (!DailyInventoryLog::where('log_date', $today)->exists()) {
            $this->decrementDailySupply('Feeds', 'Daily feed use');
            $this->decrementDailySupply('Supplement', 'Daily supplement use');
            $this->decrementDailySupply('Supplements', 'Daily supplement use');

            DailyInventoryLog::create([
                'log_date' => $today
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Latest Production Date
        |--------------------------------------------------------------------------
        */

        $latestDate = Production::max('production_date');


        /*
        |--------------------------------------------------------------------------
        | Dashboard Cards
        |--------------------------------------------------------------------------
        */

        // Total Eggs Today
        $todayProduction = Production::whereDate(
                'production_date',
                $latestDate
            )
            ->sum('eggs_produced');


        // Active Batches
        $activeBatches = Production::whereDate(
                'production_date',
                $latestDate
            )
            ->distinct('batch_id')
            ->count('batch_id');


        // Feeds Available
        $feeds = InventoryItem::where('item_name', 'Feeds')
            ->sum('current_stock');


        // Egg Trays Available
        $eggTrays = InventoryItem::where('item_name', 'Egg Trays')
            ->sum('current_stock');


        /*
        |--------------------------------------------------------------------------
        | Weekly Production Chart
        |--------------------------------------------------------------------------
        | Always show last 7 days
        |--------------------------------------------------------------------------
        */

        $weeklyProduction = collect();


        for ($i = 6; $i >= 0; $i--) {

            $date = now()->subDays($i);


            $total = Production::whereDate(
                    'production_date',
                    $date->toDateString()
                )
                ->sum('eggs_produced');


            $weeklyProduction->push([
                'production_date' => $date->toDateString(),
                'total' => $total > 0 ? $total : null,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Forecast
        |--------------------------------------------------------------------------
        */

        $predictedEggs = app(GenerateEggForecast::class)->predictedEggs();



        /*
        |--------------------------------------------------------------------------
        | Return Dashboard
        |--------------------------------------------------------------------------
        */

        return view('staff.dashboard', compact(
            'todayProduction',
            'activeBatches',
            'feeds',
            'eggTrays',
            'weeklyProduction',
            'predictedEggs'
        ));
    }

    private function decrementDailySupply(string $itemName, string $note): void
    {
        $item = InventoryItem::where('item_name', $itemName)->first();

        if (! $item) {
            return;
        }

        $before = (float) $item->current_stock;
        $after = max(0, $before - 1);
        $item->update(['current_stock' => $after]);

        InventoryItemHistory::record(
            $item->id,
            $item->item_name,
            'Deducted',
            -1,
            $before,
            $after,
            $note
        );
    }
}