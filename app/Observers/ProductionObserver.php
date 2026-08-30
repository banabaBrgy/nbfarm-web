<?php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Services\DailyInventory;
use Illuminate\Support\Facades\Log;

class ProductionObserver
{
    /** Eggs per 1 tray (client). */
    private const EGGS_PER_TRAY = 30;

    public function created(Production $production): void
    {
        app(DailyInventory::class)->applyForToday();

        $traysUsed = intdiv((int) $production->eggs_produced, self::EGGS_PER_TRAY);

        if ($traysUsed > 0) {
            $this->deductTrays($traysUsed);
        }
    }

    public function updated(Production $production): void
    {
        app(DailyInventory::class)->applyForToday();

        if (! $production->isDirty('eggs_produced')) {
            return;
        }

        $oldTrays = intdiv((int) $production->getOriginal('eggs_produced'), self::EGGS_PER_TRAY);
        $newTrays = intdiv((int) $production->eggs_produced, self::EGGS_PER_TRAY);
        $diff = $newTrays - $oldTrays;

        if ($diff !== 0) {
            $this->adjustTrays($diff);
        }
    }

    public function deleted(Production $production): void
    {
        $traysToReturn = intdiv((int) $production->eggs_produced, self::EGGS_PER_TRAY);

        if ($traysToReturn > 0) {
            $this->returnTrays($traysToReturn);
        }
    }

    private function deductTrays(int $trays): void
    {
        $this->adjustTrays($trays);
    }

    /** Positive = harvest used trays. Negative = eggs reduced, return trays. Never below 0. */
    private function adjustTrays(int $diff): void
    {
        $trayItem = InventoryItem::where('item_name', 'Egg Trays')->first();

        if (! $trayItem) {
            Log::warning('Egg Trays inventory item hindi nahanap.');
            return;
        }

        $before = (float) $trayItem->current_stock;
        $next = max(0, $before - $diff);
        $trayItem->update(['current_stock' => $next]);

        InventoryItemHistory::record(
            $trayItem->id,
            $trayItem->item_name,
            $diff > 0 ? 'Deducted' : 'Returned',
            -1.0 * $diff,
            $before,
            $next,
            $diff > 0 ? 'Trays used for harvest' : 'Trays returned'
        );
    }

    private function returnTrays(int $trays): void
    {
        $this->adjustTrays(-$trays);
    }
}
