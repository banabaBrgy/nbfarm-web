<?php

namespace App\Http\Controllers;

use App\Models\QrRecord;
use App\Models\Production;
use Illuminate\Http\Request;
class QrRecordController extends Controller
{
    public function index()
    {
        $records = QrRecord::orderBy('batch_id')
            ->orderBy('egg_size')
            ->orderBy('qr_code')
            ->get();

        return view('qr.list', compact('records'));
    }

    public function print()
    {
        $records = QrRecord::orderBy('batch_id')
            ->orderBy('egg_size')
            ->orderBy('qr_code')
            ->get();

        return view('qr.print', compact('records'));
    }

    public function generate()
{
    $today = today();

$productions = Production::whereDate(
    'production_date',
    $today
)->get();

$sizes = [

    'Small'=>'QRS',

    'Medium'=>'QRM',

    'Large'=>'QRL',

    'Extra Large'=>'QRXL',

    'Cracked'=>'QRC',

];

foreach($productions as $production){

    foreach($sizes as $size=>$prefix){

        for($i=1;$i<=10;$i++){

            QrRecord::firstOrCreate(

                [

                    'qr_code'=>$prefix.
                    '-B'.$production->batch_id.
                    '-'.str_pad($i,3,'0',STR_PAD_LEFT)

                ],

                [

                    'production_id'=>$production->production_id,

                    'batch_id'=>$production->batch_id,

                    'egg_size'=>$size,

                    'tray_count'=>10,

                    'eggs_per_tray'=>30

                ]

            );

        }

    }

}

$role = strtolower(trim(auth()->user()->role));
    return redirect()->route($role .'.qr.print');

    return redirect()
        ->route($role . '.qr.print')
        ->with('success', '30 QR Codes generated successfully.');

        return redirect()
            ->route($role . '.qr.print')
            ->with('success', '150 QR Codes generated successfully.');

    }
}