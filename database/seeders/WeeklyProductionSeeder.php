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
    /** Demo batch ID — does not overwrite real batch 1/2 QR production. */
    private const DEMO_BATCH_ID = 99;

    /**
     * Daily totals for the last 7 days (oldest → today).
     */
    private array $dailyTotals = [
        1100,
        1450,
        980,
        2200,
        1750,
        1900,
        600,
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

        $demoProductionIds = Production::where('batch_id', self::DEMO_BATCH_ID)
            ->whereBetween('production_date', [$start, $end])
            ->pluck('production_id');

        if ($demoProductionIds->isNotEmpty()) {
            QrTransaction::whereIn('production_id', $demoProductionIds)->delete();
        }

        QrRecord::where('batch_id', self::DEMO_BATCH_ID)
            ->where('qr_code', 'like', '%-B' . self::DEMO_BATCH_ID . '-DEMO-%')
            ->delete();

        Production::where('batch_id', self::DEMO_BATCH_ID)
            ->whereBetween('production_date', [$start, $end])
            ->delete();

        foreach ($this->dailyTotals as $index => $total) {
            $date = Carbon::today()->subDays(6 - $index);
            $sizes = $this->splitBySize($total);

            $production = Production::create([
                'production_date' => $date->toDateString(),
                'batch_id' => self::DEMO_BATCH_ID,
                'small_eggs' => $sizes['small'],
                'medium_eggs' => $sizes['medium'],
                'large_eggs' => $sizes['large'],
                'extra_large_eggs' => $sizes['extra_large'],
                'cracked_eggs' => $sizes['cracked'],
                'eggs_produced' => $sizes['produced'],
            ]);

            $this->seedQrRecordsForProduction($production, $date, $sizes, demo: true);
        }

        $this->rebalanceUndistributedProductions($start, $end);
        $this->syncBatchQrRecords(1, $start, $end);

        $this->command?->info('Seeded demo weekly production for the last 7 days (batch 99).');
        $this->command?->info("Date range: {$start} → {$end}");
        $this->command?->info('Included QR records and scan transactions for each demo day.');
    }

    private function rebalanceUndistributedProductions(string $start, string $end): void
    {
        Production::whereBetween('production_date', [$start, $end])
            ->where('batch_id', '!=', self::DEMO_BATCH_ID)
            ->get()
            ->each(function (Production $production) {
                $total = $production->totalEggCount();

                if ($total < 50) {
                    return;
                }

                $sizeCounts = [
                    $production->small_eggs,
                    $production->medium_eggs,
                    $production->large_eggs,
                    $production->extra_large_eggs,
                    $production->cracked_eggs,
                ];

                $emptyColumns = collect($sizeCounts)->filter(fn (int $count) => $count === 0)->count();

                if ($emptyColumns < 2) {
                    return;
                }

                $sizes = $this->splitBySize($total);

                $production->update([
                    'small_eggs' => $sizes['small'],
                    'medium_eggs' => $sizes['medium'],
                    'large_eggs' => $sizes['large'],
                    'extra_large_eggs' => $sizes['extra_large'],
                    'cracked_eggs' => $sizes['cracked'],
                    'eggs_produced' => $sizes['produced'],
                ]);

                $this->syncQrRecordsFromProduction($production->fresh());
            });
    }

    private function syncBatchQrRecords(int $batchId, string $start, string $end): void
    {
        Production::where('batch_id', $batchId)
            ->whereBetween('production_date', [$start, $end])
            ->each(fn (Production $production) => $this->syncQrRecordsFromProduction($production));
    }

    private function syncQrRecordsFromProduction(Production $production): void
    {
        $qrRecordIds = QrTransaction::where('production_id', $production->production_id)
            ->pluck('qr_record_id');

        QrTransaction::where('production_id', $production->production_id)->delete();

        if ($qrRecordIds->isNotEmpty()) {
            QrRecord::whereIn('id', $qrRecordIds)->delete();
        }

        QrRecord::where('production_id', $production->production_id)->delete();

        $dateStamp = Carbon::parse($production->production_date)->format('Ymd');
        QrRecord::where('batch_id', $production->batch_id)
            ->where('qr_code', 'like', '%-B' . $production->batch_id . '-' . $dateStamp . '-%')
            ->delete();

        $date = Carbon::parse($production->production_date);

        $this->seedQrRecordsForProduction($production, $date, [
            'small' => $production->small_eggs,
            'medium' => $production->medium_eggs,
            'large' => $production->large_eggs,
            'extra_large' => $production->extra_large_eggs,
            'cracked' => $production->cracked_eggs,
        ], demo: $production->batch_id === self::DEMO_BATCH_ID);
    }

    private function seedQrRecordsForProduction(
        Production $production,
        Carbon $date,
        array $sizes,
        bool $demo = false
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
            $trayCount = max(1, (int) ceil($eggCount / $eggsPerTray));

            $qrCode = $this->qrCodeFor(
                $production->batch_id,
                $prefix,
                $date,
                $sequence,
                $demo
            );

            $recordData = [
                'production_id' => $production->production_id,
                'batch_id' => $production->batch_id,
                'qr_code' => $qrCode,
                'egg_size' => $eggSize,
                'tray_count' => $trayCount,
                'eggs_per_tray' => $eggsPerTray,
            ];

            if ($hasQrDate) {
                $recordData['qr_date'] = $date->toDateString();
            }

            $qrRecord = QrRecord::create($recordData);

            QrTransaction::create([
                'production_id' => $production->production_id,
                'qr_record_id' => $qrRecord->id,
                'total_eggs' => $eggCount,
                'status' => 'Scanned',
                'scanned_at' => $scannedAt,
            ]);

            $sequence++;
            $scannedAt = $scannedAt->copy()->addMinutes(15);
        }
    }

    private function qrCodeFor(int $batchId, string $prefix, Carbon $date, int $sequence, bool $demo): string
    {
        if ($demo) {
            return sprintf(
                '%s-B%d-DEMO-%s-%03d',
                $prefix,
                $batchId,
                $date->format('Ymd'),
                $sequence
            );
        }

        if (! Schema::hasColumn('qr_records', 'qr_date')) {
            return sprintf(
                '%s-B%d-%s-%03d',
                $prefix,
                $batchId,
                $date->format('Ymd'),
                $sequence
            );
        }

        return sprintf('%s-B%d-%03d', $prefix, $batchId, $sequence);
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

        $cracked = max(5, (int) round($total * 0.03));
        $good = max(0, $total - $cracked);

        $weights = [
            'small' => 0.22,
            'medium' => 0.30,
            'large' => 0.28,
            'extra_large' => 0.20,
        ];

        $minimumEach = $good >= 200 ? 35 : ($good >= 100 ? 20 : 10);
        $minimumTotal = $minimumEach * 4;

        if ($good < $minimumTotal) {
            $minimumEach = max(1, (int) floor($good / 4));
            $minimumTotal = $minimumEach * 4;
        }

        $sizes = [
            'small' => $minimumEach,
            'medium' => $minimumEach,
            'large' => $minimumEach,
            'extra_large' => $minimumEach,
        ];

        $remainder = $good - $minimumTotal;

        foreach ($weights as $size => $weight) {
            $sizes[$size] += (int) floor($remainder * $weight);
        }

        $allocated = array_sum($sizes);
        $sizes['extra_large'] += $good - $allocated;

        return [
            'small' => $sizes['small'],
            'medium' => $sizes['medium'],
            'large' => $sizes['large'],
            'extra_large' => $sizes['extra_large'],
            'cracked' => $cracked,
            'produced' => array_sum($sizes) + $cracked,
        ];
    }
}
