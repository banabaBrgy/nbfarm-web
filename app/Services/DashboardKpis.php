<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DashboardKpis
{
    /**
     * Farm-wide totals for the dashboard KPI cards.
     * Production, dispatch, and revenue cover every saved record.
     * Available stock is the eggs still on hand.
     *
     * @return array{
     *     totalEggProduction: int,
     *     availableEggStock: int,
     *     totalDispatchedEggs: int,
     *     totalRevenue: float
     * }
     */
    public function summary(): array
    {
        $dispatchedTrays = (int) DB::table('dispatch_items')->sum('quantity');

        return [
            'totalEggProduction' => (int) Production::query()->sum('eggs_produced'),
            'availableEggStock' => Production::leftoverStock(),
            'totalDispatchedEggs' => $dispatchedTrays * Production::EGGS_PER_TRAY,
            'totalRevenue' => (float) Transaction::query()->sum('price'),
        ];
    }
}
