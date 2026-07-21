<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyInventoryLog extends Model
{
    protected $table = 'daily_inventory_logs';

    protected $fillable = [
        'log_date',
    ];

    public $timestamps = false;
}