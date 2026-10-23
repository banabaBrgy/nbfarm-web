<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\Production;
use App\Models\StockNotification;
use App\Models\User;
use App\Services\ProductionDropNotifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDropAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_twenty_percent_drop_alerts_owner_and_manager_once(): void
    {
        Carbon::setTestNow('2026-10-25 00:05:00');
        $owner = User::factory()->create(['role' => 'owner']);
        $manager = User::factory()->create(['role' => 'manager']);
        User::factory()->create(['role' => 'staff']);

        $this->forecast('2026-10-24', 7260);
        $this->harvest('2026-10-24', 5800);

        $notifier = app(ProductionDropNotifier::class);
        $notifier->check('2026-10-24');
        $notifier->check('2026-10-24');

        $alerts = StockNotification::query()->where('type', 'production_drop')->get();

        $this->assertCount(2, $alerts);
        $this->assertEqualsCanonicalizing(
            [$owner->id, $manager->id],
            $alerts->pluck('user_id')->all()
        );

        $body = $alerts->firstWhere('user_id', $owner->id)->body;
        $this->assertStringContainsString('Expected 7,260 eggs', $body);
        $this->assertStringContainsString('Actual 5,800 eggs', $body);
        $this->assertStringContainsString('Difference: 1,460 eggs', $body);
        $this->assertStringContainsString('20.1% below expected', $body);
        $this->assertSame('Significant drop in egg production', $alerts->first()->title);
        $this->assertSame('/owner/production', $alerts->firstWhere('user_id', $owner->id)->url);
        $this->assertSame('/manager/production', $alerts->firstWhere('user_id', $manager->id)->url);
    }

    public function test_drop_under_twenty_percent_stays_quiet(): void
    {
        Carbon::setTestNow('2026-10-25 00:05:00');
        User::factory()->create(['role' => 'owner']);
        $this->forecast('2026-10-24', 7260);
        $this->harvest('2026-10-24', 6500);

        app(ProductionDropNotifier::class)->check('2026-10-24');

        $this->assertSame(0, StockNotification::query()->where('type', 'production_drop')->count());
    }

    public function test_exact_twenty_percent_alerts_and_one_egg_less_does_not(): void
    {
        Carbon::setTestNow('2026-10-25 00:05:00');
        User::factory()->create(['role' => 'owner']);
        $this->forecast('2026-10-24', 7260);
        $this->harvest('2026-10-24', 5809);

        app(ProductionDropNotifier::class)->check('2026-10-24');
        $this->assertSame(0, StockNotification::count());

        Production::query()->whereDate('production_date', '2026-10-24')->update(['eggs_produced' => 5808]);
        app(ProductionDropNotifier::class)->check('2026-10-24');

        $this->assertSame(1, StockNotification::query()->where('type', 'production_drop')->open()->count());
    }

    public function test_missing_forecast_or_scans_do_not_alert(): void
    {
        Carbon::setTestNow('2026-10-25 00:05:00');
        User::factory()->create(['role' => 'owner']);

        $this->harvest('2026-10-24', 1000);
        app(ProductionDropNotifier::class)->check('2026-10-24');
        $this->assertSame(0, StockNotification::count());

        Production::query()->delete();
        $this->forecast('2026-10-24', 7260);
        app(ProductionDropNotifier::class)->check('2026-10-24');
        $this->assertSame(0, StockNotification::count());
    }

    public function test_correction_closes_the_alert(): void
    {
        Carbon::setTestNow('2026-10-25 08:00:00');
        User::factory()->create(['role' => 'owner']);
        User::factory()->create(['role' => 'manager']);
        $this->forecast('2026-10-24', 7260);

        $production = $this->harvest('2026-10-24', 5800, false);

        $this->assertSame(2, StockNotification::query()->where('type', 'production_drop')->open()->count());

        $production->update(['eggs_produced' => 7000]);

        $this->assertSame(0, StockNotification::query()->where('type', 'production_drop')->open()->count());
    }

    public function test_same_day_scans_do_not_alert_and_command_checks_yesterday(): void
    {
        Carbon::setTestNow('2026-10-25 00:05:00');
        User::factory()->create(['role' => 'owner']);
        User::factory()->create(['role' => 'manager']);

        $this->forecast('2026-10-24', 7260);
        $this->forecast('2026-10-25', 7260);
        $this->harvest('2026-10-24', 5800);
        $this->harvest('2026-10-25', 1000, false);

        $this->assertSame(0, StockNotification::query()->where('type', 'production_drop')->count());

        $this->artisan('alerts:production-drop')->assertSuccessful();

        $alerts = StockNotification::query()->where('type', 'production_drop')->get();
        $this->assertCount(2, $alerts);
        $this->assertTrue($alerts->every(fn (StockNotification $alert) => $alert->alert_date->toDateString() === '2026-10-24'));
        $this->assertStringContainsString('Actual 5,800 eggs', $alerts->first()->body);
    }

    private function forecast(string $date, int $eggs): void
    {
        Forecast::create([
            'forecast_date' => $date,
            'predicted_eggs' => $eggs,
        ]);
    }

    private function harvest(string $date, int $eggs, bool $quietly = true): Production
    {
        $create = fn () => Production::create([
            'production_date' => $date,
            'batch_id' => 1,
            'small_eggs' => $eggs,
            'medium_eggs' => 0,
            'large_eggs' => 0,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => $eggs,
        ]);

        return $quietly ? Production::withoutEvents($create) : $create();
    }
}
