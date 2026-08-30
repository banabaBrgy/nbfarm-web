<?php

namespace Tests\Unit;

use App\Models\Production;
use Carbon\Carbon;
use Tests\TestCase;

class PresentYearProductionTest extends TestCase
{
    public function test_visible_year_follows_the_clock(): void
    {
        Carbon::setTestNow('2026-08-30');

        $this->assertSame(2026, Production::visibleYear());
        $this->assertSame(2023, Production::visibleYear('2023-05-01'));

        Carbon::setTestNow();
    }
}
