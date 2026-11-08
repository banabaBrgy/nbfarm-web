<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockNotification extends Model
{
    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    protected $fillable = [
        'user_id',
        'type',
        'priority',
        'alert_date',
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
            'alert_date' => 'date',
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

    public function scopeOrderedForPanel($query)
    {
        return $query
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderByDesc('created_at');
    }

    public function priorityLabel(): string
    {
        return self::priorityLabelFor($this->priority);
    }

    public static function priorityLabelFor(?string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_MEDIUM => 'Medium',
            default => 'Low',
        };
    }

    public static function priorityRank(?string $priority): int
    {
        return match ($priority) {
            self::PRIORITY_HIGH => 1,
            self::PRIORITY_MEDIUM => 2,
            default => 3,
        };
    }

    protected static function booted(): void
    {
        static::creating(function (StockNotification $notification): void {
            if (filled($notification->priority)) {
                return;
            }

            $notification->priority = match ($notification->type) {
                'production_drop', 'low_stock' => self::PRIORITY_HIGH,
                default => self::PRIORITY_LOW,
            };
        });
    }
}
