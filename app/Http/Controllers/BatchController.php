<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\User;

class BatchController extends Controller
{
    public function show($batch)
    {
        $production = Production::where('batch_id', $batch)
            ->whereDate('production_date', today())
            ->first();

        if (! $production) {
            abort(404);
        }

        return redirect()->route(
            User::routeRole().'.production.details',
            $production->production_id
        );
    }
}
