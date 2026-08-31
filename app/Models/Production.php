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
        return '2024-01-01';
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
     * Delivered trays in the same window are subtracted.
     */
    public static function leftoverStock(DateTimeInterface|string|null $asOf = null): int
    {
        $asOfDate = $asOf ? Carbon::parse($asOf) : now();
        $end = $asOfDate->toDateString();
        $start = $asOfDate->copy()->subDays(self::STOCK_DAYS - 1)->toDateString();

        $harvested = (int) static::query()
            ->whereDate('production_date', '>=', $start)
            ->whereDate('production_date', '<=', $end)
            ->sum('eggs_produced');

        $trays = (int) DB::table('dispatches')
            ->join('dispatch_items', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->where('dispatches.status', 'Delivered')
            ->whereDate('dispatches.dispatch_date', '>=', $start)
            ->whereDate('dispatches.dispatch_date', '<=', $end)
            ->sum('dispatch_items.quantity');

        return max(0, $harvested - ($trays * self::EGGS_PER_TRAY));
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
