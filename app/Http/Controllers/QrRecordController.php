<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\User;
use Illuminate\Http\Request;

class QrRecordController extends Controller
{
    public function index()
    {
        $records = QrRecord::printOrder()->get();

        return view('qr.list', compact('records'));
    }

    public function print(Request $request)
    {
        $printAll = $request->boolean('all');
        $query = QrRecord::printOrder();

        $records = $printAll
            ? $query->get()
            : $query->paginate(12)->withQueryString();

        return view('qr.print', compact('records', 'printAll'));
    }

    public function generate(Request $request)
    {
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
}
