<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\Forecasting\GenerateEggForecast;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date
            ? Carbon::parse($request->date)
            : today();

        $view = $request->view ?? 'day';

        $query = Production::query();

        if ($view == 'day') {

            $query->whereDate('production_date', $date);

        } elseif ($view == 'week') {

            $query->whereBetween('production_date', [

                $date->copy()->startOfWeek(),

                $date->copy()->endOfWeek()

            ]);

        } else {

            $query->whereMonth('production_date', $date->month)
                  ->whereYear('production_date', $date->year);

        }

        $productions = $query
            ->orderBy('batch_id')
            ->get();

        $totalEggs = $productions->sum('eggs_produced');

        $predictedEggs = app(GenerateEggForecast::class)->predictedEggs();

        // Fetch QR transactions related to the selected productions
        $productionIds = $productions->pluck('production_id');

       $qrTransactions = QrTransaction::with(['qrRecord','production'])
    ->whereIn('production_id', $productionIds)
    ->get();

            $selectedDate = $date;
    $viewType = $view;

    // Determine view based on route name prefix (staff. / owner. / manager.)
    $routeName = $request->route()->getName(); // e.g. 'staff.production'
    $role = explode('.', $routeName)[0]; // 'staff'

    return view($role . '.production', compact(
        'productions',
        'totalEggs',
        'predictedEggs',
        'qrTransactions',
        'selectedDate',
        'viewType'
        ));
    }
}