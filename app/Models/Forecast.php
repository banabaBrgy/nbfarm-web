<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    protected $table = 'forecasts';

    protected $fillable = [
        'forecast_date',
        'predicted_eggs',
        'series',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'series' => 'array',
    ];

    public $timestamps = true;
}