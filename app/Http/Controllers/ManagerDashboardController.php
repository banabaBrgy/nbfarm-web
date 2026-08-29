<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\InventoryItem;
use App\Models\Dispatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ForecastingService;

class ManagerDashboardController extends Controller
{
    public function index(Request $request)
    {
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


        $supplements = InventoryItem::where(
            'item_name',
            'Supplements'
        )->sum('current_stock');


        $eggTrays = InventoryItem::where(
            'item_name',
            'Egg Trays'
        )->sum('current_stock');



        // ============================
        // TOTAL PRODUCTION
        // ============================

        $eggsTotal = Production::sum(
            'eggs_produced'
        );



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
        // FORECAST CARD
        // ALWAYS FROM LATEST PRODUCTION
        // ============================

        $predictedEggs = ForecastingService::latestPrediction();



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

                $eggChartData[] =
                    $weekly->has($key)
                    ? (int)$weekly[$key]->total
                    : 0;

            }else{

                $eggChartData[] = null;

            }


            $cursor->addDay();

        }



        // ============================
        // PYTHON FORECAST CHART LINE
        // ============================

        $eggForecastLabels = [];
        $eggForecastData = [];


        $pythonPath = env(
            'PYTHON_PATH',
            'python'
        );


        $scriptPath = base_path(
            'scripts/forecast_eggs.py'
        );



        if(file_exists($scriptPath)){


            $command =
                escapeshellcmd($pythonPath)
                .' '
                .escapeshellarg($scriptPath)
                .' --host='
                .escapeshellarg(env('DB_HOST','127.0.0.1'))
                .' --database='
                .escapeshellarg(env('DB_DATABASE'))
                .' --user='
                .escapeshellarg(env('DB_USERNAME'))
                .' --password='
                .escapeshellarg(env('DB_PASSWORD'))
                .' --start-date='
                .escapeshellarg($weekStart->format('Y-m-d'))
                .' --end-date='
                .escapeshellarg($weekEnd->format('Y-m-d'));



            $output = shell_exec(
                $command.' 2>&1'
            );


            $pythonForecast = json_decode(
                $output,
                true
            );



            if(
                is_array($pythonForecast)
                &&
                empty($pythonForecast['error'])
            ){

                $eggForecastLabels =
                    $pythonForecast['labels'] ?? [];


                $eggForecastData =
                    $pythonForecast['data'] ?? [];

            }

        }



        return view(
            'manager.dashboard',
            compact(

                'eggsToday',
                'eggsChange',

                'feedStock',
                'feedMin',
                'feedLow',

                'supplements',
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

                'predictedEggs'

            )
        );
    }
}