<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'inventory_item_id',
        'report_id',
        'item_name',
        'title',
        'body',
        'url',
        'current_stock',
        'min_level',
        'unit',
        'read_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'float',
            'min_level' => 'float',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->whereNull('resolved_at');
    }
}
