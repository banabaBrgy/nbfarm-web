<?php

namespace App\Services;

use App\Models\Report;
use App\Models\StockNotification;
use App\Models\User;

class ReportNotifier
{
    public function __construct(private WebPushSender $push)
    {
    }

    public function sent(Report $report): void
    {
        $report->loadMissing('manager');

        $managerName = $report->manager?->name ?: 'Manager';
        $body = sprintf(
            '%s sent “%s” (%s).',
            $managerName,
            $report->title,
            $report->report_no
        );

        foreach (User::owners() as $owner) {
            $created = StockNotification::create([
                'user_id' => $owner->id,
                'type' => 'report',
                'report_id' => $report->id,
                'item_name' => 'Report',
                'title' => 'New report',
                'body' => $body,
                'url' => '/owner/reports/'.$report->id,
            ]);

            $this->push->send($created);
        }
    }

    public function forget(int $reportId): void
    {
        StockNotification::query()
            ->where('report_id', $reportId)
            ->open()
            ->update(['resolved_at' => now()]);
    }
}
