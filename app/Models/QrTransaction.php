<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrTransaction extends Model
{
    protected $fillable = [

        'qr_record_id',

        'production_id',

        'total_eggs',

        'status',

        'scanned_by',

        'scanned_at',

    ];

    public function qrRecord()
    {
        return $this->belongsTo(QrRecord::class);
    }

    public function production()
    {
        return $this->belongsTo(
            Production::class,
            'production_id',
            'production_id'
        );
    }
}