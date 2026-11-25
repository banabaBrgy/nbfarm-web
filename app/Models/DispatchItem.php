<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchItem extends Model
{
    protected $table = 'dispatch_items';

    protected $fillable = [
        'dispatch_id',
        'size',
        'quantity',
        'loaded_quantity',
    ];

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->loaded_quantity);
    }

    public function isFullyLoaded(): bool
    {
        return $this->remainingQuantity() === 0;
    }
}