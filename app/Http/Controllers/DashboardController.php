<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Production;
use App\Services\DailyInventory;
use App\Services\Forecasting\GenerateEggForecast;
use App\Services\ProductionYearTrend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        app(DailyInventory::class)->applyForToday();


        $eggsToday = (int) Production::whereDate('production_date', today())
            ->sum('eggs_produced');

        $eggsYesterday = (int) Production::whereDate(
            'production_date',
            today()->subDay()
        )->sum('eggs_produced');

        $eggsChange = $eggsYesterday > 0
            ? round((($eggsToday - $eggsYesterday) / $eggsYesterday) * 100, 1)
            : 0;

        $activeBatches = (int) Production::whereDate('production_date', today())
            ->distinct('batch_id')
            ->count('batch_id');

        $feedStock = InventoryItem::where('item_name', 'Feeds')
            ->sum('current_stock');

        $feedMin = InventoryItem::where('item_name', 'Feeds')
            ->sum('min_level');

        $feedLow = $feedStock <= $feedMin;

        $vitamins = InventoryItem::where('item_name', 'Vitamins')
            ->sum('current_stock');

        $vitaminMin = InventoryItem::where('item_name', 'Vitamins')
            ->sum('min_level');

        $vitaminLow = $vitamins <= $vitaminMin;

        $eggsTotal = Production::leftoverStock();
        $eggTrays = Production::leftoverTrays();

        $lowStockAlert = InventoryItem::whereColumn(
            'current_stock',
            '<=',
            'min_level'
        )->count();


        /*
        |--------------------------------------------------------------------------
        | Weekly Production Chart
        |--------------------------------------------------------------------------
        | Always show last 7 days
        |--------------------------------------------------------------------------
        */

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $today = Carbon::today();

        $weekly = Production::selectRaw('
                DATE(production_date) as production_date,
                SUM(eggs_produced) as total
            ')
            ->whereBetween('production_date', [
                $weekStart->copy()->startOfDay(),
                $weekEnd->copy()->endOfDay(),
            ])
            ->groupBy(DB::raw('DATE(production_date)'))
            ->orderBy('production_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        $eggChartLabels = [];
        $eggChartData = [];
        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $key = $cursor->format('Y-m-d');
            $eggChartLabels[] = $cursor->format('D');

            if ($cursor->lte($today)) {
                $total = $weekly->has($key) ? (int) $weekly[$key]->total : 0;
                $eggChartData[] = $total > 0 ? $total : null;
            } else {
                $eggChartData[] = null;
            }

            $cursor->addDay();
        }

        $forecast = app(GenerateEggForecast::class)->forWeek($weekStart, $weekEnd);
        $eggForecastLabels = $forecast['labels'];
        $eggForecastData = $forecast['data'];
        $predictedEggs = $forecast['predicted_eggs'];

        $productionTrend = app(ProductionYearTrend::class)
            ->forYear($request->query('trend_year'));



        /*
        |--------------------------------------------------------------------------
        | Return Dashboard
        |--------------------------------------------------------------------------
        */

        return view('staff.dashboard', compact(
            'eggsToday',
            'eggsChange',
            'activeBatches',
            'feedStock',
            'feedMin',
            'feedLow',
            'vitamins',
            'vitaminMin',
            'vitaminLow',
            'eggsTotal',
            'lowStockAlert',
            'eggTrays',
            'eggChartLabels',
            'eggChartData',
            'eggForecastLabels',
            'eggForecastData',
            'predictedEggs',
            'productionTrend',
        ));
    }
}