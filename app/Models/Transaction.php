<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatch_id',
        'customer',
        'transaction_date',
        'quantity',
        'price',
        'status',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'price'      => 'decimal:2',
    ];

    /* ── Query Scopes ── */

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('transaction_date', Carbon::today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('transaction_date', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('transaction_date', Carbon::now()->month)
                      ->whereYear('transaction_date', Carbon::now()->year);
    }

    /**
     * Sales history periods: all, today, month, previous, YYYY-MM, or custom.
     */
    public function scopeForSalesRange(Builder $query, string $range, ?string $from = null, ?string $to = null): Builder
    {
        $today = Carbon::today();

        if ($range === 'today') {
            return $query->whereDate('transaction_date', $today);
        }

        if ($range === 'month') {
            return $query->whereBetween('transaction_date', [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ]);
        }

        if ($range === 'previous') {
            return $query->where('transaction_date', '<', $today->copy()->startOfMonth()->toDateString());
        }

        if (preg_match('/^\d{4}-\d{2}$/', $range) === 1) {
            [$year, $month] = array_map('intval', explode('-', $range));
            $start = Carbon::create($year, $month, 1)->startOfMonth();

            return $query->whereBetween('transaction_date', [
                $start->toDateString(),
                $start->copy()->endOfMonth()->toDateString(),
            ]);
        }

        if ($range === 'custom' && $from && $to) {
            return $query->whereBetween('transaction_date', [$from, $to]);
        }

        if ($range === 'custom') {
            return $query->whereRaw('0 = 1');
        }

        return $query;
    }

    public static function thisMonthTotal(): float
    {
        return (float) static::query()->forSalesRange('month')->sum('price');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'Pending');
    }

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where('customer', 'like', "%{$term}%");
    }

    /**
     * Last 7 days of transactions grouped by date, for the revenue chart.
     */
    public static function last7DaysTotals(): array
    {
        $start = Carbon::today()->subDays(6);

        $rows = static::selectRaw('transaction_date, SUM(price) as total')
            ->where('transaction_date', '>=', $start)
            ->groupBy('transaction_date')
            ->pluck('total', 'transaction_date');

        $result = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $key  = $date->toDateString();

            $result[] = [
                'day'    => $date->format('D'),
                'date'   => $key,
                'amount' => (float) ($rows[$key] ?? 0),
            ];
        }

        return $result;
    }
}
