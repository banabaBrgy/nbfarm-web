<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\InventoryItem;
use App\Models\Dispatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\DailyInventory;
use App\Services\Forecasting\GenerateEggForecast;
use App\Services\ProductionYearTrend;

class ManagerDashboardController extends Controller
{
    public function index(Request $request)
    {
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


        $eggTrays = Production::leftoverTrays();



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



        // ============================
        // WEEKLY PRODUCTION CHART
        // ============================

        $selectedDate = $request->date
            ? Carbon::parse($request->date)
            : Carbon::today();


        $weekStart = $selectedDate->copy()
            ->startOfWeek(Carbon::MONDAY);


        $weekEnd = $weekStart->copy()
            ->addDays(6);


        $today = $selectedDate;



        $weekly = Production::selectRaw("
                DATE(production_date) as production_date,
                SUM(eggs_produced) as total
            ")
            ->whereBetween(
                'production_date',
                [
                    $weekStart->copy()->startOfDay(),
                    $weekEnd->copy()->endOfDay()
                ]
            )
            ->groupBy(
                DB::raw('DATE(production_date)')
            )
            ->orderBy(
                'production_date'
            )
            ->get()
            ->keyBy(function($row){

                return Carbon::parse(
                    $row->production_date
                )->format('Y-m-d');

            });



        $eggChartLabels = [];
        $eggChartData = [];


        $cursor = $weekStart->copy();


        while($cursor->lte($weekEnd)){

            $key = $cursor->format('Y-m-d');


            $eggChartLabels[] = $cursor->format('D');


            if($cursor->lte($today)){

                $total = $weekly->has($key)
                    ? (int) $weekly[$key]->total
                    : 0;

                $eggChartData[] = $total > 0 ? $total : null;

            }else{

                $eggChartData[] = null;

            }


            $cursor->addDay();

        }



        $forecast = app(GenerateEggForecast::class)->forWeek($weekStart, $weekEnd);
        $eggForecastLabels = $forecast['labels'];
        $eggForecastData = $forecast['data'];
        $forecastSummary = app(GenerateEggForecast::class)->summary();
        $predictedEggs = (int) ($forecastSummary['predicted_eggs'] ?? $forecast['predicted_eggs']);

        $productionTrend = app(ProductionYearTrend::class)
            ->forYear($request->query('trend_year'));

        return view(
            'manager.dashboard',
            compact(

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

                'eggChartLabels',
                'eggChartData',

                'eggForecastLabels',
                'eggForecastData',

                'predictedEggs',
                'forecastSummary',

                'productionTrend'

            )
        );
    }
}