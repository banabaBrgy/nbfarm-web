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

    /** Newest scan for a harvest. Earlier trays stay locked. */
    public static function latestIdForProduction(int $productionId): ?int
    {
        $id = static::query()
            ->where('production_id', $productionId)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function isLastRecordedTray(): bool
    {
        return (int) $this->id === (int) static::latestIdForProduction((int) $this->production_id);
    }
}