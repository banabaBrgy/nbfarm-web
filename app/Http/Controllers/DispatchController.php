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
            ->orderByDesc('created_at')
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
        $validated = $this->validatedDispatchPayload($request);
        $originalDate = $this->dispatchDateString($dispatch);

        if ($validated['dispatch_date'] !== $originalDate) {
            $this->validateDispatchDate($validated['dispatch_date']);
        }

        DB::transaction(function () use ($dispatch, $validated) {
            Production::lockStockWindow();

            $requestedBySize = $this->requestedTraysBySize($validated);

            if ($this->isWithinStockWindow($validated['dispatch_date'])) {
                $this->assertEnoughTrays(
                    $requestedBySize,
                    $this->availableTraysForUpdate($dispatch)
                );
            }

            $dispatch->update([
                'recipient' => $validated['recipient'],
                'address' => $validated['address'],
                'dispatch_date' => $validated['dispatch_date'],
                'price' => $validated['price'],
                'status' => $validated['status'],
            ]);

            $dispatch->items()->delete();

            foreach ($validated['size'] as $index => $size) {
                $dispatch->items()->create([
                    'size' => $size,
                    'quantity' => $validated['quantity'][$index],
                ]);
            }

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
        $validated = $this->validatedDispatchPayload($request, true);
        $this->validateDispatchDate($validated['dispatch_date']);

        DB::transaction(function () use ($validated) {
            Production::lockStockWindow();
            $this->assertEnoughTrays(
                $this->requestedTraysBySize($validated),
                Production::availableDispatchTraysBySize()
            );

            $dispatch = Dispatch::create([
                'recipient' => $validated['recipient'],
                'contact' => $validated['contact'],
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

    private function validatedDispatchPayload(Request $request, bool $requireContact = false): array
    {
        $rules = [
            'recipient' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'dispatch_date' => 'required|date',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:Pending,On Transit,Delivered',
            'size' => 'required|array|min:1',
            'size.*' => 'required|in:Small,Medium,Large,Extra Large',
            'quantity' => 'required|array|min:1',
            'quantity.*' => 'required|integer|min:1',
        ];

        if ($requireContact) {
            $request->merge([
                'contact' => trim((string) $request->input('contact')),
            ]);
            $rules['contact'] = [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $contact = (string) $value;
                    $digits = preg_replace('/\D/', '', $contact) ?? '';

                    if (! preg_match('/^[0-9+\-\s()]+$/', $contact) || strlen($digits) < 10 || strlen($digits) > 13) {
                        $fail('Enter a contact number with 10 to 13 digits. Letters are not allowed.');
                    }
                },
            ];
        }

        $validated = $request->validate($rules, [
            'recipient.required' => 'Recipient is required.',
            'address.required' => 'Delivery address is required.',
            'dispatch_date.required' => 'Dispatch date is required.',
            'dispatch_date.date' => 'Enter a valid dispatch date.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid amount.',
            'price.min' => 'Price cannot be negative.',
            'status.required' => 'Status is required.',
            'status.in' => 'Select a valid status.',
            'size.required' => 'Add at least one egg size.',
            'size.*.required' => 'Select an egg size.',
            'size.*.in' => 'Select a valid egg size.',
            'quantity.required' => 'Enter the tray quantity.',
            'quantity.*.required' => 'Enter the tray quantity.',
            'quantity.*.integer' => 'Tray quantity must be a whole number.',
            'quantity.*.min' => 'Enter at least 1 tray.',
            'contact.required' => 'Contact number is required.',
            'contact.max' => 'Contact number must be 30 characters or fewer.',
        ]);

        if (count($validated['size']) !== count($validated['quantity'])) {
            throw ValidationException::withMessages([
                'quantity' => 'Each egg size must have a matching tray quantity.',
            ]);
        }

        return $validated;
    }

    private function requestedTraysBySize(array $validated): array
    {
        $requestedBySize = array_fill_keys(array_keys(Production::EGG_SIZE_COLUMNS), 0);

        foreach ($validated['size'] as $index => $size) {
            $requestedBySize[$size] += (int) $validated['quantity'][$index];
        }

        return $requestedBySize;
    }

    private function availableTraysForUpdate(Dispatch $dispatch): array
    {
        $available = Production::availableDispatchTraysBySize();

        if (! $this->isWithinStockWindow($this->dispatchDateString($dispatch))) {
            return $available;
        }

        $dispatch->loadMissing('items');

        foreach ($dispatch->items as $item) {
            $available[$item->size] = ($available[$item->size] ?? 0) + (int) $item->quantity;
        }

        return $available;
    }

    private function assertEnoughTrays(array $requestedBySize, array $availableBySize): void
    {
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

    private function dispatchDateString(Dispatch $dispatch): string
    {
        return \Carbon\Carbon::parse($dispatch->dispatch_date)->toDateString();
    }
}