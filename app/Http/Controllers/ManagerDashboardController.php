<?php

namespace App\Http\Controllers;

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
            DashboardDateFilter::chartRules(),
            DashboardDateFilter::effectivenessRules(),
        ));

        app(DailyInventory::class)->applyForToday();

        $overview = app(DashboardOverview::class)->forRequest($request);
        $effectiveness = app(SystemEffectiveness::class)->forRequest($request);
        $roleTodos = app(RoleTodoList::class)->forUser(auth()->user());

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
                compact('effectiveness', 'roleTodos'),
                app(DashboardKpis::class)->summary()
            )
        );
    }
}
