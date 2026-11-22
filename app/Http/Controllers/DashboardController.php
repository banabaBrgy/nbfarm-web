<?php

namespace App\Http\Controllers;

use App\Services\DailyInventory;
use App\Services\DashboardDateFilter;
use App\Services\DashboardKpis;
use App\Services\DashboardOverview;
use App\Services\RoleTodoList;
use App\Services\StaffMyActivity;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(DashboardDateFilter::rules());

        app(DailyInventory::class)->applyForToday();

        $filter = DashboardDateFilter::fromRequest($request);
        $roleTodos = app(RoleTodoList::class)->forUser(auth()->user());
        $staffActivities = app(StaffMyActivity::class)->forUser(auth()->user(), $filter);

        return view('staff.dashboard', array_merge(
            app(DashboardOverview::class)->forRequest($request, includeChart: false),
            compact('roleTodos', 'staffActivities'),
            app(DashboardKpis::class)->summary(),
        ));
    }
}
