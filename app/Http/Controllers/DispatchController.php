<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;

class DispatchController extends Controller
{
    public function index(Request $request)
    {
        $query = Dispatch::with('items');

        // Search Recipient
        if ($request->filled('search')) {
            $query->where('recipient', 'like', '%' . $request->search . '%');
        }

        // Filter Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter Size
        if ($request->filled('size')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('size', $request->size);
            });
        }

        $dispatches = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $role = auth()->user()->role ?? '';

        if ($role === 'owner') {
            return view('owner.dispatch', compact('dispatches'));
        }

        return view('manager.dispatch', compact('dispatches'));
    }

    public function update(Request $request, Dispatch $dispatch)
    {
        $request->validate([
            'dispatch_date' => 'required|date',
            'status' => 'required|in:Pending,On Transit,Delivered',
        ]);

        // Dating status
        $oldStatus = $dispatch->status;
        $wasDelivered = $dispatch->status === 'Delivered';

        // Update dispatch
        $dispatch->update([
            'dispatch_date' => $request->dispatch_date,
            'status' => $request->status,
        ]);

        // Kapag unang beses naging Delivered
        if ($request->status === 'Delivered' && !$wasDelivered) {

            $totalQty = $dispatch->items->sum('quantity');

            Transaction::create([
                'customer' => $dispatch->recipient,
                'transaction_date' => now(),
                'quantity' => $totalQty,
                'price' => $dispatch->price,
                'status' => 'Paid',
            ]);
        }

        return back()->with('success', 'Dispatch updated successfully.');
    }
    public function destroy(Dispatch $dispatch)
    {
        // Burahin muna ang related items kung meron
        $dispatch->items()->delete();

        // Burahin ang dispatch
        $dispatch->delete();

        return back()->with('success', 'Dispatch deleted successfully.');
    }
    public function store(Request $request)
    {
        $request->validate([
            'recipient' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'dispatch_date' => 'required|date',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:Pending,On Transit,Delivered',
            'size' => 'required|array|min:1',
            'size.*' => 'required|in:Small,Medium,Large',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request) {

            $dispatch = Dispatch::create([
                'recipient' => $request->recipient,
                'address' => $request->address,
                'dispatch_date' => $request->dispatch_date,
                'price' => $request->price,
                'status' => $request->status,   // ✅ ibase sa dropdown, hindi hardcoded
            ]);

            foreach ($request->size as $index => $size) {

                $dispatch->items()->create([
                    'size' => $size,
                    'quantity' => $request->quantity[$index],
                ]);

            }

        });

        return redirect()
            ->route('owner.dispatch')
            ->with('success', 'Dispatch created successfully.');
    }
}