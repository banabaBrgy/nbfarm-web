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


        $todayProduction = (int) Production::whereDate('production_date', today())
            ->sum('eggs_produced');

        $activeBatches = (int) Production::whereDate('production_date', today())
            ->distinct('batch_id')
            ->count('batch_id');


        // Feeds Available
        $feeds = InventoryItem::where('item_name', 'Feeds')
            ->sum('current_stock');

        $lowStockAlert = InventoryItem::whereColumn(
            'current_stock',
            '<=',
            'min_level'
        )->count();


        // Egg Trays Available — same formula as Inventory: 1000 − (Eggs Stock ÷ 30)
        $eggTrays = Production::leftoverTrays();


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
            'lowStockAlert',
            'eggTrays',
            'weeklyProduction',
            'predictedEggs'
        ));
    }
}