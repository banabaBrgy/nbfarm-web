<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            ->orderByDesc('dispatch_date')
            ->orderByDesc('id')
            ->paginate(5)
            ->withQueryString();

        $statusCounts = Dispatch::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $dispatchStats = [
            'total' => (int) $statusCounts->sum(),
            'pending' => (int) ($statusCounts['Pending'] ?? 0),
            'transit' => (int) ($statusCounts['On Transit'] ?? 0),
            'delivered' => (int) ($statusCounts['Delivered'] ?? 0),
        ];

        $role = strtolower((string) (auth()->user()->role ?? ''));

        if ($role === 'owner' || $role === 'farm owner') {
            return view('owner.dispatch', compact('dispatches', 'dispatchStats'));
        }

        return view('manager.dispatch', compact('dispatches', 'dispatchStats'));
    }

    public function update(Request $request, Dispatch $dispatch)
    {
        $validated = $request->validate([
            'dispatch_date' => 'required|date',
            'status' => 'required|in:Pending,On Transit,Delivered',
        ]);
        $this->validateDispatchDate($validated['dispatch_date']);

        DB::transaction(function () use ($dispatch, $validated) {
            Production::lockStockWindow();

            if (! $this->isWithinStockWindow($dispatch->dispatch_date)) {
                $this->ensureDispatchItemsAvailable($dispatch);
            }

            $dispatch->update([
                'dispatch_date' => $validated['dispatch_date'],
                'status' => $validated['status'],
            ]);

            $dispatch->syncSale();
        });

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

    public function destroyBulk(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:dispatches,id',
        ]);

        $ids = $request->input('ids');

        DispatchItem::whereIn('dispatch_id', $ids)->delete();
        Dispatch::whereIn('id', $ids)->delete();

        $count = count($ids);

        return back()->with('success', $count === 1
            ? '1 dispatch deleted successfully.'
            : $count.' dispatches deleted successfully.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'dispatch_date' => 'required|date',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:Pending,On Transit,Delivered',
            'size' => 'required|array|min:1',
            'size.*' => 'required|in:Small,Medium,Large,Extra Large,Cracked',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|integer|min:1',
        ]);

        $this->validateDispatchDate($validated['dispatch_date']);

        if (count($validated['size']) !== count($validated['quantity'])) {
            throw ValidationException::withMessages([
                'quantity' => 'Each egg size must have a matching tray quantity.',
            ]);
        }

        DB::transaction(function () use ($validated) {
            Production::lockStockWindow();
            $availableBySize = Production::availableDispatchTraysBySize();
            $requestedBySize = array_fill_keys(array_keys(Production::EGG_SIZE_COLUMNS), 0);

            foreach ($validated['size'] as $index => $size) {
                $requestedBySize[$size] += (int) $validated['quantity'][$index];
            }

            $shortages = [];
            foreach ($requestedBySize as $size => $requested) {
                $available = $availableBySize[$size] ?? 0;
                if ($requested > $available) {
                    $shortages[] = "{$size}: {$available} tray(s) available";
                }
            }

            if ($shortages !== []) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient egg stock. '.implode('; ', $shortages).'.',
                ]);
            }

            $dispatch = Dispatch::create([
                'recipient' => $validated['recipient'],
                'address' => $validated['address'],
                'dispatch_date' => $validated['dispatch_date'],
                'price' => $validated['price'],
                'status' => $validated['status'],
            ]);

            foreach ($validated['size'] as $index => $size) {

                $dispatch->items()->create([
                    'size' => $size,
                    'quantity' => $validated['quantity'][$index],
                ]);

            }

            $dispatch->syncSale();

        });

        return redirect()
            ->route($this->dispatchIndexRoute())
            ->with('success', 'Dispatch created successfully.');
    }

    public function updateStatus(Request $request, Dispatch $dispatch)
    {
        $request->validate([
            'status' => 'required|in:Pending,On Transit,Delivered',
        ]);

        $dispatch->update([
            'status' => $request->status,
        ]);

        $dispatch->syncSale();

        return back()->with('success', 'Dispatch status updated successfully.');
    }

    private function dispatchIndexRoute(): string
    {
        $role = strtolower((string) (auth()->user()->role ?? 'manager'));

        return $role === 'owner' ? 'owner.dispatch' : 'manager.dispatch';
    }

    private function validateDispatchDate(string $date): void
    {
        if (! $this->isWithinStockWindow($date)) {
            [$start, $end] = Production::stockWindow();
            throw ValidationException::withMessages([
                'dispatch_date' => "Dispatch date must be between {$start} and {$end}.",
            ]);
        }
    }

    private function isWithinStockWindow(string $date): bool
    {
        [$start, $end] = Production::stockWindow();

        return $date >= $start && $date <= $end;
    }

    private function ensureDispatchItemsAvailable(Dispatch $dispatch): void
    {
        $dispatch->loadMissing('items');
        $availableBySize = Production::availableDispatchTraysBySize();
        $requestedBySize = array_fill_keys(array_keys(Production::EGG_SIZE_COLUMNS), 0);

        foreach ($dispatch->items as $item) {
            $requestedBySize[$item->size] = ($requestedBySize[$item->size] ?? 0)
                + (int) $item->quantity;
        }

        $shortages = [];
        foreach ($requestedBySize as $size => $requested) {
            $available = $availableBySize[$size] ?? 0;
            if ($requested > $available) {
                $shortages[] = "{$size}: {$available} tray(s) available";
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages([
                'dispatch_date' => 'Insufficient egg stock. '.implode('; ', $shortages).'.',
            ]);
        }
    }
}