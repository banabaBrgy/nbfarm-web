<?php

namespace App\Services;

use App\Models\Forecast;
use App\Models\Production;
use App\Models\StockNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductionDropNotifier
{
    public function __construct(private WebPushSender $push) {}

    public function check(Carbon|string $date): void
    {
        $day = Carbon::parse($date)->startOfDay()->toDateString();
        $predicted = (int) Forecast::query()->whereDate('forecast_date', $day)->value('predicted_eggs');
        $actual = (int) Production::query()->whereDate('production_date', $day)->sum('eggs_produced');
        $recorded = Production::query()->whereDate('production_date', $day)->exists();

        if (! $recorded || ! $this->isSignificant($predicted, $actual)) {
            $this->resolveDate($day);

            return;
        }

        $this->openAlerts($day, $predicted, $actual);
    }

    private function isSignificant(int $predicted, int $actual): bool
    {
        if ($predicted <= 0 || $actual >= $predicted) {
            return false;
        }

        $percent = (float) config('forecasting.drop_alert_percent', 0.20);
        $required = (int) round($predicted * $percent * 10000);
        $drop = ($predicted - $actual) * 10000;

        return $drop >= $required;
    }

    private function openAlerts(string $day, int $predicted, int $actual): void
    {
        $difference = $predicted - $actual;
        $percent = round(($difference / $predicted) * 100, 1);
        $body = sprintf(
            'Expected %s eggs. Actual %s eggs. Difference: %s eggs (%s%% below expected).',
            number_format($predicted),
            number_format($actual),
            number_format($difference),
            number_format($percent, 1)
        );

        foreach (User::farmLeads() as $user) {
            $created = null;

            DB::transaction(function () use ($user, $day, $body, &$created) {
                $open = StockNotification::query()
                    ->where('user_id', $user->id)
                    ->where('type', 'production_drop')
                    ->whereDate('alert_date', $day)
                    ->open()
                    ->lockForUpdate()
                    ->first();

                if ($open) {
                    $open->update([
                        'title' => 'Significant drop in egg production',
                        'body' => $body,
                    ]);

                    return;
                }

                $role = User::routeRole($user->role);
                $created = StockNotification::create([
                    'user_id' => $user->id,
                    'type' => 'production_drop',
                    'alert_date' => $day,
                    'item_name' => 'Egg production',
                    'title' => 'Significant drop in egg production',
                    'body' => $body,
                    'url' => '/'.$role.'/production',
                ]);
            });

            if ($created) {
                $this->push->send($created);
            }
        }
    }

    private function resolveDate(string $day): void
    {
        StockNotification::query()
            ->where('type', 'production_drop')
            ->whereDate('alert_date', $day)
            ->open()
            ->update(['resolved_at' => now()]);
    }
}
