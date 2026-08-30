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

    /** Eggs on hand: harvest minus delivered. Pass a date for stock as of that day. */
    public static function leftoverStock(DateTimeInterface|string|null $asOf = null): int
    {
        $date = $asOf ? Carbon::parse($asOf)->toDateString() : null;

        $harvestQuery = static::query()->selectRaw('
            COALESCE(SUM(small_eggs), 0) as small,
            COALESCE(SUM(medium_eggs), 0) as medium,
            COALESCE(SUM(large_eggs), 0) as large,
            COALESCE(SUM(extra_large_eggs), 0) as extra_large,
            COALESCE(SUM(cracked_eggs), 0) as cracked
        ');

        if ($date) {
            $harvestQuery->whereDate('production_date', '<=', $date);
        }

        $totals = $harvestQuery->first();

        $harvested = (int) ($totals->small ?? 0)
            + (int) ($totals->medium ?? 0)
            + (int) ($totals->large ?? 0)
            + (int) ($totals->extra_large ?? 0)
            + (int) ($totals->cracked ?? 0);

        $dispatchQuery = DB::table('dispatches')
            ->join('dispatch_items', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->where('dispatches.status', 'Delivered');

        if ($date) {
            $dispatchQuery->whereDate('dispatches.dispatch_date', '<=', $date);
        }

        $trays = (int) $dispatchQuery->sum('dispatch_items.quantity');

        return $harvested - ($trays * 30);
    }
}
