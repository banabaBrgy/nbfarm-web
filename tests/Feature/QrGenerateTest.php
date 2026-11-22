<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrGenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_page_asks_for_one_batch_before_creating_a_code(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.qr.create', [
                'batch_id' => 2,
            ]))
            ->assertOk()
            ->assertSee('Batch QR Code', false)
            ->assertSee('Batch Details', false)
            ->assertSee('One QR per size per batch', false)
            ->assertSee('Each scan', false)
            ->assertSee('Print copies', false)
            ->assertSee('Trays', false)
            ->assertSee('Extra Large', false)
            ->assertSee('Download', false)
            ->assertSee('Print Label', false)
            ->assertDontSee('Share', false)
            ->assertDontSee('Production Date', false);

        $this->assertSame(0, QrRecord::query()->count());
    }

    public function test_staff_production_page_opens_the_generate_form(): void
    {
        $this->bindForecastEngine();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.production'))
            ->assertOk()
            ->assertSee(route('staff.qr.create'), false)
            ->assertSee('Generate QR', false);
    }

    public function test_generate_reuses_one_code_per_batch_and_size(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('staff.qr.generate'), [
                'batch_id' => 1,
                'trays' => 10,
                'egg_size' => 'Small',
            ])
            ->assertRedirect();

        $first = QrRecord::query()->firstOrFail();

        $this->assertSame(1, QrRecord::query()->count());
        $this->assertSame(10, (int) $first->tray_count);
        $this->assertSame(30, (int) $first->eggs_per_tray);
        $this->assertSame(300, $first->eggCount());
        $this->assertSame('QRS-B1', $first->qr_code);

        $this->actingAs($staff)
            ->followingRedirects()
            ->post(route('staff.qr.generate'), [
                'batch_id' => 1,
                'trays' => 10,
                'egg_size' => 'Small',
            ])
            ->assertOk()
            ->assertSee('QRS-B1', false)
            ->assertSee('already uses', false)
            ->assertDontSee('-002', false);

        $this->assertSame(1, QrRecord::query()->count());

        $this->actingAs($staff)
            ->post(route('staff.qr.generate'), [
                'batch_id' => 1,
                'trays' => 10,
                'egg_size' => 'Medium',
            ])
            ->assertRedirect();

        $this->assertSame(['QRS-B1', 'QRM-B1'], QrRecord::query()->orderBy('id')->pluck('qr_code')->all());
    }

    public function test_download_and_print_label_use_the_generated_code(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $record = $this->label($staff);

        $this->actingAs($staff)
            ->get(route('staff.qr.download', $record))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="'.$record->qr_code.'.svg"')
            ->assertSee('<svg', false);

        $this->actingAs($staff)
            ->get(route('staff.qr.label', $record))
            ->assertOk()
            ->assertSee($record->qr_code, false)
            ->assertSee('1,200 eggs', false)
            ->assertSee('Small', false)
            ->assertSee('40 trays', false);

        $copies = $this->actingAs($staff)
            ->get(route('staff.qr.label', ['record' => $record, 'copies' => 3]))
            ->assertOk()
            ->getContent();

        $this->assertSame(3, substr_count($copies, 'class="batch-label"'));
    }

    public function test_scanning_a_generated_label_counts_on_today_and_can_repeat(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->trays();
        $record = $this->label($staff);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('staged', true)
            ->assertJsonPath('eggs', 1200)
            ->assertJsonPath('summary.scans', 1);

        $this->assertSame(0, QrTransaction::query()->count());

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('saved.scans', 1)
            ->assertJsonPath('saved.eggs', 1200);

        $this->assertDatabaseHas('production', [
            'batch_id' => 1,
            'production_date' => today()->toDateString(),
            'small_eggs' => 1200,
            'eggs_produced' => 1200,
        ]);

        $this->travel(9)->seconds();

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.scans', 1);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('saved.scans', 2)
            ->assertJsonPath('saved.eggs', 2400);

        $this->assertSame(2400, (int) Production::query()->value('small_eggs'));
    }

    public function test_old_stickers_still_count_on_the_day_they_are_scanned(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->trays();

        QrRecord::create([
            'batch_id' => 2,
            'qr_code' => 'QRL-B2-001',
            'qr_date' => today()->subDays(5)->toDateString(),
            'egg_size' => 'Large',
            'tray_count' => 2,
            'eggs_per_tray' => 30,
        ]);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), [
                'qr_code' => 'QRL-B2-001',
            ])
            ->assertOk()
            ->assertJsonPath('eggs', 60);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), [
                'qr_code' => 'QRL-B2-001',
            ])
            ->assertOk()
            ->assertJsonPath('saved.eggs', 60);

        $this->assertDatabaseHas('production', [
            'batch_id' => 2,
            'production_date' => today()->toDateString(),
            'large_eggs' => 60,
        ]);
    }

    public function test_a_label_can_be_edited_up_to_its_own_egg_count(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $record = $this->label($staff);
        $production = Production::create([
            'production_date' => $record->qr_date->toDateString(),
            'batch_id' => 1,
            'small_eggs' => 1200,
            'medium_eggs' => 0,
            'large_eggs' => 0,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => 1200,
        ]);
        $transaction = QrTransaction::create([
            'qr_record_id' => $record->id,
            'production_id' => $production->production_id,
            'total_eggs' => 1200,
            'status' => 'Scanned',
            'scanned_at' => now(),
        ]);

        $this->actingAs($staff)
            ->postJson(route('staff.qr.update', $transaction->id), [
                'total_eggs' => 1201,
                'production_id' => $production->production_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total_eggs');

        $this->actingAs($staff)
            ->postJson(route('staff.qr.update', $transaction->id), [
                'total_eggs' => 900,
                'production_id' => $production->production_id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(900, (int) $transaction->fresh()->total_eggs);
        $this->assertSame(900, (int) $production->fresh()->small_eggs);
    }

    public function test_invalid_batch_details_do_not_create_a_code(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('staff.qr.generate'), [
                'batch_id' => 9,
                'trays' => 0,
                'egg_size' => 'Jumbo',
            ])
            ->assertSessionHasErrors(['batch_id', 'trays', 'egg_size']);

        $this->assertSame(0, QrRecord::query()->count());
    }

    public function test_owner_and_manager_cannot_generate_a_label(): void
    {
        foreach (['owner', 'manager'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->post(route($role.'.qr.generate'), [
                    'batch_id' => 1,
                    'trays' => 4,
                    'egg_size' => 'Small',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('staff.qr.create'))
                ->assertRedirect(route($role.'.dashboard'));
        }

        $this->assertSame(0, QrRecord::query()->count());
    }

    private function label(User $staff, ?string $date = null): QrRecord
    {
        $this->actingAs($staff)
            ->post(route('staff.qr.generate'), [
                'batch_id' => 1,
                'trays' => 40,
                'egg_size' => 'Small',
            ])
            ->assertRedirect();

        return QrRecord::query()->latest('id')->firstOrFail();
    }

    private function trays(): void
    {
        InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 500,
            'min_level' => 10,
            'daily_stock' => 500,
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
