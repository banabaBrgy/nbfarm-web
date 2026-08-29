<?php

namespace App\Http\Controllers;

use App\Models\Production;
use Illuminate\Support\Facades\DB;
use App\Models\DailyInventoryLog;
use App\Models\Forecast;
use App\Models\InventoryItem;

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

            // FEEDS
            InventoryItem::where('item_name', 'Feeds')
                ->decrement('current_stock');

            // SUPPLEMENT
            InventoryItem::where('item_name', 'Supplement')
                ->decrement('current_stock');


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
                'total' => $total
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Forecast
        |--------------------------------------------------------------------------
        */

        $forecast = Forecast::latest('forecast_date')
            ->first();


        $predictedEggs = $forecast?->predicted_eggs ?? 0;



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
}