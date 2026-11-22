<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrScanDraft extends Model
{
    protected $fillable = [
        'qr_record_id',
        'harvest_date',
        'tray_count',
        'eggs_per_tray',
        'total_eggs',
        'scanned_by',
        'scanned_at',
    ];

    protected $casts = [
        'harvest_date' => 'date',
        'scanned_at' => 'datetime',
    ];

    public function qrRecord()
    {
        return $this->belongsTo(QrRecord::class);
    }

    public function setTrays(int $trays): void
    {
        $this->setCount($trays, null);
    }

    public function setCount(int $trays, ?int $eggs): void
    {
        $perTray = max(1, (int) $this->eggs_per_tray);
        $maxEggs = $trays * $perTray;

        $this->tray_count = $trays;
        $this->total_eggs = $eggs === null ? $maxEggs : $eggs;
    }

    public function maxEggsForTrays(int $trays): int
    {
        return $trays * max(1, (int) $this->eggs_per_tray);
    }
}
