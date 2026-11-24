<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrScanDraft;
use App\Models\QrTransaction;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use App\Services\QrScanRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrReusableScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_batch_one_sizes_are_four_permanent_codes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        foreach (['Small', 'Medium', 'Large', 'Extra Large'] as $size) {
            $this->actingAs($staff)
                ->post(route('staff.qr.generate'), [
                    'batch_id' => 1,
                    'trays' => 10,
                    'egg_size' => $size,
                ])
                ->assertRedirect();
        }

        $this->assertSame([
            'QRS-B1',
            'QRM-B1',
            'QRL-B1',
            'QRXL-B1',
        ], QrRecord::query()->orderBy('id')->pluck('qr_code')->all());

        $this->actingAs($staff)
            ->get(route('staff.qr.print'))
            ->assertOk()
            ->assertSee('QRS-B1', false)
            ->assertSee('QRXL-B1', false)
            ->assertSee('one QR per egg size per batch', false)
            ->assertDontSee('QRS-B1-001', false);
    }

    public function test_scans_stay_on_the_review_list_until_confirm_and_save(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->trays();
        $record = QrRecord::issueSizeCode(1, 'Small', 10);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('staged', true)
            ->assertJsonPath('summary.scans', 1)
            ->assertJsonPath('summary.trays', 10)
            ->assertJsonPath('summary.eggs', 300)
            ->assertJsonPath('saved.scans', 0);

        $this->assertSame(0, Production::query()->count());
        $this->assertSame(0, QrTransaction::query()->count());
        $this->assertSame(1, QrScanDraft::query()->count());

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This QR was just scanned. Wait a few seconds before scanning it again.');

        $this->travel(QrScanRecorder::DEBOUNCE_SECONDS + 1)->seconds();

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('summary.scans', 2)
            ->assertJsonPath('summary.eggs', 600);

        $this->assertSame(0, Production::query()->count());
        $this->assertSame(2, QrScanDraft::query()->count());

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('committed', true)
            ->assertJsonPath('saved.scans', 2)
            ->assertJsonPath('saved.eggs', 600)
            ->assertJsonPath('summary.scans', 0);

        $this->assertSame(600, (int) Production::query()->value('small_eggs'));
        $this->assertSame(2, QrTransaction::query()->count());
        $this->assertSame(0, QrScanDraft::query()->count());
    }

    public function test_review_list_can_edit_delete_and_add_before_save(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->trays();
        $record = QrRecord::issueSizeCode(1, 'Small', 10);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk();

        $this->travel(QrScanRecorder::DEBOUNCE_SECONDS + 1)->seconds();

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('summary.scans', 2);

        $firstId = (int) QrScanDraft::query()->orderBy('id')->value('id');
        $secondId = (int) QrScanDraft::query()->orderByDesc('id')->value('id');

        $this->actingAs($staff)
            ->patchJson(route('staff.scan.drafts.update', $firstId), ['trays' => 8, 'eggs' => 230])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.eggs', 530);

        $this->actingAs($staff)
            ->deleteJson(route('staff.scan.drafts.destroy', $secondId))
            ->assertOk()
            ->assertJsonPath('summary.scans', 1)
            ->assertJsonPath('summary.trays', 8);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.drafts.add'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('summary.scans', 2)
            ->assertJsonPath('summary.trays', 18);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), ['qr_code' => $record->qr_code])
            ->assertOk()
            ->assertJsonPath('saved.eggs', 530);

        $this->assertSame(530, (int) Production::query()->value('small_eggs'));
        $this->assertSame(0, QrScanDraft::query()->count());
    }

    public function test_review_list_lives_on_scanner_and_disappears_after_save(): void
    {
        $this->bindForecastEngine();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->trays();
        $record = QrRecord::issueSizeCode(1, 'Small', 10);

        $this->actingAs($staff)
            ->get(route('staff.production'))
            ->assertOk()
            ->assertDontSee('id="productionReviewPanel"', false)
            ->assertDontSee('Confirm &amp; Save', false);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('staff.production'))
            ->assertOk()
            ->assertDontSee('id="productionReviewPanel"', false)
            ->assertDontSee('Confirm &amp; Save', false);

        $this->actingAs($staff)
            ->get(route('staff.production.scan'))
            ->assertOk()
            ->assertSee('id="productionReviewPanel"', false)
            ->assertSee('id="productionReviewModal"', false)
            ->assertSee('id="scanReviewBtn"', false)
            ->assertDontSee('id="draft-title"', false)
            ->assertSee('Review list', false)
            ->assertSee('QRS-B1', false)
            ->assertSee('Confirm &amp; Save', false)
            ->assertSee('Double-check batch, size, stacks, and egg count', false)
            ->assertSee('One size at a time', false)
            ->assertSee('data-batch=', false)
            ->assertSee('data-size=', false)
            ->assertSee('data-eggs=', false)
            ->assertSee('Batch and size come from the QR and cannot be changed', false);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), ['harvest_date' => today()->toDateString()])
            ->assertOk()
            ->assertJsonPath('committed', true)
            ->assertJsonPath('summary.scans', 0);

        $this->assertSame(300, (int) Production::query()->value('small_eggs'));
        $this->assertSame(0, QrScanDraft::query()->count());

        $this->actingAs($staff)
            ->get(route('staff.production.scan'))
            ->assertOk()
            ->assertDontSee('QRS-B1', false);

        $this->actingAs($staff)
            ->get(route('staff.production'))
            ->assertOk()
            ->assertDontSee('id="productionReviewPanel"', false)
            ->assertSee('>300<', false);
    }

    public function test_review_list_stays_on_one_size_and_edits_trays_and_eggs_only(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->trays();
        $small = QrRecord::issueSizeCode(1, 'Small', 10);
        $medium = QrRecord::issueSizeCode(1, 'Medium', 10);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $small->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $medium->qr_code])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Scan one size at a time. Confirm & Save the Small stacks first, then scan Medium.'
            );

        $draftId = (int) QrScanDraft::query()->value('id');

        $this->actingAs($staff)
            ->patchJson(route('staff.scan.drafts.update', $draftId), ['trays' => 8, 'eggs' => 241])
            ->assertOk()
            ->assertJsonPath('success', false);

        $this->actingAs($staff)
            ->patchJson(route('staff.scan.drafts.update', $draftId), ['trays' => 8, 'eggs' => 230])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.trays', 8)
            ->assertJsonPath('summary.eggs', 230);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), ['harvest_date' => today()->toDateString()])
            ->assertOk()
            ->assertJsonPath('committed', true);

        $this->assertSame(230, (int) Production::query()->value('small_eggs'));
        $this->assertSame(0, QrScanDraft::query()->count());

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $medium->qr_code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('size', 'Medium');
    }

    public function test_owner_cannot_scan_or_save(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $record = QrRecord::issueSizeCode(1, 'Small', 10);

        $this->actingAs($owner)
            ->postJson(route('owner.scan.store'), ['qr_code' => $record->qr_code])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson(route('owner.scan.commit'), ['qr_code' => $record->qr_code])
            ->assertForbidden();
    }

    public function test_manager_production_does_not_show_the_review_list(): void
    {
        $this->bindForecastEngine();
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);
        $this->trays();
        $record = QrRecord::issueSizeCode(1, 'Small', 10);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => $record->qr_code])
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('manager.production'))
            ->assertOk()
            ->assertDontSee('id="productionReviewPanel"', false)
            ->assertDontSee('id="productionReviewOpenBtn"', false)
            ->assertDontSee('Confirm &amp; Save', false);
    }

    public function test_unique_stickers_still_cannot_be_scanned_twice(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->trays();

        QrRecord::create([
            'batch_id' => 2,
            'qr_code' => 'QRL-B2-001',
            'egg_size' => 'Large',
            'tray_count' => 2,
            'eggs_per_tray' => 30,
        ]);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => 'QRL-B2-001'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.scans', 1);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => 'QRL-B2-001'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'QR already scanned.');

        $this->actingAs($staff)
            ->postJson(route('staff.scan.commit'), ['qr_code' => 'QRL-B2-001'])
            ->assertOk()
            ->assertJsonPath('saved.eggs', 60);

        $this->actingAs($staff)
            ->postJson(route('staff.scan.store'), ['qr_code' => 'QRL-B2-001'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'QR already scanned.');
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
                    'forecast_date' => today()->toDateString(),
                    'labels' => [],
                    'data' => [],
                    'series' => [[
                        'date' => today()->toDateString(),
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
