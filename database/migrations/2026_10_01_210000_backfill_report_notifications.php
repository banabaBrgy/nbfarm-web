<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports') || ! Schema::hasTable('stock_notifications')) {
            return;
        }

        $owners = DB::table('users')
            ->whereIn('role', ['owner', 'farm owner'])
            ->get(['id']);

        $reports = DB::table('reports')
            ->leftJoin('users as managers', 'managers.id', '=', 'reports.manager_id')
            ->where('reports.status', 'Sent')
            ->select([
                'reports.id',
                'reports.report_no',
                'reports.title',
                'reports.created_at',
                'managers.name as manager_name',
            ])
            ->orderBy('reports.id')
            ->get();

        foreach ($reports as $report) {
            foreach ($owners as $owner) {
                $exists = DB::table('stock_notifications')
                    ->where('user_id', $owner->id)
                    ->where('report_id', $report->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $timestamp = $report->created_at;
                DB::table('stock_notifications')->insert([
                    'user_id' => $owner->id,
                    'type' => 'report',
                    'report_id' => $report->id,
                    'item_name' => 'Report',
                    'title' => 'New report',
                    'body' => sprintf(
                        '%s sent “%s” (%s).',
                        $report->manager_name ?: 'Manager',
                        $report->title,
                        $report->report_no
                    ),
                    'url' => '/owner/reports/'.$report->id,
                    'read_at' => null,
                    'resolved_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }
    }

    public function down(): void
    {
        // This data migration is intentionally non-destructive on rollback.
    }
};
