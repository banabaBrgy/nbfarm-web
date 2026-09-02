<?php

namespace Database\Seeders;

use App\Models\Dispatch;
use App\Models\Production;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WeeklyDispatchSeeder extends Seeder
{
    private const RECIPIENT_PREFIX = 'SAMPLE-WEEK';

    /** Trays to leave on the farm that day. 0 = dispatch the full harvest. */
    private array $leftoverTrays = [
        '2026-08-25' => 10,
        '2026-08-26' => 0,
        '2026-08-27' => 8,
        '2026-08-28' => 0,
        '2026-08-29' => 12,
        '2026-08-30' => 10,
        '2026-08-31' => 0,
    ];

    public function run(): void
    {
        $this->deletePreviousSamples();

        $created = 0;

        foreach ($this->leftoverTrays as $date => $leaveTrays) {
            $harvest = Production::query()
                ->whereDate('production_date', $date)
                ->selectRaw('
                    COALESCE(SUM(eggs_produced), 0) as eggs,
                    COALESCE(SUM(small_eggs), 0) as small,
                    COALESCE(SUM(medium_eggs), 0) as medium,
                    COALESCE(SUM(large_eggs), 0) as large,
                    COALESCE(SUM(extra_large_eggs), 0) as extra_large,
                    COALESCE(SUM(cracked_eggs), 0) as cracked
                ')
                ->first();

            $harvestTrays = intdiv((int) $harvest->eggs, Production::EGGS_PER_TRAY);
            $targetDelivered = max(0, $harvestTrays - $leaveTrays);

            $alreadyDelivered = (int) DB::table('dispatches')
                ->join('dispatch_items', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
                ->whereDate('dispatches.dispatch_date', $date)
                ->where('dispatches.status', 'Delivered')
                ->sum('dispatch_items.quantity');

            $need = $targetDelivered - $alreadyDelivered;
            if ($need <= 0) {
                continue;
            }

            $items = $this->traysBySize($harvest, $need);
            if ($items === []) {
                $items = [['size' => 'Large', 'quantity' => $need]];
            }

            $price = $need * 45;

            DB::transaction(function () use ($date, $need, $items, $price, &$created) {
                $dispatch = Dispatch::create([
                    'recipient' => self::RECIPIENT_PREFIX.' '.$date,
                    'address' => 'San Jose, Batangas (sample delivery)',
                    'dispatch_date' => $date,
                    'price' => $price,
                    'status' => 'Delivered',
                ]);

                foreach ($items as $item) {
                    $dispatch->items()->create($item);
                }

                $dispatch->syncSale();

                $created++;
            });
        }

        $this->command?->info("Sample Delivered dispatches Aug 25–31: {$created} new rows.");
        $this->command?->info('Eggs Stock now: '.number_format(Production::leftoverStock()).' pcs.');
    }

    private function deletePreviousSamples(): void
    {
        $ids = Dispatch::query()
            ->where('recipient', 'like', self::RECIPIENT_PREFIX.'%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        Transaction::query()
            ->where('customer', 'like', self::RECIPIENT_PREFIX.'%')
            ->delete();

        DB::table('dispatch_items')->whereIn('dispatch_id', $ids)->delete();
        Dispatch::whereIn('id', $ids)->delete();
    }

    /**
     * @return list<array{size: string, quantity: int}>
     */
    private function traysBySize(object $harvest, int $need): array
    {
        $parts = [
            'Small' => intdiv((int) $harvest->small, Production::EGGS_PER_TRAY),
            'Medium' => intdiv((int) $harvest->medium, Production::EGGS_PER_TRAY),
            'Large' => intdiv((int) $harvest->large, Production::EGGS_PER_TRAY),
            'Extra Large' => intdiv((int) $harvest->extra_large, Production::EGGS_PER_TRAY),
            'Cracked' => intdiv((int) $harvest->cracked, Production::EGGS_PER_TRAY),
        ];
        $parts = array_filter($parts, fn (int $trays) => $trays > 0);
        $sum = array_sum($parts);

        if ($sum <= 0) {
            return [];
        }

        $items = [];
        $allocated = 0;
        $keys = array_keys($parts);
        $last = end($keys);

        foreach ($parts as $size => $trays) {
            if ($size === $last) {
                $qty = max(1, $need - $allocated);
                $items[] = ['size' => $size, 'quantity' => $qty];
                break;
            }

            $qty = max(1, (int) round($trays / $sum * $need));
            if ($allocated + $qty >= $need) {
                $qty = max(1, $need - $allocated);
                $items[] = ['size' => $size, 'quantity' => $qty];
                break;
            }

            $items[] = ['size' => $size, 'quantity' => $qty];
            $allocated += $qty;
        }

        return $items;
    }
}
