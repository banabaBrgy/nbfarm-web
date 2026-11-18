<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\InventoryItem;
use App\Models\Production;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;

class RoleTodoList
{
    /**
     * Daily checklist for the signed-in role, with live done/pending state.
     *
     * @return array{
     *     role: string,
     *     roleLabel: string,
     *     title: string,
     *     subtitle: string,
     *     done: int,
     *     total: int,
     *     items: list<array{
     *         key: string,
     *         title: string,
     *         hint: string,
     *         done: bool,
     *         urgent: bool,
     *         url: string,
     *         icon: string
     *     }>
     * }
     */
    public function forUser(User $user): array
    {
        $role = User::routeRole($user->role);
        $roleLabel = match ($role) {
            'owner' => 'Farm Owner',
            'manager' => 'Farm Manager',
            'staff' => 'Poultry Staff',
            default => 'User',
        };

        $items = match ($role) {
            'staff' => $this->staffItems(),
            'manager' => $this->managerItems($user),
            'owner' => $this->ownerItems(),
            default => [],
        };

        $done = count(array_filter($items, fn (array $item) => $item['done']));

        return [
            'role' => $role,
            'roleLabel' => $roleLabel,
            'title' => "Today's To-Do",
            'subtitle' => 'Things you can do as '.$roleLabel,
            'done' => $done,
            'total' => count($items),
            'items' => $items,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function staffItems(): array
    {
        $eggsToday = $this->eggsToday();
        $lowStock = $this->lowStockCount();

        return [
            $this->item(
                'record-production',
                "Record today's egg collection",
                $eggsToday > 0
                    ? number_format($eggsToday).' eggs logged today.'
                    : 'No harvest logged yet. Scan a QR code to save it.',
                $eggsToday > 0,
                $eggsToday === 0,
                route('staff.production.scan'),
                'fas fa-qrcode'
            ),
            $this->item(
                'restock-inventory',
                'Check and restock inventory',
                $lowStock > 0
                    ? $lowStock.' item(s) are low. Restock feeds, vitamins, or trays.'
                    : 'Stock is at a safe level.',
                $lowStock === 0,
                $lowStock > 0,
                route('staff.inventory'),
                'fas fa-box'
            ),
            $this->item(
                'review-production',
                'Review production records',
                $eggsToday > 0
                    ? "Today's harvest is on the production page."
                    : 'Open production to see batches and harvest history.',
                $eggsToday > 0,
                false,
                route('staff.production'),
                'fas fa-egg'
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function managerItems(User $user): array
    {
        $eggsToday = $this->eggsToday();
        $lowStock = $this->lowStockCount();
        $pending = $this->pendingDeliveries();
        $revenueToday = $this->revenueToday();
        $drafts = (int) Report::query()
            ->where('manager_id', $user->id)
            ->where('status', 'Draft')
            ->count();
        $sentToday = Report::query()
            ->where('manager_id', $user->id)
            ->whereIn('status', ['Sent', 'Read'])
            ->where(function ($query) {
                $query->whereDate('sent_at', today())
                    ->orWhere(function ($inner) {
                        $inner->whereNull('sent_at')->whereDate('report_date', today());
                    });
            })
            ->exists();

        if ($drafts > 0) {
            $reportHint = $drafts.' draft report(s) ready to send to the owner.';
        } elseif ($sentToday) {
            $reportHint = 'A report was sent to the owner today.';
        } else {
            $reportHint = "Share today's farm update with the owner.";
        }

        return [
            $this->item(
                'record-production',
                "Review today's production",
                $eggsToday > 0
                    ? number_format($eggsToday).' eggs logged today.'
                    : 'No harvest logged yet. Open production to see batches and harvest history.',
                $eggsToday > 0,
                $eggsToday === 0,
                route('manager.production'),
                'fas fa-egg'
            ),
            $this->item(
                'restock-inventory',
                'Check and restock inventory',
                $lowStock > 0
                    ? $lowStock.' item(s) are below the minimum.'
                    : 'Stock is at a safe level.',
                $lowStock === 0,
                $lowStock > 0,
                route('manager.inventory'),
                'fas fa-box'
            ),
            $this->item(
                'follow-dispatch',
                'Follow up deliveries',
                $pending > 0
                    ? $pending.' dispatch(es) still Pending or On Transit.'
                    : 'No open deliveries right now.',
                $pending === 0,
                $pending > 0,
                route('manager.dispatch'),
                'fas fa-truck'
            ),
            $this->item(
                'review-sales',
                "Review today's sales",
                $revenueToday > 0
                    ? '₱'.number_format($revenueToday, 0).' recorded today.'
                    : 'No sales yet. Create a dispatch to log a sale.',
                $revenueToday > 0,
                $revenueToday <= 0,
                route('manager.sales.history'),
                'fas fa-peso-sign'
            ),
            $this->item(
                'send-report',
                'Send a report to the owner',
                $reportHint,
                $sentToday && $drafts === 0,
                $drafts > 0 || ! $sentToday,
                route('manager.reports'),
                'fas fa-file-alt'
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function ownerItems(): array
    {
        $eggsToday = $this->eggsToday();
        $lowStock = $this->lowStockCount();
        $pending = $this->pendingDeliveries();
        $revenueToday = $this->revenueToday();
        $unreadReports = (int) Report::query()->where('status', 'Sent')->count();

        return [
            $this->item(
                'review-production',
                "Review today's production",
                $eggsToday > 0
                    ? number_format($eggsToday).' eggs collected today.'
                    : 'No harvest logged yet. Check production when staff records it.',
                $eggsToday > 0,
                $eggsToday === 0,
                route('owner.production'),
                'fas fa-egg'
            ),
            $this->item(
                'read-reports',
                'Read new reports',
                $unreadReports > 0
                    ? $unreadReports.' new report(s) from the manager.'
                    : 'No unread reports.',
                $unreadReports === 0,
                $unreadReports > 0,
                route('owner.reports'),
                'fas fa-file-alt'
            ),
            $this->item(
                'check-stock',
                'Check low stock',
                $lowStock > 0
                    ? $lowStock.' item(s) need restocking.'
                    : 'Inventory looks healthy.',
                $lowStock === 0,
                $lowStock > 0,
                route('owner.inventory'),
                'fas fa-box'
            ),
            $this->item(
                'follow-dispatch',
                'Follow up deliveries',
                $pending > 0
                    ? $pending.' dispatch(es) still open.'
                    : 'No open deliveries right now.',
                $pending === 0,
                $pending > 0,
                route('owner.dispatch'),
                'fas fa-truck'
            ),
            $this->item(
                'review-sales',
                "Review today's sales",
                $revenueToday > 0
                    ? '₱'.number_format($revenueToday, 0).' recorded today.'
                    : 'No sales recorded yet.',
                $revenueToday > 0,
                $revenueToday <= 0,
                route('owner.revenue'),
                'fas fa-peso-sign'
            ),
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     hint: string,
     *     done: bool,
     *     urgent: bool,
     *     url: string,
     *     icon: string
     * }
     */
    private function item(
        string $key,
        string $title,
        string $hint,
        bool $done,
        bool $urgent,
        string $url,
        string $icon
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'hint' => $hint,
            'done' => $done,
            'urgent' => $urgent && ! $done,
            'url' => $url,
            'icon' => $icon,
        ];
    }

    private function eggsToday(): int
    {
        return (int) Production::query()
            ->whereDate('production_date', today())
            ->sum('eggs_produced');
    }

    private function lowStockCount(): int
    {
        return (int) InventoryItem::query()
            ->whereColumn('current_stock', '<=', 'min_level')
            ->count();
    }

    private function pendingDeliveries(): int
    {
        return (int) Dispatch::query()
            ->whereIn('status', ['Pending', 'On Transit'])
            ->count();
    }

    private function revenueToday(): float
    {
        return (float) Transaction::query()
            ->whereDate('transaction_date', today())
            ->sum('price');
    }
}
