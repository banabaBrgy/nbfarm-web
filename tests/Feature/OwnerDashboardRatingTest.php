<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Production;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardRatingTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_owner_cards_show_only_up_down_rating_not_actual_target_labels(): void
    {
        $this->harvest('2026-11-07', 10000);
        $this->harvest('2026-11-08', 8000);
        $this->dispatchDay('2026-11-07', 12, 1200, 'Delivered');
        $this->dispatchDay('2026-11-08', 8, 800, 'Delivered');
        $this->dispatchDay('2026-11-08', 5, 500, 'Pending');

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('Total Eggs Produced')
            ->assertSee('Eggs Stock')
            ->assertSee('Total Dispatch')
            ->assertSee('Revenue')
            ->assertSee('-20.0%')
            ->assertSee('-33.3%')
            ->assertSee('target-rating--down', false)
            ->assertSee('fa-arrow-down', false)
            ->assertDontSee('Achievement')
            ->assertDontSee('Below Target')
            ->assertDontSee('On Target')
            ->assertDontSee('Above Target')
            ->assertDontSee('vs yesterday')
            ->assertDontSee('vs expected')
            ->assertDontSee('kpi-indicator')
            ->assertDontSee('No scheduled orders');
    }

    public function test_production_card_shows_db_total_while_rating_uses_previous_period(): void
    {
        // Previous period for Nov 1–2 is Oct 30–31 (same length).
        $this->harvest('2026-10-30', 5000);
        $this->harvest('2026-10-31', 5000);
        $this->harvest('2026-11-01', 7000);
        $this->harvest('2026-11-02', 7400);

        $owner = User::factory()->create(['role' => 'owner']);

        $html = $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'dash_period' => 'range',
                'dash_from' => '2026-11-01',
                'dash_to' => '2026-11-02',
            ]))
            ->assertOk()
            ->getContent();

        // Card keeps DB sum (14,400). Rating: (14400 - 10000) / 10000 = +44.0%
        $this->assertMatchesRegularExpression(
            '/stat-card--eggs[\s\S]*?<h2>\s*14,400\s*<\/h2>[\s\S]*?\+44\.0%/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/Stock In[\s\S]*?14,400\.00/',
            $html
        );
    }

    public function test_dispatch_and_revenue_ratings_compare_delivered_to_previous_period(): void
    {
        $this->dispatchDay('2026-11-07', 10, 1000, 'Delivered');
        $this->dispatchDay('2026-11-08', 6, 800, 'Delivered');
        $this->dispatchDay('2026-11-08', 6, 800, 'Pending');

        $owner = User::factory()->create(['role' => 'owner']);

        $html = $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->getContent();

        // Card totals stay as DB summary; rating uses Delivered only vs yesterday Delivered.
        // Dispatch: 6 vs 10 = -40.0%. Revenue: 800 vs 1000 = -20.0%.
        $this->assertMatchesRegularExpression(
            '/stat-card--dispatch[\s\S]*?<h2>\s*12\s*<\/h2>[\s\S]*?-40\.0%/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/stat-card--revenue[\s\S]*?<h2>\s*₱800\s*<\/h2>[\s\S]*?-20\.0%/',
            $html
        );
    }

    public function test_stock_rating_compares_to_previous_period_end(): void
    {
        $this->harvest('2026-11-07', 5000);
        $this->harvest('2026-11-08', 8000);

        $owner = User::factory()->create(['role' => 'owner']);

        $html = $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->getContent();

        $previous = Production::leftoverStock('2026-11-07');
        $current = Production::leftoverStock();
        $this->assertGreaterThan(0, $previous);
        $expected = round((($current - $previous) / $previous) * 100, 1);
        $sign = $expected > 0 ? '\\+' : '';

        $this->assertMatchesRegularExpression(
            '/stat-card--predicted[\s\S]*?'.$sign.preg_quote(number_format($expected, 1), '/').'%/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/stat-card--predicted[\s\S]*?target-rating--caution/',
            $html
        );
    }

    public function test_stock_rating_uses_range_end_against_previous_period_end(): void
    {
        $this->harvest('2026-10-30', 4000);
        $this->harvest('2026-10-31', 4000);
        $this->harvest('2026-11-01', 5000);
        $this->harvest('2026-11-02', 6000);

        $owner = User::factory()->create(['role' => 'owner']);

        $html = $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'dash_period' => 'range',
                'dash_from' => '2026-11-01',
                'dash_to' => '2026-11-02',
            ]))
            ->assertOk()
            ->getContent();

        $previous = Production::leftoverStock('2026-10-31');
        $current = Production::leftoverStock('2026-11-02');
        $this->assertGreaterThan(0, $previous);
        $expected = round((($current - $previous) / $previous) * 100, 1);
        $sign = $expected > 0 ? '\\+' : '';

        $this->assertMatchesRegularExpression(
            '/stat-card--predicted[\s\S]*?'.$sign.preg_quote(number_format($expected, 1), '/').'%/',
            $html
        );
    }

    public function test_no_previous_data_shows_message_without_percentage(): void
    {
        $this->harvest('2026-11-08', 7200);
        $this->dispatchDay('2026-11-08', 4, 400, 'Delivered');

        $owner = User::factory()->create(['role' => 'owner']);

        $html = $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('No previous data')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/stat-card--dispatch[\s\S]*?No previous data/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/stat-card--revenue[\s\S]*?No previous data/',
            $html
        );
    }

    public function test_manager_dashboard_does_not_show_owner_target_ratings(): void
    {
        $this->harvest('2026-11-08', 7200);
        $this->dispatchDay('2026-11-08', 10, 1000, 'Delivered');

        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertDontSee('target-rating')
            ->assertDontSee('Actual')
            ->assertDontSee('Achievement')
            ->assertDontSee('Below Target')
            ->assertDontSee('No previous data');
    }

    private function harvest(string $date, int $eggs): void
    {
        Production::query()->create([
            'production_date' => $date,
            'batch_id' => 1,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => $eggs,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => $eggs,
        ]);
    }

    private function dispatchDay(string $date, int $trays, float $price, string $status): void
    {
        $dispatch = Dispatch::create([
            'recipient' => 'Store '.$date.' '.$status.' '.$trays,
            'address' => 'Lipa City',
            'dispatch_date' => $date,
            'price' => $price,
            'status' => $status,
        ]);
        DispatchItem::create([
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
            'quantity' => $trays,
        ]);

        if ($status === 'Delivered') {
            Transaction::create([
                'dispatch_id' => $dispatch->id,
                'customer' => $dispatch->recipient,
                'transaction_date' => $date,
                'quantity' => $trays,
                'price' => $price,
                'status' => 'Paid',
            ]);
        }
    }
}
