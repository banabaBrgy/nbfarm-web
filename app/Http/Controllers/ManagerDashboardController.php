<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\InventoryItem;
use App\Models\Dispatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\DailyInventory;
use App\Services\DashboardKpis;
use App\Services\EggProductionChart;
use App\Services\RoleTodoList;
use App\Services\SystemEffectiveness;
use App\Services\Forecasting\GenerateEggForecast;

class ManagerDashboardController extends Controller
{
    public function index(Request $request)
{
    $request->validate([
        'start_date' => 'nullable|required_with:end_date|date',
        'end_date' => 'nullable|required_with:start_date|date|after_or_equal:start_date',
        'effectiveness_from' => 'nullable|required_with:effectiveness_to|date',
        'effectiveness_to' => 'nullable|required_with:effectiveness_from|date|after_or_equal:effectiveness_from',
    ]);

    app(DailyInventory::class)->applyForToday();

        // ============================
        // PRODUCTION TODAY
        // ============================

        $eggsToday = Production::whereDate(
            'production_date',
            today()
        )->sum('eggs_produced');

        $eggsYesterday = Production::whereDate(
            'production_date',
            today()->subDay()
        )->sum('eggs_produced');

        $eggsChange = $eggsYesterday > 0
            ? round((($eggsToday - $eggsYesterday) / $eggsYesterday) * 100, 1)
            : 0;

        // ============================
        // INVENTORY
        // ============================

        $feedStock = InventoryItem::where(
            'item_name',
            'Feeds'
        )->sum('current_stock');

        $feedMin = InventoryItem::where(
            'item_name',
            'Feeds'
        )->sum('min_level');

        $feedLow = $feedStock <= $feedMin;

        $vitamins = InventoryItem::where(
            'item_name',
            'Vitamins'
        )->sum('current_stock');

        $vitaminMin = InventoryItem::where(
            'item_name',
            'Vitamins'
        )->sum('min_level');

        $vitaminLow = $vitamins <= $vitaminMin;

        $eggTrays = (float) (
            InventoryItem::where('item_name', 'Egg Trays')
                ->value('current_stock') ?? 0
        );

        // ============================
        // TOTAL PRODUCTION
        // ============================

        $eggsTotal = Production::leftoverStock();

        // ============================
        // DISPATCH
        // ============================

        $dispatchedTrays = DB::table('dispatch_items')
            ->sum('quantity');

        $pendingDeliveries = Dispatch::whereIn(
            'status',
            [
                'Pending',
                'On Transit'
            ]
        )->count();

        // ============================
        // REVENUE
        // ============================

        $revenueToday = Transaction::whereDate(
            'transaction_date',
            today()
        )->sum('price');

        // ============================
        // LOW STOCK ALERT
        // ============================

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
        $effectiveness = app(SystemEffectiveness::class)->forRequest($request);
        $roleTodos = app(RoleTodoList::class)->forUser(auth()->user());

        Log::info('MANAGER FORECAST DIAGNOSTIC', [
            'predicted_eggs' => $predictedEggs,
            'summary_eggs' => $forecastSummary['predicted_eggs'] ?? null,
            'database' => config('database.connections.mysql.database'),
        ]);

        // ============================
        // RETURN DASHBOARD
        // ============================

        return view(
            'manager.dashboard',
            array_merge(compact(
                'eggsToday',
                'eggsChange',

                'feedStock',
                'feedMin',
                'feedLow',

                'vitamins',
                'vitaminMin',
                'vitaminLow',
                'eggTrays',

                'eggsTotal',

                'dispatchedTrays',
                'pendingDeliveries',

                'revenueToday',

                'lowStockAlert',

                'predictedEggs',
                'forecastSummary',
                'previousForecastSummary',
                'eggProductionChart',
                'effectiveness',
                'roleTodos'
            ), app(DashboardKpis::class)->summary())
        );
    }
}
