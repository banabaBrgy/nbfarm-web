<?php

namespace App\Observers;

use App\Models\Production;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\Log;

class ProductionObserver
{
    /** Eggs per 1 tray (client). */
    private const EGGS_PER_TRAY = 30;

    public function created(Production $production): void
    {
        $traysUsed = intdiv((int) $production->eggs_produced, self::EGGS_PER_TRAY);

        if ($traysUsed > 0) {
            $this->deductTrays($traysUsed);
        }
    }

    public function updated(Production $production): void
    {
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

        $next = (float) $trayItem->current_stock - $diff;
        $trayItem->update(['current_stock' => max(0, $next)]);
    }

    private function returnTrays(int $trays): void
    {
        $this->adjustTrays(-$trays);
    }
}
