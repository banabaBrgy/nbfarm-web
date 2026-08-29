<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\InventoryItem;
use App\Models\Dispatch;
use App\Models\Transaction;
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

        // ============================
        // FORECAST (Linear Regression via Python script)
        // Buong linggo (Mon-Sun) ang tinatanong, kasama yung mga araw
        // na nakalipas na, para may makumparang forecast vs actual
        // ============================
        $eggForecastLabels = [];
        $eggForecastData = [];

        $pythonPath = env('PYTHON_PATH', 'python');
        $scriptPath = base_path('scripts/forecast_eggs.py');

        if (file_exists($scriptPath)) {
            $command = escapeshellcmd($pythonPath) . ' ' . escapeshellarg($scriptPath)
                . ' --host=' . escapeshellarg(env('DB_HOST', '127.0.0.1'))
                . ' --database=' . escapeshellarg(env('DB_DATABASE'))
                . ' --user=' . escapeshellarg(env('DB_USERNAME'))
                . ' --password=' . escapeshellarg(env('DB_PASSWORD'))
                . ' --start-date=' . escapeshellarg($weekStart->format('Y-m-d'))
                . ' --end-date=' . escapeshellarg($weekEnd->format('Y-m-d'));

            $output = shell_exec($command . ' 2>&1');
            $forecast = json_decode($output, true);

            if (is_array($forecast) && empty($forecast['error'])) {
                $eggForecastLabels = $forecast['labels'] ?? [];
                $eggForecastData = $forecast['data'] ?? [];
            } else {
                // Silent fail lang muna — bar chart mag-render pa rin,
                // wala lang dashed forecast line. Pwede i-log kung kailangan:
                // \Log::warning('Forecast script failed', ['output' => $output]);
            }
        }

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
            'eggForecastData'
        ));
    }
}