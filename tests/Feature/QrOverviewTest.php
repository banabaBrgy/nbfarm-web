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

    public function test_overview_is_a_today_tally_of_size_codes_only(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $small = QrRecord::issueSizeCode(1, 'Small', 10);
        $medium = QrRecord::issueSizeCode(1, 'Medium', 10);
        $large = QrRecord::issueSizeCode(2, 'Large', 10);
        $this->record(1, 'QRL-B1-001', 'Large', 2, 30);

        $today = $this->production(1, today()->toDateString());
        $yesterday = $this->production(1, today()->subDay()->toDateString());

        QrTransaction::create([
            'qr_record_id' => $small->id,
            'production_id' => $today->production_id,
            'total_eggs' => 300,
            'status' => 'Scanned',
            'scanned_at' => now()->subMinutes(2),
        ]);
        QrTransaction::create([
            'qr_record_id' => $small->id,
            'production_id' => $today->production_id,
            'total_eggs' => 300,
            'status' => 'Scanned',
            'scanned_at' => now(),
        ]);
        QrTransaction::create([
            'qr_record_id' => $small->id,
            'production_id' => $yesterday->production_id,
            'total_eggs' => 900,
            'status' => 'Scanned',
            'scanned_at' => now()->subDay(),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview'))
            ->assertOk()
            ->assertSee('Scanned QR Overview', false)
            ->assertDontSee('Scans today', false)
            ->assertSee('Trays today', false)
            ->assertSee('Eggs today', false)
            ->assertSee('QRS-B1', false)
            ->assertSee('QRM-B1', false)
            ->assertSee('QRL-B2', false)
            ->assertDontSee('QRL-B1-001', false)
            ->assertSee('>20<', false)
            ->assertSee('>600<', false)
            ->assertDontSee('>900<', false)
            ->assertDontSee('Available', false)
            ->assertDontSee('QRL-B1-001', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['egg_size' => 'Small', 'batch_id' => 1]))
            ->assertOk()
            ->assertSee('QRS-B1', false)
            ->assertDontSee('QRM-B1', false)
            ->assertDontSee('QRL-B2', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['search' => 'QRM-B1']))
            ->assertOk()
            ->assertSee('QRM-B1', false)
            ->assertDontSee('QRS-B1', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['search' => '%']))
            ->assertOk()
            ->assertSee('No QR records match this search.', false)
            ->assertDontSee('QRS-B1', false);
    }

    public function test_overview_fits_all_size_codes_on_one_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        foreach (QrRecord::BATCH_IDS as $batchId) {
            foreach (array_keys(QrRecord::SIZE_PREFIXES) as $size) {
                QrRecord::issueSizeCode($batchId, $size, 10);
            }
        }

        $this->actingAs($staff)
            ->get(route('staff.qr.overview'))
            ->assertOk()
            ->assertSee('QRS-B1', false)
            ->assertSee('QRXL-B3', false)
            ->assertDontSee('Page 2 of', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.overview', ['egg_size' => 'Small']))
            ->assertOk()
            ->assertSee('QRS-B1', false)
            ->assertSee('QRS-B3', false)
            ->assertDontSee('QRL-B1', false)
            ->assertSee('value="Small" selected', false);
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

        $this->actingAs($manager)
            ->get(route('manager.qr.overview'))
            ->assertOk();

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
