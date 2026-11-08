<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LowStockNotifier
{
    public function __construct(private WebPushSender $push)
    {
    }

    public function check(InventoryItem $item): void
    {
        $stock = $this->effectiveStock($item);
        $min = (float) $item->min_level;
        $priority = StockBand::priority($stock, $min);

        if ($priority !== null) {
            $this->openAlerts($item, $stock, $min, $priority);

            return;
        }

        $this->resolveItem((int) $item->id);
    }

    public function forget(int $inventoryItemId): void
    {
        $this->resolveItem($inventoryItemId);
    }

    private function openAlerts(InventoryItem $item, float $stock, float $min, string $priority): void
    {
        $critical = $priority === StockNotification::PRIORITY_HIGH;
        $title = $critical ? 'Critical low stock' : 'Stock approaching minimum';
        $body = sprintf(
            $critical
                ? '%s is critically low — %s %s (min %s).'
                : '%s is approaching minimum — %s %s (min %s).',
            $item->item_name,
            $this->qty($stock),
            $item->unit,
            $this->qty($min)
        );

        foreach (User::farmLeads() as $user) {
            $created = null;

            DB::transaction(function () use ($user, $item, $stock, $min, $priority, $title, $body, &$created) {
                $open = StockNotification::query()
                    ->where('user_id', $user->id)
                    ->where('inventory_item_id', $item->id)
                    ->open()
                    ->lockForUpdate()
                    ->first();

                $fields = [
                    'priority' => $priority,
                    'title' => $title,
                    'body' => $body,
                    'current_stock' => $stock,
                    'min_level' => $min,
                    'unit' => $item->unit,
                ];

                if ($open) {
                    $rose = StockNotification::priorityRank($priority) < StockNotification::priorityRank($open->priority);
                    if ($rose) {
                        $fields['read_at'] = null;
                    }

                    $open->update($fields);

                    if ($rose) {
                        $created = $open;
                    }

                    return;
                }

                $role = User::routeRole($user->role);
                $url = '/'.$role.'/inventory';

                $created = StockNotification::create([
                    'user_id' => $user->id,
                    'type' => 'low_stock',
                    'priority' => $priority,
                    'inventory_item_id' => $item->id,
                    'item_name' => $item->item_name,
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                    'current_stock' => $stock,
                    'min_level' => $min,
                    'unit' => $item->unit,
                ]);
            });

            if ($created) {
                $this->push->send($created);
            }
        }
    }

    private function resolveItem(int $inventoryItemId): void
    {
        StockNotification::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->open()
            ->update(['resolved_at' => now()]);
    }

    private function effectiveStock(InventoryItem $item): float
    {
        return (float) $item->current_stock;
    }

    private function qty(float $value): string
    {
        if ($value == (int) $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
