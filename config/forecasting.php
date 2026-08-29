<?php

return [

    /*
    | Python forecasting API (folder: ../forecasting).
    | Local: http://127.0.0.1:8001
    | Vercel: https://your-app.vercel.app
    */
    'api_url' => rtrim((string) env('FORECAST_API_URL', 'http://127.0.0.1:8001'), '/'),

    'api_key' => env('FORECAST_API_KEY', ''),

    'window' => (int) env('FORECAST_WINDOW', 14),

    'timeout' => (int) env('FORECAST_TIMEOUT', 10),

];
