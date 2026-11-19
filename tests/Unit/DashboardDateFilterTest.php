<?php

namespace Tests\Unit;

use App\Services\DashboardDateFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardDateFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_defaults_to_today(): void
    {
        $filter = DashboardDateFilter::fromRequest(Request::create('/dashboard', 'GET'));

        $this->assertTrue($filter->isToday());
        $this->assertSame('2026-11-08', $filter->startDate());
        $this->assertSame('2026-11-09', $filter->forecastStart()->toDateString());
    }

    public function test_single_date_sets_the_forecast_origin(): void
    {
        $filter = DashboardDateFilter::fromRequest(Request::create('/dashboard', 'GET', [
            'dash_period' => 'date',
            'dash_date' => '2026-11-01',
        ]));

        $this->assertFalse($filter->isToday());
        $this->assertSame('2026-11-01', $filter->endDate());
        $this->assertSame('2026-11-02', $filter->forecastStart()->toDateString());
        $this->assertSame('Farm overview for November 1, 2026.', $filter->subtitle());
    }

    public function test_selecting_today_as_a_custom_date_stays_in_today_mode(): void
    {
        $filter = DashboardDateFilter::fromRequest(Request::create('/dashboard', 'GET', [
            'dash_period' => 'date',
            'dash_date' => '2026-11-08',
        ]));

        $this->assertTrue($filter->isToday());
        $this->assertSame('today', $filter->period);
        $this->assertSame('2026-11-08', $filter->endDate());
    }

    public function test_range_uses_the_end_date_as_the_reference(): void
    {
        $filter = DashboardDateFilter::fromRequest(Request::create('/dashboard', 'GET', [
            'dash_period' => 'range',
            'dash_from' => '2026-11-01',
            'dash_to' => '2026-11-03',
        ]));

        $this->assertTrue($filter->isRange());
        $this->assertSame('2026-11-01', $filter->startDate());
        $this->assertSame('2026-11-03', $filter->endDate());
        $this->assertSame('2026-11-04', $filter->forecastStart()->toDateString());
    }

    public function test_from_and_to_work_without_the_period_tabs(): void
    {
        $single = DashboardDateFilter::fromRequest(Request::create('/dashboard', 'GET', [
            'dash_from' => '2026-11-01',
            'dash_to' => '2026-11-01',
        ]));

        $this->assertSame('date', $single->period);
        $this->assertFalse($single->isRange());
        $this->assertSame('2026-11-02', $single->forecastStart()->toDateString());

        $today = DashboardDateFilter::fromRequest(Request::create('/dashboard', 'GET', [
            'dash_from' => '2026-11-08',
            'dash_to' => '2026-11-08',
        ]));

        $this->assertTrue($today->isToday());
        $this->assertSame('today', $today->period);
    }
}
