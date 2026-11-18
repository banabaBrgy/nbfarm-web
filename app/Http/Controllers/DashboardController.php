<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Production;
use App\Services\DailyInventory;
use App\Services\DashboardKpis;
use App\Services\EggProductionChart;
use App\Services\Forecasting\GenerateEggForecast;
use App\Services\RoleTodoList;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
   public function index(Request $request)
{
    $request->validate([
        'start_date' => 'nullable|required_with:end_date|date',
        'end_date' => 'nullable|required_with:start_date|date|after_or_equal:start_date',
    ]);

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

        $eggTrays = (float) (
            InventoryItem::where('item_name', 'Egg Trays')
                ->value('current_stock') ?? 0
        );

        $lowStockAlert = InventoryItem::whereColumn(
            'current_stock',
            '<=',
            'min_level'
        )->count();

        $forecastService = app(GenerateEggForecast::class);
        $forecastSummary = $forecastService->summary();
        $previousForecastSummary = $forecastService->previousSummary();
        $predictedEggs = (int) ($forecastSummary['predicted_eggs'] ?? 0);
        $eggProductionChart = app(EggProductionChart::class)->forRequest($request);
        $roleTodos = app(RoleTodoList::class)->forUser(auth()->user());

        /*
        |--------------------------------------------------------------------------
        | Return Dashboard
        |--------------------------------------------------------------------------
        */

        return view('staff.dashboard', array_merge(compact(
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
            'predictedEggs',
            'forecastSummary',
            'previousForecastSummary',
            'eggProductionChart',
            'roleTodos',
        ), app(DashboardKpis::class)->summary()));
    }
}
