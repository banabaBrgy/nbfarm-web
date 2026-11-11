<?php

namespace Tests\Feature;

use App\Models\Production;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EggProductionSummaryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-11 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_month_filter_returns_json_totals_for_that_month_only(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->harvest('2026-03-04', 10, 30, 0, 0);
        $this->harvest('2026-11-02', 4, 6, 0, 0);

        $this->actingAs($owner)
            ->getJson(route('owner.inventory', ['year' => 2026, 'month' => 3]))
            ->assertOk()
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('month', 3)
            ->assertJsonPath('grandTotal', '40')
            ->assertJsonPath('rows.0.label', 'Small')
            ->assertJsonPath('rows.0.value', '10')
            ->assertJsonPath('rows.0.percent', 25)
            ->assertJsonPath('rows.1.label', 'Medium')
            ->assertJsonPath('rows.1.value', '30')
            ->assertJsonPath('rows.1.percent', 75)
            ->assertJsonPath('rows.3.label', 'Extra Large')
            ->assertJsonPath('rows.3.value', '0');
    }

    public function test_inventory_pages_update_the_egg_summary_without_auto_submit(): void
    {
        $this->harvest('2026-11-02', 4, 6, 0, 0);

        foreach (['owner', 'manager', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route($role.'.inventory'))
                ->assertOk()
                ->assertSee('data-page-body', false)
                ->assertSee('data-page-scripts', false)
                ->assertSee('Egg Production Summary', false)
                ->assertSee('data-egg-summary-form', false)
                ->assertSee('js/egg-summary.js', false)
                ->assertDontSee('name="year" class="sort-select" data-auto-submit', false)
                ->assertDontSee('name="month" class="sort-select" data-auto-submit', false);
        }
    }

    public function test_other_filter_dropdowns_use_auto_submit_without_full_form_submit(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('id="trendView"', false)
            ->assertSee('data-auto-submit', false)
            ->assertDontSee('form.submit();', false);

        $this->actingAs($owner)
            ->get(route('owner.users'))
            ->assertOk()
            ->assertSee('name="status" data-auto-submit', false);
    }

    private function harvest(string $date, int $small, int $medium, int $large, int $extraLarge): void
    {
        Production::create([
            'production_date' => $date,
            'batch_id' => 1,
            'small_eggs' => $small,
            'medium_eggs' => $medium,
            'large_eggs' => $large,
            'extra_large_eggs' => $extraLarge,
            'cracked_eggs' => 0,
            'eggs_produced' => $small + $medium + $large + $extraLarge,
        ]);
    }
}
