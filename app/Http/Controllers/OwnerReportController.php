<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\StockNotification;
use App\Services\WebPushSender;
use Illuminate\Http\Request;

class OwnerReportController extends Controller{
    /**
     * Show all reports sent by the manager.
     */
    public function index(Request $request)
    {
        $reportTypes = ['overall', 'production', 'inventory', 'dispatch', 'sales'];
        $statuses = ['Sent', 'Read'];
        $filters = [
            'report_type' => in_array($request->query('report_type'), $reportTypes, true)
                ? $request->query('report_type')
                : '',
            'status' => in_array($request->query('status'), $statuses, true)
                ? $request->query('status')
                : '',
        ];

        $query = Report::with('manager')
            ->whereIn('status', $statuses)
            ->latest();

        if ($filters['report_type'] !== '') {
            $query->where('report_type', $filters['report_type']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $reports = $query->paginate(5)->withQueryString();

        $visibleReports = Report::whereIn('status', $statuses);

        return view('owner.reports', [
            'reports' => $reports,
            'filters' => $filters,
            'reportStats' => [
                'total' => (clone $visibleReports)->count(),
                'sent' => (clone $visibleReports)->where('status', 'Sent')->count(),
                'read' => (clone $visibleReports)->where('status', 'Read')->count(),
            ],
        ]);
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
    public function saveRemark(Request $request, Report $report, WebPushSender $push)
    {
        $request->validate([
            'owner_comment' => 'required|string|max:5000',
        ]);

        $remark = $request->input('owner_comment');
        $remarkChanged = $report->owner_comment !== $remark;

        $report->update(Report::existing([
            'owner_comment' => $remark,
            'status' => 'Read',
            'is_read' => 1,
            'read_at' => now(),
        ]));

        if ($remarkChanged && $report->manager_id) {
            $notification = StockNotification::create([
                'user_id' => $report->manager_id,
                'type' => 'report_remark',
                'report_id' => $report->id,
                'item_name' => 'Report',
                'title' => 'New report remark',
                'body' => sprintf(
                    'The owner added a remark to “%s” (%s).',
                    $report->title,
                    $report->report_no
                ),
                'url' => '/manager/reports',
            ]);

            $push->send($notification);
        }

        return redirect()
            ->route('owner.reports')
            ->with('success', 'Remark sent successfully.');
    }
}