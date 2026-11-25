<?php

namespace App\Services;

use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerDashboardKpis
{
    /**
     * Owner dashboard card ratings vs the previous period of equal length.
     * Example: Nov 1–17 compares to Oct 15–31. Today/single day compares to the day before.
     * Dispatch/Revenue ratings use Delivered only on both sides.
     *
     * @return array{
     *     kpis: array{
     *         production: array<string, mixed>,
     *         stock: array<string, mixed>,
     *         dispatch: array<string, mixed>,
     *         revenue: array<string, mixed>
     *     }
     * }
     */
    public function forRequest(Request $request): array
    {
        $filter = DashboardDateFilter::fromRequest($request);
        $start = $filter->startDate();
        $end = $filter->endDate();
        [$prevStart, $prevEnd] = $filter->previousWindow();
        $prevAsOf = $filter->previousAsOf();

        $productionCurrent = $this->eggsProduced($start, $end);
        $productionPrevious = $this->eggsProduced($prevStart, $prevEnd);

        $stockCurrent = (float) Production::leftoverStock($filter->isToday() ? null : $filter->asOf());
        $stockPrevious = (float) Production::leftoverStock($prevAsOf);

        $dispatchCurrent = $this->deliveredTrayQuantity($start, $end);
        $dispatchPrevious = $this->deliveredTrayQuantity($prevStart, $prevEnd);

        $revenueCurrent = $this->deliveredTransactionAmount($start, $end);
        $revenuePrevious = $this->deliveredTransactionAmount($prevStart, $prevEnd);

        return [
            'kpis' => [
                'production' => $this->pack($productionCurrent, $productionPrevious),
                'stock' => $this->pack($stockCurrent, $stockPrevious),
                'dispatch' => $this->pack(
                    $dispatchCurrent,
                    $dispatchPrevious,
                    empty: 'No previous data',
                ),
                'revenue' => $this->pack(
                    $revenueCurrent,
                    $revenuePrevious,
                    empty: 'No previous data',
                ),
            ],
        ];
    }

    private function eggsProduced(string $start, string $end): float
    {
        return (float) Production::query()
            ->whereDate('production_date', '>=', $start)
            ->whereDate('production_date', '<=', $end)
            ->sum('eggs_produced');
    }

    private function deliveredTrayQuantity(string $start, string $end): float
    {
        return (float) DB::table('dispatch_items')
            ->join('dispatches', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->where('dispatches.status', 'Delivered')
            ->whereDate('dispatches.dispatch_date', '>=', $start)
            ->whereDate('dispatches.dispatch_date', '<=', $end)
            ->sum('dispatch_items.quantity');
    }

    /**
     * Delivered sales only, dated by transaction_date (same date field as the revenue card).
     */
    private function deliveredTransactionAmount(string $start, string $end): float
    {
        return (float) DB::table('transactions')
            ->join('dispatches', 'dispatches.id', '=', 'transactions.dispatch_id')
            ->where('dispatches.status', 'Delivered')
            ->whereDate('transactions.transaction_date', '>=', $start)
            ->whereDate('transactions.transaction_date', '<=', $end)
            ->sum('transactions.price');
    }

    /**
     * @return array{
     *     actual: float,
     *     previous: float,
     *     change: float|null,
     *     hasBase: bool,
     *     empty: string
     * }
     */
    private function pack(
        float $actual,
        float $previous,
        string $empty = 'No previous data',
    ): array {
        if ($previous <= 0) {
            return [
                'actual' => $actual,
                'previous' => $previous,
                'change' => null,
                'hasBase' => false,
                'empty' => $empty,
            ];
        }

        return [
            'actual' => $actual,
            'previous' => $previous,
            'change' => round((($actual - $previous) / $previous) * 100, 1),
            'hasBase' => true,
            'empty' => $empty,
        ];
    }
}
