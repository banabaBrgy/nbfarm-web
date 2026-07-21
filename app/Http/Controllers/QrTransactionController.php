<?php

namespace App\Http\Controllers;

use App\Models\QrTransaction;
use App\Models\Production;
use Illuminate\Http\Request;
class QrTransactionController extends Controller
{
    public function update(Request $request, $id)
    {
    $request->validate([
        'total_eggs'=>'required|integer|min:0|max:300'
    ]);

    $transaction = QrTransaction::with('qrRecord')->findOrFail($id);

    $transaction->update([
        'total_eggs'=>$request->total_eggs
    ]);

    $production = Production::findOrFail($transaction->production_id);

    $transactions = QrTransaction::with('qrRecord')
        ->where('production_id',$production->production_id)
        ->get();

    $small=0;
    $medium=0;
    $large=0;
    $xl=0;
    $cracked=0;

    foreach($transactions as $t){

        switch($t->qrRecord->egg_size){

            case 'Small':
                $small += $t->total_eggs;
            break;

            case 'Medium':
                $medium += $t->total_eggs;
            break;

            case 'Large':
                $large += $t->total_eggs;
            break;

            case 'Extra Large':
                $xl += $t->total_eggs;
            break;

            case 'Cracked':
                $cracked += $t->total_eggs;
            break;
        }

    }

    $production->update([
        'small_eggs'=>$small,
        'medium_eggs'=>$medium,
        'large_eggs'=>$large,
        'extra_large_eggs'=>$xl,
        'cracked_eggs'=>$cracked,
        'eggs_produced'=>$small+$medium+$large+$xl+$cracked
    ]);

    return response()->json([
        'success'=>true
    ]);
}

}