<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WeeklyProductionSeeder extends Seeder
{
    /** Client farm: 3 harvest batches. */
    private const BATCH_IDS = [1, 2, 3];

    /**
     * Eggs per batch for the last 7 days (oldest → today).
     * About 1,400–1,590 per batch (client example ~1,500), multiples of 30.
     */
    private array $eggsByBatch = [
        1 => [1440, 1470, 1500, 1530, 1560, 1590, 1500],
        2 => [1410, 1440, 1470, 1500, 1530, 1560, 1470],
        3 => [1380, 1410, 1440, 1470, 1500, 1530, 1440],
    ];

    public function run(): void
    {
        $start = Carbon::today()->subDays(6)->toDateString();
        $end = Carbon::today()->toDateString();
        $batchIds = array_merge(self::BATCH_IDS, [99]);

        $productionIds = Production::whereIn('batch_id', $batchIds)
            ->whereBetween('production_date', [$start, $end])
            ->pluck('production_id');

        if ($productionIds->isNotEmpty()) {
            QrTransaction::whereIn('production_id', $productionIds)->delete();
            Production::withoutEvents(function () use ($productionIds) {
                Production::whereIn('production_id', $productionIds)->delete();
            });
        }

        $this->deleteDatedAndDemoQrRecords();

        QrRecord::ensurePrintedCodes();

        Production::withoutEvents(function () {
            foreach (self::BATCH_IDS as $batchId) {
                foreach ($this->eggsByBatch[$batchId] as $index => $total) {
                    $date = Carbon::today()->subDays(6 - $index);
                    $sizes = $this->splitBySize($total);

                    $production = Production::create([
                        'production_date' => $date->toDateString(),
                        'batch_id' => $batchId,
                        'small_eggs' => $sizes['small'],
                        'medium_eggs' => $sizes['medium'],
                        'large_eggs' => $sizes['large'],
                        'extra_large_eggs' => $sizes['extra_large'],
                        'cracked_eggs' => $sizes['cracked'],
                        'eggs_produced' => $sizes['produced'],
                    ]);

                    $this->seedScansForProduction($production, $date, $sizes);
                }
            }
        });

        $todayEggs = Production::whereDate('production_date', Carbon::today())
            ->sum('eggs_produced');
        $traysUsedToday = intdiv((int) $todayEggs, 30);
        $trayStock = max(0, 500 - $traysUsedToday);

        InventoryItem::where('item_name', 'Egg Trays')->update([
            'current_stock' => $trayStock,
            'min_level' => 100,
        ]);

        Inventory::where('item_type', 'Egg Tray')->update([
            'quantity' => $trayStock,
            'minimum_stock' => 100,
        ]);

        $this->command?->info('Seeded 3 batches × last 7 harvest days (client-style dummy data).');
        $this->command?->info("Date range: {$start} → {$end}");
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

            $chunks = array_slice($chunks, 0, 10);

            foreach ($chunks as $index => $chunk) {
                $qrCode = QrRecord::clientCode($prefix, $production->batch_id, $index + 1);
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

    private function splitBySize(int $total): array
    {
        if ($total <= 0) {
            return [
                'small' => 0,
                'medium' => 0,
                'large' => 0,
                'extra_large' => 0,
                'cracked' => 0,
                'produced' => 0,
            ];
        }

        $cracked = (int) (round($total * 0.03 / 30) * 30);
        $cracked = max(30, min($cracked, $total - 120));
        $good = $total - $cracked;

        $weights = [
            'small' => 0.22,
            'medium' => 0.32,
            'large' => 0.28,
            'extra_large' => 0.18,
        ];

        $sizes = [];
        $allocated = 0;
        $keys = array_keys($weights);
        $last = end($keys);

        foreach ($weights as $size => $weight) {
            if ($size === $last) {
                $sizes[$size] = $good - $allocated;
                break;
            }
            $part = (int) (round($good * $weight / 30) * 30);
            $sizes[$size] = $part;
            $allocated += $part;
        }

        return [
            'small' => $sizes['small'],
            'medium' => $sizes['medium'],
            'large' => $sizes['large'],
            'extra_large' => $sizes['extra_large'],
            'cracked' => $cracked,
            'produced' => $total,
        ];
    }
}
