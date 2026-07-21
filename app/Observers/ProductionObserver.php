<?php

namespace App\Observers;

use App\Models\Production;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\Log;

class ProductionObserver
{
    /**
     * Bilang ng eggs kada 1 tray.
     */
    private const EGGS_PER_TRAY = 30;

    /**
     * Handle the Production "created" event.
     * Bawat bagong production record, magbabawas ng Egg Trays stock.
     */
    public function created(Production $production): void
    {
        $traysUsed = intdiv($production->eggs_produced, self::EGGS_PER_TRAY);

        if ($traysUsed > 0) {
            $this->deductTrays($traysUsed);
        }
    }

    /**
     * Handle the Production "updated" event.
     * Kung na-edit ang eggs_produced, i-adjust ang tray stock
     * base sa difference (pwedeng dagdag or bawas).
     */
    public function updated(Production $production): void
    {
        if (!$production->isDirty('eggs_produced')) {
            return;
        }

        $original = $production->getOriginal('eggs_produced');
        $new = $production->eggs_produced;

        $oldTrays = intdiv($original, self::EGGS_PER_TRAY);
        $newTrays = intdiv($new, self::EGGS_PER_TRAY);

        $diff = $newTrays - $oldTrays;

        if ($diff !== 0) {
            $this->adjustTrays($diff);
        }
    }

    /**
     * Handle the Production "deleted" event.
     * Ibalik ang trays na nagamit noong na-delete ang record.
     */
    public function deleted(Production $production): void
    {
        $traysToReturn = intdiv($production->eggs_produced, self::EGGS_PER_TRAY);

        if ($traysToReturn > 0) {
            $this->returnTrays($traysToReturn);
        }
    }

    /**
     * Bawasan ang Egg Trays stock.
     */
    private function deductTrays(int $trays): void
    {
        $trayItem = InventoryItem::where('item_name', 'Egg Trays')->first();

        if (!$trayItem) {
            Log::warning('Egg Trays inventory item hindi nahanap. Hindi na-deduct.');
            return;
        }

        if ($trayItem->current_stock < $trays) {
            Log::warning("Kulang ang Egg Trays stock. Kailangan: {$trays}, Meron lang: {$trayItem->current_stock}");
        }

        $trayItem->decrement('current_stock', $trays);
    }

    /**
     * I-adjust ang stock (positive = bawas, negative = dagdag).
     */
    private function adjustTrays(int $diff): void
    {
        $trayItem = InventoryItem::where('item_name', 'Egg Trays')->first();

        if (!$trayItem) {
            Log::warning('Egg Trays inventory item hindi nahanap. Hindi na-adjust.');
            return;
        }

        if ($diff > 0) {
            $trayItem->decrement('current_stock', $diff);
        } else {
            $trayItem->increment('current_stock', abs($diff));
        }
    }

    /**
     * Ibalik ang trays sa stock (kapag na-delete ang record).
     */
    private function returnTrays(int $trays): void
    {
        $trayItem = InventoryItem::where('item_name', 'Egg Trays')->first();

        if ($trayItem) {
            $trayItem->increment('current_stock', $trays);
        }
    }
}