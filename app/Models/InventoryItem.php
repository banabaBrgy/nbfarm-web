<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'item_name', 'unit', 'current_stock', 'min_level',
    ];

    protected $casts = [
        'current_stock' => 'float',
        'min_level' => 'float',
    ];

    public function getStatusAttribute()
    {
        return $this->current_stock < $this->min_level
            ? ['key' => 'low', 'label' => 'Low Stock']
            : ['key' => 'ok', 'label' => 'In Stock'];
    }
}