<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use App\Models\Production;
use App\Models\QrTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\Forecasting\GenerateEggForecast;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $minDate = Carbon::parse(Production::sampleStartDate());
        $maxDate = today();

        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : today();

        if ($date->lt($minDate) || $date->gt($maxDate)) {
            $date = today();
        }

        $view = $request->view ?? 'day';

        $productions = Production::query()
            ->whereDate('production_date', $date)
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where('batch_id', 'like', '%'.$term.'%');
            })
            ->orderBy('batch_id')
            ->get();

        $totalEggs = (int) $productions->sum('eggs_produced');

        $predictedEggs = app(GenerateEggForecast::class)->predictedEggs();

        $forecastHistory = Forecast::query()
            ->orderByDesc('forecast_date')
            ->paginate(5, ['*'], 'forecast_page')
            ->withQueryString();

        $productionIds = $productions->pluck('production_id');

        $qrTransactions = QrTransaction::with(['qrRecord', 'production'])
            ->whereIn('production_id', $productionIds)
            ->get();

        $selectedDate = $date;
        $viewType = $view;

        $routeName = $request->route()->getName();
        $role = explode('.', $routeName)[0];

        return view($role.'.production', compact(
            'productions',
            'totalEggs',
            'predictedEggs',
            'forecastHistory',
            'qrTransactions',
            'selectedDate',
            'viewType'
        ));
    }
}
