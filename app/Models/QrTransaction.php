<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrTransaction extends Model
{
    protected $fillable = [

        'qr_record_id',

        'production_id',

        'total_eggs',

        'status',

        'scanned_by',

        'scanned_at',

        'edited_by',

        'edited_at',

    ];

    public function qrRecord()
    {
        return $this->belongsTo(QrRecord::class);
    }

    public function production()
    {
        return $this->belongsTo(
            Production::class,
            'production_id',
            'production_id'
        );
    }

    /**
     * Newest scan for each egg size in a harvest.
     * Earlier trays of that size stay locked.
     *
     * @return array<int, int>
     */
    public static function latestIdsBySizeForProduction(int $productionId): array
    {
        $latest = [];

        $transactions = static::query()
            ->with('qrRecord:id,egg_size')
            ->where('production_id', $productionId)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->get(['id', 'qr_record_id']);

        foreach ($transactions as $transaction) {
            $size = $transaction->qrRecord?->egg_size;

            if (! is_string($size) || $size === '' || isset($latest[$size])) {
                continue;
            }

            $latest[$size] = (int) $transaction->id;
        }

        return array_values($latest);
    }

    public function isLastRecordedTray(): bool
    {
        return in_array(
            (int) $this->id,
            static::latestIdsBySizeForProduction((int) $this->production_id),
            true
        );
    }
}