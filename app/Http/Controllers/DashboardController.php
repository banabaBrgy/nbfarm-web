<?php

namespace App\Http\Controllers;

use App\Services\DailyInventory;
use App\Services\DashboardDateFilter;
use App\Services\DashboardKpis;
use App\Services\DashboardOverview;
use App\Services\RoleTodoList;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(array_merge(
            DashboardDateFilter::rules(),
            DashboardDateFilter::chartRules(),
        ));

        app(DailyInventory::class)->applyForToday();

        $roleTodos = app(RoleTodoList::class)->forUser(auth()->user());

        return view('staff.dashboard', array_merge(
            app(DashboardOverview::class)->forRequest($request),
            compact('roleTodos'),
            app(DashboardKpis::class)->summary(),
        ));
    }
}
