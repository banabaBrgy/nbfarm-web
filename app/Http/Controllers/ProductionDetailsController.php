<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrTransaction;

class ProductionDetailsController extends Controller
{
    public function index($id)
    {
        $production = Production::with(['qrTransactions.qrRecord'])
            ->findOrFail($id);

        $transactions = $production->qrTransactions()
            ->with('qrRecord')
            ->join('qr_records', 'qr_transactions.qr_record_id', '=', 'qr_records.id')
            ->orderBy('qr_records.batch_id')
            ->orderByRaw("
                CASE qr_records.egg_size
                    WHEN 'Small' THEN 1
                    WHEN 'Medium' THEN 2
                    WHEN 'Large' THEN 3
                    WHEN 'Extra Large' THEN 4
                    ELSE 5
                END
            ")
            ->orderBy('qr_records.qr_code')
            ->select('qr_transactions.*')
            ->paginate(5)
            ->withQueryString();

        return view('production-details', [
            'production' => $production,
            'transactions' => $transactions,
            'editableTransactionIds' => QrTransaction::latestIdsBySizeForProduction((int) $production->production_id),
        ]);
    }
}