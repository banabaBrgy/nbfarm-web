<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        'Cracked' => 'cracked_eggs',
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

    public function totalEggCount(): int
    {
        return $this->small_eggs
            + $this->medium_eggs
            + $this->large_eggs
            + $this->extra_large_eggs
            + $this->cracked_eggs;
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
     * Existing dispatch trays in the same window are reserved.
     */
    public static function leftoverStock(DateTimeInterface|string|null $asOf = null): int
    {
        return array_sum(static::availableEggStockBySize($asOf));
    }

    /**
     * Return remaining egg stock by size after reserved dispatch trays.
     *
     * @return array<string, int>
     */
    public static function availableEggStockBySize(DateTimeInterface|string|null $asOf = null): array
    {
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

        $reserved = DB::table('dispatches')
            ->join('dispatch_items', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->whereDate('dispatches.dispatch_date', '>=', $start)
            ->whereDate('dispatches.dispatch_date', '<=', $end)
            ->select('dispatch_items.size')
            ->selectRaw('COALESCE(SUM(dispatch_items.quantity), 0) as trays')
            ->groupBy('dispatch_items.size')
            ->pluck('trays', 'size');

        $available = [];
        foreach (self::EGG_SIZE_COLUMNS as $size => $column) {
            $available[$size] = max(
                0,
                (int) ($harvested->{$column} ?? 0)
                - ((int) ($reserved[$size] ?? 0) * self::EGGS_PER_TRAY)
            );
        }

        return $available;
    }

    /**
     * Return whole-tray stock available by egg size.
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
