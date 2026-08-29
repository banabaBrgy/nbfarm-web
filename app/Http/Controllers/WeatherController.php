<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;

class WeatherController extends Controller
{
    public function current(WeatherService $weather)
    {
        $data = $weather->current();

        if (! $data) {
            return response()->json([
                'ok' => false,
                'message' => 'Weather is unavailable right now.',
            ], 503);
        }

        return response()->json(['ok' => true] + $data);
    }
}
