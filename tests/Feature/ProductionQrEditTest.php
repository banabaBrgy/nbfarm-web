<?php

namespace Tests\Feature;

use App\Models\DailyInventoryLog;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionQrEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_page_waits_for_bootstrap_before_opening_edit(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        ['production' => $production, 'smallTx' => $smallTx, 'mediumTx' => $mediumTx] = $this->harvest();
        $earlierSmall = $this->earlierSmall($production);

        $this->actingAs($staff)
            ->get(route('staff.production.details', $production->production_id))
            ->assertOk()
            ->assertSee(route('staff.qr.update', $smallTx->id), false)
            ->assertSee(route('staff.qr.update', $mediumTx->id), false)
            ->assertDontSee(route('staff.qr.update', $earlierSmall->id), false)
            ->assertSee('DOMContentLoaded', false)
            ->assertSee('window.bootstrap', false)
            ->assertDontSee('new bootstrap.Modal', false)
            ->assertSee('title="Edit"', false)
            ->assertSee('fa-pen', false)
            ->assertDontSee('>Edit<', false)
            ->assertSee('Recorded', false);
    }

    public function test_only_the_last_recorded_tray_of_each_size_can_be_edited(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        ['mediumTx' => $mediumTx, 'production' => $production] = $this->harvest();
        $earlierSmall = $this->earlierSmall($production);

        $this->actingAs($staff)
            ->postJson(route('staff.qr.update', $earlierSmall->id), [
                'total_eggs' => 90,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only the last recorded tray for this size can be edited.');

        $this->assertSame(300, (int) $earlierSmall->fresh()->total_eggs);

        $this->actingAs($staff)
            ->postJson(route('staff.qr.update', $mediumTx->id), [
                'total_eggs' => 90,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(90, (int) $mediumTx->fresh()->total_eggs);
        $this->assertSame(690, (int) $production->fresh()->eggs_produced);
    }

    public function test_edit_saves_string_egg_count_and_returns_unused_trays(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        [
            'trays' => $trays,
            'production' => $production,
            'smallTx' => $smallTx,
        ] = $this->harvest();

        $before = (float) $trays->fresh()->current_stock;

        $this->actingAs($staff)
            ->postJson(route('staff.qr.update', $smallTx->id), [
                'total_eggs' => '90',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $smallTx->refresh();
        $production->refresh();

        $this->assertSame(90, (int) $smallTx->total_eggs);
        $this->assertSame('Edited', $smallTx->status);
        $this->assertSame(90, (int) $production->small_eggs);
        $this->assertSame(150, (int) $production->medium_eggs);
        $this->assertSame(240, (int) $production->eggs_produced);
        $this->assertSame($before + 7, (float) $trays->fresh()->current_stock);
    }

    public function test_edit_rejects_counts_above_one_qr_stack(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        ['smallTx' => $smallTx, 'production' => $production] = $this->harvest();

        $this->actingAs($owner)
            ->postJson(route('owner.qr.update', $smallTx->id), [
                'total_eggs' => 301,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total_eggs');

        $this->assertSame(300, (int) $smallTx->fresh()->total_eggs);
        $this->assertSame(450, (int) $production->fresh()->eggs_produced);
    }

    public function test_batch_shortcut_opens_the_working_editor(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        ['production' => $production] = $this->harvest();

        $this->actingAs($staff)
            ->get(route('staff.batch.details', $production->batch_id))
            ->assertRedirect(route('staff.production.details', $production->production_id));

        $this->actingAs($staff)
            ->get(route('staff.batch.details', 9))
            ->assertNotFound();
    }

    public function test_scan_recalculates_the_same_batch_totals(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 500,
            'min_level' => 10,
            'daily_stock' => 500,
        ]);
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
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('production', [
            'batch_id' => 2,
            'large_eggs' => 60,
            'eggs_produced' => 60,
        ]);
    }

    /**
     * @return array{trays: InventoryItem, production: Production, smallTx: QrTransaction, mediumTx: QrTransaction}
     */
    private function harvest(): array
    {
        $trays = InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 400,
            'min_level' => 10,
            'daily_stock' => 500,
        ]);

        DailyInventoryLog::create(['log_date' => today()->toDateString()]);
        InventoryItemHistory::record(
            $trays->id,
            'Egg Trays',
            'Checked',
            0,
            400,
            400,
            'Daily tray restock'
        );

        $production = Production::create([
            'production_date' => today()->toDateString(),
            'batch_id' => 1,
            'small_eggs' => 300,
            'medium_eggs' => 150,
            'large_eggs' => 0,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => 450,
        ]);

        $small = QrRecord::create([
            'production_id' => $production->production_id,
            'batch_id' => 1,
            'qr_code' => 'QRS-B1-001',
            'egg_size' => 'Small',
            'tray_count' => 10,
            'eggs_per_tray' => 30,
        ]);
        $medium = QrRecord::create([
            'production_id' => $production->production_id,
            'batch_id' => 1,
            'qr_code' => 'QRM-B1-001',
            'egg_size' => 'Medium',
            'tray_count' => 5,
            'eggs_per_tray' => 30,
        ]);

        $mediumTx = QrTransaction::create([
            'qr_record_id' => $medium->id,
            'production_id' => $production->production_id,
            'total_eggs' => 150,
            'status' => 'Scanned',
            'scanned_at' => now()->subMinute(),
        ]);
        $smallTx = QrTransaction::create([
            'qr_record_id' => $small->id,
            'production_id' => $production->production_id,
            'total_eggs' => 300,
            'status' => 'Scanned',
            'scanned_at' => now(),
        ]);

        return [
            'trays' => $trays,
            'production' => $production,
            'smallTx' => $smallTx,
            'mediumTx' => $mediumTx,
        ];
    }

    private function earlierSmall(Production $production): QrTransaction
    {
        $record = QrRecord::create([
            'production_id' => $production->production_id,
            'batch_id' => 1,
            'qr_code' => 'QRS-B1-000',
            'egg_size' => 'Small',
            'tray_count' => 10,
            'eggs_per_tray' => 30,
        ]);

        return QrTransaction::create([
            'qr_record_id' => $record->id,
            'production_id' => $production->production_id,
            'total_eggs' => 300,
            'status' => 'Scanned',
            'scanned_at' => now()->subMinutes(5),
        ]);
    }
}
