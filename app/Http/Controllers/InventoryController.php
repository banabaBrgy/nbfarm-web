<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
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

        // ============================
        // 1. EGGS STOCK (all-time, para sa "Eggs Stock" stat card)
        //    Hindi ito monthly — running balance ito ng available eggs.
        // ============================
        $stockTotals = DB::table('production')->selectRaw('
            SUM(small_eggs) as small,
            SUM(medium_eggs) as medium,
            SUM(large_eggs) as large,
            SUM(extra_large_eggs) as extra_large
        ')->first();

        // Total na-dispatch (Delivered lang), i-convert trays -> eggs (1 tray = 30)
        $totalDispatchedTrays = DB::table('dispatches')
            ->join('dispatch_items', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->where('dispatches.status', 'Delivered')
            ->sum('dispatch_items.quantity');

        $totalDispatchedEggs = $totalDispatchedTrays * 30;

        // Available stock = Produced (excluding cracked) - Dispatched
        $eggsStock = ($stockTotals->small ?? 0)
            + ($stockTotals->medium ?? 0)
            + ($stockTotals->large ?? 0)
            + ($stockTotals->extra_large ?? 0)
            - $totalDispatchedEggs;

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

        DB::table('inventory_items')->insert([
            'item_name'     => $validated['item_name'],
            'unit'          => $validated['unit'],
            'current_stock' => $validated['current_stock'],
            'min_level'     => $validated['min_level'],
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return back()->with('success', 'New item added successfully!');
    }

    public function increaseStock(Request $request)
    {
        $validated = $request->validate([
            'item_id'  => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::table('inventory_items')
            ->where('id', $validated['item_id'])
            ->update([
                'current_stock' => DB::raw('current_stock + ' . (float) $validated['quantity']),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Stock quantity updated successfully!');
    }

    public function deleteItem($id)
    {
        DB::table('inventory_items')->where('id', $id)->delete();

        return back()->with('success', 'Item deleted successfully!');
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