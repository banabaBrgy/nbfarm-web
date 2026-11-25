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

    public function isFullyLoaded(): bool
    {
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return false;
        }

        foreach ($this->items as $item) {
            if (! $item->isFullyLoaded()) {
                return false;
            }
        }

        return true;
    }

    public function totalOrderedTrays(): int
    {
        $this->loadMissing('items');

        return (int) $this->items->sum('quantity');
    }

    public function totalLoadedTrays(): int
    {
        $this->loadMissing('items');

        return (int) $this->items->sum('loaded_quantity');
    }
}