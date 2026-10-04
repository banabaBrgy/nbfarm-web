<?php

return [

    /*
    | Python forecasting API (folder: ../forecasting).
    | Kept for optional/local tooling; the live egg card uses the 3-year + temperature model.
    */
    'api_url' => rtrim((string) env('FORECAST_API_URL', 'http://127.0.0.1:8001'), '/'),

    'api_key' => env('FORECAST_API_KEY', ''),

    'timeout' => (int) env('FORECAST_TIMEOUT', 10),

    /*
    | Rolling window: last N *completed* calendar years before the forecast year.
    | Example: forecasting in 2026 → 2023–2025; in 2027 → 2024–2026.
    | Plus tomorrow's temperature for size mix.
    */
    'history_years' => (int) env('FORECAST_HISTORY_YEARS', 3),

    /*
    | Earliest harvest year allowed in the rolling window (client PDF start).
    | Optional hard end: leave empty for fully dynamic last-N-years behavior.
    */
    'archive_start' => (string) env('FORECAST_ARCHIVE_START', '2023-01-01'),

    'archive_end' => (string) env('FORECAST_ARCHIVE_END', ''),

    /** °C treated as neutral size mix (no Small/Large shift). */
    'neutral_temperature' => (float) env('FORECAST_NEUTRAL_TEMP', 28),

    /**
     * Share of transferable eggs moved Small↔Large per °C away from neutral.
     * Hotter → more Small; cooler → more Large (farm owner rule).
     * Rule-based because client PDFs have no historical temperature column.
     */
    'temp_shift_per_degree' => (float) env('FORECAST_TEMP_SHIFT', 0.015),

    /** Cap on the Small/Large temperature shift (0–1). */
    'temp_shift_cap' => (float) env('FORECAST_TEMP_SHIFT_CAP', 0.20),

];
