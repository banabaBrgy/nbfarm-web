<?php

namespace App\Http\Controllers;

use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Services\DailyInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        app(DailyInventory::class)->applyForToday();

        $search = $request->query('search');
        $statusFilter = $request->query('status');

        $itemsQuery = DB::table('inventory_items')->orderBy('id');

        if ($search) {
            $itemsQuery->where('item_name', 'like', "%{$search}%");
        }

        $items = $itemsQuery->paginate(6, ['*'], 'page')->withQueryString();

        $items->getCollection()->transform(function ($item) {
            $item->status = $this->computeStatus($item->current_stock, $item->min_level);
            return $item;
        });

        if ($statusFilter) {
            $filtered = $items->getCollection()->filter(fn($item) => $item->status === $statusFilter)->values();
            $items->setCollection($filtered);
        }

        $allItems = DB::table('inventory_items')->get();
        $feed        = $allItems->firstWhere('item_name', 'Feeds');
        $eggTrays    = $allItems->firstWhere('item_name', 'Egg Trays');
        $supplements = $allItems->firstWhere('item_name', 'Supplements');
        $vitamins    = $allItems->firstWhere('item_name', 'Vitamins');

        if ($feed) {
            $feed->status = $this->computeStatus($feed->current_stock, $feed->min_level);
        }

        if ($eggTrays) {
            $eggTrays->status = $this->computeStatus($eggTrays->current_stock, $eggTrays->min_level);
        }

        if ($supplements) {
            $supplements->status = $this->computeStatus($supplements->current_stock, $supplements->min_level);
        }

        if ($vitamins) {
            $vitamins->status = $this->computeStatus($vitamins->current_stock, $vitamins->min_level);
        }

        $eggsStock = Production::leftoverStock();

        $eggsToday = (int) DB::table('production')
            ->whereDate('production_date', today())
            ->sum('eggs_produced');

        $eggsYesterday = (int) DB::table('production')
            ->whereDate('production_date', today()->subDay())
            ->sum('eggs_produced');

        // ============================
        // 2. EGG PRODUCTION SUMMARY (napiling buwan, default: kasalukuyang buwan)
        // ============================
        $selectedMonth = $request->query('month', now()->month);
        $selectedYear  = $request->query('year', now()->year);

        $eggTotals = DB::table('production')
            ->whereMonth('production_date', $selectedMonth)
            ->whereYear('production_date', $selectedYear)
            ->selectRaw('
                SUM(small_eggs) as small,
                SUM(medium_eggs) as medium,
                SUM(large_eggs) as large,
                SUM(extra_large_eggs) as extra_large,
                SUM(cracked_eggs) as cracked
            ')->first();

        // Listahan ng mga buwan/taon na may laman sa production table (para sa dropdown)
        $availableMonths = DB::table('production')
            ->selectRaw('DISTINCT YEAR(production_date) as year, MONTH(production_date) as month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        $grandTotal = ($eggTotals->small ?? 0)
            + ($eggTotals->medium ?? 0)
            + ($eggTotals->large ?? 0)
            + ($eggTotals->extra_large ?? 0)
            + ($eggTotals->cracked ?? 0);

        // Recent stock movements (simple log base sa updated_at ng inventory_items)
        $recentMovements = DB::table('inventory_items')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        $role = auth()->user()->role ?? '';

        return view($role . '.inventory', compact(
            'items',
            'feed',
            'eggTrays',
            'supplements',
            'vitamins',
            'eggsStock',
            'eggsToday',
            'eggsYesterday',
            'eggTotals',
            'grandTotal',
            'recentMovements',
            'search',
            'statusFilter',
            'selectedMonth',
            'selectedYear',
            'availableMonths'
        ));
    }

    public function addStock(Request $request)
    {
        $validated = $request->validate([
            'item_name'     => ['required', 'string', 'max:255', 'unique:inventory_items,item_name'],
            'unit'          => ['required', 'string', 'max:50'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'min_level'     => ['required', 'numeric', 'min:0'],
        ]);

        $id = DB::table('inventory_items')->insertGetId([
            'item_name'     => $validated['item_name'],
            'unit'          => $validated['unit'],
            'current_stock' => $validated['current_stock'],
            'min_level'     => $validated['min_level'],
            'daily_stock'   => $validated['item_name'] === 'Egg Trays'
                ? $validated['current_stock']
                : null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        InventoryItemHistory::record(
            $id,
            $validated['item_name'],
            'Created',
            (float) $validated['current_stock'],
            0,
            (float) $validated['current_stock'],
            'New item added'
        );

        return back()->with('success', 'New item added successfully!');
    }

    public function increaseStock(Request $request)
    {
        $validated = $request->validate([
            'item_id'  => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $item = DB::table('inventory_items')->where('id', $validated['item_id'])->first();
        $before = (float) $item->current_stock;
        $qty = (float) $validated['quantity'];
        $after = $before + $qty;

        $update = [
            'current_stock' => $after,
            'updated_at' => now(),
        ];

        if ($item->item_name === 'Egg Trays') {
            $update['daily_stock'] = (float) ($item->daily_stock ?? DailyInventory::TRAY_DAILY_STOCK) + $qty;
        }

        DB::table('inventory_items')
            ->where('id', $validated['item_id'])
            ->update($update);

        InventoryItemHistory::record(
            (int) $item->id,
            $item->item_name,
            'Restocked',
            $qty,
            $before,
            $after,
            'Stock added'
        );

        return back()->with('success', 'Stock quantity updated successfully!');
    }

    public function deleteItem($id)
    {
        $item = DB::table('inventory_items')->where('id', $id)->first();

        if ($item) {
            InventoryItemHistory::record(
                (int) $item->id,
                $item->item_name,
                'Deleted',
                -1 * (float) $item->current_stock,
                (float) $item->current_stock,
                0,
                'Item removed'
            );
        }

        DB::table('inventory_items')->where('id', $id)->delete();

        return back()->with('success', 'Item deleted successfully!');
    }

    public function history($id)
    {
        $item = DB::table('inventory_items')->where('id', $id)->first();

        if (! $item) {
            abort(404);
        }

        $history = InventoryItemHistory::where('inventory_item_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'when' => $row->created_at->format('M d, Y g:i A'),
                'action' => $row->action,
                'change' => $row->quantity_change,
                'before' => $row->stock_before,
                'after' => $row->stock_after,
                'note' => $row->note,
            ]);

        return response()->json([
            'item' => $item->item_name,
            'unit' => $item->unit,
            'history' => $history,
        ]);
    }

    private function computeStatus($currentStock, $minLevel)
    {
        if ($currentStock < $minLevel) {
            return 'LOW';
        }

        if ($currentStock < $minLevel * 1.2) {
            return 'WATCH';
        }

        return 'OK';
    }
}