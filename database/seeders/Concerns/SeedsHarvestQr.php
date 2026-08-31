<?php

namespace Database\Seeders\Concerns;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use Carbon\Carbon;

trait SeedsHarvestQr
{
    use SplitsEggSizes;

    /**
     * Replace harvest rows in [$start, $end] and attach dummy QR scans that match size totals.
     */
    protected function seedHarvestRange(Carbon $start, Carbon $end, callable $totalForDate): int
    {
        QrRecord::ensurePrintedCodes();

        $qrIds = QrRecord::query()->pluck('id', 'qr_code');
        $days = 0;
        $cursor = $start->copy()->startOfDay();
        $rangeEnd = $end->copy()->startOfDay();

        Production::withoutEvents(function () use ($cursor, $rangeEnd, $totalForDate, $qrIds, &$days) {
            while ($cursor->lte($rangeEnd)) {
                $chunkEnd = $cursor->copy()->addDays(45);
                if ($chunkEnd->gt($rangeEnd)) {
                    $chunkEnd = $rangeEnd->copy();
                }

                $dates = [];
                for ($day = $cursor->copy(); $day->lte($chunkEnd); $day->addDay()) {
                    $dates[] = $day->toDateString();
                }

                $existingIds = Production::query()
                    ->whereIn('production_date', $dates)
                    ->pluck('production_id');

                if ($existingIds->isNotEmpty()) {
                    QrTransaction::whereIn('production_id', $existingIds)->delete();
                    Production::whereIn('production_id', $existingIds)->delete();
                }

                $rows = [];
                foreach ($dates as $dateStr) {
                    $date = Carbon::parse($dateStr);
                    $farmTotal = (int) $totalForDate($date);
                    foreach ($this->splitAcrossBatches($farmTotal, $date) as $batchId => $batchTotal) {
                        $sizes = $this->splitBySize($batchTotal);
                        $rows[] = [
                            'production_date' => $dateStr,
                            'batch_id' => $batchId,
                            'small_eggs' => $sizes['small'],
                            'medium_eggs' => $sizes['medium'],
                            'large_eggs' => $sizes['large'],
                            'extra_large_eggs' => $sizes['extra_large'],
                            'cracked_eggs' => $sizes['cracked'],
                            'eggs_produced' => $sizes['produced'],
                        ];
                    }
                }

                Production::insert($rows);

                $productions = Production::query()
                    ->whereIn('production_date', $dates)
                    ->orderBy('production_date')
                    ->orderBy('batch_id')
                    ->get();

                $scans = [];
                $now = now();
                foreach ($productions as $production) {
                    $scans = array_merge(
                        $scans,
                        $this->scanRowsForProduction($production, $qrIds, $now)
                    );
                }

                foreach (array_chunk($scans, 400) as $batch) {
                    QrTransaction::insert($batch);
                }

                $days += count($dates);
                $cursor = $chunkEnd->copy()->addDay();
            }
        });

        return $days;
    }

    /**
     * Split one farm-day total across Batch 1–3. Parts stay multiples of 30 and sum to $farmTotal.
     *
     * @return array<int, int>
     */
    protected function splitAcrossBatches(int $farmTotal, Carbon $date): array
    {
        $batchIds = QrRecord::BATCH_IDS;
        $count = count($batchIds);
        $weights = [0.36, 0.33, 0.31];
        $shift = $date->dayOfYear % $count;
        $weights = array_merge(array_slice($weights, $shift), array_slice($weights, 0, $shift));

        $totals = [];
        $allocated = 0;
        foreach ($batchIds as $index => $batchId) {
            if ($index === $count - 1) {
                $totals[$batchId] = max(0, $farmTotal - $allocated);
                break;
            }

            $part = (int) (round($farmTotal * $weights[$index] / 30) * 30);
            $totals[$batchId] = $part;
            $allocated += $part;
        }

        return $totals;
    }

    /** Deterministic daily farm total, multiple of 30. */
    protected function dailyWaveTotal(Carbon $date): int
    {
        $wave = (int) (round(sin($date->dayOfYear / 365 * M_PI * 2) * 240 / 30) * 30);
        $weekdayBoost = [0, 60, 90, 120, 150, 180, 90][$date->dayOfWeek] ?? 0;

        return 4200 + $wave + $weekdayBoost;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>|array<string, int>  $qrIds
     * @return list<array<string, mixed>>
     */
    private function scanRowsForProduction(Production $production, $qrIds, Carbon $now): array
    {
        $date = Carbon::parse($production->production_date);
        $scannedAt = $date->copy()->setTime(8, 30);
        $rows = [];

        $sizes = [
            'Small' => (int) $production->small_eggs,
            'Medium' => (int) $production->medium_eggs,
            'Large' => (int) $production->large_eggs,
            'Extra Large' => (int) $production->extra_large_eggs,
            'Cracked' => (int) $production->cracked_eggs,
        ];

        $batchId = (int) $production->batch_id;

        foreach (QrRecord::SIZE_PREFIXES as $eggSize => $prefix) {
            $eggCount = $sizes[$eggSize] ?? 0;
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
                $sequence = $index + 1;
                $qrCode = QrRecord::clientCode($prefix, $batchId, $sequence);
                $qrRecordId = $qrIds[$qrCode] ?? null;
                if (! $qrRecordId) {
                    continue;
                }

                $rows[] = [
                    'qr_record_id' => $qrRecordId,
                    'production_id' => $production->production_id,
                    'total_eggs' => $chunk,
                    'status' => $chunk === 300 ? 'Scanned' : 'Edited',
                    'scanned_by' => null,
                    'scanned_at' => $scannedAt->toDateTimeString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $scannedAt = $scannedAt->copy()->addMinutes(10);
            }
        }

        return $rows;
    }
}
