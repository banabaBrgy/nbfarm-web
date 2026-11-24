<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Services\DailyInventory;
use App\Services\DashboardDateFilter;
use App\Services\DashboardKpis;
use App\Services\DashboardOverview;
use App\Services\RoleTodoList;
use App\Services\SystemEffectiveness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ManagerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(array_merge(
            DashboardDateFilter::rules(),
            DashboardDateFilter::effectivenessRules(),
        ));

        app(DailyInventory::class)->applyForToday();

        $overview = app(DashboardOverview::class)->forRequest($request, includeChart: false);
        $effectiveness = app(SystemEffectiveness::class)->forRequest($request);
        $roleTodos = app(RoleTodoList::class)->forUser(auth()->user());
        $pendingDispatchCount = (int) ($overview['pendingDeliveries'] ?? 0);
        $pendingDispatches = Dispatch::query()
            ->with('items')
            ->whereIn('status', ['Pending', 'On Transit'])
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->orderByDesc('dispatch_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        if (config('app.debug')) {
            Log::info('MANAGER FORECAST DIAGNOSTIC', [
                'predicted_eggs' => $overview['predictedEggs'] ?? null,
                'summary_eggs' => $overview['forecastSummary']['predicted_eggs'] ?? null,
                'database' => config('database.connections.mysql.database'),
            ]);
        }

        return view(
            'manager.dashboard',
            array_merge(
                $overview,
                compact('effectiveness', 'roleTodos', 'pendingDispatches', 'pendingDispatchCount'),
                app(DashboardKpis::class)->summary()
            )
        );
    }
}
