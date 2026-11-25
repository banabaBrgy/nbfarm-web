<?php

namespace App\Http\Controllers;

use App\Services\DailyInventory;
use App\Services\DashboardDateFilter;
use App\Services\DashboardOverview;
use App\Services\OwnerDashboardKpis;
use App\Services\RoleTodoList;
use App\Services\SystemEffectiveness;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
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

        return view('owner.dashboard', array_merge(
            $overview,
            compact('effectiveness', 'roleTodos'),
            app(OwnerDashboardKpis::class)->forRequest($request)
        ));
    }
}
