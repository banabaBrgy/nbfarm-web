<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\Forecast;
use App\Models\Production;
use App\Models\Transaction;
use App\Services\Forecasting\GenerateEggForecast;
use Carbon\Carbon;
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
    public function forRequest(Request $request, bool $includeChart = true): array
    {
        $filter = DashboardDateFilter::fromRequest($request);
        $useCurrentInventory = $filter->isToday();
        $asOf = $filter->asOf();
        [$previousStart, $previousEnd] = $this->previousWindow($filter);

        $eggsToday = (int) Production::query()
            ->whereDate('production_date', '>=', $filter->startDate())
            ->whereDate('production_date', '<=', $filter->endDate())
            ->sum('eggs_produced');

        $eggsRating = $this->rating($eggsToday, $this->savedExpectedEggs($filter));

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
        $trayMin = $this->inventory->firstNamedMin('Egg Trays');
        $trayLow = $trayMin > 0 && $eggTrays <= $trayMin;
        $eggsTotal = Production::leftoverStock($useCurrentInventory ? null : $asOf);
        $lowStockAlert = $this->inventory->lowStockCount($asOf, $useCurrentInventory);

        $inventoryPreviousAsOf = $asOf->copy()->subDay();
        $inventoryRatings = [
            'eggs' => $this->rating($eggsTotal, Production::leftoverStock($inventoryPreviousAsOf)),
            'feed' => $this->rating(
                $feedStock,
                $this->inventory->namedStock('Feeds', $inventoryPreviousAsOf, false),
            ),
            'trays' => $this->rating(
                $eggTrays,
                $this->inventory->firstNamedStock('Egg Trays', $inventoryPreviousAsOf, false),
            ),
            'vitamins' => $this->rating(
                $vitamins,
                $this->inventory->namedStock('Vitamins', $inventoryPreviousAsOf, false),
            ),
        ];

        $dispatchedTrays = $this->dispatchedTraysBetween($filter->startDate(), $filter->endDate());
        $dispatchPrevious = $this->dispatchedTraysBetween($previousStart, $previousEnd);
        $dispatchRating = $this->rating($dispatchedTrays, $dispatchPrevious);

        if ($filter->isToday()) {
            $pendingDeliveries = Dispatch::query()
                ->whereIn('status', ['Pending', 'On Transit'])
                ->count();
        } else {
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

        $revenuePrevious = (float) Transaction::query()
            ->whereDate('transaction_date', '>=', $previousStart)
            ->whereDate('transaction_date', '<=', $previousEnd)
            ->sum('price');
        $revenueRating = $this->rating($revenueToday, $revenuePrevious);

        $forecastSummary = $this->forecasts->summaryFrom($asOf);
        $previousForecastSummary = $this->forecasts->previousSummaryFrom($asOf);
        $predictedEggs = (int) ($forecastSummary['predicted_eggs'] ?? 0);

        $payload = [
            'dateFilter' => $filter->toView(),
            'overviewSubtitle' => $filter->subtitle(),
            'eggsTitle' => $filter->isToday() ? 'Total Eggs Today' : 'Total Eggs',
            'eggsChangeLabel' => '',
            'revenueTitle' => $filter->isToday() ? 'Revenue Today' : 'Revenue',
            'revenueSubtitle' => $filter->isToday() ? "Today's Income" : $filter->formatPeriod(),
            'predictedSubtitle' => $filter->isToday()
                ? 'Next-day forecast · tap for sizes'
                : 'Forecast from '.$filter->asOf()->format('M j, Y').' · tap for sizes',
            'activeBatchesSubtitle' => $filter->isToday() ? 'Currently producing' : 'Producing in this period',
            'inventoryAsOfLabel' => $filter->isToday() ? null : $filter->formatPeriod(),
            'inventoryMovements' => $this->inventory->movements($filter->start, $filter->end),
            'eggsToday' => $eggsToday,
            'eggsChange' => $eggsRating['change'],
            'eggsChangeHasBase' => $eggsRating['hasBase'],
            'activeBatches' => $activeBatches,
            'feedStock' => $feedStock,
            'feedMin' => $feedMin,
            'feedLow' => $feedLow,
            'vitamins' => $vitamins,
            'vitaminMin' => $vitaminMin,
            'vitaminLow' => $vitaminLow,
            'eggTrays' => $eggTrays,
            'trayMin' => $trayMin,
            'trayLow' => $trayLow,
            'eggsTotal' => $eggsTotal,
            'lowStockAlert' => $lowStockAlert,
            'inventoryRatings' => $inventoryRatings,
            'dispatchedTrays' => $dispatchedTrays,
            'pendingDeliveries' => $pendingDeliveries,
            'dispatchChange' => $dispatchRating['change'],
            'dispatchChangeHasBase' => $dispatchRating['hasBase'],
            'dispatchChangeLabel' => '',
            'revenueToday' => $revenueToday,
            'revenueChange' => $revenueRating['change'],
            'revenueChangeHasBase' => $revenueRating['hasBase'],
            'revenueChangeLabel' => '',
            'predictedEggs' => $predictedEggs,
            'forecastSummary' => $forecastSummary,
            'previousForecastSummary' => $previousForecastSummary,
        ];

        if ($includeChart) {
            $payload['eggProductionChart'] = $this->chart->forRequest($request, $filter);
        }

        return $payload;
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

    /**
     * Saved forecast totals for every day in the selected filter.
     * Incomplete coverage returns 0 so the card shows no rating.
     */
    private function savedExpectedEggs(DashboardDateFilter $filter): int
    {
        $days = (int) $filter->start->diffInDays($filter->end) + 1;
        $byDate = Forecast::query()
            ->whereDate('forecast_date', '>=', $filter->startDate())
            ->whereDate('forecast_date', '<=', $filter->endDate())
            ->get(['forecast_date', 'predicted_eggs'])
            ->groupBy(fn (Forecast $forecast): string => Carbon::parse($forecast->forecast_date)->toDateString())
            ->map(fn ($rows): int => (int) $rows->first()->predicted_eggs);

        if ($byDate->count() < $days) {
            return 0;
        }

        return (int) $byDate->sum();
    }

    private function dispatchedTraysBetween(string $start, string $end): float
    {
        return (float) DB::table('dispatch_items')
            ->join('dispatches', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->whereDate('dispatches.dispatch_date', '>=', $start)
            ->whereDate('dispatches.dispatch_date', '<=', $end)
            ->sum('dispatch_items.quantity');
    }

    /**
     * @return array{change: float, hasBase: bool}
     */
    private function rating(float $current, float $base): array
    {
        if ($base <= 0) {
            return [
                'change' => 0.0,
                'hasBase' => false,
            ];
        }

        return [
            'change' => round((($current - $base) / $base) * 100, 1),
            'hasBase' => true,
        ];
    }
}
