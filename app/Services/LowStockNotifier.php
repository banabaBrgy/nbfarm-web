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

        if ($stock <= $min) {
            $this->openAlerts($item, $stock, $min);

            return;
        }

        $this->resolveItem((int) $item->id);
    }

    public function forget(int $inventoryItemId): void
    {
        $this->resolveItem($inventoryItemId);
    }

    private function openAlerts(InventoryItem $item, float $stock, float $min): void
    {
        $body = sprintf(
            '%s is low — %s %s (min %s).',
            $item->item_name,
            $this->qty($stock),
            $item->unit,
            $this->qty($min)
        );

        foreach (User::farmLeads() as $user) {
            $created = null;

            DB::transaction(function () use ($user, $item, $stock, $min, $body, &$created) {
                $open = StockNotification::query()
                    ->where('user_id', $user->id)
                    ->where('inventory_item_id', $item->id)
                    ->open()
                    ->lockForUpdate()
                    ->first();

                if ($open) {
                    $open->update([
                        'body' => $body,
                        'current_stock' => $stock,
                        'min_level' => $min,
                        'unit' => $item->unit,
                    ]);

                    return;
                }

                $role = User::routeRole($user->role);
                $url = '/'.$role.'/inventory';

                $created = StockNotification::create([
                    'user_id' => $user->id,
                    'type' => 'low_stock',
                    'inventory_item_id' => $item->id,
                    'item_name' => $item->item_name,
                    'title' => 'Low stock',
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
