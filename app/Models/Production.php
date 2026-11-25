<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Production extends Model
{
    protected $table = 'production';

    protected $primaryKey = 'production_id';

    public $timestamps = false;

    /** Rolling window for Eggs Stock / leftover totals. */
    public const STOCK_DAYS = 7;

    public const EGGS_PER_TRAY = 30;

    public const EGG_SIZE_COLUMNS = [
        'Small' => 'small_eggs',
        'Medium' => 'medium_eggs',
        'Large' => 'large_eggs',
        'Extra Large' => 'extra_large_eggs',
    ];

    /** Tray pool used for the Inventory Overview Egg Trays card. */
    public const TRAY_CAPACITY = 1000;

    protected $fillable = [
        'production_date',
        'batch_id',
        'small_eggs',
        'medium_eggs',
        'large_eggs',
        'extra_large_eggs',
        'cracked_eggs',
        'eggs_produced',
    ];

    public function qrTransactions()
    {
        return $this->hasMany(
            QrTransaction::class,
            'production_id',
            'production_id'
        );
    }

    /** Rebuild size totals from the batch's QR scans. */
    public function recalculateEggTotals(): void
    {
        $totals = array_fill_keys(array_keys(self::EGG_SIZE_COLUMNS), 0);

        foreach ($this->qrTransactions()->with('qrRecord')->get() as $transaction) {
            $size = $transaction->qrRecord?->egg_size;

            if (! isset($totals[$size])) {
                continue;
            }

            $totals[$size] += (int) $transaction->total_eggs;
        }

        $columns = [];
        foreach (self::EGG_SIZE_COLUMNS as $size => $column) {
            $columns[$column] = $totals[$size];
        }
        $columns['eggs_produced'] = array_sum($columns);

        $this->update($columns);
    }

    public function totalEggCount(): int
    {
        return $this->small_eggs
            + $this->medium_eggs
            + $this->large_eggs
            + $this->extra_large_eggs;
    }

    public static function sampleStartDate(): string
    {
        return '2023-01-01';
    }

    public static function visibleYear(DateTimeInterface|string|null $asOf = null): int
    {
        return (int) ($asOf ? Carbon::parse($asOf)->year : now()->year);
    }

    public function scopePresentYear($query, DateTimeInterface|string|int|null $year = null)
    {
        $visibleYear = is_int($year)
            ? $year
            : static::visibleYear($year);

        return $query->whereYear('production_date', $visibleYear);
    }

    /**
     * Eggs on hand for the last 7 harvest days (not the 3-year archive).
     * Loaded/scanned dispatch trays in the same window are deducted.
     */
    public static function leftoverStock(DateTimeInterface|string|null $asOf = null): int
    {
        return array_sum(static::availableEggStockBySize($asOf));
    }

    /**
     * Physical remaining egg stock by size after owner-scanned (loaded) trays.
     * Pending ordered trays that are not yet scanned do not reduce this.
     *
     * @return array<string, int>
     */
    public static function availableEggStockBySize(DateTimeInterface|string|null $asOf = null): array
    {
        return static::eggStockAfterTrayDeduction(
            static::loadedDispatchTraysBySize($asOf),
            $asOf,
        );
    }

    /**
     * Whole trays still physically available (after loaded/scanned deduct).
     *
     * @return array<string, int>
     */
    public static function availableDispatchTraysBySize(
        DateTimeInterface|string|null $asOf = null
    ): array {
        return array_map(
            static fn (int $eggs): int => intdiv($eggs, self::EGGS_PER_TRAY),
            static::availableEggStockBySize($asOf)
        );
    }

    /**
     * Trays that can still be promised on new Pending orders.
     * Counts full ordered quantities so Pending orders cannot oversell stock.
     *
     * @return array<string, int>
     */
    public static function promiseableDispatchTraysBySize(
        DateTimeInterface|string|null $asOf = null
    ): array {
        return array_map(
            static fn (int $eggs): int => intdiv($eggs, self::EGGS_PER_TRAY),
            static::eggStockAfterTrayDeduction(
                static::orderedDispatchTraysBySize($asOf),
                $asOf,
            )
        );
    }

    /**
     * @return array<string, int>
     */
    public static function loadedDispatchTraysBySize(DateTimeInterface|string|null $asOf = null): array
    {
        return static::dispatchTraysBySize('loaded_quantity', $asOf);
    }

    /**
     * @return array<string, int>
     */
    public static function orderedDispatchTraysBySize(DateTimeInterface|string|null $asOf = null): array
    {
        return static::dispatchTraysBySize('quantity', $asOf);
    }

    /**
     * @param  array<string, int|float|string>  $traysBySize
     * @return array<string, int>
     */
    private static function eggStockAfterTrayDeduction(
        array $traysBySize,
        DateTimeInterface|string|null $asOf = null,
    ): array {
        [$start, $end] = static::stockWindow($asOf);
        $columns = implode(', ', array_map(
            static fn (string $column): string => "COALESCE(SUM({$column}), 0) as {$column}",
            array_values(self::EGG_SIZE_COLUMNS)
        ));
        $harvested = static::query()
            ->whereDate('production_date', '>=', $start)
            ->whereDate('production_date', '<=', $end)
            ->selectRaw($columns)
            ->first();

        $available = [];
        foreach (self::EGG_SIZE_COLUMNS as $size => $column) {
            $available[$size] = max(
                0,
                (int) ($harvested->{$column} ?? 0)
                - ((int) ($traysBySize[$size] ?? 0) * self::EGGS_PER_TRAY)
            );
        }

        return $available;
    }

    /**
     * @return array<string, int>
     */
    private static function dispatchTraysBySize(
        string $column,
        DateTimeInterface|string|null $asOf = null,
    ): array {
        [$start, $end] = static::stockWindow($asOf);
        $qtyColumn = $column === 'loaded_quantity' ? 'loaded_quantity' : 'quantity';

        if ($qtyColumn === 'loaded_quantity' && ! Schema::hasColumn('dispatch_items', 'loaded_quantity')) {
            $qtyColumn = 'quantity';
        }

        return DB::table('dispatches')
            ->join('dispatch_items', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->whereDate('dispatches.dispatch_date', '>=', $start)
            ->whereDate('dispatches.dispatch_date', '<=', $end)
            ->select('dispatch_items.size')
            ->selectRaw('COALESCE(SUM(dispatch_items.'.$qtyColumn.'), 0) as trays')
            ->groupBy('dispatch_items.size')
            ->pluck('trays', 'size')
            ->map(fn ($trays): int => (int) $trays)
            ->all();
    }

    /**
     * Lock production rows in the stock window for dispatch reservations.
     */
    public static function lockStockWindow(DateTimeInterface|string|null $asOf = null): void
    {
        DB::table('dispatch_stock_locks')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        [$start, $end] = static::stockWindow($asOf);

        static::query()
            ->whereDate('production_date', '>=', $start)
            ->whereDate('production_date', '<=', $end)
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function stockWindow(DateTimeInterface|string|null $asOf = null): array
    {
        $asOfDate = $asOf ? Carbon::parse($asOf) : now();

        return [
            $asOfDate->copy()->subDays(self::STOCK_DAYS - 1)->toDateString(),
            $asOfDate->toDateString(),
        ];
    }

    /**
     * Empty trays left from the 1,000 pool: 1000 − (Eggs Stock ÷ 30).
     */
    public static function leftoverTrays(DateTimeInterface|string|null $asOf = null): int
    {
        $eggs = static::leftoverStock($asOf);

        return max(0, self::TRAY_CAPACITY - intdiv($eggs, self::EGGS_PER_TRAY));
    }
}
