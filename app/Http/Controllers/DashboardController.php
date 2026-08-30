<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Production;
use App\Services\DailyInventory;
use App\Services\Forecasting\GenerateEggForecast;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        app(DailyInventory::class)->applyForToday();


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
}