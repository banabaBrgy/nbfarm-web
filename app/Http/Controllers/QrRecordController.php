<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrRecordController extends Controller
{
    public function index()
    {
        $records = QrRecord::printOrder()->get();

        return view('qr.list', compact('records'));
    }

    public function overview(Request $request)
    {
        abort_unless(in_array(User::routeRole(), ['staff', 'manager'], true), 403);

        $eggSize = $this->resolvedEggSize($request->input('egg_size'));
        $batchId = $this->resolvedBatchId($request->input('batch_id'));
        $search = mb_substr(trim((string) $request->input('search', '')), 0, 80);

        $todayProductionIds = Production::query()
            ->whereDate('production_date', today())
            ->pluck('production_id');

        $query = QrRecord::query()
            ->sizeCodes()
            ->with(['transactions' => function ($relation) use ($todayProductionIds) {
                $relation->whereIn('production_id', $todayProductionIds);
            }])
            ->whereIn('batch_id', QrRecord::BATCH_IDS)
            ->whereIn('egg_size', array_keys(QrRecord::SIZE_PREFIXES));

        if ($eggSize) {
            $query->where('egg_size', $eggSize);
        }

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        // Retrieve and prepare all displayed QR records.
        $allRows = $query
            ->orderBy('batch_id')
            ->orderByRaw("CASE egg_size WHEN 'Small' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Large' THEN 3 WHEN 'Extra Large' THEN 4 ELSE 5 END")
            ->orderBy('qr_code')
            ->get()
            ->map(function (QrRecord $record) {
                $eggs = (int) $record->transactions->sum('total_eggs');
                $perTray = max(1, (int) $record->eggs_per_tray);

                return [
                    'qr_code' => (string) $record->qr_code,
                    'batch_id' => (int) $record->batch_id,
                    'egg_size' => (string) $record->egg_size,
                    'scans' => $record->transactions->count(),
                    'trays' => intdiv($eggs, $perTray),
                    'eggs' => $eggs,
                ];
            })
            ->values();

        // Search across all displayed table columns.
        if ($search !== '') {
            $allRows = $allRows->filter(function (array $row) use ($search) {
                foreach ([
                    'qr_code',
                    'batch_id',
                    'egg_size',
                    'scans',
                    'trays',
                    'eggs',
                ] as $field) {
                    if (mb_stripos((string) $row[$field], $search) !== false) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        // Paginate the filtered results.
        $perPage = 12;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $rows = new LengthAwarePaginator(
            $allRows->forPage($currentPage, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('qr.overview', [
            'rows' => $rows,
            'sizes' => array_keys(QrRecord::SIZE_PREFIXES),
            'batches' => QrRecord::BATCH_IDS,
            'eggSize' => $eggSize,
            'batchId' => $batchId,
            'search' => $search,
        ]);
    }

    public function print(Request $request)
    {
        $printAll = $request->boolean('all');
        $eggSize = $this->resolvedEggSize($request->input('egg_size'));
        $batchId = $this->resolvedBatchId($request->input('batch_id'));

        $query = QrRecord::printOrder();

        if ($eggSize) {
            $query->where('egg_size', $eggSize);
        }

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $records = $query->get();
        $groups = $this->groupRecords($records);
        $sizes = array_keys(QrRecord::SIZE_PREFIXES);
        $batches = QrRecord::BATCH_IDS;

        return view('qr.print', compact(
            'records',
            'groups',
            'printAll',
            'eggSize',
            'batchId',
            'sizes',
            'batches'
        ));
    }

    public function create(Request $request)
    {
        $this->ensureStaff();

        $record = $request->filled('record')
            ? QrRecord::query()->find($request->integer('record'))
            : null;

        $copies = max(1, min(50, $request->integer('copies', 1)));

        return view('qr.generate', [
            'batches' => QrRecord::BATCH_IDS,
            'sizes' => array_keys(QrRecord::SIZE_PREFIXES),
            'eggsPerTray' => Production::EGGS_PER_TRAY,
            'record' => $record,
            'batchId' => $record?->batch_id ?? $this->resolvedBatchId($request->input('batch_id')) ?? QrRecord::BATCH_IDS[0],
            'trays' => $record?->tray_count ?? (is_numeric($request->input('trays')) ? (int) $request->input('trays') : 10),
            'eggSize' => $record?->egg_size ?? $this->resolvedEggSize($request->input('egg_size')) ?? 'Small',
            'copies' => $copies,
        ]);
    }

    public function generate(Request $request)
    {
        $this->ensureStaff();

        $data = $request->validate([
            'batch_id' => ['required', 'integer', Rule::in(QrRecord::BATCH_IDS)],
            'trays' => ['required', 'integer', 'min:1', 'max:999'],
            'egg_size' => ['required', 'string', Rule::in(array_keys(QrRecord::SIZE_PREFIXES))],
        ]);

        $batchId = (int) $data['batch_id'];
        $code = QrRecord::sizeCode($batchId, $data['egg_size']);
        $existed = QrRecord::query()->where('qr_code', $code)->exists();

        $record = QrRecord::issueSizeCode(
            $batchId,
            $data['egg_size'],
            (int) $data['trays']
        );

        $message = $existed
            ? 'Batch '.$batchId.' '.$data['egg_size'].' already uses '.$record->qr_code.'. Print copies for every '.$record->tray_count.'-tray stack.'
            : 'QR code '.$record->qr_code.' is ready. Print copies and scan this same code on every matching stack.';

        return redirect()
            ->route(User::routeRole().'.qr.create', ['record' => $record->id])
            ->with('success', $message);
    }

    public function download(QrRecord $record)
    {
        $this->ensureStaff();

        $svg = (string) QrCode::format('svg')
            ->size(640)
            ->margin(2)
            ->generate($record->qr_code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$record->qr_code.'.svg"',
        ]);
    }

    public function label(QrRecord $record, Request $request)
    {
        $this->ensureStaff();

        $copies = max(1, min(50, $request->integer('copies', 1)));

        return view('qr.label', [
            'record' => $record,
            'copies' => $copies,
        ]);
    }

    private function ensureStaff(): void
    {
        abort_unless(User::routeRole() === 'staff', 403);
    }

    private function dateInput(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolvedEggSize(mixed $eggSize): ?string
    {
        if (! is_string($eggSize) || $eggSize === '') {
            return null;
        }

        return array_key_exists($eggSize, QrRecord::SIZE_PREFIXES) ? $eggSize : null;
    }

    private function resolvedBatchId(mixed $batchId): ?int
    {
        if ($batchId === null || $batchId === '') {
            return null;
        }

        $id = (int) $batchId;

        return in_array($id, QrRecord::BATCH_IDS, true) ? $id : null;
    }

    /**
     * @return Collection<int, array{batch_id:int, egg_size:string, title:string, records:Collection<int, QrRecord>}>
     */
    private function groupRecords(Collection $records): Collection
    {
        return $records
            ->groupBy(fn (QrRecord $record) => $record->batch_id.'|'.$record->egg_size)
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'batch_id' => (int) $first->batch_id,
                    'egg_size' => (string) $first->egg_size,
                    'title' => 'Batch '.$first->batch_id.' '.$first->egg_size,
                    'records' => $items->values(),
                ];
            })
            ->values();
    }
}
