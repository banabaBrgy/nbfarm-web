<?php

namespace App\Services;

use App\Exceptions\QrScanRejected;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrScanDraft;
use App\Models\QrTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QrScanRecorder
{
    public const DEBOUNCE_SECONDS = 2;

    public const NOTE = 'Double-check batch, size, stacks, and egg count before you Confirm & Save. Scan one size at a time. You can edit trays and eggs only — batch and size stay locked.';

    /**
     * @return array<string, mixed>
     */
    public function listing(?string $qrCode = null): array
    {
        $qr = $this->resolveListingQr($qrCode);

        if (! $qr) {
            return $this->emptyPayload();
        }

        return $this->groupPayload($qr);
    }

    /**
     * @return array<string, mixed>
     */
    public function listingForDate(?Carbon $date = null): array
    {
        $harvestDate = ($date ?? Carbon::today())->copy()->startOfDay();
        $drafts = QrScanDraft::query()
            ->with('qrRecord')
            ->whereDate('harvest_date', $harvestDate)
            ->orderBy('id')
            ->get();

        $rows = $drafts->values()->map(function (QrScanDraft $draft, int $index): ?array {
            $qr = $draft->qrRecord;

            if (! $qr) {
                return null;
            }

            return [
                'id' => (int) $draft->id,
                'number' => $index + 1,
                'qr_code' => (string) $qr->qr_code,
                'batch' => (int) $qr->batch_id,
                'size' => (string) $qr->egg_size,
                'trays' => (int) $draft->tray_count,
                'eggs' => (int) $draft->total_eggs,
                'eggs_per_tray' => max(1, (int) $draft->eggs_per_tray),
                'scanned_at' => optional($draft->scanned_at)->toIso8601String(),
            ];
        })->filter()->values();

        $last = $rows->last();

        return [
            'success' => true,
            'empty' => $rows->isEmpty(),
            'note' => self::NOTE,
            'harvest_date' => $harvestDate->toDateString(),
            'qr_code' => is_array($last) ? $last['qr_code'] : null,
            'batch' => is_array($last) ? $last['batch'] : null,
            'size' => is_array($last) ? $last['size'] : null,
            'drafts' => $rows->all(),
            'summary' => [
                'scans' => $rows->count(),
                'trays' => (int) $rows->sum('trays'),
                'eggs' => (int) $rows->sum('eggs'),
            ],
            'groups' => $this->pendingGroups($harvestDate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stage(string $qrCode, User $user): array
    {
        return $this->addDraft($qrCode, $user, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function addStack(string $qrCode, User $user): array
    {
        $staged = $this->addDraft($qrCode, $user, false);
        $payload = $this->listingForDate(
            ! empty($staged['harvest_date'])
                ? Carbon::parse($staged['harvest_date'])
                : Carbon::today()
        );
        $payload['message'] = $staged['message'] ?? 'Stack added. Review the list before you save.';

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateDraft(int $draftId, int $trays, User $user, ?int $eggs = null): array
    {
        if ($trays < 1 || $trays > 999) {
            throw new QrScanRejected('Enter 1 to 999 trays.');
        }

        $qr = DB::transaction(function () use ($draftId, $trays, $eggs) {
            $draft = QrScanDraft::query()
                ->with('qrRecord')
                ->lockForUpdate()
                ->find($draftId);

            if (! $draft || ! $draft->qrRecord) {
                throw new QrScanRejected('That stack is no longer in the review list.');
            }

            $maxEggs = $draft->maxEggsForTrays($trays);

            if ($eggs !== null && ($eggs < 1 || $eggs > $maxEggs)) {
                throw new QrScanRejected(
                    'Enter 1 to '.number_format($maxEggs).' eggs for '.$trays.' trays. Batch and size cannot be changed.'
                );
            }

            $draft->setCount($trays, $eggs);
            $draft->save();

            return $draft->qrRecord;
        });

        $payload = $this->listingForDate($qr->appliesOnRecordedDate()
            ? Carbon::parse($qr->qr_date)
            : Carbon::today());
        $payload['message'] = 'Stack updated. Review the list before you save.';

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteDraft(int $draftId, User $user): array
    {
        $qr = DB::transaction(function () use ($draftId) {
            $draft = QrScanDraft::query()
                ->with('qrRecord')
                ->lockForUpdate()
                ->find($draftId);

            if (! $draft || ! $draft->qrRecord) {
                throw new QrScanRejected('That stack is no longer in the review list.');
            }

            $qr = $draft->qrRecord;
            $draft->delete();

            return $qr;
        });

        $payload = $this->listingForDate($qr->appliesOnRecordedDate()
            ? Carbon::parse($qr->qr_date)
            : Carbon::today());
        $payload['message'] = 'Stack removed from the review list.';

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function commit(string $qrCode, User $user): array
    {
        [$qr, $harvestDate] = $this->locate($qrCode);

        $result = DB::transaction(function () use ($qr, $harvestDate, $user) {
            $drafts = QrScanDraft::query()
                ->where('qr_record_id', $qr->id)
                ->whereDate('harvest_date', $harvestDate)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($drafts->isEmpty()) {
                throw new QrScanRejected('Nothing to save. Scan the stacks first, then Confirm & Save.');
            }

            $production = $this->persistDrafts($qr, $harvestDate, $drafts, $user);

            return [$production, $drafts->count()];
        });

        [$production, $savedCount] = $result;
        $payload = $this->listingForDate($harvestDate);
        $payload['committed'] = true;
        $payload['saved'] = $this->savedSummary($qr, $production);
        $payload['message'] = $this->savedMessage($savedCount);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function commitAll(?string $date, User $user): array
    {
        $harvestDate = $date
            ? Carbon::parse($date)->startOfDay()
            : Carbon::today();

        $savedCount = DB::transaction(function () use ($harvestDate, $user) {
            $drafts = QrScanDraft::query()
                ->with('qrRecord')
                ->whereDate('harvest_date', $harvestDate)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($drafts->isEmpty()) {
                throw new QrScanRejected('Nothing to save. Scan the stacks first, then Confirm & Save.');
            }

            $saved = 0;

            foreach ($drafts->groupBy('qr_record_id') as $group) {
                $qr = $group->first()?->qrRecord;

                if (! $qr) {
                    continue;
                }

                $this->persistDrafts($qr, $harvestDate, $group->values(), $user);
                $saved += $group->count();
            }

            return $saved;
        });

        $payload = $this->listingForDate($harvestDate);
        $payload['committed'] = true;
        $payload['message'] = $this->savedMessage($savedCount);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function addDraft(string $qrCode, User $user, bool $debounce): array
    {
        [$qr, $harvestDate] = $this->locate($qrCode);

        DB::transaction(function () use ($qr, $harvestDate, $user, $debounce) {
            QrRecord::query()->whereKey($qr->id)->lockForUpdate()->first();

            $latest = QrScanDraft::query()
                ->where('qr_record_id', $qr->id)
                ->whereDate('harvest_date', $harvestDate)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $production = Production::query()
                ->whereDate('production_date', $harvestDate)
                ->where('batch_id', (int) ($qr->batch_id ?: 1))
                ->lockForUpdate()
                ->first();

            if (! $qr->isReusable()) {
                if ($latest || ($production && $this->existingSavedScan($qr, $production))) {
                    throw new QrScanRejected('QR already scanned.');
                }
            }

            if ($debounce && $latest && $this->isDebounced($latest)) {
                throw new QrScanRejected('This QR was just scanned. Wait a few seconds before scanning it again.');
            }

            $this->assertSameSize($qr, $harvestDate);

            QrScanDraft::create([
                'qr_record_id' => $qr->id,
                'harvest_date' => $harvestDate->toDateString(),
                'tray_count' => (int) $qr->tray_count,
                'eggs_per_tray' => max(1, (int) $qr->eggs_per_tray),
                'total_eggs' => $qr->eggCount(),
                'scanned_by' => $user->id,
                'scanned_at' => now(),
            ]);
        });

        $payload = $this->groupPayload($qr);
        $payload['staged'] = true;
        $payload['trays'] = (int) $qr->tray_count;
        $payload['eggs'] = $qr->eggCount();
        $payload['message'] = 'Added to the review list. Double-check below, then Confirm & Save.';

        return $payload;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, QrScanDraft>  $drafts
     */
    private function persistDrafts(QrRecord $qr, Carbon $harvestDate, $drafts, User $user): Production
    {
        QrRecord::query()->whereKey($qr->id)->lockForUpdate()->first();

        $production = Production::query()
            ->whereDate('production_date', $harvestDate)
            ->where('batch_id', (int) ($qr->batch_id ?: 1))
            ->lockForUpdate()
            ->first();

        if (! $production) {
            $production = Production::create([
                'production_date' => $harvestDate->toDateString(),
                'batch_id' => (int) ($qr->batch_id ?: 1),
                'small_eggs' => 0,
                'medium_eggs' => 0,
                'large_eggs' => 0,
                'extra_large_eggs' => 0,
                'cracked_eggs' => 0,
                'eggs_produced' => 0,
            ]);
        }

        if (! $qr->isReusable() && $this->existingSavedScan($qr, $production)) {
            throw new QrScanRejected('QR already scanned.');
        }

        if ($qr->appliesOnRecordedDate() && (int) $qr->production_id !== (int) $production->production_id) {
            $qr->update(['production_id' => $production->production_id]);
        }

        foreach ($drafts as $draft) {
            QrTransaction::create([
                'production_id' => $production->production_id,
                'qr_record_id' => $qr->id,
                'total_eggs' => (int) $draft->total_eggs,
                'status' => 'Scanned',
                'scanned_by' => $draft->scanned_by ?: $user->id,
                'scanned_at' => $draft->scanned_at ?: now(),
            ]);
        }

        QrScanDraft::query()
            ->whereIn('id', $drafts->pluck('id'))
            ->delete();

        $production->recalculateEggTotals();

        return $production->fresh();
    }

    private function savedMessage(int $savedCount): string
    {
        return $savedCount === 1
            ? 'Saved 1 stack to today’s QR production.'
            : 'Saved '.$savedCount.' stacks to today’s QR production.';
    }

    /**
     * @return array{0: QrRecord, 1: Carbon}
     */
    private function locate(string $qrCode): array
    {
        $qr = QrRecord::query()
            ->where('qr_code', $qrCode)
            ->orderBy('id')
            ->first();

        if (! $qr) {
            throw new QrScanRejected('QR Code not found.');
        }

        if ($qr->egg_size === 'Cracked' || str_starts_with((string) $qr->qr_code, 'QRC-')) {
            throw new QrScanRejected('Cracked QR codes are no longer used.');
        }

        $harvestDate = $qr->appliesOnRecordedDate()
            ? Carbon::parse($qr->qr_date)->startOfDay()
            : Carbon::today();

        return [$qr, $harvestDate];
    }

    private function resolveListingQr(?string $qrCode): ?QrRecord
    {
        if (is_string($qrCode) && trim($qrCode) !== '') {
            return QrRecord::query()
                ->where('qr_code', trim($qrCode))
                ->orderBy('id')
                ->first();
        }

        $latest = QrScanDraft::query()
            ->with('qrRecord')
            ->orderByDesc('id')
            ->first();

        return $latest?->qrRecord;
    }

    private function existingSavedScan(QrRecord $qr, Production $production): bool
    {
        return QrTransaction::query()
            ->where('production_id', $production->production_id)
            ->where('qr_record_id', $qr->id)
            ->exists();
    }

    private function isDebounced(QrScanDraft $latest): bool
    {
        $scannedAt = $latest->scanned_at
            ? Carbon::parse($latest->scanned_at)
            : null;

        return $scannedAt !== null
            && $scannedAt->gte(now()->subSeconds(self::DEBOUNCE_SECONDS));
    }

    private function assertSameSize(QrRecord $qr, Carbon $harvestDate): void
    {
        $sizes = QrScanDraft::query()
            ->with('qrRecord')
            ->whereDate('harvest_date', $harvestDate)
            ->lockForUpdate()
            ->get()
            ->map(fn (QrScanDraft $draft) => $draft->qrRecord?->egg_size)
            ->filter()
            ->unique()
            ->values();

        if ($sizes->isEmpty()) {
            return;
        }

        if ($sizes->count() > 1) {
            throw new QrScanRejected(
                'The review list already has more than one size. Confirm & Save or delete extra sizes first.'
            );
        }

        $current = (string) $sizes->first();

        if ($current !== (string) $qr->egg_size) {
            throw new QrScanRejected(
                'Scan one size at a time. Confirm & Save the '.$current.' stacks first, then scan '.$qr->egg_size.'.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function groupPayload(QrRecord $qr): array
    {
        $harvestDate = $qr->appliesOnRecordedDate()
            ? Carbon::parse($qr->qr_date)->startOfDay()
            : Carbon::today();

        $drafts = QrScanDraft::query()
            ->where('qr_record_id', $qr->id)
            ->whereDate('harvest_date', $harvestDate)
            ->orderBy('id')
            ->get();

        $eggs = (int) $drafts->sum('total_eggs');
        $trays = (int) $drafts->sum('tray_count');
        $production = Production::query()
            ->whereDate('production_date', $harvestDate)
            ->where('batch_id', (int) ($qr->batch_id ?: 1))
            ->first();

        return [
            'success' => true,
            'empty' => $drafts->isEmpty(),
            'note' => self::NOTE,
            'harvest_date' => $harvestDate->toDateString(),
            'qr_code' => (string) $qr->qr_code,
            'batch' => (int) $qr->batch_id,
            'size' => (string) $qr->egg_size,
            'trays' => (int) $qr->tray_count,
            'eggs' => $qr->eggCount(),
            'drafts' => $drafts->values()->map(fn (QrScanDraft $draft, int $index): array => [
                'id' => (int) $draft->id,
                'number' => $index + 1,
                'qr_code' => (string) $qr->qr_code,
                'batch' => (int) $qr->batch_id,
                'size' => (string) $qr->egg_size,
                'trays' => (int) $draft->tray_count,
                'eggs' => (int) $draft->total_eggs,
                'eggs_per_tray' => max(1, (int) $draft->eggs_per_tray),
                'scanned_at' => optional($draft->scanned_at)->toIso8601String(),
            ])->all(),
            'summary' => [
                'scans' => $drafts->count(),
                'trays' => $trays,
                'eggs' => $eggs,
            ],
            'saved' => $this->savedSummary($qr, $production),
            'groups' => $this->pendingGroups($harvestDate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'success' => true,
            'empty' => true,
            'note' => self::NOTE,
            'qr_code' => null,
            'batch' => null,
            'size' => null,
            'drafts' => [],
            'summary' => [
                'scans' => 0,
                'trays' => 0,
                'eggs' => 0,
            ],
            'saved' => [
                'scans' => 0,
                'trays' => 0,
                'eggs' => 0,
            ],
            'groups' => $this->pendingGroups(Carbon::today()),
        ];
    }

    /**
     * @return list<array{qr_code: string, batch: int, size: string, scans: int, trays: int, eggs: int}>
     */
    private function pendingGroups(?Carbon $date = null): array
    {
        $query = QrScanDraft::query()
            ->with('qrRecord')
            ->orderBy('id');

        if ($date) {
            $query->whereDate('harvest_date', $date);
        }

        return $query
            ->get()
            ->groupBy('qr_record_id')
            ->map(function ($drafts) {
                $qr = $drafts->first()?->qrRecord;

                if (! $qr) {
                    return null;
                }

                return [
                    'qr_code' => (string) $qr->qr_code,
                    'batch' => (int) $qr->batch_id,
                    'size' => (string) $qr->egg_size,
                    'scans' => $drafts->count(),
                    'trays' => (int) $drafts->sum('tray_count'),
                    'eggs' => (int) $drafts->sum('total_eggs'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{scans: int, trays: int, eggs: int}
     */
    private function savedSummary(QrRecord $qr, ?Production $production): array
    {
        if (! $production) {
            return [
                'scans' => 0,
                'trays' => 0,
                'eggs' => 0,
            ];
        }

        $eggs = (int) QrTransaction::query()
            ->where('production_id', $production->production_id)
            ->where('qr_record_id', $qr->id)
            ->sum('total_eggs');

        $perTray = max(1, (int) $qr->eggs_per_tray);

        return [
            'scans' => (int) QrTransaction::query()
                ->where('production_id', $production->production_id)
                ->where('qr_record_id', $qr->id)
                ->count(),
            'trays' => intdiv($eggs, $perTray),
            'eggs' => $eggs,
        ];
    }
}
