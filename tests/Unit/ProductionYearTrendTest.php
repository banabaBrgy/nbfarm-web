<?php

namespace Tests\Unit;

use App\Services\ProductionYearTrend;
use Tests\TestCase;

class ProductionYearTrendTest extends TestCase
{
    public function test_month_labels_are_january_through_december(): void
    {
        $trend = new ProductionYearTrend;

        $this->assertSame(
            ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            (new \ReflectionClass($trend))->getConstant('MONTHS')
        );
    }
}
