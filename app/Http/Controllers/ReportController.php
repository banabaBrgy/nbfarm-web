<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\InventoryItem;
use App\Models\Production;
use App\Models\Report;
use App\Models\Transaction;
use App\Services\Forecasting\GenerateEggForecast;
use App\Services\ReportNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reportTypes = ['overall', 'production', 'inventory', 'dispatch', 'sales'];
        $statuses = ['Draft', 'Sent', 'Read'];
        $filters = [
            'report_type' => in_array($request->query('report_type'), $reportTypes, true)
                ? $request->query('report_type')
                : '',
            'status' => in_array($request->query('status'), $statuses, true)
                ? $request->query('status')
                : '',
        ];

        $query = Report::with('manager')
            ->where('manager_id', auth()->id())
            ->latest();

        if ($filters['report_type'] !== '') {
            $query->where('report_type', $filters['report_type']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $reports = $query->paginate(5)->appends(
            collect($request->query())->except(['open', 'page'])->all()
        );

        $openReport = null;
        $openId = (int) $request->query('open');
        if ($openId > 0) {
            $openReport = Report::query()
                ->where('manager_id', auth()->id())
                ->find($openId);
        }

        return view('manager.reports', [
            'reports' => $reports,
            'openReport' => $openReport,
            'filters' => $filters,
            'reportStats' => [
                'total' => Report::where('manager_id', auth()->id())->count(),
                'sent' => Report::where('manager_id', auth()->id())->where('status', 'Sent')->count(),
                'read' => Report::where('manager_id', auth()->id())->where('status', 'Read')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:overall,production,inventory,dispatch,sales',
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:100000',
        ]);

        Report::create(Report::existing([
            'report_no' => 'RPT-'.now()->format('YmdHis'),
            'report_type' => $request->input('report_type'),
            'manager_id' => auth()->id(),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'report_date' => now()->toDateString(),
            'status' => 'Draft',
            'owner_comment' => null,
            'is_read' => 0,
        ]));

        return redirect()
            ->route('manager.reports')
            ->with('success', 'Report saved as draft.');
    }

    public function send($id)
    {
        $report = $this->ownedReport($id);

        if ($report->status !== 'Draft') {
            return back()->with('success', 'Report sent to Owner.');
        }

        $report->update(Report::existing([
            'status' => 'Sent',
            'sent_at' => now(),
        ]));

        app(ReportNotifier::class)->sent($report);

        return back()->with('success', 'Report sent to Owner.');
    }

    public function show(Report $report)
    {
        $owned = Report::query()
            ->where('manager_id', auth()->id())
            ->whereKey($report->id)
            ->first();

        abort_unless($owned, 404);

        return redirect()->route('manager.reports', ['open' => $owned->id]);
    }

    public function destroy($id)
    {
        $report = $this->ownedReport($id);

        app(ReportNotifier::class)->forget((int) $report->id);

        $report->delete();

        return back()->with('success', 'Report deleted successfully.');
    }

    private function ownedReport($id): Report
    {
        return Report::query()
            ->where('manager_id', auth()->id())
            ->findOrFail($id);
    }

    public function preview(Request $request, $type)
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $label = $date->format('F d, Y');
        $titles = [
            'production' => 'Production Report',
            'inventory' => 'Inventory Report',
            'dispatch' => 'Dispatch Report',
            'sales' => 'Sales Report',
            'overall' => 'Overall Farm Report',
        ];
        $title = ($titles[$type] ?? 'Farm Report').' — '.$label;

        $content = match ($type) {
            'production' => $this->productionReport($date, $label),
            'inventory' => $this->inventoryReport($date, $label),
            'dispatch' => $this->dispatchReport($date, $label),
            'sales' => $this->salesReport($date, $label),
            default => $this->overallReport($date, $label),
        };

        return response()->json([
            'title' => $title,
            'content' => $content,
            'html' => \App\Support\ReportDocument::html($content),
        ]);
    }

    private function productionReport(Carbon $date, string $label): string
    {
        return
            $this->reportHeader('PRODUCTION REPORT', $label).
            $this->productionSection($date).
            "\n".
            $this->forecastSection().
            "\n".
            $this->reportFooter();
    }

    private function inventoryReport(Carbon $date, string $label): string
    {
        return
            $this->reportHeader('INVENTORY REPORT', $label).
            $this->inventorySection($date).
            "\n".
            $this->reportFooter();
    }

    private function dispatchReport(Carbon $date, string $label): string
    {
        return
            $this->reportHeader('DISPATCH REPORT', $label).
            $this->dispatchSection($date).
            "\n".
            $this->reportFooter();
    }

    private function salesReport(Carbon $date, string $label): string
    {
        return
            $this->reportHeader('SALES REPORT', $label).
            $this->salesSection($date).
            "\n".
            $this->reportFooter();
    }

    private function overallReport(Carbon $date, string $label): string
    {
        return
            "==============================\n".
            "NB POULTRY FARM\n".
            "OVERALL FARM REPORT\n".
            "==============================\n\n".
            $this->line('Report Date', $label).
            "\n".
            "========= PRODUCTION =========\n".
            $this->productionSection($date).
            "\n".
            "========= INVENTORY =========\n".
            $this->inventorySection($date).
            "\n".
            "========= DISPATCH =========\n".
            $this->dispatchSection($date).
            "\n".
            "========= SALES =========\n".
            $this->salesSection($date).
            "\n".
            "========= FORECAST =========\n".
            $this->forecastSection().
            "\n".
            $this->reportFooter();
    }

    private function reportHeader(string $title, string $label): string
    {
        return
            "========== {$title} ==========\n\n".
            $this->line('Report Date', $label).
            "\n";
    }

    private function reportFooter(): string
    {
        return 'Prepared by Farm Manager.';
    }

    private function productionSection(Carbon $date): string
    {
        $p = $this->productionTotals($date);

        return
            $this->line('Small Eggs', $this->qty($p['small'])).
            $this->line('Medium Eggs', $this->qty($p['medium'])).
            $this->line('Large Eggs', $this->qty($p['large'])).
            $this->line('Extra Large Eggs', $this->qty($p['xl'])).
            $this->line('TOTAL HARVEST', $this->qty($p['total']));
    }

    private function inventorySection(Carbon $date): string
    {
        return
            $this->line('Eggs Stock', $this->qty(Production::leftoverStock($date)).' pcs').
            $this->supplyItemLines();
    }

    private function dispatchSection(Carbon $date): string
    {
        $rows = Dispatch::with('items')->whereDate('dispatch_date', $date)->orderBy('id')->get();
        $trays = (int) $rows->sum(fn ($d) => $d->items->sum('quantity'));

        $text =
            $this->line('Total Dispatch', $this->qty($rows->count())).
            $this->line('Pending', $this->qty($rows->where('status', 'Pending')->count())).
            $this->line('On Transit', $this->qty($rows->where('status', 'On Transit')->count())).
            $this->line('Delivered', $this->qty($rows->where('status', 'Delivered')->count())).
            $this->line('Total Trays', $this->qty($trays)).
            $this->line('Eggs (trays x 30)', $this->qty($trays * 30));

        if ($rows->isEmpty()) {
            return $text."No dispatch records on this date.\n";
        }

        $text .= "--------------------------------------\n";
        foreach ($rows as $dispatch) {
            $itemList = $dispatch->items
                ->map(fn ($item) => $item->size.' '.$this->qty((int) $item->quantity).' trays')
                ->implode(', ');
            $text .= $dispatch->recipient.' | '.$dispatch->status.' | '.$this->peso((float) $dispatch->price)."\n";
            if ($itemList !== '') {
                $text .= $itemList."\n";
            }
        }

        return $text;
    }

    private function salesSection(Carbon $date): string
    {
        $rows = Transaction::whereDate('transaction_date', $date)->orderBy('id')->get();
        $revenue = (float) $rows->sum('price');

        $text =
            $this->line('Transactions', $this->qty($rows->count())).
            $this->line('Revenue', $this->peso($revenue));

        if ($rows->isEmpty()) {
            return $text."No sales records on this date.\n";
        }

        $text .= "--------------------------------------\n";
        foreach ($rows as $sale) {
            $text .=
                $sale->customer.' | '.$sale->status.' | '.
                $this->qty((int) $sale->quantity).' | '.
                $this->peso((float) $sale->price)."\n";
        }

        return $text;
    }

    private function forecastSection(): string
    {
        $predicted = app(GenerateEggForecast::class)->predictedEggs();

        return $this->line('Forecast (next day)', $this->qty($predicted).' eggs');
    }

    /**
     * @return array{small: int, medium: int, large: int, xl: int, total: int}
     */
    private function productionTotals(Carbon $date): array
    {
        $row = Production::query()
            ->whereDate('production_date', $date)
            ->selectRaw('
                COALESCE(SUM(small_eggs), 0) as small,
                COALESCE(SUM(medium_eggs), 0) as medium,
                COALESCE(SUM(large_eggs), 0) as large,
                COALESCE(SUM(extra_large_eggs), 0) as xl,
                COALESCE(SUM(small_eggs + medium_eggs + large_eggs + extra_large_eggs), 0) as total
            ')
            ->first();

        return [
            'small' => (int) ($row->small ?? 0),
            'medium' => (int) ($row->medium ?? 0),
            'large' => (int) ($row->large ?? 0),
            'xl' => (int) ($row->xl ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    private function supplyItemLines(): string
    {
        $lines = '';

        foreach (InventoryItem::orderBy('id')->get() as $item) {
            $low = $item->current_stock <= $item->min_level;
            $lines .=
                $this->line(
                    $item->item_name.' ('.$item->unit.')',
                    $this->qty((float) $item->current_stock).($low ? ' LOW STOCK (min '.$this->qty((float) $item->min_level).')' : '')
                );
        }

        return $lines;
    }

    private function line(string $label, string $value): string
    {
        return "{$label} : {$value}\n";
    }

    private function qty(int|float $n): string
    {
        return number_format($n);
    }

    private function peso(float $n): string
    {
        return '₱'.number_format($n, 2);
    }

}
