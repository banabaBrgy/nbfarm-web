<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\Forecast;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Models\QrTransaction;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemEffectiveness
{
    /**
     * Effectiveness for one date range. The range defaults to the current month.
     * A rate is null when the denominator is zero.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     from_label: string,
     *     to_label: string,
     *     indicators: list<array{
     *         title: string,
     *         headline: string,
     *         rate: float|null,
     *         rate_label: string|null,
     *         detail: string,
     *         basis: string
     *     }>
     * }
     */
    public function forRequest(Request $request): array
    {
        if ($request->filled('effectiveness_from') && $request->filled('effectiveness_to')) {
            $from = Carbon::parse($request->query('effectiveness_from'))->startOfDay();
            $to = Carbon::parse($request->query('effectiveness_to'))->startOfDay();
        } else {
            $from = now()->startOfMonth();
            $to = now()->endOfMonth()->startOfDay();
        }

        return $this->forPeriod($from, $to);
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     from_label: string,
     *     to_label: string,
     *     indicators: list<array{
     *         title: string,
     *         headline: string,
     *         rate: float|null,
     *         rate_label: string|null,
     *         detail: string,
     *         basis: string
     *     }>
     * }
     */
    public function forPeriod(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'from_label' => $from->format('M j, Y'),
            'to_label' => $to->format('M j, Y'),
            'indicators' => [
                $this->production($from, $to),
                $this->inventory($to),
                $this->dispatch($from, $to),
                $this->forecast($from, $to),
                $this->reports($from, $to),
            ],
        ];
    }

    /**
     * @return array{title: string, headline: string, rate: float|null, rate_label: string|null, detail: string, basis: string}
     */
    private function production(Carbon $from, Carbon $to): array
    {
        $basis = 'A record matches when the eggs produced are greater than zero and equal the eggs on its QR scans.';

        $rows = Production::query()
            ->whereDate('production_date', '>=', $from->toDateString())
            ->whereDate('production_date', '<=', $to->toDateString())
            ->get(['production_id', 'eggs_produced']);

        $scanned = QrTransaction::query()
            ->whereIn('production_id', $rows->pluck('production_id'))
            ->selectRaw('production_id, SUM(total_eggs) as scanned')
            ->groupBy('production_id')
            ->pluck('scanned', 'production_id');

        $numerator = 0;
        foreach ($rows as $row) {
            $eggs = (int) $row->eggs_produced;
            $scanTotal = (int) ($scanned[$row->production_id] ?? 0);

            if ($eggs > 0 && $eggs === $scanTotal) {
                $numerator++;
            }
        }

        $denominator = $rows->count();

        if ($denominator === 0) {
            return $this->pack(
                'Production recording',
                'No basis',
                null,
                'No production records in this period.',
                $basis
            );
        }

        $gap = $denominator - $numerator;
        $recordWord = $denominator === 1
            ? 'production record matches its QR scans'
            : 'production records match their QR scans';

        if ($gap === 0) {
            $detail = 'Every saved record matches its QR scans.';
        } elseif ($gap === 1) {
            $detail = '1 saved record does not match its QR scans.';
        } else {
            $detail = $gap.' saved records do not match their QR scans.';
        }

        return $this->pack(
            'Production recording',
            $numerator.' of '.$denominator.' '.$recordWord,
            $this->rate($numerator, $denominator),
            $detail,
            $basis
        );
    }

    /**
     * @return array{title: string, headline: string, rate: float|null, rate_label: string|null, detail: string, basis: string}
     */
    private function inventory(Carbon $to): array
    {
        $asOfToday = $to->toDateString() >= now()->toDateString();
        $basis = $asOfToday
            ? 'A supply item matches when its latest ledger entry balances (stock after equals stock before plus the change) and current stock equals that entry. Items with no ledger entry are left out.'
            : 'A supply item matches when its latest ledger entry on or before '.$to->format('M j, Y').' balances (stock after equals stock before plus the change). Items with no ledger entry are left out.';

        $numerator = 0;
        $denominator = 0;
        $unmatched = [];

        foreach (InventoryItem::query()->orderBy('item_name')->get() as $item) {
            $latest = InventoryItemHistory::query()
                ->where('inventory_item_id', $item->id)
                ->where('created_at', '<=', $to->copy()->endOfDay())
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if (! $latest) {
                continue;
            }

            $denominator++;
            $balances = $this->entryBalances($latest);
            $matchesStock = ! $asOfToday || abs((float) $item->current_stock - (float) $latest->stock_after) < 0.01;

            if ($balances && $matchesStock) {
                $numerator++;
            } else {
                $unmatched[] = $item->item_name;
            }
        }

        $egg = $this->eggStockSentence();

        if ($denominator === 0) {
            return $this->pack(
                'Inventory monitoring',
                'No basis',
                null,
                'No supply items have a ledger entry yet. '.$egg,
                $basis
            );
        }

        $itemWord = $denominator === 1
            ? 'supply item matches its latest ledger entry'
            : 'supply items match their latest ledger entry';

        if ($unmatched === []) {
            $detail = 'Every supply item with a ledger entry matches. '.$egg;
        } elseif (count($unmatched) === 1) {
            $detail = $unmatched[0].' does not match its latest ledger entry. '.$egg;
        } else {
            $detail = $this->nameList($unmatched).' do not match their latest ledger entry. '.$egg;
        }

        return $this->pack(
            'Inventory monitoring',
            $numerator.' of '.$denominator.' '.$itemWord,
            $this->rate($numerator, $denominator),
            $detail,
            $basis
        );
    }

    /**
     * @return array{title: string, headline: string, rate: float|null, rate_label: string|null, detail: string, basis: string}
     */
    private function dispatch(Carbon $from, Carbon $to): array
    {
        $basis = 'A dispatch counts as successful when its status is Delivered and it has a sales transaction. Pending and on transit stay in the total.';

        $rows = Dispatch::query()
            ->with('transaction')
            ->whereDate('dispatch_date', '>=', $from->toDateString())
            ->whereDate('dispatch_date', '<=', $to->toDateString())
            ->get();

        $denominator = $rows->count();
        $numerator = $rows->filter(
            fn (Dispatch $dispatch): bool => $dispatch->status === 'Delivered' && $dispatch->transaction !== null
        )->count();
        $pending = $rows->where('status', 'Pending')->count();
        $transit = $rows->where('status', 'On Transit')->count();
        $deliveredWithoutSale = $rows->filter(
            fn (Dispatch $dispatch): bool => $dispatch->status === 'Delivered' && $dispatch->transaction === null
        )->count();

        if ($denominator === 0) {
            return $this->pack(
                'Dispatch processing',
                'No basis',
                null,
                'No dispatches in this period.',
                $basis
            );
        }

        $dispatchWord = $denominator === 1
            ? 'dispatch was delivered and posted as a sale'
            : 'dispatches were delivered and posted as sales';

        return $this->pack(
            'Dispatch processing',
            $numerator.' of '.$denominator.' '.$dispatchWord,
            $this->rate($numerator, $denominator),
            'Pending: '.$pending.'. On transit: '.$transit.'. Delivered without a sales row: '.$deliveredWithoutSale.'.',
            $basis
        );
    }

    /**
     * @return array{title: string, headline: string, rate: float|null, rate_label: string|null, detail: string, basis: string}
     */
    private function forecast(Carbon $from, Carbon $to): array
    {
        $basis = 'A day counts when a forecast row is stored for that date. Days before the first stored forecast, and days after tomorrow, are left out.';
        $first = Forecast::query()->min('forecast_date');

        if ($first === null) {
            return $this->pack(
                'Forecasting',
                'No basis',
                null,
                'No forecast was stored for this period.',
                $basis
            );
        }

        $firstDate = Carbon::parse($first)->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        $expectedStart = $from->copy()->max($firstDate);
        $expectedEnd = $to->copy()->min($tomorrow);

        if ($expectedStart->gt($expectedEnd)) {
            $detail = $from->gt($tomorrow)
                ? 'Forecasts are stored through '.$tomorrow->format('M j, Y').', which is before this period.'
                : 'The earliest stored forecast is '.$firstDate->format('M j, Y').', which is after this period.';

            return $this->pack('Forecasting', 'No basis', null, $detail, $basis);
        }

        $denominator = $this->inclusiveDays($expectedStart, $expectedEnd);
        $numerator = (int) Forecast::query()
            ->whereDate('forecast_date', '>=', $expectedStart->toDateString())
            ->whereDate('forecast_date', '<=', $expectedEnd->toDateString())
            ->distinct()
            ->count('forecast_date');

        $dayWord = $denominator === 1 ? 'day' : 'days';
        $detail = 'Counted from '.$expectedStart->format('M j, Y').' through '.$expectedEnd->format('M j, Y').'.';

        if ($expectedStart->gt($from) || $expectedEnd->lt($to)) {
            $detail .= ' Days outside that span are left out: forecasts start on the first stored date and stop after tomorrow.';
        }

        return $this->pack(
            'Forecasting',
            'Forecast stored for '.$numerator.' of '.$denominator.' '.$dayWord,
            $this->rate($numerator, $denominator),
            $detail,
            $basis
        );
    }

    /**
     * @return array{title: string, headline: string, rate: float|null, rate_label: string|null, detail: string, basis: string}
     */
    private function reports(Carbon $from, Carbon $to): array
    {
        $basis = 'A report counts as generated when it is saved with content. Draft, sent, and read are shown separately.';

        $dateColumn = Schema::hasColumn('reports', 'report_date')
            ? 'report_date'
            : 'created_at';

        $rows = Report::query()
            ->whereDate($dateColumn, '>=', $from->toDateString())
            ->whereDate($dateColumn, '<=', $to->toDateString())
            ->get(['content', 'status']);

        $denominator = $rows->count();
        $numerator = $rows->filter(
            fn (Report $report): bool => trim((string) $report->content) !== ''
        )->count();

        if ($denominator === 0) {
            return $this->pack(
                'Report generation',
                'No basis',
                null,
                'No reports were generated in this period.',
                $basis
            );
        }

        $empty = $denominator - $numerator;
        if ($empty === 0) {
            $contentNote = 'Every saved report has content.';
        } elseif ($empty === 1) {
            $contentNote = '1 saved report has no content.';
        } else {
            $contentNote = $empty.' saved reports have no content.';
        }

        $reportWord = $denominator === 1
            ? 'report was generated with content'
            : 'reports were generated with content';

        $detail = $contentNote
            .' Draft: '.$rows->where('status', 'Draft')->count()
            .'. Sent: '.$rows->where('status', 'Sent')->count()
            .'. Read: '.$rows->where('status', 'Read')->count().'.';

        return $this->pack(
            'Report generation',
            $numerator.' of '.$denominator.' '.$reportWord,
            $this->rate($numerator, $denominator),
            $detail,
            $basis
        );
    }

    private function eggStockSentence(): string
    {
        [$start, $end] = Production::stockWindow();
        $select = implode(', ', array_map(
            static fn (string $column): string => "COALESCE(SUM({$column}), 0) as {$column}",
            array_values(Production::EGG_SIZE_COLUMNS)
        ));
        $harvestedRow = Production::query()
            ->whereDate('production_date', '>=', $start)
            ->whereDate('production_date', '<=', $end)
            ->selectRaw($select)
            ->first();

        $reserved = DB::table('dispatches')
            ->join('dispatch_items', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->whereDate('dispatches.dispatch_date', '>=', $start)
            ->whereDate('dispatches.dispatch_date', '<=', $end)
            ->select('dispatch_items.size')
            ->selectRaw('COALESCE(SUM(dispatch_items.quantity), 0) as trays')
            ->groupBy('dispatch_items.size')
            ->pluck('trays', 'size');

        $harvested = 0;
        $reservedEggs = 0;
        foreach (Production::EGG_SIZE_COLUMNS as $size => $column) {
            $harvested += (int) ($harvestedRow->{$column} ?? 0);
            $reservedEggs += (int) ($reserved[$size] ?? 0) * Production::EGGS_PER_TRAY;
        }

        return 'Egg stock on hand is '.number_format(Production::leftoverStock()).' eggs: '
            .number_format($harvested).' harvested from '
            .Carbon::parse($start)->format('M j, Y').' to '
            .Carbon::parse($end)->format('M j, Y')
            .', minus '.number_format($reservedEggs)
            .' eggs reserved by dispatch. Each size is counted on its own and cannot go below zero.';
    }

    private function entryBalances(InventoryItemHistory $row): bool
    {
        if ($row->stock_before === null || $row->stock_after === null) {
            return false;
        }

        $expected = (float) $row->stock_before + (float) $row->quantity_change;

        return abs((float) $row->stock_after - $expected) < 0.01;
    }

    /**
     * @param  list<string>  $names
     */
    private function nameList(array $names): string
    {
        $names = array_values($names);
        $count = count($names);

        if ($count <= 1) {
            return $names[0] ?? '';
        }

        if ($count === 2) {
            return $names[0].' and '.$names[1];
        }

        $last = array_pop($names);

        return implode(', ', $names).', and '.$last;
    }

    private function inclusiveDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $days++;
            $cursor->addDay();
        }

        return $days;
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round($numerator / $denominator * 100, 1);
    }

    private function formatRate(float $rate): string
    {
        $rounded = round($rate, 1);
        $label = abs($rounded - round($rounded)) < 0.001
            ? number_format($rounded, 0)
            : number_format($rounded, 1);

        return $label.'%';
    }

    /**
     * @return array{title: string, headline: string, rate: float|null, rate_label: string|null, detail: string, basis: string}
     */
    private function pack(string $title, string $headline, ?float $rate, string $detail, string $basis): array
    {
        return [
            'title' => $title,
            'headline' => $headline,
            'rate' => $rate,
            'rate_label' => $rate === null ? null : $this->formatRate($rate),
            'detail' => $detail,
            'basis' => $basis,
        ];
    }
}
