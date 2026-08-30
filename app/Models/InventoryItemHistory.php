<?php

namespace App\Models;

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
