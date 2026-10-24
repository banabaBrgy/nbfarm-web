<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class QrRecord extends Model
{
    protected $table = 'qr_records';

    public const BATCH_IDS = [1, 2, 3];

    public const SIZE_PREFIXES = [
        'Small' => 'QRS',
        'Medium' => 'QRM',
        'Large' => 'QRL',
        'Extra Large' => 'QRXL',
    ];

    protected $fillable = [
        'production_id',
        'batch_id',
        'qr_code',
        'qr_date',
        'egg_size',
        'tray_count',
        'eggs_per_tray'
    ];

    public function production()
    {
        return $this->belongsTo(Production::class,'production_id','production_id');
    }

    public function transactions()
    {
        return $this->hasMany(QrTransaction::class,'qr_record_id');
    }

    public static function clientCode(string $prefix, int $batchId, int $sequence): string
    {
        return sprintf('%s-B%d-%03d', $prefix, $batchId, $sequence);
    }

    public static function isClientCode(string $qrCode): bool
    {
        return (bool) preg_match('/^(QRS|QRM|QRL|QRXL)-B\d+-\d{3}$/', $qrCode);
    }

    /** Create QRS/QRM/QRL/QRXL-B{n}-001 … 010 for the farm batches. */
    public static function ensurePrintedCodes(?int $batchId = null, ?int $productionId = null): void
    {
        $batches = $batchId ? [$batchId] : self::BATCH_IDS;
        $hasQrDate = Schema::hasColumn('qr_records', 'qr_date');

        foreach ($batches as $id) {
            foreach (self::SIZE_PREFIXES as $eggSize => $prefix) {
                for ($i = 1; $i <= 10; $i++) {
                    $data = [
                        'batch_id' => $id,
                        'egg_size' => $eggSize,
                        'tray_count' => 10,
                        'eggs_per_tray' => 30,
                    ];

                    if ($productionId) {
                        $data['production_id'] = $productionId;
                    }

                    if ($hasQrDate) {
                        $data['qr_date'] = now()->toDateString();
                    }

                    self::firstOrCreate(
                        ['qr_code' => self::clientCode($prefix, $id, $i)],
                        $data
                    );
                }
            }
        }
    }

    public function scopePrintOrder($query)
    {
        return $query
            ->whereIn('batch_id', self::BATCH_IDS)
            ->whereRaw("qr_code REGEXP '^(QRS|QRM|QRL|QRXL)-B[0-9]+-[0-9]{3}$'")
            ->orderBy('batch_id')
            ->orderByRaw("FIELD(egg_size, 'Small', 'Medium', 'Large', 'Extra Large')")
            ->orderBy('qr_code');
    }
}