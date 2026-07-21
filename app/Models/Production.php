<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $table = 'production';

    protected $primaryKey = 'production_id';

    public $timestamps = false;

    protected $fillable = [
        'production_date',
        'batch_id',
        'small_eggs',
        'medium_eggs',
        'large_eggs',
        'extra_large_eggs',
        'cracked_eggs',
        'eggs_produced',
    ];

    public function qrTransactions()
{
    return $this->hasMany(
        QrTransaction::class,
        'production_id',
        'production_id'
    );
}
}
