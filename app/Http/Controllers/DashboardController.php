<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use App\Models\DailyInventoryLog;
use App\Models\Forecast;
use App\Models\InventoryItem;

class DashboardController extends Controller
{
   public function index()
{
    $today = now()->toDateString();

if (!DailyInventoryLog::where('log_date', $today)->exists()) {

    // FEEDS
    InventoryItem::where('item_name', 'Feeds')
        ->decrement('current_stock');

    // VITAMINS
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
    $todayProduction = Production::whereDate('production_date', $latestDate)
        ->sum('eggs_produced');

    // Active Batches
    $activeBatches = Production::whereDate('production_date', $latestDate)
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
    */

    $weeklyProduction = Production::selectRaw("
        DATE(production_date) as production_date,
        SUM(eggs_produced) as total
    ")
    ->whereBetween('production_date', [
        now()->subDays(6)->startOfDay(),
        now()->endOfDay()
    ])
    ->groupBy(DB::raw('DATE(production_date)'))
    ->orderBy('production_date')
    ->get();

    $forecast = Forecast::latest('forecast_date')->first();

$predictedEggs = $forecast?->predicted_eggs ?? 0; 

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