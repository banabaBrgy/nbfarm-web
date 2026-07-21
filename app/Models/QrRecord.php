<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrRecord extends Model
{
    protected $table = 'qr_records';

    protected $fillable = [
        'production_id',
        'batch_id',
        'qr_code',
        'egg_size',
        'tray_count',
        'eggs_per_tray'
    ];

    public function production()
    {
        return $this->belongsTo(Production::class,'production_id','production_id');
    }

    public function transactions()
    {
        return $this->hasMany(QrTransaction::class,'qr_record_id');
    }
}