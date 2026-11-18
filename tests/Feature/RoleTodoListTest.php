<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\InventoryItem;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use App\Services\RoleTodoList;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleTodoListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturn(28.0);
        $weather->method('current')->willReturn(null);
        $this->app->instance(WeatherService::class, $weather);

        $this->app->instance(ForecastEngine::class, new class implements ForecastEngine
        {
            public function predict(
                array $history,
                array $predictDates,
                int $window = 7,
                array $temperatures = [],
                array $options = [],
            ): array {
                return [
                    'predicted_eggs' => 0,
                    'forecast_date' => $predictDates[0] ?? today()->toDateString(),
                    'sample_days' => 0,
                    'temperature' => 28.0,
                    'model' => 'test',
                    'series' => [],
                    'error' => null,
                ];
            }
        });
    }

    public function test_staff_sees_pending_egg_collection_until_harvest_is_logged(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $pending = collect(app(RoleTodoList::class)->forUser($staff)['items'])
            ->firstWhere('key', 'record-production');

        $this->assertNotNull($pending);
        $this->assertFalse($pending['done']);
        $this->assertTrue($pending['urgent']);
        $this->assertSame(route('staff.production.scan'), $pending['url']);

        $this->harvest(today()->toDateString(), 120);

        $done = collect(app(RoleTodoList::class)->forUser($staff->fresh())['items'])
            ->firstWhere('key', 'record-production');

        $this->assertTrue($done['done']);
        $this->assertFalse($done['urgent']);
        $this->assertStringContainsString('120', $done['hint']);
    }

    public function test_manager_and_owner_lists_only_show_their_own_actions(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $manager = User::factory()->create(['role' => 'manager']);

        InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 1,
            'min_level' => 10,
        ]);

        Dispatch::create([
            'recipient' => 'North Store',
            'address' => 'Lipa City',
            'dispatch_date' => today()->toDateString(),
            'price' => 40,
            'status' => 'Pending',
        ]);

        Report::create([
            'report_no' => 'RPT-TODO-1',
            'manager_id' => $manager->id,
            'title' => 'Morning harvest',
            'content' => 'Need owner review.',
            'report_date' => today()->toDateString(),
            'status' => 'Sent',
            'sent_at' => now(),
        ]);

        $managerKeys = collect(app(RoleTodoList::class)->forUser($manager)['items'])->pluck('key');
        $ownerKeys = collect(app(RoleTodoList::class)->forUser($owner)['items'])->pluck('key');

        $this->assertTrue($managerKeys->contains('send-report'));
        $this->assertTrue($managerKeys->contains('follow-dispatch'));
        $this->assertFalse($managerKeys->contains('read-reports'));

        $this->assertTrue($ownerKeys->contains('read-reports'));
        $this->assertTrue($ownerKeys->contains('follow-dispatch'));
        $this->assertFalse($ownerKeys->contains('send-report'));

        $ownerReports = collect(app(RoleTodoList::class)->forUser($owner)['items'])
            ->firstWhere('key', 'read-reports');
        $this->assertFalse($ownerReports['done']);
        $this->assertStringContainsString('1 new report', $ownerReports['hint']);
    }

    public function test_each_dashboard_shows_the_role_todo_list(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee("Today's To-Do")
            ->assertSee('id="roleTodoModal"', false)
            ->assertSee('data-bs-target="#roleTodoModal"', false)
            ->assertSee('window.bootstrap.Modal.getOrCreateInstance(modal).show()', false)
            ->assertDontSee('new bootstrap.Modal', false)
            ->assertSee('Things you can do as Poultry Staff')
            ->assertSee("Record today's egg collection")
            ->assertDontSee('Send a report to the owner');

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee("Today's To-Do")
            ->assertSee('id="roleTodoModal"', false)
            ->assertSee("Review today's production")
            ->assertSee(route('manager.production'), false)
            ->assertDontSee(route('manager.production.scan'), false)
            ->assertSee('Send a report to the owner')
            ->assertDontSee("Record today's egg collection");

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee("Today's To-Do")
            ->assertSee('id="roleTodoModal"', false)
            ->assertSee('Read new reports')
            ->assertDontSee('Send a report to the owner');
    }

    public function test_manager_report_item_completes_after_sending_today(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        Report::create([
            'report_no' => 'RPT-TODO-2',
            'manager_id' => $manager->id,
            'title' => 'Daily update',
            'content' => 'All houses collected.',
            'report_date' => today()->toDateString(),
            'status' => 'Sent',
            'sent_at' => now(),
        ]);

        Transaction::create([
            'customer' => 'North Store',
            'transaction_date' => today()->toDateString(),
            'quantity' => 2,
            'price' => 80,
            'status' => 'Paid',
        ]);

        $items = collect(app(RoleTodoList::class)->forUser($manager)['items'])->keyBy('key');

        $this->assertTrue($items['send-report']['done']);
        $this->assertTrue($items['review-sales']['done']);
        $this->assertTrue($items['follow-dispatch']['done']);
    }

    private function harvest(string $date, int $eggs): void
    {
        DB::table('production')->insert([
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
}
