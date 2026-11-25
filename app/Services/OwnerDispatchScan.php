<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Production;
use App\Models\QrRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerDispatchScan
{
    /**
     * @return array{
     *     stockBySize: array<string, array{eggs:int, trays:int}>,
     *     pendingOrders: list<array<string, mixed>>
     * }
     */
    public function pageData(): array
    {
        $eggsBySize = Production::availableEggStockBySize();
        $traysBySize = Production::availableDispatchTraysBySize();
        $stockBySize = [];

        foreach (array_keys(Production::EGG_SIZE_COLUMNS) as $size) {
            $stockBySize[$size] = [
                'eggs' => (int) ($eggsBySize[$size] ?? 0),
                'trays' => (int) ($traysBySize[$size] ?? 0),
            ];
        }

        $pending = Dispatch::query()
            ->with('items')
            ->where('status', 'Pending')
            ->orderByDesc('dispatch_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Dispatch $dispatch): array {
                $items = $dispatch->items->map(fn (DispatchItem $item): array => [
                    'size' => $item->size,
                    'ordered' => (int) $item->quantity,
                    'loaded' => (int) $item->loaded_quantity,
                    'remaining' => $item->remainingQuantity(),
                ])->values()->all();

                return [
                    'id' => $dispatch->id,
                    'recipient' => $dispatch->recipient,
                    'contact' => $dispatch->contact,
                    'address' => $dispatch->address,
                    'dispatch_date' => \Carbon\Carbon::parse($dispatch->dispatch_date)->toDateString(),
                    'price' => (float) $dispatch->price,
                    'ordered_trays' => $dispatch->totalOrderedTrays(),
                    'loaded_trays' => $dispatch->totalLoadedTrays(),
                    'items' => $items,
                    'label' => $this->orderLabel($dispatch),
                ];
            })
            ->values()
            ->all();

        return [
            'stockBySize' => $stockBySize,
            'pendingOrders' => $pending,
        ];
    }

    /**
     * @return array{qr_code:string, size:string, batch_id:int|null, trays_per_scan:int}
     */
    public function resolve(string $qrCode): array
    {
        $code = trim($qrCode);
        if (str_contains($code, '/')) {
            $code = trim((string) basename(parse_url($code, PHP_URL_PATH) ?: $code));
        }

        $qr = QrRecord::query()
            ->where('qr_code', $code)
            ->orderBy('id')
            ->first();

        if (! $qr) {
            throw ValidationException::withMessages([
                'qr_code' => 'QR code not found. Generate/print the size code first.',
            ]);
        }

        $size = (string) $qr->egg_size;
        if (! array_key_exists($size, Production::EGG_SIZE_COLUMNS)) {
            throw ValidationException::withMessages([
                'qr_code' => 'This QR size cannot be used for dispatch.',
            ]);
        }

        return [
            'qr_code' => (string) $qr->qr_code,
            'size' => $size,
            'batch_id' => $qr->batch_id !== null ? (int) $qr->batch_id : null,
            'trays_per_scan' => max(1, (int) ($qr->tray_count ?: 1)),
        ];
    }

    /**
     * @return array{
     *     dispatch_id:int,
     *     status:string,
     *     size:string,
     *     added_trays:int,
     *     loaded:int,
     *     ordered:int,
     *     completed:bool,
     *     message:string
     * }
     */
    public function apply(int $dispatchId, string $qrCode, int $trays): array
    {
        if ($trays < 1) {
            throw ValidationException::withMessages([
                'trays' => 'Enter at least 1 tray.',
            ]);
        }

        $resolved = $this->resolve($qrCode);
        $size = $resolved['size'];

        return DB::transaction(function () use ($dispatchId, $size, $trays) {
            Production::lockStockWindow();

            /** @var Dispatch $dispatch */
            $dispatch = Dispatch::query()
                ->with('items')
                ->whereKey($dispatchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispatch->status !== 'Pending') {
                throw ValidationException::withMessages([
                    'dispatch_id' => 'Only Pending orders can receive scanned trays.',
                ]);
            }

            $sizeItems = $dispatch->items
                ->where('size', $size)
                ->sortBy('id')
                ->values();

            if ($sizeItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'dispatch_id' => 'This Pending order has no '.$size.' trays to fill.',
                ]);
            }

            $remaining = (int) $sizeItems->sum(fn (DispatchItem $item): int => $item->remainingQuantity());
            if ($trays > $remaining) {
                throw ValidationException::withMessages([
                    'trays' => "Only {$remaining} more {$size} tray(s) needed for this order.",
                ]);
            }

            $available = Production::availableDispatchTraysBySize()[$size] ?? 0;
            if ($trays > $available) {
                throw ValidationException::withMessages([
                    'trays' => "Insufficient {$size} stock. {$available} tray(s) available.",
                ]);
            }

            $left = $trays;
            $loadedTotal = 0;
            $orderedTotal = 0;
            foreach ($sizeItems as $sizeItem) {
                $item = DispatchItem::query()
                    ->whereKey($sizeItem->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $orderedTotal += (int) $item->quantity;
                $canTake = $item->remainingQuantity();
                if ($canTake < 1 || $left < 1) {
                    $loadedTotal += (int) $item->loaded_quantity;
                    continue;
                }

                $take = min($canTake, $left);
                $item->update([
                    'loaded_quantity' => (int) $item->loaded_quantity + $take,
                ]);
                $left -= $take;
                $loadedTotal += (int) $item->fresh()->loaded_quantity;
            }

            $dispatch->load('items');
            $completed = $dispatch->isFullyLoaded();
            if ($completed) {
                $dispatch->update(['status' => 'On Transit']);
            }

            $dispatch->syncSale();

            return [
                'dispatch_id' => $dispatch->id,
                'status' => $dispatch->fresh()->status,
                'size' => $size,
                'added_trays' => $trays,
                'loaded' => $loadedTotal,
                'ordered' => $orderedTotal,
                'completed' => $completed,
                'message' => $completed
                    ? 'Order complete. Status set to On Transit.'
                    : "Added {$trays} {$size} tray(s) to {$dispatch->recipient}.",
            ];
        });
    }

    private function orderLabel(Dispatch $dispatch): string
    {
        $parts = [];
        foreach ($dispatch->items as $item) {
            $parts[] = $item->size.' '.$item->loaded_quantity.'/'.$item->quantity;
        }

        $summary = $parts !== [] ? implode(', ', $parts) : 'No items';

        return $dispatch->recipient.' · '.$summary.' · '.\Carbon\Carbon::parse($dispatch->dispatch_date)->format('M j, Y');
    }
}
