<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrTransactionController extends Controller
{
    public function update(Request $request, $id)
    {
        $user = $request->user();
        abort_unless($user && strtolower((string) $user->status) !== 'inactive', 403);
        abort_unless(in_array(User::routeRole($user->role), ['staff', 'manager', 'owner'], true), 403);

        $validated = $request->validate([
            'total_eggs' => 'required|integer|min:0|max:300',
            'production_id' => 'required|integer',
        ]);

        $transaction = QrTransaction::query()
            ->where('production_id', $validated['production_id'])
            ->findOrFail($id);

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
                'edited_by' => auth()->id(),
                'edited_at' => now(),
            ]);

            $production = Production::query()->findOrFail($transaction->production_id);
            $production->recalculateEggTotals();
        });

        return response()->json([
            'success' => true,
        ]);
    }
}
