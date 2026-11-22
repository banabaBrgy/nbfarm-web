<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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

    protected $casts = [
        'qr_date' => 'date',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class,'production_id','production_id');
    }

    public function transactions()
    {
        return $this->hasMany(QrTransaction::class,'qr_record_id');
    }

    public function drafts()
    {
        return $this->hasMany(QrScanDraft::class, 'qr_record_id');
    }

    public static function clientCode(string $prefix, int $batchId, int $sequence): string
    {
        return sprintf('%s-B%d-%03d', $prefix, $batchId, $sequence);
    }

    public static function isClientCode(string $qrCode): bool
    {
        return (bool) preg_match('/^(QRS|QRM|QRL|QRXL)-B\d+-\d{3}$/', $qrCode);
    }

    public static function isBatchLabel(string $qrCode): bool
    {
        return (bool) preg_match('/^(QRS|QRM|QRL|QRXL)-B[1-3]-\d{8}-\d{3}$/', $qrCode);
    }

    /** One permanent code per batch and egg size, reused every harvest. */
    public static function sizeCode(int $batchId, string $eggSize): string
    {
        return sprintf('%s-B%d', self::SIZE_PREFIXES[$eggSize], $batchId);
    }

    public static function isSizeCode(string $qrCode): bool
    {
        return (bool) preg_match('/^(QRS|QRM|QRL|QRXL)-B[1-3]$/', $qrCode);
    }

    /** @return list<string> */
    public static function sizeCodeList(): array
    {
        $codes = [];

        foreach (self::BATCH_IDS as $batchId) {
            foreach (array_keys(self::SIZE_PREFIXES) as $eggSize) {
                $codes[] = self::sizeCode($batchId, $eggSize);
            }
        }

        return $codes;
    }

    public function isReusable(): bool
    {
        return self::isSizeCode((string) $this->qr_code);
    }

    /** A generated dated label counts on the date stored with it. Size codes and stickers count when scanned. */
    public function appliesOnRecordedDate(): bool
    {
        return self::isBatchLabel((string) $this->qr_code) && $this->qr_date !== null;
    }

    public function eggCount(): int
    {
        return (int) $this->tray_count * (int) $this->eggs_per_tray;
    }

    /**
     * One QR per batch and size. Generate again returns the same code for printing copies.
     */
    public static function issueSizeCode(int $batchId, string $eggSize, int $trays): self
    {
        $code = self::sizeCode($batchId, $eggSize);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($code, $batchId, $eggSize, $trays) {
                    $record = self::query()
                        ->where('qr_code', $code)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first();

                    $values = [
                        'batch_id' => $batchId,
                        'egg_size' => $eggSize,
                        'tray_count' => $trays,
                        'eggs_per_tray' => Production::EGGS_PER_TRAY,
                    ];

                    if ($record) {
                        $record->update($values);

                        return $record->fresh();
                    }

                    return self::create($values + [
                        'qr_code' => $code,
                        'qr_date' => today()->toDateString(),
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to issue a QR code.');
    }

    public function scopeSizeCodes($query)
    {
        return $query->whereIn('qr_code', self::sizeCodeList());
    }

    /**
     * One new label for a batch, date, and size. The next generate gets the next sequence.
     */
    public static function issueBatchLabel(
        int $batchId,
        string $eggSize,
        string $date,
        int $trays,
        ?int $productionId
    ): self {
        $date = Carbon::parse($date)->toDateString();
        $prefix = self::SIZE_PREFIXES[$eggSize];
        $stem = sprintf('%s-B%d-%s-', $prefix, $batchId, Carbon::parse($date)->format('Ymd'));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($stem, $batchId, $eggSize, $date, $trays, $productionId) {
                    $latest = self::query()
                        ->where('qr_code', 'like', $stem.'%')
                        ->lockForUpdate()
                        ->orderByDesc('qr_code')
                        ->value('qr_code');

                    $sequence = 1;

                    if (is_string($latest) && preg_match('/-(\d{3})$/', $latest, $matches)) {
                        $sequence = (int) $matches[1] + 1;
                    }

                    if ($sequence > 999) {
                        throw new \OverflowException('QR sequence exhausted.');
                    }

                    return self::create([
                        'production_id' => $productionId,
                        'batch_id' => $batchId,
                        'qr_code' => $stem.sprintf('%03d', $sequence),
                        'qr_date' => $date,
                        'egg_size' => $eggSize,
                        'tray_count' => $trays,
                        'eggs_per_tray' => Production::EGGS_PER_TRAY,
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to issue a QR code.');
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
            ->sizeCodes()
            ->orderBy('batch_id')
            ->orderByRaw("CASE egg_size WHEN 'Small' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Large' THEN 3 WHEN 'Extra Large' THEN 4 ELSE 5 END")
            ->orderBy('qr_code');
    }
}