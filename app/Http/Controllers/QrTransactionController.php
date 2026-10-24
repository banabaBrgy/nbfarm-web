<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrTransactionController extends Controller
{
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'total_eggs' => 'required|integer|min:0|max:300',
        ]);

        $transaction = QrTransaction::query()->findOrFail($id);

        if (! $transaction->isLastRecordedTray()) {
            return response()->json([
                'success' => false,
                'message' => 'Only the last recorded tray for this size can be edited.',
            ], 422);
        }

        DB::transaction(function () use ($transaction, $validated) {
            $transaction->update([
                'total_eggs' => (int) $validated['total_eggs'],
                'status' => 'Edited',
            ]);

            $production = Production::query()->findOrFail($transaction->production_id);
            $production->recalculateEggTotals();
        });

        return response()->json([
            'success' => true,
        ]);
    }
}
