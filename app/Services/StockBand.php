<?php

namespace App\Services;

use App\Models\StockNotification;

/**
 * Same bands the inventory page already shows.
 * LOW is below the minimum. WATCH is at the minimum up to 20% above it.
 */
class StockBand
{
    public static function status(float $stock, float $min): string
    {
        if ($stock < $min) {
            return 'LOW';
        }

        if ($stock < $min * 1.2) {
            return 'WATCH';
        }

        return 'OK';
    }

    public static function priority(float $stock, float $min): ?string
    {
        return match (self::status($stock, $min)) {
            'LOW' => StockNotification::PRIORITY_HIGH,
            'WATCH' => StockNotification::PRIORITY_MEDIUM,
            default => null,
        };
    }
}
