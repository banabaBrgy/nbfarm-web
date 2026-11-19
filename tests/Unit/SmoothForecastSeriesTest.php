<?php

namespace Tests\Unit;

use App\Services\Forecasting\SmoothForecastSeries;
use PHPUnit\Framework\TestCase;

class SmoothForecastSeriesTest extends TestCase
{
    public function test_a_series_that_already_moves_slowly_is_unchanged(): void
    {
        $smoother = new SmoothForecastSeries;

        $this->assertSame([2222, 2300], $smoother->apply([2222, 2300]));
        $this->assertSame([6900, 6930, 6960], $smoother->apply([6900, 6930, 6960]));
    }

    public function test_a_one_day_spike_shrinks_without_leaving_the_model(): void
    {
        $raw = [6800, 7000, 6850, 6900, 6780];
        $smoothed = (new SmoothForecastSeries)->apply($raw);

        $this->assertCount(5, $smoothed);
        foreach ($raw as $index => $value) {
            $band = max(120, (int) round($value * 0.02));
            $this->assertGreaterThanOrEqual($value - $band, $smoothed[$index]);
            $this->assertLessThanOrEqual($value + $band, $smoothed[$index]);
        }

        $rawStep = $this->maxStep($raw);
        $smoothStep = $this->maxStep($smoothed);
        $this->assertLessThan($rawStep, $smoothStep);
        $this->assertLessThanOrEqual(90, $smoothStep);
    }

    public function test_a_real_level_change_is_not_flattened_away(): void
    {
        $smoothed = (new SmoothForecastSeries)->apply([6000, 9000, 6000]);

        $this->assertGreaterThanOrEqual(5880, $smoothed[0]);
        $this->assertLessThanOrEqual(6120, $smoothed[0]);
        $this->assertGreaterThanOrEqual(8820, $smoothed[1]);
        $this->assertLessThanOrEqual(9180, $smoothed[1]);
        $this->assertGreaterThanOrEqual(5880, $smoothed[2]);
        $this->assertLessThanOrEqual(6120, $smoothed[2]);
        $this->assertGreaterThan(2000, abs($smoothed[1] - $smoothed[0]));
    }

    public function test_the_first_point_can_stay_locked_to_the_model(): void
    {
        $smoothed = (new SmoothForecastSeries)->apply([6800, 7000, 6800], 0);

        $this->assertSame(6800, $smoothed[0]);
        $this->assertLessThan(7000, $smoothed[1]);
        $this->assertGreaterThanOrEqual(6860, $smoothed[1]);
    }

    public function test_a_middle_day_can_stay_locked_to_the_model(): void
    {
        $smoothed = (new SmoothForecastSeries)->apply([6800, 7000, 6800], 1);

        $this->assertSame(7000, $smoothed[1]);
        $this->assertLessThanOrEqual(6936, $smoothed[0]);
        $this->assertGreaterThanOrEqual(6664, $smoothed[0]);
    }

    public function test_lock_from_keeps_tomorrow_and_later_on_the_model(): void
    {
        $raw = [7320, 7200, 7080, 6870, 6870, 6870];
        $smoothed = (new SmoothForecastSeries)->apply($raw, 3, true);

        $this->assertSame(6870, $smoothed[3]);
        $this->assertSame(6870, $smoothed[4]);
        $this->assertSame(6870, $smoothed[5]);
        $this->assertNotSame($raw[0], $smoothed[0]);
    }

    public function test_missing_days_stay_missing(): void
    {
        $smoothed = (new SmoothForecastSeries)->apply([6800, null, 7200, 'n/a']);

        $this->assertSame([6800, null, 7200, null], $smoothed);
    }

    public function test_near_model_uses_the_same_two_percent_band(): void
    {
        $smoother = new SmoothForecastSeries;

        $this->assertTrue($smoother->isNearModel(6840, 6900));
        $this->assertFalse($smoother->isNearModel(2000, 6900));
    }

    /**
     * @param  list<int>  $values
     */
    private function maxStep(array $values): int
    {
        $max = 0;
        for ($i = 1, $count = count($values); $i < $count; $i++) {
            $max = max($max, abs($values[$i] - $values[$i - 1]));
        }

        return $max;
    }
}
