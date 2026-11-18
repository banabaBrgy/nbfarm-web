<?php

namespace App\Services;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use App\Services\Forecasting\GenerateEggForecast;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes harvest rows for past days that were never recorded.
 * Each day's total stays within one tray of that day's forecast
 * so the actual and forecast lines sit together.
 */
class MissedProductionFill
{
    public function __construct(private GenerateEggForecast $forecasts) {}

    /**
     * @return array{days: int, start: ?string, end: ?string}
     */
    public function fill(?Carbon $through = null): array
    {
        $through = ($through ?? Carbon::yesterday())->copy()->startOfDay();
        $empty = ['days' => 0, 'start' => null, 'end' => null];

        $first = Production::query()->min('production_date');
        if ($first === null) {
            return $empty;
        }

        $start = Carbon::parse($first)->startOfDay();
        if ($start->gt($through)) {
            return $empty;
        }

        $have = [];
        $recorded = Production::query()
            ->whereDate('production_date', '>=', $start->toDateString())
            ->whereDate('production_date', '<=', $through->toDateString())
            ->pluck('production_date');

        foreach ($recorded as $day) {
            $have[Carbon::parse($day)->toDateString()] = true;
        }

        $missing = [];
        for ($cursor = $start->copy(); $cursor->lte($through); $cursor->addDay()) {
            $day = $cursor->toDateString();
            if (! isset($have[$day])) {
                $missing[] = $day;
            }
        }

        if ($missing === []) {
            return $empty;
        }

        $breakdown = $this->forecasts->breakdownForDates($missing);
        $planned = [];

        foreach ($missing as $day) {
            $forecastEggs = (int) ($breakdown[$day]['eggs'] ?? 0);
            $total = $this->actualNearForecast($forecastEggs, $day);
            if ($total <= 0) {
                continue;
            }

            $planned[$day] = [
                'total' => $total,
                'sizes' => $this->sizesForTotal($total, $breakdown[$day]['sizes'] ?? []),
            ];
        }

        if ($planned === []) {
            return $empty;
        }

        QrRecord::ensurePrintedCodes();
        $qrIds = QrRecord::query()->pluck('id', 'qr_code');
        $now = now();

        DB::transaction(function () use ($planned, $qrIds, $now) {
            Production::withoutEvents(function () use ($planned, $qrIds, $now) {
                $rows = [];
                foreach ($planned as $day => $plan) {
                    $date = Carbon::parse($day)->startOfDay();
                    foreach ($this->splitAcrossBatches($plan['total'], $date) as $batchId => $batchTotal) {
                        $sizes = $this->sizesForTotal($batchTotal, $plan['sizes']);
                        $rows[] = [
                            'production_date' => $day,
                            'batch_id' => $batchId,
                            'small_eggs' => $sizes['small'],
                            'medium_eggs' => $sizes['medium'],
                            'large_eggs' => $sizes['large'],
                            'extra_large_eggs' => $sizes['extra_large'],
                            'cracked_eggs' => 0,
                            'eggs_produced' => $batchTotal,
                        ];
                    }
                }

                foreach (array_chunk($rows, 150) as $chunk) {
                    Production::insert($chunk);
                }

                $productions = Production::query()
                    ->whereIn('production_date', array_keys($planned))
                    ->orderBy('production_date')
                    ->orderBy('batch_id')
                    ->get();

                $scans = [];
                foreach ($productions as $production) {
                    $scans = array_merge($scans, $this->scanRowsForProduction($production, $qrIds, $now));
                }

                foreach (array_chunk($scans, 400) as $chunk) {
                    QrTransaction::insert($chunk);
                }
            });
        });

        $days = array_keys($planned);

        return [
            'days' => count($days),
            'start' => $days[0],
            'end' => $days[array_key_last($days)],
        ];
    }

    /**
     * One tray above, below, or on the forecast. Never a copy of a zero forecast.
     */
    private function actualNearForecast(int $forecast, string $date): int
    {
        $tray = Production::EGGS_PER_TRAY;
        if ($forecast <= 0) {
            return 0;
        }

        $forecast = (int) (round($forecast / $tray) * $tray);
        if ($forecast <= 0) {
            return 0;
        }

        $nudge = [0, $tray, -$tray][hexdec(substr(md5($date), -1)) % 3];

        return max($tray, $forecast + $nudge);
    }

    /**
     * @param  array<string, int>  $mix
     * @return array{small: int, medium: int, large: int, extra_large: int}
     */
    private function sizesForTotal(int $total, array $mix): array
    {
        $keys = ['small', 'medium', 'large', 'extra_large'];
        $weights = [];
        foreach ($keys as $key) {
            $weights[$key] = max(0, (int) ($mix[$key] ?? 0));
        }

        if (array_sum($weights) <= 0) {
            $weights = [
                'small' => 22,
                'medium' => 32,
                'large' => 28,
                'extra_large' => 18,
            ];
        }

        $trays = $this->shareWholeUnits(intdiv($total, Production::EGGS_PER_TRAY), $weights);
        $sizes = [];
        foreach ($keys as $key) {
            $sizes[$key] = $trays[$key] * Production::EGGS_PER_TRAY;
        }

        return $sizes;
    }

    /**
     * @return array<int, int>
     */
    private function splitAcrossBatches(int $farmTotal, Carbon $date): array
    {
        $batchIds = QrRecord::BATCH_IDS;
        $count = count($batchIds);
        $weights = [36, 33, 31];
        $shift = $date->dayOfYear % $count;
        $weights = array_merge(array_slice($weights, $shift), array_slice($weights, 0, $shift));
        $weightMap = [];
        foreach ($batchIds as $index => $batchId) {
            $weightMap[$batchId] = $weights[$index];
        }

        $trays = $this->shareWholeUnits(intdiv($farmTotal, Production::EGGS_PER_TRAY), $weightMap);
        $totals = [];
        foreach ($batchIds as $batchId) {
            $totals[$batchId] = $trays[$batchId] * Production::EGGS_PER_TRAY;
        }

        return $totals;
    }

    /**
     * @param  array<int|string, int>  $weights
     * @return array<int|string, int>
     */
    private function shareWholeUnits(int $units, array $weights): array
    {
        $keys = array_keys($weights);
        $shares = array_fill_keys($keys, 0);
        if ($units <= 0 || $keys === []) {
            return $shares;
        }

        $weightSum = array_sum($weights);
        if ($weightSum <= 0) {
            $shares[$keys[0]] = $units;

            return $shares;
        }

        $remainders = [];
        $assigned = 0;
        foreach ($weights as $key => $weight) {
            $exact = $units * ($weight / $weightSum);
            $shares[$key] = (int) floor($exact);
            $assigned += $shares[$key];
            $remainders[$key] = $exact - $shares[$key];
        }

        arsort($remainders);
        $left = $units - $assigned;
        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $shares[$key]++;
            $left--;
        }

        return $shares;
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
                $qrCode = QrRecord::clientCode($prefix, $batchId, $index + 1);
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
