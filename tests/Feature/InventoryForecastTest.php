<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Forecasting\GenerateEggForecast;
use App\Services\InventoryForecast;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_feed_and_vitamin_use_is_seven_days_and_names_the_minimum_day(): void
    {
        Carbon::setTestNow('2026-10-23 08:00:00');

        $forecast = app(InventoryForecast::class)->fromEggs(
            (object) ['current_stock' => 100, 'min_level' => 20, 'unit' => 'sacks'],
            (object) ['current_stock' => 40, 'min_level' => 10, 'unit' => 'bottles'],
            (object) ['current_stock' => 500, 'min_level' => 10, 'daily_stock' => 500, 'unit' => 'pcs'],
            $this->eggDays([3000, 3000, 3000, 3000, 3000, 3000, 3000])
        );

        $feeds = $forecast['supplies'][0];
        $vitamins = $forecast['supplies'][1];

        $this->assertSame(15.0, $feeds['daily_use']);
        $this->assertSame(105.0, $feeds['consumption']);
        $this->assertSame(-5.0, $feeds['stock_after']);
        $this->assertFalse($feeds['already_at_minimum']);
        $this->assertSame('2026-10-29', $feeds['reaches_minimum_on']);

        $this->assertSame(5.0, $vitamins['daily_use']);
        $this->assertSame(35.0, $vitamins['consumption']);
        $this->assertSame(5.0, $vitamins['stock_after']);
        $this->assertSame('2026-10-29', $vitamins['reaches_minimum_on']);
    }

    public function test_tray_days_reset_instead_of_adding_into_one_balance(): void
    {
        Carbon::setTestNow('2026-10-23 08:00:00');

        $forecast = app(InventoryForecast::class)->fromEggs(
            (object) ['current_stock' => 10, 'min_level' => 20, 'unit' => 'sacks'],
            (object) ['current_stock' => 40, 'min_level' => 0, 'unit' => 'bottles'],
            (object) ['current_stock' => 80, 'min_level' => 10, 'daily_stock' => 500, 'unit' => 'pcs'],
            $this->eggDays([3000, 15100, 600, 600, 600, 600, 600])
        );

        $this->assertTrue($forecast['supplies'][0]['already_at_minimum']);
        $this->assertNull($forecast['supplies'][0]['reaches_minimum_on']);

        $trays = $forecast['trays'];
        $this->assertSame(100, $trays[0]['trays_needed']);
        $this->assertSame(400.0, $trays[0]['trays_left']);
        $this->assertFalse($trays[0]['below_minimum']);

        $this->assertSame(503, $trays[1]['trays_needed']);
        $this->assertSame(-3.0, $trays[1]['trays_left']);
        $this->assertTrue($trays[1]['below_minimum']);

        $this->assertSame(20, $trays[2]['trays_needed']);
        $this->assertSame(480.0, $trays[2]['trays_left']);
        $this->assertSame(100 + 503 + (20 * 5), (int) $forecast['supplies'][2]['consumption']);
        $this->assertNull($forecast['supplies'][2]['stock_after']);
        $this->assertTrue($forecast['supplies'][2]['resets_daily']);
    }

    public function test_upcoming_egg_days_do_not_write_forecast_rows(): void
    {
        Carbon::setTestNow('2026-10-23 08:00:00');
        config([
            'services.weather.lat' => '',
            'services.weather.lon' => '',
        ]);

        $days = app(GenerateEggForecast::class)->upcomingDays(7);

        $this->assertCount(7, $days);
        $this->assertSame('2026-10-24', $days[0]['date']);
        $this->assertSame('2026-10-30', $days[6]['date']);
        $this->assertSame(0, Forecast::count());
    }

    public function test_inventory_pages_show_the_next_seven_days(): void
    {
        Carbon::setTestNow('2026-10-23 08:00:00');
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = User::factory()->create(['role' => 'staff']);

        InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 200,
            'min_level' => 20,
        ]);
        InventoryItem::create([
            'item_name' => 'Vitamins',
            'unit' => 'bottles',
            'current_stock' => 40,
            'min_level' => 5,
        ]);
        InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 500,
            'min_level' => 10,
            'daily_stock' => 500,
        ]);

        $this->mock(GenerateEggForecast::class, function ($mock) {
            $mock->shouldReceive('upcomingDays')->andReturn($this->eggDays([
                3000, 3000, 15000, 3000, 3000, 3000, 3000,
            ]));
        });

        $this->actingAs($owner)
            ->get(route('owner.inventory'))
            ->assertOk()
            ->assertSee('Next 7 days')
            ->assertSee('105')
            ->assertSee('35')
            ->assertSee('15,000')
            ->assertSee('At or under minimum')
            ->assertSee('Resets each day');

        $this->actingAs($staff)
            ->get(route('staff.inventory'))
            ->assertOk()
            ->assertSee('Next 7 days')
            ->assertSee('15,000');
    }

    /**
     * @param  list<int>  $eggs
     * @return list<array{date: string, eggs: int}>
     */
    private function eggDays(array $eggs): array
    {
        $start = Carbon::parse('2026-10-24')->startOfDay();
        $days = [];

        foreach ($eggs as $index => $count) {
            $days[] = [
                'date' => $start->copy()->addDays($index)->toDateString(),
                'eggs' => $count,
            ];
        }

        return $days;
    }
}
