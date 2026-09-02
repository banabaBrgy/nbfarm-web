<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\StockNotification;
use Illuminate\Http\Request;

class OwnerReportController extends Controller{
    /**
     * Show all reports sent by the manager.
     */
    public function index()
    {
        $reports = Report::with('manager')
            ->whereIn('status', ['Sent', 'Read'])
            ->latest()
            ->get();

        return view('owner.reports', compact('reports'));
    }

    /**
     * View a single report.
     * Automatically marks it as read.
     */
    public function show(Report $report)
    {
        if (! in_array($report->status, ['Sent', 'Read'], true)) {
            abort(404);
        }

        if ($report->status === 'Sent') {
            $report->update(Report::existing([
                'is_read' => 1,
                'status' => 'Read',
                'read_at' => now(),
            ]));
        }

        StockNotification::query()
            ->where('user_id', auth()->id())
            ->where('report_id', $report->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->route('owner.reports');
    }

    /**
     * Save owner's remark.
     */
    public function saveRemark(Request $request, Report $report)
    {
        $request->validate([
            'owner_comment' => 'required|string|max:5000',
        ]);

        $report->update(Report::existing([
            'owner_comment' => $request->owner_comment,
            'status' => 'Read',
            'is_read' => 1,
            'read_at' => now(),
        ]));

        return redirect()
            ->route('owner.reports')
            ->with('success', 'Remark sent successfully.');
    }
}