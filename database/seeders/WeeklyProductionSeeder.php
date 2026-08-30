<?php

namespace Database\Seeders;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

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

    private array $sizePrefixes = [
        'Small' => 'QRS',
        'Medium' => 'QRM',
        'Large' => 'QRL',
        'Extra Large' => 'QRXL',
        'Cracked' => 'QRC',
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
            $qrIds = QrTransaction::whereIn('production_id', $productionIds)->pluck('qr_record_id');
            QrTransaction::whereIn('production_id', $productionIds)->delete();
            if ($qrIds->isNotEmpty()) {
                QrRecord::whereIn('id', $qrIds)->delete();
            }
            QrRecord::whereIn('production_id', $productionIds)->delete();
            Production::whereIn('production_id', $productionIds)->delete();
        }

        QrRecord::where('batch_id', 99)
            ->where('qr_code', 'like', '%-B99-DEMO-%')
            ->delete();

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

                $this->seedQrRecordsForProduction($production, $date, $sizes);
            }
        }

        $this->command?->info('Seeded 3 batches × last 7 harvest days (client-style dummy data).');
        $this->command?->info("Date range: {$start} → {$end}");
        $this->command?->info('Farm daily totals: 4230, 4320, 4410, 4500, 4590, 4680, 4410.');
    }

    private function seedQrRecordsForProduction(
        Production $production,
        Carbon $date,
        array $sizes
    ): void {
        $sequence = 1;
        $scannedAt = $date->copy()->setTime(8, 30);
        $hasQrDate = Schema::hasColumn('qr_records', 'qr_date');

        foreach ($this->sizePrefixes as $eggSize => $prefix) {
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

            $eggsPerTray = 30;
            $fullStacks = intdiv($eggCount, 300);
            $leftover = $eggCount % 300;

            $chunks = array_fill(0, $fullStacks, 300);
            if ($leftover > 0) {
                $chunks[] = $leftover;
            }
            if ($chunks === []) {
                $chunks[] = $eggCount;
            }

            foreach ($chunks as $chunk) {
                $trayCount = max(1, (int) ceil($chunk / $eggsPerTray));
                $qrCode = sprintf(
                    '%s-B%d-%s-%03d',
                    $prefix,
                    $production->batch_id,
                    $date->format('Ymd'),
                    $sequence
                );

                $recordData = [
                    'production_id' => $production->production_id,
                    'batch_id' => $production->batch_id,
                    'qr_code' => $qrCode,
                    'egg_size' => $eggSize,
                    'tray_count' => min(10, $trayCount),
                    'eggs_per_tray' => $eggsPerTray,
                ];

                if ($hasQrDate) {
                    $recordData['qr_date'] = $date->toDateString();
                }

                $qrRecord = QrRecord::create($recordData);

                QrTransaction::create([
                    'production_id' => $production->production_id,
                    'qr_record_id' => $qrRecord->id,
                    'total_eggs' => $chunk,
                    'status' => 'Scanned',
                    'scanned_at' => $scannedAt,
                ]);

                $sequence++;
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
