<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class QrRecordController extends Controller
{
    public function index()
    {
        $records = QrRecord::printOrder()->get();

        return view('qr.list', compact('records'));
    }

    public function overview(Request $request)
    {
        abort_unless(User::routeRole() === 'staff', 403);

        $eggSize = $this->resolvedEggSize($request->input('egg_size'));
        $batchId = $this->resolvedBatchId($request->input('batch_id'));
        $search = mb_substr(trim((string) $request->input('search', '')), 0, 80);
        $todayProductionIds = Production::query()
            ->whereDate('production_date', today())
            ->pluck('production_id');

        $query = QrRecord::query()
            ->with(['transactions' => function ($relation) use ($todayProductionIds) {
                $relation
                    ->whereIn('production_id', $todayProductionIds)
                    ->orderByDesc('scanned_at')
                    ->orderByDesc('id');
            }])
            ->whereIn('batch_id', QrRecord::BATCH_IDS)
            ->whereIn('egg_size', array_keys(QrRecord::SIZE_PREFIXES));

        if ($eggSize) {
            $query->where('egg_size', $eggSize);
        }

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $safeSearch = str_replace(['%', '_'], '', $search);

        if ($safeSearch !== '') {
            $query->where('qr_code', 'like', '%'.$safeSearch.'%');
        }

        $rows = $query
            ->orderBy('batch_id')
            ->orderByRaw("CASE egg_size WHEN 'Small' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Large' THEN 3 WHEN 'Extra Large' THEN 4 ELSE 5 END")
            ->orderBy('qr_code')
            ->paginate(5)
            ->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map(function (QrRecord $record) {
                $latest = $record->transactions->first();
                $scanned = $latest !== null;

                return [
                    'qr_code' => (string) $record->qr_code,
                    'batch_id' => (int) $record->batch_id,
                    'egg_size' => (string) $record->egg_size,
                    'quantity' => $scanned
                        ? (int) $latest->total_eggs
                        : (int) $record->tray_count * (int) $record->eggs_per_tray,
                    'status' => $scanned ? 'Scanned' : 'Available',
                ];
            })
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

    public function generate(Request $request)
    {
        abort_unless(User::routeRole() === 'staff', 403);

        $batchId = $request->filled('batch_id') ? (int) $request->batch_id : null;
        $batches = $batchId ? [$batchId] : QrRecord::BATCH_IDS;

        $today = Production::whereDate('production_date', today())
            ->whereIn('batch_id', $batches)
            ->get()
            ->keyBy('batch_id');

        foreach ($batches as $id) {
            QrRecord::ensurePrintedCodes(
                $id,
                $today->get($id)?->production_id
            );
        }

        $role = User::routeRole();
        $count = count($batches) * count(QrRecord::SIZE_PREFIXES) * 10;

        return redirect()
            ->route($role . '.qr.print')
            ->with('success', $count.' QR codes ready (001–010 per size).');
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
