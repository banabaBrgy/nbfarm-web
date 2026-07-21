<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    protected $table = 'dispatches';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(DispatchItem::class, 'dispatch_id');
    }
}