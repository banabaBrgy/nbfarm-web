<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // OPTIONAL: i-uncomment kung gusto mong linisin muna yung table
        // bago mag-insert (iwas duplicate kung paulit-ulit patakbuhin)
        // DB::table('production')->truncate();

        $start = Carbon::create(2026, 1, 1);
        $end   = Carbon::create(2026, 7, 20);

        $rows = [];
        $batchId = 1;
        $dayIndex = 0;

        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            // 1-3 batch scans (QR log entries) bawat araw
            $batchesToday = rand(1, 3);

            // Konting upward trend habang tumatagal (lumalaki/nagma-mature
            // yung flock), plus random daily noise
            $trend = $dayIndex * 1.2;
            $dailyBase = 850 + $trend + rand(-60, 60);

            $remaining = $dailyBase;

            for ($b = 0; $b < $batchesToday; $b++) {
                $isLast = ($b === $batchesToday - 1);
                $portion = $isLast
                    ? $remaining
                    : round($dailyBase / $batchesToday) + rand(-20, 20);
                $portion = max(50, $portion); // laging may minimum bawat batch
                $remaining -= $portion;

                $small      = max(0, round($portion * 0.28) + rand(-5, 5));
                $medium     = max(0, round($portion * 0.34) + rand(-5, 5));
                $large      = max(0, round($portion * 0.28) + rand(-5, 5));
                $extraLarge = max(0, round($portion * 0.10) + rand(-3, 3));
                $cracked    = max(0, round($portion * 0.03) + rand(0, 3));

                // Produced = magandang itlog lang (small+medium+large+extra_large),
                // hindi kasama ang cracked
                $eggsProduced = $small + $medium + $large + $extraLarge;

                $timestamp = $cursor->copy()
                    ->addHours(rand(6, 17))
                    ->addMinutes(rand(0, 59));

                $rows[] = [
                    'production_date'  => $cursor->format('Y-m-d'),
                    'batch_id'         => $batchId++,
                    'small_eggs'       => $small,
                    'medium_eggs'      => $medium,
                    'large_eggs'       => $large,
                    'extra_large_eggs' => $extraLarge,
                    'cracked_eggs'     => $cracked,
                    'eggs_produced'    => $eggsProduced,
                    'created_at'       => $timestamp,
                    'updated_at'       => $timestamp,
                ];
            }

            // I-insert per batch of 500 rows para hindi mabigatan yung memory/query
            if (count($rows) >= 500) {
                DB::table('production')->insert($rows);
                $rows = [];
            }

            $cursor->addDay();
            $dayIndex++;
        }

        if (!empty($rows)) {
            DB::table('production')->insert($rows);
        }

        $this->command->info('Na-seed na ang production data mula Jan 1 hanggang Jul 20, 2026.');
    }
}