<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Production;
use App\Models\InventoryItem;
use App\Models\Dispatch;
use App\Models\Forecast;
use Illuminate\Support\Facades\DB;
class OwnerReportController extends Controller
{
    /**
     * Show all reports sent by the manager.
     */
    public function index()
    {
        $reports = Report::latest()->get();

        return view('owner.reports', compact('reports'));
    }

    /**
     * View a single report.
     * Automatically marks it as read.
     */
    public function show(Report $report)
    {
        if (!$report->is_read) {

            $report->update([
                'is_read' => 1,
                'status' => 'Read'
            ]);

        }

        return view('owner.report-view', compact('report'));
    }

    /**
     * Save owner's remark.
     */
    public function saveRemark(Request $request, Report $report)
    {
        $request->validate([
            'owner_comment' => 'required|string|max:5000',
        ]);

        $report->update([
            'owner_comment' => $request->owner_comment,
            'status' => 'Read',
            'is_read' => 1,
        ]);

        return redirect()
            ->route('owner.reports')
            ->with('success', 'Remark sent successfully.');
    }
}