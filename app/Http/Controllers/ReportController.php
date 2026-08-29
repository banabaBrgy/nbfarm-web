<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Production;
use App\Models\Inventory;
use App\Models\Dispatch;
use App\Models\Transaction;
use Carbon\Carbon;
use App\Models\InventoryItem;
use App\Models\Forecast;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $reports = Report::latest()->get();

        return view('manager.reports', compact('reports'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'report_type' => 'required',
            'title' => 'required',
            'content' => 'required',
        ]);

        Report::create([
            'report_no' => 'RPT-' . now()->format('YmdHis'),
            'report_type' => $request->input('report_type'),
            'manager_id' => auth()->id(),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'status' => 'Draft',
            'owner_comment' => null,
            'is_read' => 0,
        ]);

        return redirect()
            ->route('manager.reports')
            ->with('success', 'Report generated successfully.');
    }

    public function send($id)
    {
        $report = Report::findOrFail($id);

        $report->update([

            'status' => 'Sent'

        ]);

        return back()->with('success', 'Report sent to Owner.');
    }

    public function destroy($id)
    {
        Report::findOrFail($id)->delete();

        return back()->with('success', 'Report deleted successfully.');
    }

    public function preview($type)
    {
        $today = Carbon::today()->format('F d, Y');

        switch ($type) {

            case 'production':

                $production = Production::selectRaw("
                SUM(small_eggs) as small,
                SUM(medium_eggs) as medium,
                SUM(large_eggs) as large,
                SUM(extra_large_eggs) as extra_large,
                SUM(cracked_eggs) as cracked,
                SUM(eggs_produced) as total
            ")->first();

                $forecast = Forecast::latest('forecast_date')->first();

                $content =
                    "========== PRODUCTION REPORT ==========\n\n"

                    . "Date : {$today}\n\n"

                    . "Small Eggs : {$production->small}\n"
                    . "Medium Eggs : {$production->medium}\n"
                    . "Large Eggs : {$production->large}\n"
                    . "Extra Large Eggs : {$production->extra_large}\n"
                    . "Cracked Eggs : {$production->cracked}\n\n"

                    . "TOTAL EGGS : {$production->total}\n\n"

                    . "Forecast Next Production : "
                    . ($forecast->predicted_eggs ?? 0)
                    . " eggs\n\n"

                    . "Prepared by Farm Manager.";

                break;


            case 'inventory':

                $items = InventoryItem::all();

                $lowStock = InventoryItem::whereColumn('current_stock', '<=', 'min_level')->count();

                $content = "========== INVENTORY REPORT ==========\n\n";
                $content .= "Report Date : " . Carbon::now()->format('F d, Y') . "\n\n";
                $content .= "Current Inventory Status\n";
                $content .= "--------------------------------------\n\n";

                foreach ($items as $item) {

                    $content .= "Item : {$item->item_name}\n";
                    $content .= "Stock : {$item->current_stock} {$item->unit}\n";
                    $content .= "Minimum Level : {$item->min_level} {$item->unit}\n";

                    if ($item->current_stock <= $item->min_level) {
                        $content .= "Status : LOW STOCK\n";
                    } else {
                        $content .= "Status : AVAILABLE\n";
                    }

                    $content .= "\n";
                }

                $content .= "--------------------------------------\n";
                $content .= "Total Inventory Items : " . $items->count() . "\n";
                $content .= "Low Stock Items : " . $lowStock . "\n\n";
                $content .= "Prepared by Farm Manager.";

                break;


            case 'dispatch':

                $dispatch = Dispatch::count();

                $pending = Dispatch::where('status', 'Pending')->count();

                $transit = Dispatch::where('status', 'On Transit')->count();

                $delivered = Dispatch::where('status', 'Delivered')->count();

                $content =
                    "========== DISPATCH REPORT ==========\n\n"

                    . "Date : {$today}\n\n"

                    . "Total Dispatch : {$dispatch}\n"

                    . "Pending : {$pending}\n"

                    . "On Transit : {$transit}\n"

                    . "Delivered : {$delivered}\n\n"

                    . "Prepared by Farm Manager.";

                break;


            case 'sales':

                $sales = DB::table('transactions')->sum('price');

                $transactions = DB::table('transactions')->count();

                $content =
                    "========== SALES REPORT ==========\n\n"

                    . "Date : {$today}\n\n"

                    . "Transactions : {$transactions}\n"

                    . "Revenue : ₱" . number_format($sales, 2) . "\n\n"

                    . "Prepared by Farm Manager.";

                break;


            default:

                $production = Production::selectRaw("
        SUM(small_eggs) as small,
        SUM(medium_eggs) as medium,
        SUM(large_eggs) as large,
        SUM(extra_large_eggs) as extra_large,
        SUM(cracked_eggs) as cracked,
        SUM(eggs_produced) as total
    ")->first();

                $forecast = Forecast::latest('forecast_date')->first();

                $dispatch = Dispatch::count();

                $pending = Dispatch::where('status', 'Pending')->count();

                $delivered = Dispatch::where('status', 'Delivered')->count();

                $inventoryItems = InventoryItem::count();

                $lowStocks = InventoryItem::whereColumn(
                    'current_stock',
                    '<=',
                    'min_level'
                )->count();

                $sales = DB::table('transactions')->sum('price');

                $transactions = DB::table('transactions')->count();

                $content =

                    "==============================\n"
                    . "NB POULTRY FARM\n"
                    . "OVERALL FARM REPORT\n"
                    . "==============================\n\n"

                    . "Report Date : {$today}\n\n"

                    . "========= PRODUCTION =========\n"

                    . "Small Eggs       : {$production->small}\n"

                    . "Medium Eggs      : {$production->medium}\n"

                    . "Large Eggs       : {$production->large}\n"

                    . "Extra Large      : {$production->extra_large}\n"

                    . "Cracked Eggs     : {$production->cracked}\n"

                    . "TOTAL PRODUCTION : {$production->total}\n\n"

                    . "========= INVENTORY =========\n"

                    . "Inventory Items : {$inventoryItems}\n"

                    . "Low Stock Items : {$lowStocks}\n\n"

                    . "========= DISPATCH =========\n"

                    . "Total Dispatch : {$dispatch}\n"

                    . "Pending        : {$pending}\n"

                    . "Delivered      : {$delivered}\n\n"

                    . "========= SALES =========\n"

                    . "Transactions : {$transactions}\n"

                    . "Revenue : ₱" . number_format($sales, 2) . "\n\n"

                    . "========= FORECAST =========\n"

                    . "Predicted Egg Production\n"

                    . ($forecast->predicted_eggs ?? 0)

                    . " eggs\n\n"

                    . "Farm Status : NORMAL\n\n"

                    . "Prepared by Farm Manager.";
                break;
        }

        return response()->json([
            'content' => $content
        ]);
    }
}