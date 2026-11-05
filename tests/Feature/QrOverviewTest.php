<?php

namespace Tests\Feature;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_production_page_links_to_the_overview(): void
    {
        $this->bindForecastEngine();

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.production'))
            ->assertOk()
            ->assertSee(route('staff.qr.overview'), false)
            ->assertSee('Overview', false);
    }

    public function test_owner_production_page_does_not_link_to_the_overview(): void
    {
        $this->bindForecastEngine();

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.production'))
            ->assertOk()
            ->assertDontSee('/qr/overview', false);
    }

    public function test_overview_lists_today_status_quantity_and_filters(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $today = $this->production(1, today()->toDateString());
        $yesterday = $this->production(2, today()->subDay()->toDateString());

        $scanned = $this->record(1, 'QRL-B1-001', 'Large', 2, 30);
        $edited = $this->record(1, 'QRS-B1-002', 'Small', 10, 30);
        $available = $this->record(2, 'QRM-B2-003', 'Medium', 4, 30);
        $usedYesterday = $this->record(2, 'QRXL-B2-004', 'Extra Large', 7, 30);

        QrTransaction::create([
            'qr_record_id' => $scanned->id,
            'production_id' => $today->production_id,
            'total_eggs' => 60,
            'status' => 'Scanned',
            'scanned_at' => now(),
        ]);
        QrTransaction::create([
            'qr_record_id' => $edited->id,
            'production_id' => $today->production_id,
            'total_eggs' => 300,
            'status' => 'Scanned',
            'scanned_at' => now()->subHour(),
        ]);
        QrTransaction::create([
            'qr_record_id' => $edited->id,
            'production_id' => $today->production_id,
            'total_eggs' => 90,
            'status' => 'Edited',
            'scanned_at' => now(),
        ]);
        QrTransaction::create([
            'qr_record_id' => $usedYesterday->id,
            'production_id' => $yesterday->production_id,
            'total_eggs' => 30,
            'status' => 'Scanned',
            'scanned_at' => now()->subDay(),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview'))
            ->assertOk()
            ->assertSee('Scanned QR Overview', false)
            ->assertSee('QRL-B1-001', false)
            ->assertSee('QRS-B1-002', false)
            ->assertSee('QRM-B2-003', false)
            ->assertSee('QRXL-B2-004', false)
            ->assertSee('Scanned', false)
            ->assertSee('Available', false)
            ->assertSee('>60<', false)
            ->assertSee('>90<', false)
            ->assertSee('>120<', false)
            ->assertSee('>210<', false)
            ->assertDontSee('>30<', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['egg_size' => 'Large', 'batch_id' => 1]))
            ->assertOk()
            ->assertSee('QRL-B1-001', false)
            ->assertDontSee('QRS-B1-002', false)
            ->assertDontSee('QRM-B2-003', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['search' => 'QRM-B2']))
            ->assertOk()
            ->assertSee('QRM-B2-003', false)
            ->assertDontSee('QRL-B1-001', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['egg_size' => 'Nope', 'batch_id' => 99, 'search' => '%']))
            ->assertOk()
            ->assertSee('QRL-B1-001', false)
            ->assertSee('QRM-B2-003', false);
    }

    public function test_overview_paginates_five_rows_and_keeps_the_filter(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        foreach (range(1, 6) as $sequence) {
            $this->record(1, sprintf('QRS-B1-%03d', $sequence), 'Small', 1, 30);
        }

        $this->record(2, 'QRL-B2-001', 'Large', 1, 30);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['egg_size' => 'Small']))
            ->assertOk()
            ->assertSee('Showing 1–5 of 6', false)
            ->assertSee('Page 1 of 2', false)
            ->assertSee('QRS-B1-001', false)
            ->assertSee('QRS-B1-005', false)
            ->assertDontSee('QRS-B1-006', false)
            ->assertDontSee('QRL-B2-001', false)
            ->assertSee('egg_size=Small', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['egg_size' => 'Small', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 6–6 of 6', false)
            ->assertSee('Page 2 of 2', false)
            ->assertSee('QRS-B1-006', false)
            ->assertDontSee('QRS-B1-001', false)
            ->assertSee('egg_size=Small', false);
    }

    public function test_other_roles_cannot_open_the_staff_overview(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($owner)
            ->get(route('staff.qr.overview'))
            ->assertRedirect(route('owner.dashboard'));

        $this->actingAs($manager)
            ->get(route('staff.qr.overview'))
            ->assertRedirect(route('manager.dashboard'));

        auth()->logout();

        $this->get(route('staff.qr.overview'))
            ->assertRedirect(route('login'));
    }

    private function production(int $batchId, string $date): Production
    {
        return Production::create([
            'production_date' => $date,
            'batch_id' => $batchId,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => 0,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => 0,
        ]);
    }

    private function record(int $batchId, string $code, string $size, int $trays, int $eggsPerTray): QrRecord
    {
        return QrRecord::create([
            'batch_id' => $batchId,
            'qr_code' => $code,
            'egg_size' => $size,
            'tray_count' => $trays,
            'eggs_per_tray' => $eggsPerTray,
        ]);
    }

    private function bindForecastEngine(): void
    {
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
                    'forecast_date' => '2026-11-06',
                    'labels' => [],
                    'data' => [],
                    'series' => [[
                        'date' => '2026-11-06',
                        'eggs' => 0,
                        'sample_days' => 0,
                        'temperature' => null,
                        'sizes' => [
                            'small' => 0,
                            'medium' => 0,
                            'large' => 0,
                            'extra_large' => 0,
                            'cracked' => 0,
                        ],
                    ]],
                ];
            }
        });
    }
}
