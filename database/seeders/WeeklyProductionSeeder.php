<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsHarvestQr;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WeeklyProductionSeeder extends Seeder
{
    use SeedsHarvestQr;

    private const START_DATE = '2026-01-01';

    private const END_DATE = '2026-08-31';

    /** Farm daily totals for Aug 25 → 31 (demo week). */
    private array $eggsByDay = [
        '2026-08-25' => 4230,
        '2026-08-26' => 4320,
        '2026-08-27' => 4410,
        '2026-08-28' => 4500,
        '2026-08-29' => 4590,
        '2026-08-30' => 4680,
        '2026-08-31' => 4410,
    ];

    public function run(): void
    {
        $start = Carbon::parse(self::START_DATE)->startOfDay();
        $end = Carbon::parse(self::END_DATE)->startOfDay();

        QrRecord::query()->update(['production_id' => null]);

        $this->deleteDatedAndDemoQrRecords();

        $days = $this->seedHarvestRange(
            $start,
            $end,
            function (Carbon $date) {
                $key = $date->toDateString();

                return $this->eggsByDay[$key] ?? $this->dailyWaveTotal($date);
            }
        );

        $todayEggs = (int) Production::whereDate('production_date', Carbon::today())
            ->sum('eggs_produced');
        $traysUsedToday = intdiv($todayEggs, 30);
        $trayStock = max(0, 500 - $traysUsedToday);

        $trayUpdate = [
            'current_stock' => $trayStock,
            'min_level' => 100,
        ];
        if (Schema::hasColumn('inventory_items', 'daily_stock')) {
            $trayUpdate['daily_stock'] = 500;
        }

        InventoryItem::where('item_name', 'Egg Trays')->update($trayUpdate);

        Inventory::where('item_type', 'Egg Tray')->update([
            'quantity' => $trayStock,
            'minimum_stock' => 100,
        ]);

        Cache::flush();

        $this->command?->info("2026 dummy harvest {$start->toDateString()} → {$end->toDateString()} ({$days} days) with QR scans.");
    }

    private function deleteDatedAndDemoQrRecords(): void
    {
        QrRecord::query()
            ->where(function ($query) {
                $query->whereIn('batch_id', array_merge(QrRecord::BATCH_IDS, [99]))
                    ->orWhere('qr_code', 'like', '%-DEMO-%');
            })
            ->get()
            ->each(function (QrRecord $record) {
                if (QrRecord::isClientCode($record->qr_code)) {
                    return;
                }
                QrTransaction::where('qr_record_id', $record->id)->delete();
                $record->delete();
            });
    }
}
