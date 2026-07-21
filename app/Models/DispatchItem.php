<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchItem extends Model
{
    protected $table = 'dispatch_items';

    protected $guarded = [];

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }
}