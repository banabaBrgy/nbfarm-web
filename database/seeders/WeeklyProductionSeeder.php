<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use Carbon\Carbon;
use Database\Seeders\Concerns\SplitsEggSizes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WeeklyProductionSeeder extends Seeder
{
    use SplitsEggSizes;
    /** Client farm: 3 harvest batches. */
    private const BATCH_IDS = [1, 2, 3];

    private const START_DATE = '2026-08-25';

    private const END_DATE = '2026-08-31';

    /**
     * Farm daily totals for Aug 25 → 31 (one row per day).
     * Sum of the three harvest batches, multiples of 30.
     */
    private array $eggsByDay = [4230, 4320, 4410, 4500, 4590, 4680, 4410];

    public function run(): void
    {
        $start = Carbon::parse(self::START_DATE)->startOfDay();
        $end = Carbon::parse(self::END_DATE)->startOfDay();

        QrRecord::query()->update(['production_id' => null]);

        $this->deleteDatedAndDemoQrRecords();

        QrRecord::ensurePrintedCodes();

        Production::withoutEvents(function () use ($start, $end) {
            $ids = Production::query()
                ->whereDate('production_date', '>=', $start->toDateString())
                ->whereDate('production_date', '<=', $end->toDateString())
                ->pluck('production_id');

            if ($ids->isNotEmpty()) {
                QrTransaction::whereIn('production_id', $ids)->delete();
                Production::whereIn('production_id', $ids)->delete();
            }
        });

        Production::withoutEvents(function () use ($start) {
            foreach ($this->eggsByDay as $index => $total) {
                $date = $start->copy()->addDays($index);
                $sizes = $this->splitBySize($total);

                $production = Production::create([
                    'production_date' => $date->toDateString(),
                    'batch_id' => 1,
                    'small_eggs' => $sizes['small'],
                    'medium_eggs' => $sizes['medium'],
                    'large_eggs' => $sizes['large'],
                    'extra_large_eggs' => $sizes['extra_large'],
                    'cracked_eggs' => $sizes['cracked'],
                    'eggs_produced' => $sizes['produced'],
                ]);

                $this->seedScansForProduction($production, $date, $sizes);
            }
        });

        $todayEggs = Production::whereDate('production_date', Carbon::today())
            ->sum('eggs_produced');
        $traysUsedToday = intdiv((int) $todayEggs, 30);
        $trayStock = max(0, 500 - $traysUsedToday);

        $trayUpdate = [
            'current_stock' => $trayStock,
            'min_level' => 100,
        ];
        if (Schema::hasColumn('inventory_items', 'daily_stock')) {
            $trayUpdate['daily_stock'] = 500;
        }

        InventoryItem::where('item_name', 'Egg Trays')->update($trayUpdate);

        Inventory::where('item_type', 'Egg Tray')->update([
            'quantity' => $trayStock,
            'minimum_stock' => 100,
        ]);

        Cache::flush();

        $this->command?->info("Seeded 7 farm harvest rows {$start->toDateString()} → {$end->toDateString()}.");
        $this->command?->info('QR codes: QRS/QRM/QRL/QRXL/QRC-B{1-3}-001 to 010 (reused each day).');
        $this->command?->info('Farm daily totals: 4230, 4320, 4410, 4500, 4590, 4680, 4410.');
    }

    private function deleteDatedAndDemoQrRecords(): void
    {
        QrRecord::query()
            ->where(function ($query) {
                $query->whereIn('batch_id', array_merge(self::BATCH_IDS, [99]))
                    ->orWhere('qr_code', 'like', '%-DEMO-%');
            })
            ->get()
            ->each(function (QrRecord $record) {
                if (QrRecord::isClientCode($record->qr_code)) {
                    return;
                }
                QrTransaction::where('qr_record_id', $record->id)->delete();
                $record->delete();
            });
    }

    private function seedScansForProduction(
        Production $production,
        Carbon $date,
        array $sizes
    ): void {
        $scannedAt = $date->copy()->setTime(8, 30);

        foreach (QrRecord::SIZE_PREFIXES as $eggSize => $prefix) {
            $key = match ($eggSize) {
                'Small' => 'small',
                'Medium' => 'medium',
                'Large' => 'large',
                'Extra Large' => 'extra_large',
                'Cracked' => 'cracked',
            };

            $eggCount = $sizes[$key] ?? 0;
            if ($eggCount <= 0) {
                continue;
            }

            $chunks = [];
            $fullStacks = intdiv($eggCount, 300);
            $leftover = $eggCount % 300;
            for ($n = 0; $n < $fullStacks; $n++) {
                $chunks[] = 300;
            }
            if ($leftover > 0) {
                $chunks[] = $leftover;
            }

            $chunks = array_slice($chunks, 0, 30);

            foreach ($chunks as $index => $chunk) {
                $batchId = self::BATCH_IDS[$index % count(self::BATCH_IDS)];
                $sequence = intdiv($index, count(self::BATCH_IDS)) + 1;
                $qrCode = QrRecord::clientCode($prefix, $batchId, $sequence);
                $qrRecord = QrRecord::where('qr_code', $qrCode)->first();
                if (!$qrRecord) {
                    continue;
                }

                QrTransaction::create([
                    'production_id' => $production->production_id,
                    'qr_record_id' => $qrRecord->id,
                    'total_eggs' => $chunk,
                    'status' => $chunk === 300 ? 'Scanned' : 'Edited',
                    'scanned_at' => $scannedAt,
                ]);

                $scannedAt = $scannedAt->copy()->addMinutes(10);
            }
        }
    }
}
