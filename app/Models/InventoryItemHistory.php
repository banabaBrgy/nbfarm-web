<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoryItemHistory extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'item_name',
        'action',
        'quantity_change',
        'stock_before',
        'stock_after',
        'note',
        'user_id',
    ];

    protected $casts = [
        'quantity_change' => 'float',
        'stock_before' => 'float',
        'stock_after' => 'float',
    ];

    /**
     * Inventory record periods: all, daily, weekly, monthly, or custom.
     */
    public function scopeForDateRange(Builder $query, string $range, ?string $from = null, ?string $to = null): Builder
    {
        $today = Carbon::today();

        if ($range === 'daily') {
            return $query->whereDate('created_at', $today);
        }

        if ($range === 'weekly') {
            return $query->whereBetween('created_at', [
                $today->copy()->startOfWeek()->startOfDay(),
                $today->copy()->endOfWeek()->endOfDay(),
            ]);
        }

        if ($range === 'monthly') {
            return $query->whereBetween('created_at', [
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfMonth()->endOfDay(),
            ]);
        }

        if ($range === 'custom' && $from && $to) {
            return $query->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);
        }

        if ($range === 'custom') {
            return $query->whereRaw('0 = 1');
        }

        return $query;
    }

    public static function record(
        ?int $itemId,
        string $itemName,
        string $action,
        float $quantityChange,
        ?float $stockBefore,
        ?float $stockAfter,
        ?string $note = null
    ): void {
        static::create([
            'inventory_item_id' => $itemId,
            'item_name' => $itemName,
            'action' => $action,
            'quantity_change' => $quantityChange,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }
}
