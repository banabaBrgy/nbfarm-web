<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportRemark extends Model
{
    protected $fillable = [

        'report_id',
        'owner_id',
        'remarks'

    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class,'owner_id');
    }
}