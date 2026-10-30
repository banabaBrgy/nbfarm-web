<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    protected $table = 'dispatches';

    protected $fillable = [
        'recipient',
        'contact',
        'address',
        'dispatch_date',
        'price',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(DispatchItem::class, 'dispatch_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    /** COD: unpaid until the delivery is marked Delivered. */
    public function paymentStatus(): string
    {
        return $this->status === 'Delivered' ? 'Paid' : 'Pending';
    }

    public function syncSale(): Transaction
    {
        $this->loadMissing('items');

        return Transaction::updateOrCreate(
            ['dispatch_id' => $this->id],
            [
                'customer' => $this->recipient,
                'transaction_date' => $this->dispatch_date,
                'quantity' => (int) $this->items->sum('quantity'),
                'price' => $this->price,
                'status' => $this->paymentStatus(),
            ]
        );
    }
}