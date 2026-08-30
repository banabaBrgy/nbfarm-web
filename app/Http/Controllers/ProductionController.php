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
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : null;

        if ($date && (int) $date->year !== Production::visibleYear()) {
            $date = null;
        }

        $view = $request->view ?? 'day';

        $query = Production::query()->presentYear();

        if ($date) {
            $query->whereDate('production_date', $date);
        }

        $productions = $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(function ($inner) use ($term) {
                    $inner->where('batch_id', 'like', '%'.$term.'%')
                        ->orWhereDate('production_date', $term);
                });
            })
            ->orderBy('production_date')
            ->orderBy('batch_id')
            ->get();

        $totalEggs = (int) Production::whereDate('production_date', today())
            ->sum('eggs_produced');

        $activeBatches = count(QrRecord::BATCH_IDS);

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
        'activeBatches',
        'predictedEggs',
        'qrTransactions',
        'selectedDate',
        'viewType'
        ));
    }
}