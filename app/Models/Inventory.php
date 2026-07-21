<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';

    protected $primaryKey = 'inventory_id';

    public $timestamps = false;

   protected $fillable = [

    'item_name',
    'item_type',
    'quantity',
    'minimum_stock',

];
}