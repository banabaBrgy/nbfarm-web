<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\InventoryItem;
use App\Models\Dispatch;
use App\Models\Transaction;
use App\Services\Forecasting\GenerateEggForecast;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OwnerDashboardController extends Controller
{
    public function index()
    {
        $eggsToday = Production::whereDate('production_date', today())
            ->sum('eggs_produced');

        $eggsYesterday = Production::whereDate(
            'production_date',
            today()->subDay()
        )->sum('eggs_produced');

        $eggsChange = $eggsYesterday > 0
            ? round((($eggsToday - $eggsYesterday) / $eggsYesterday) * 100, 1)
            : 0;

        $feedStock = InventoryItem::where('item_name', 'Feeds')
            ->sum('current_stock');

        $feedMin = InventoryItem::where('item_name', 'Feeds')
            ->sum('min_level');

        $feedLow = $feedStock <= $feedMin;

        $supplements = InventoryItem::where('item_name', 'Supplements')
            ->sum('current_stock');

        $eggTrays = InventoryItem::where('item_name', 'Egg Trays')
            ->sum('current_stock');

        $eggsTotal = Production::sum('eggs_produced');

        $dispatchedTrays = DB::table('dispatch_items')
            ->sum('quantity');

        $pendingDeliveries = Dispatch::whereIn('status', [
            'Pending',
            'On Transit'
        ])->count();

        $revenueToday = Transaction::whereDate(
            'transaction_date',
            today()
        )->sum('price');

        $lowStockAlert = InventoryItem::whereColumn(
            'current_stock',
            '<=',
            'min_level'
        )->count();

        // ============================
        // WEEKLY CHART: iisang Mon-Sun na linggo
        // Bars: actual data lang, null sa mga araw na hindi pa nangyayari
        // Line: forecast/predicted values sa BUONG linggo (kasama
        //       yung mga araw na nakalipas na, para makumpara sa actual)
        // ============================
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6); // Sunday
        $today = Carbon::today();

        $weekly = Production::selectRaw("
        DATE(production_date) as production_date,
        SUM(eggs_produced) as total
    ")
            ->whereBetween('production_date', [
                $weekStart->copy()->startOfDay(),
                $weekEnd->copy()->endOfDay()
            ])
            ->groupBy(DB::raw('DATE(production_date)'))
            ->orderBy('production_date')
            ->get()
            ->keyBy(fn($row) => Carbon::parse($row->production_date)->format('Y-m-d'));

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
                // Araw pa lang darating — null para walang bar na lalabas
                $eggChartData[] = null;
            }

            $cursor->addDay();
        }

        $forecast = app(GenerateEggForecast::class)->forWeek($weekStart, $weekEnd);
        $eggForecastLabels = $forecast['labels'];
        $eggForecastData = $forecast['data'];
        $predictedEggs = $forecast['predicted_eggs'];

        return view('owner.dashboard', compact(
            'eggsToday',
            'eggsChange',
            'feedStock',
            'feedMin',
            'feedLow',
            'dispatchedTrays',
            'pendingDeliveries',
            'revenueToday',
            'eggsTotal',
            'eggTrays',
            'supplements',
            'lowStockAlert',
            'eggChartLabels',
            'eggChartData',
            'eggForecastLabels',
            'eggForecastData',
            'predictedEggs'
        ));
    }
}