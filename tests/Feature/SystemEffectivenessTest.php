<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\Forecast;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SystemEffectiveness;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemEffectivenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-08 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_production_recording_counts_only_records_that_match_qr_scans(): void
    {
        $this->harvest('2026-11-02', 30, 30);
        $this->harvest('2026-11-03', 30, 10);
        $this->harvest('2026-11-04', 0, null);
        $this->harvest('2026-10-15', 30, 30);

        $indicator = $this->indicator($this->month(), 'Production recording');

        $this->assertSame('1 of 3 production records match their QR scans', $indicator['headline']);
        $this->assertSame(33.3, $indicator['rate']);
        $this->assertSame('33.3%', $indicator['rate_label']);
        $this->assertSame('2 saved records do not match their QR scans.', $indicator['detail']);
    }

    public function test_inventory_matches_the_latest_balanced_ledger_entry_and_states_egg_stock(): void
    {
        $feeds = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 20,
            'min_level' => 5,
        ]);
        InventoryItem::create([
            'item_name' => 'Vitamins',
            'unit' => 'bottles',
            'current_stock' => 8,
            'min_level' => 2,
        ]);
        $this->ledger($feeds, '2026-11-08 08:00:00', 15, 5, 20);
        $this->harvest('2026-11-08', 90, null);

        $indicator = $this->indicator($this->month(), 'Inventory monitoring');

        $this->assertSame('1 of 1 supply item matches its latest ledger entry', $indicator['headline']);
        $this->assertSame(100.0, $indicator['rate']);
        $this->assertStringContainsString('Every supply item with a ledger entry matches.', $indicator['detail']);
        $this->assertStringContainsString(
            'Egg stock on hand is 90 eggs: 90 harvested from Nov 2, 2026 to Nov 8, 2026, minus 0 eggs reserved by dispatch.',
            $indicator['detail']
        );
    }

    public function test_a_past_period_uses_the_ledger_entry_from_that_period(): void
    {
        $feeds = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 10,
            'min_level' => 5,
        ]);
        $this->ledger($feeds, '2026-11-07 08:00:00', 5, 5, 10);
        $this->ledger($feeds, '2026-11-08 08:00:00', 10, 1, 12);

        $past = $this->indicator(
            app(SystemEffectiveness::class)->forPeriod(
                Carbon::parse('2026-11-07'),
                Carbon::parse('2026-11-07')
            ),
            'Inventory monitoring'
        );
        $today = $this->indicator(
            app(SystemEffectiveness::class)->forPeriod(
                Carbon::parse('2026-11-08'),
                Carbon::parse('2026-11-08')
            ),
            'Inventory monitoring'
        );

        $this->assertSame('1 of 1 supply item matches its latest ledger entry', $past['headline']);
        $this->assertSame('100%', $past['rate_label']);
        $this->assertSame('0 of 1 supply item matches its latest ledger entry', $today['headline']);
        $this->assertSame('0%', $today['rate_label']);
        $this->assertStringContainsString('Feeds does not match its latest ledger entry.', $today['detail']);
    }

    public function test_dispatch_success_requires_a_delivered_status_and_a_sales_row(): void
    {
        $this->delivery('Delivered', true);
        $this->delivery('Pending', true);
        $this->delivery('On Transit', false);
        $this->delivery('Delivered', false);
        $this->delivery('Delivered', true, '2026-10-02');

        $indicator = $this->indicator($this->month(), 'Dispatch processing');

        $this->assertSame('1 of 4 dispatches were delivered and posted as sales', $indicator['headline']);
        $this->assertSame(25.0, $indicator['rate']);
        $this->assertSame(
            'Pending: 1. On transit: 1. Delivered without a sales row: 1.',
            $indicator['detail']
        );
    }

    public function test_forecast_availability_counts_stored_days_through_tomorrow(): void
    {
        Forecast::create([
            'forecast_date' => '2026-11-01',
            'predicted_eggs' => 100,
        ]);
        Forecast::create([
            'forecast_date' => '2026-11-03',
            'predicted_eggs' => 110,
        ]);

        $indicator = $this->indicator($this->month(), 'Forecasting');

        $this->assertSame('Forecast stored for 2 of 9 days', $indicator['headline']);
        $this->assertSame(22.2, $indicator['rate']);
        $this->assertSame('22.2%', $indicator['rate_label']);
        $this->assertStringContainsString('Counted from Nov 1, 2026 through Nov 9, 2026.', $indicator['detail']);

        $before = $this->indicator(
            app(SystemEffectiveness::class)->forPeriod(
                Carbon::parse('2026-10-01'),
                Carbon::parse('2026-10-31')
            ),
            'Forecasting'
        );
        $this->assertSame('No basis', $before['headline']);
        $this->assertNull($before['rate']);
        $this->assertStringContainsString('after this period', $before['detail']);

        $empty = $this->indicator(
            app(SystemEffectiveness::class)->forPeriod(
                Carbon::parse('2026-12-01'),
                Carbon::parse('2026-12-31')
            ),
            'Forecasting'
        );
        $this->assertSame('No basis', $empty['headline']);
        $this->assertStringContainsString('before this period', $empty['detail']);
    }

    public function test_report_generation_counts_saved_reports_that_have_content(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->report($manager, 'RPT-1', 'Harvest notes.', 'Sent', '2026-11-02');
        $this->report($manager, 'RPT-2', '', 'Draft', '2026-11-03');
        $this->report($manager, 'RPT-3', 'Owner copy.', 'Read', '2026-11-04');
        $this->report($manager, 'RPT-4', 'October.', 'Sent', '2026-10-04');

        $indicator = $this->indicator($this->month(), 'Report generation');

        $this->assertSame('2 of 3 reports were generated with content', $indicator['headline']);
        $this->assertSame(66.7, $indicator['rate']);
        $this->assertSame(
            '1 saved report has no content. Draft: 1. Sent: 1. Read: 1.',
            $indicator['detail']
        );
    }

    public function test_reports_fall_back_to_created_at_when_report_date_is_missing(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('report_date');
        });

        $manager = User::factory()->create(['role' => 'manager']);
        $report = $this->report($manager, 'RPT-LIVE', 'Saved without a report date.', 'Sent', '2026-11-08');
        DB::table('reports')->where('id', $report->id)->update([
            'created_at' => '2026-11-08 09:00:00',
            'updated_at' => '2026-11-08 09:00:00',
        ]);

        $older = $this->report($manager, 'RPT-OLD', 'October report.', 'Draft', '2026-11-08');
        DB::table('reports')->where('id', $older->id)->update([
            'created_at' => '2026-10-04 09:00:00',
            'updated_at' => '2026-10-04 09:00:00',
        ]);

        $indicator = $this->indicator($this->month(), 'Report generation');

        $this->assertSame('1 of 1 report was generated with content', $indicator['headline']);
        $this->assertSame('100%', $indicator['rate_label']);
    }

    public function test_an_empty_period_has_no_percentage(): void
    {
        $result = $this->month();

        foreach (['Production recording', 'Dispatch processing', 'Forecasting', 'Report generation'] as $title) {
            $indicator = $this->indicator($result, $title);
            $this->assertSame('No basis', $indicator['headline']);
            $this->assertNull($indicator['rate']);
            $this->assertNull($indicator['rate_label']);
        }
    }

    public function test_owner_and_manager_dashboards_show_the_section_and_staff_does_not(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('data-bs-target="#systemEffectivenessModal"', false)
            ->assertSee('System Effectiveness')
            ->assertSee('Production recording')
            ->assertSee('No production records in this period.')
            ->assertSee('value="2026-11-01"', false)
            ->assertSee('value="2026-11-30"', false);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'effectiveness_from' => '2026-10-01',
                'effectiveness_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('Oct 1, 2026 to Oct 31, 2026');

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'effectiveness_from' => '2026-11-10',
                'effectiveness_to' => '2026-11-01',
            ]))
            ->assertSessionHasErrors('effectiveness_to');

        $this->actingAs($owner)
            ->from(route('owner.dashboard'))
            ->get(route('owner.dashboard', [
                'effectiveness_from' => '2025-01-01',
                'effectiveness_to' => '2026-01-02',
            ]))
            ->assertRedirect(route('owner.dashboard'))
            ->assertSessionHasErrors('effectiveness_to');

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('data-bs-target="#systemEffectivenessModal"', false)
            ->assertSee('System Effectiveness')
            ->assertSee('Dispatch processing');

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('System Effectiveness');
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     from_label: string,
     *     to_label: string,
     *     indicators: list<array<string, mixed>>
     * }
     */
    private function month(): array
    {
        return app(SystemEffectiveness::class)->forRequest(Request::create('/dashboard', 'GET'));
    }

    /**
     * @param  array{indicators: list<array<string, mixed>>}  $result
     * @return array<string, mixed>
     */
    private function indicator(array $result, string $title): array
    {
        $indicator = collect($result['indicators'])->firstWhere('title', $title);

        $this->assertIsArray($indicator);

        return $indicator;
    }

    private function harvest(string $date, int $eggs, ?int $scanned): void
    {
        $productionId = DB::table('production')->insertGetId([
            'production_date' => $date,
            'batch_id' => 1,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => $eggs,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => $eggs,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'production_id');

        if ($scanned === null) {
            return;
        }

        $qrId = DB::table('qr_records')->insertGetId([
            'batch_id' => 1,
            'qr_code' => 'QR-'.$productionId.'-'.$date,
            'egg_size' => 'Large',
            'tray_count' => 1,
            'eggs_per_tray' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('qr_transactions')->insert([
            'qr_record_id' => $qrId,
            'production_id' => $productionId,
            'total_eggs' => $scanned,
            'status' => 'Scanned',
            'scanned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ledger(InventoryItem $item, string $at, float $before, float $change, float $after): void
    {
        $history = InventoryItemHistory::create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->item_name,
            'action' => 'Adjusted',
            'quantity_change' => $change,
            'stock_before' => $before,
            'stock_after' => $after,
        ]);

        DB::table('inventory_item_histories')->where('id', $history->id)->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function delivery(string $status, bool $withSale, string $date = '2026-11-08'): void
    {
        $dispatch = Dispatch::create([
            'recipient' => $status.' '.$date,
            'address' => 'Lipa City',
            'dispatch_date' => $date,
            'price' => 100,
            'status' => $status,
        ]);

        if (! $withSale) {
            return;
        }

        Transaction::create([
            'dispatch_id' => $dispatch->id,
            'customer' => $dispatch->recipient,
            'transaction_date' => $date,
            'quantity' => 1,
            'price' => 100,
            'status' => $status === 'Delivered' ? 'Paid' : 'Pending',
        ]);
    }

    private function report(User $manager, string $number, string $content, string $status, string $date): Report
    {
        $attributes = [
            'report_no' => $number,
            'manager_id' => $manager->id,
            'title' => $number,
            'content' => $content,
            'status' => $status,
        ];

        if (Schema::hasColumn('reports', 'report_date')) {
            $attributes['report_date'] = $date;
        }

        return Report::create($attributes);
    }
}
