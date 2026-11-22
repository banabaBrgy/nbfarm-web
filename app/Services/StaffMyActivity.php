<?php

namespace App\Services;

use App\Models\InventoryItemHistory;
use App\Models\QrTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StaffMyActivity
{
    public const LIMIT = 15;

    /**
     * Recent production scans and inventory restocks for the signed-in staff member.
     *
     * @return array{
     *     items: list<array{
     *         key: string,
     *         type: string,
     *         title: string,
     *         detail: string,
     *         when: string,
     *         at: string,
     *         icon: string,
     *         url: string
     *     }>,
     *     empty: bool,
     *     subtitle: string,
     *     emptyMessage: string,
     *     emptyMessages: array{all: string, production: string, inventory: string}
     * }
     */
    public function forUser(User $user, DashboardDateFilter $filter): array
    {
        $start = $filter->start->copy()->startOfDay();
        $end = $filter->end->copy()->endOfDay();

        $edits = $this->productionEdits($user, $start, $end);
        $editedIds = $edits->pluck('transaction_id')->filter()->all();

        $items = $this->scans($user, $start, $end)
            ->reject(fn (array $row): bool => in_array($row['transaction_id'] ?? null, $editedIds, true))
            ->concat($edits)
            ->concat($this->inventoryUpdates($user, $start, $end))
            ->sortByDesc(fn (array $row): array => [$row['sort_at'], $row['sort_id']])
            ->values()
            ->take(self::LIMIT)
            ->map(function (array $row) use ($filter): array {
                $at = $row['occurred_at'];
                unset($row['occurred_at'], $row['sort_at'], $row['sort_id'], $row['transaction_id']);
                $row['when'] = $this->formatWhen($at, $filter);
                $row['at'] = $at->toIso8601String();

                return $row;
            })
            ->all();

        $today = $filter->isToday();
        $emptyAll = $today
            ? 'No production or inventory activity yet today.'
            : 'No production or inventory activity in this period.';

        return [
            'items' => $items,
            'empty' => $items === [],
            'subtitle' => $today
                ? 'Your recent activities in the system'
                : 'Activity for '.$filter->formatPeriod(),
            'emptyMessage' => $emptyAll,
            'emptyMessages' => [
                'all' => $emptyAll,
                'production' => $today
                    ? 'No production activity yet today.'
                    : 'No production activity in this period.',
                'inventory' => $today
                    ? 'No inventory activity yet today.'
                    : 'No inventory activity in this period.',
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function scans(User $user, Carbon $start, Carbon $end): Collection
    {
        return QrTransaction::query()
            ->with(['qrRecord', 'production'])
            ->where('scanned_by', $user->id)
            ->whereBetween('scanned_at', [$start, $end])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (QrTransaction $scan): array {
                $at = Carbon::parse($scan->scanned_at);
                $productionId = (int) $scan->production_id;

                return $this->productionRow(
                    'scan-'.$scan->id,
                    'scan',
                    'Recorded production',
                    'fas fa-table-cells',
                    $scan,
                    $at,
                    $productionId > 0
                        ? route('staff.production.details', $productionId)
                        : route('staff.production.scan'),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function productionEdits(User $user, Carbon $start, Carbon $end): Collection
    {
        return QrTransaction::query()
            ->with(['qrRecord', 'production'])
            ->where('edited_by', $user->id)
            ->whereNotNull('edited_at')
            ->whereBetween('edited_at', [$start, $end])
            ->orderByDesc('edited_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (QrTransaction $scan): array {
                $at = Carbon::parse($scan->edited_at);
                $productionId = (int) $scan->production_id;

                return $this->productionRow(
                    'edit-'.$scan->id,
                    'edit',
                    'Updated production',
                    'fas fa-pen-to-square',
                    $scan,
                    $at,
                    $productionId > 0
                        ? route('staff.production.details', $productionId)
                        : route('staff.production'),
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function inventoryUpdates(User $user, Carbon $start, Carbon $end): Collection
    {
        return InventoryItemHistory::query()
            ->where('user_id', $user->id)
            ->where('action', 'Restocked')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (InventoryItemHistory $row): array {
                $at = Carbon::parse($row->created_at);
                $qty = $this->formatQuantity((float) $row->quantity_change);

                return [
                    'key' => 'inventory-'.$row->id,
                    'type' => 'inventory',
                    'group' => 'inventory',
                    'title' => 'Updated inventory',
                    'detail' => 'Restocked '.$row->item_name.' • +'.$qty,
                    'occurred_at' => $at,
                    'icon' => 'fas fa-box',
                    'url' => route('staff.inventory'),
                    'sort_at' => $at->getTimestamp(),
                    'sort_id' => (int) $row->id,
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function productionRow(
        string $key,
        string $type,
        string $title,
        string $icon,
        QrTransaction $scan,
        Carbon $at,
        string $url,
    ): array {
        $size = $scan->qrRecord?->egg_size;
        $batch = $scan->qrRecord?->batch_id ?? $scan->production?->batch_id;
        $parts = array_values(array_filter([
            $batch ? 'Batch '.$batch : null,
            is_string($size) && $size !== '' ? $size : null,
            number_format((int) $scan->total_eggs).' eggs',
        ], fn ($part) => $part !== null));

        return [
            'key' => $key,
            'type' => $type,
            'group' => 'production',
            'title' => $title,
            'detail' => implode(' • ', $parts),
            'occurred_at' => $at,
            'icon' => $icon,
            'url' => $url,
            'transaction_id' => (int) $scan->id,
            'sort_at' => $at->getTimestamp(),
            'sort_id' => (int) $scan->id,
        ];
    }

    private function formatWhen(Carbon $at, DashboardDateFilter $filter): string
    {
        if ($filter->isToday() && $at->isToday()) {
            return $at->format('g:i A');
        }

        if ($at->isCurrentYear()) {
            return $at->format('M j, g:i A');
        }

        return $at->format('M j, Y g:i A');
    }

    private function formatQuantity(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.001) {
            return number_format((int) round($qty));
        }

        return number_format($qty, 2);
    }
}
