<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Models\User;
use App\Services\DailyInventory;
use App\Services\InventoryForecast;
use App\Services\LowStockNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        app(DailyInventory::class)->applyForToday();

        $search = $request->query('search');
        $statusFilter = $request->query('status');
        $range = $this->normalizeRange($request->query('range'));
        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));

        if ($range === 'custom' && $from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $itemsQuery = DB::table('inventory_items')->orderBy('id');

        if ($search) {
            $itemsQuery->where('item_name', 'like', "%{$search}%");
        }

        $items = $itemsQuery->paginate(5, ['*'], 'page')->withQueryString();

        $eggsStock = Production::leftoverStock();

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
        $vitamins    = $allItems->firstWhere('item_name', 'Vitamins');

        if ($feed) {
            $feed->status = $this->computeStatus($feed->current_stock, $feed->min_level);
        }

        if ($eggTrays) {
            $eggTrays->status = $this->computeStatus($eggTrays->current_stock, $eggTrays->min_level);
        }

        if ($vitamins) {
            $vitamins->status = $this->computeStatus($vitamins->current_stock, $vitamins->min_level);
        }

        // Egg Trays card/table use inventory stock so restocks are visible.
        $eggTraysDisplay = (float) ($eggTrays?->current_stock ?? 0);

        $eggsToday = (int) DB::table('production')
            ->whereDate('production_date', today())
            ->sum('eggs_produced');

        $eggsYesterday = (int) DB::table('production')
            ->whereDate('production_date', today()->subDay())
            ->sum('eggs_produced');

        $yearExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y', production_date)"
            : 'YEAR(production_date)';
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%m', production_date)"
            : 'MONTH(production_date)';

        $availableYears = DB::table('production')
            ->selectRaw("DISTINCT {$yearExpression} as year")
            ->orderByDesc('year')
            ->pluck('year');

        $selectedYear = (int) $request->query('year', now()->year);
        if ($availableYears->isNotEmpty() && ! $availableYears->contains($selectedYear)) {
            $selectedYear = (int) $availableYears->first();
        }

        $selectedMonth = (int) $request->query('month', now()->month);
        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = (int) now()->month;
        }

        $eggTotals = DB::table('production')
            ->whereMonth('production_date', $selectedMonth)
            ->whereYear('production_date', $selectedYear)
            ->selectRaw('
                SUM(small_eggs) as small,
                SUM(medium_eggs) as medium,
                SUM(large_eggs) as large,
                SUM(extra_large_eggs) as extra_large
            ')->first();

        $availableMonths = DB::table('production')
            ->whereYear('production_date', $selectedYear)
            ->selectRaw("DISTINCT {$yearExpression} as year, {$monthExpression} as month")
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        if ($availableMonths->isEmpty()) {
            $availableMonths = collect([(object) [
                'year' => $selectedYear,
                'month' => $selectedMonth,
            ]]);
        }

        $grandTotal = ($eggTotals->small ?? 0)
            + ($eggTotals->medium ?? 0)
            + ($eggTotals->large ?? 0)
            + ($eggTotals->extra_large ?? 0);

        $movementsQuery = InventoryItemHistory::query()
            ->where('action', '!=', 'Checked')
            ->forDateRange($range, $from, $to)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $recentMovements = (clone $movementsQuery)
            ->limit($range === 'all' ? 8 : 50)
            ->get();

        $dateFilters = [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'label' => $this->periodLabel($range, $from, $to),
            'count' => (clone $movementsQuery)->count(),
        ];

        $role = User::routeRole();

        $inventoryForecast = app(InventoryForecast::class)->summary($feed, $vitamins, $eggTrays);

        return view($role . '.inventory', compact(
            'items',
            'feed',
            'eggTrays',
            'vitamins',
            'eggsStock',
            'eggTraysDisplay',
            'eggsToday',
            'eggsYesterday',
            'eggTotals',
            'grandTotal',
            'recentMovements',
            'search',
            'statusFilter',
            'selectedMonth',
            'selectedYear',
            'availableYears',
            'availableMonths',
            'inventoryForecast',
            'dateFilters'
        ));
    }

    public function addStock(Request $request)
    {
        abort_unless(User::routeRole() === 'owner', 403);

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

        if ($model = InventoryItem::find($id)) {
            app(LowStockNotifier::class)->check($model);
        }

        return back()->with('success', 'New item added successfully!');
    }

    public function increaseStock(Request $request)
    {
        abort_unless(in_array(User::routeRole(), ['owner', 'staff'], true), 403);

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

        if ($model = InventoryItem::find((int) $item->id)) {
            app(LowStockNotifier::class)->check($model);
        }

        return back()->with('success', 'Stock quantity updated successfully!');
    }

    public function deleteItem($id)
    {
        abort_unless(User::routeRole() === 'owner', 403);

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

        app(LowStockNotifier::class)->forget((int) $id);

        DB::table('inventory_items')->where('id', $id)->delete();

        return back()->with('success', 'Item deleted successfully!');
    }

    public function history(Request $request, $id)
    {
        $item = DB::table('inventory_items')->where('id', $id)->first();

        if (! $item) {
            abort(404);
        }

        $range = $this->normalizeRange($request->query('range'));
        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));

        if ($range === 'custom' && $from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $history = InventoryItemHistory::where('inventory_item_id', $id)
            ->where('action', '!=', 'Checked')
            ->forDateRange($range, $from, $to)
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
            'period' => $this->periodLabel($range, $from, $to),
            'history' => $history,
        ]);
    }

    private function normalizeRange(mixed $range): string
    {
        $range = is_string($range) ? $range : 'all';

        return in_array($range, ['all', 'daily', 'weekly', 'monthly', 'custom'], true)
            ? $range
            : 'all';
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function periodLabel(string $range, ?string $from, ?string $to): string
    {
        if ($range === 'daily') {
            return 'Daily · '.Carbon::today()->format('M j, Y');
        }

        if ($range === 'weekly') {
            return 'Weekly · '.Carbon::today()->startOfWeek()->format('M j').'–'.Carbon::today()->endOfWeek()->format('M j, Y');
        }

        if ($range === 'monthly') {
            return 'Monthly · '.Carbon::today()->format('F Y');
        }

        if ($range === 'custom' && $from && $to) {
            return Carbon::parse($from)->format('M j, Y').' – '.Carbon::parse($to)->format('M j, Y');
        }

        if ($range === 'custom') {
            return 'Choose a start and end date';
        }

        return 'All inventory records';
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