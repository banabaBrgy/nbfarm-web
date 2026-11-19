<?php

namespace App\Services\Forecasting;

/**
 * Softens day-to-day jumps on a forecast line.
 * Each point stays within 2% of the model total (at least 120 eggs),
 * so a one-day spike shrinks and a real level change stays visible.
 */
class SmoothForecastSeries
{
    private const STEP_RATIO = 0.012;

    private const MIN_STEP = 90;

    private const DRIFT_RATIO = 0.02;

    private const MIN_DRIFT = 120;

    private const PASSES = 8;

    public function isNearModel(int $value, int $model): bool
    {
        $band = max((float) self::MIN_DRIFT, abs($model) * self::DRIFT_RATIO);

        return abs($value - $model) <= $band;
    }

    /**
     * @param  list<int|float|null>  $values
     * @return list<int|null>
     */
    public function apply(array $values, ?int $lockIndex = null, bool $lockFrom = false): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[] = is_numeric($value) ? (int) round((float) $value) : null;
        }

        $count = count($result);
        $start = null;
        for ($i = 0; $i <= $count; $i++) {
            $numeric = $i < $count && $result[$i] !== null;
            if ($numeric && $start === null) {
                $start = $i;
            }
            if (! $numeric && $start !== null) {
                $end = $i - 1;
                $lockLocal = $this->lockLocal($lockIndex, $lockFrom, $start, $end);
                $this->smoothSlice($result, $start, $end, $lockLocal, $lockFrom && $lockLocal !== null);
                $start = null;
            }
        }

        return $result;
    }

    private function lockLocal(?int $lockIndex, bool $lockFrom, int $start, int $end): ?int
    {
        if ($lockIndex === null) {
            return null;
        }

        if ($lockFrom && $lockIndex < $start) {
            return 0;
        }

        if ($lockIndex >= $start && $lockIndex <= $end) {
            return $lockIndex - $start;
        }

        return null;
    }

    /**
     * @param  list<int|null>  $result
     */
    private function smoothSlice(array &$result, int $from, int $to, ?int $lockLocal, bool $lockFrom = false): void
    {
        if ($to - $from < 1) {
            return;
        }

        $raw = [];
        for ($i = $from; $i <= $to; $i++) {
            $raw[] = (float) $result[$i];
        }

        if ($this->stepsAreSmall($raw)) {
            return;
        }

        $current = $raw;
        $length = count($raw);
        for ($pass = 0; $pass < self::PASSES; $pass++) {
            $blended = $current;
            for ($i = 0; $i < $length; $i++) {
                if ($this->isLocked($i, $lockLocal, $lockFrom)) {
                    $blended[$i] = $raw[$i];

                    continue;
                }

                $neighbor = $i === 0
                    ? $current[1]
                    : ($i === $length - 1
                        ? $current[$length - 2]
                        : ($current[$i - 1] + $current[$i + 1]) / 2);
                $target = ($current[$i] * 0.4) + ($neighbor * 0.6);
                $blended[$i] = $this->clampToModel($target, $raw[$i]);
            }
            $current = $this->capSteps($blended, $raw, $lockLocal, $lockFrom);
        }

        for ($i = 0; $i < $length; $i++) {
            $result[$from + $i] = (int) round($current[$i]);
        }
    }

    private function isLocked(int $index, ?int $lockLocal, bool $lockFrom): bool
    {
        if ($lockLocal === null) {
            return false;
        }

        return $lockFrom ? $index >= $lockLocal : $lockLocal === $index;
    }

    /**
     * @param  list<float>  $values
     */
    private function stepsAreSmall(array $values): bool
    {
        $count = count($values);
        for ($i = 1; $i < $count; $i++) {
            if (abs($values[$i] - $values[$i - 1]) > $this->stepCap($values[$i - 1]) + 0.5) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<float>  $values
     * @param  list<float>  $raw
     * @return list<float>
     */
    private function capSteps(array $values, array $raw, ?int $lockLocal, bool $lockFrom = false): array
    {
        $count = count($values);
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            if ($this->isLocked($i, $lockLocal, $lockFrom)) {
                $out[$i] = $raw[$i];

                continue;
            }

            if ($i === 0) {
                $out[$i] = $this->clampToModel($values[$i], $raw[$i]);

                continue;
            }
            $cap = $this->stepCap($out[$i - 1]);
            $stepLow = $out[$i - 1] - $cap;
            $stepHigh = $out[$i - 1] + $cap;
            [$modelLow, $modelHigh] = $this->modelBand($raw[$i]);
            $low = max($stepLow, $modelLow);
            $high = min($stepHigh, $modelHigh);

            if ($low <= $high) {
                $out[$i] = min($high, max($low, $values[$i]));

                continue;
            }

            $out[$i] = abs($modelLow - $out[$i - 1]) <= abs($modelHigh - $out[$i - 1])
                ? $modelLow
                : $modelHigh;
        }

        return $out;
    }

    private function stepCap(float $previous): float
    {
        return max((float) self::MIN_STEP, abs($previous) * self::STEP_RATIO);
    }

    private function clampToModel(float $value, float $raw): float
    {
        [$low, $high] = $this->modelBand($raw);

        return min($high, max($low, $value));
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function modelBand(float $raw): array
    {
        $band = max((float) self::MIN_DRIFT, abs($raw) * self::DRIFT_RATIO);

        return [$raw - $band, $raw + $band];
    }
}
