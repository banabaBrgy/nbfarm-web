<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\Production;
use App\Models\Transaction;
use App\Services\Forecasting\GenerateEggForecast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardOverview
{
    public function __construct(
        private HistoricalInventory $inventory,
        private GenerateEggForecast $forecasts,
        private EggProductionChart $chart,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        $filter = DashboardDateFilter::fromRequest($request);
        $useCurrentInventory = $filter->isToday();
        $asOf = $filter->asOf();

        $eggsToday = (int) Production::query()
            ->whereDate('production_date', '>=', $filter->startDate())
            ->whereDate('production_date', '<=', $filter->endDate())
            ->sum('eggs_produced');

        [$previousStart, $previousEnd] = $this->previousWindow($filter);
        $eggsPrevious = (int) Production::query()
            ->whereDate('production_date', '>=', $previousStart)
            ->whereDate('production_date', '<=', $previousEnd)
            ->sum('eggs_produced');

        $eggsChange = $eggsPrevious > 0
            ? round((($eggsToday - $eggsPrevious) / $eggsPrevious) * 100, 1)
            : 0;

        $activeBatches = (int) Production::query()
            ->whereDate('production_date', '>=', $filter->startDate())
            ->whereDate('production_date', '<=', $filter->endDate())
            ->distinct('batch_id')
            ->count('batch_id');

        $feedStock = $this->inventory->namedStock('Feeds', $asOf, $useCurrentInventory);
        $feedMin = $this->inventory->namedMin('Feeds');
        $feedLow = $feedStock <= $feedMin;

        $vitamins = $this->inventory->namedStock('Vitamins', $asOf, $useCurrentInventory);
        $vitaminMin = $this->inventory->namedMin('Vitamins');
        $vitaminLow = $vitamins <= $vitaminMin;

        $eggTrays = $this->inventory->firstNamedStock('Egg Trays', $asOf, $useCurrentInventory);
        $eggsTotal = Production::leftoverStock($useCurrentInventory ? null : $asOf);
        $lowStockAlert = $this->inventory->lowStockCount($asOf, $useCurrentInventory);

        if ($filter->isToday()) {
            $dispatchedTrays = (float) DB::table('dispatch_items')->sum('quantity');
            $pendingDeliveries = Dispatch::query()
                ->whereIn('status', ['Pending', 'On Transit'])
                ->count();
        } else {
            $dispatchedTrays = (float) DB::table('dispatch_items')
                ->join('dispatches', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
                ->whereDate('dispatches.dispatch_date', '>=', $filter->startDate())
                ->whereDate('dispatches.dispatch_date', '<=', $filter->endDate())
                ->sum('dispatch_items.quantity');

            $pendingDeliveries = Dispatch::query()
                ->whereIn('status', ['Pending', 'On Transit'])
                ->whereDate('dispatch_date', '>=', $filter->startDate())
                ->whereDate('dispatch_date', '<=', $filter->endDate())
                ->count();
        }

        $revenueToday = (float) Transaction::query()
            ->whereDate('transaction_date', '>=', $filter->startDate())
            ->whereDate('transaction_date', '<=', $filter->endDate())
            ->sum('price');

        $forecastSummary = $this->forecasts->summaryFrom($asOf);
        $previousForecastSummary = $this->forecasts->previousSummaryFrom($asOf);
        $predictedEggs = (int) ($forecastSummary['predicted_eggs'] ?? 0);
        $eggProductionChart = $this->chart->forRequest($request, $filter);

        return [
            'dateFilter' => $filter->toView(),
            'overviewSubtitle' => $filter->subtitle(),
            'eggsTitle' => $filter->isToday() ? 'Total Eggs Today' : 'Total Eggs',
            'eggsChangeLabel' => $filter->isToday()
                ? 'vs yesterday'
                : ($filter->isRange() ? 'vs prior period' : 'vs previous day'),
            'revenueTitle' => $filter->isToday() ? 'Revenue Today' : 'Revenue',
            'revenueSubtitle' => $filter->isToday() ? "Today's Income" : $filter->formatPeriod(),
            'predictedSubtitle' => $filter->isToday()
                ? 'Next-day forecast · tap for sizes'
                : 'Forecast from '.$filter->asOf()->format('M j, Y').' · tap for sizes',
            'activeBatchesSubtitle' => $filter->isToday() ? 'Currently producing' : 'Producing in this period',
            'inventoryAsOfLabel' => $filter->formatPeriod(),
            'inventoryMovements' => $this->inventory->movements($filter->start, $filter->end),
            'eggsToday' => $eggsToday,
            'eggsChange' => $eggsChange,
            'activeBatches' => $activeBatches,
            'feedStock' => $feedStock,
            'feedMin' => $feedMin,
            'feedLow' => $feedLow,
            'vitamins' => $vitamins,
            'vitaminMin' => $vitaminMin,
            'vitaminLow' => $vitaminLow,
            'eggTrays' => $eggTrays,
            'eggsTotal' => $eggsTotal,
            'lowStockAlert' => $lowStockAlert,
            'dispatchedTrays' => $dispatchedTrays,
            'pendingDeliveries' => $pendingDeliveries,
            'revenueToday' => $revenueToday,
            'predictedEggs' => $predictedEggs,
            'forecastSummary' => $forecastSummary,
            'previousForecastSummary' => $previousForecastSummary,
            'eggProductionChart' => $eggProductionChart,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function previousWindow(DashboardDateFilter $filter): array
    {
        $length = (int) $filter->start->diffInDays($filter->end);
        $previousEnd = $filter->start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($length);

        return [
            $previousStart->toDateString(),
            $previousEnd->toDateString(),
        ];
    }
}
