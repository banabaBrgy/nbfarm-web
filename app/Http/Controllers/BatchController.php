<?php

namespace App\Http\Controllers;

use App\Models\Production;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function show($batch)
    {
        $production = Production::where('batch_id', $batch)
            ->whereDate('production_date', today())
            ->first();

        if (!$production) {

            abort(404);

        }

        $qrRecords = $production->qrRecords()
            ->latest()
            ->get();

        return view('batch-details', [

            'production' => $production,
            'qrRecords' => $qrRecords,

        ]);
    }
}