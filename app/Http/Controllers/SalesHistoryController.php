<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $range = $this->normalizeRange($request->query('range'));
        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));

        if ($range === 'custom' && $from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $sales = Transaction::query()
            ->forSalesRange($range, $from, $to)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $periodTotal = (float) Transaction::query()
            ->forSalesRange($range, $from, $to)
            ->sum('price');

        return view('sales.history', [
            'sales' => $sales,
            'periodTotal' => $periodTotal,
            'filters' => [
                'range' => $range,
                'from' => $from,
                'to' => $to,
                'label' => $this->periodLabel($range, $from, $to),
            ],
        ]);
    }

    private function normalizeRange(mixed $range): string
    {
        $range = is_string($range) ? $range : 'all';

        if (in_array($range, ['all', 'today', 'month', 'previous', 'custom'], true)) {
            return $range;
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $range) === 1) {
            return $range;
        }

        return 'all';
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
        if ($range === 'today') {
            return 'Today';
        }

        if ($range === 'month') {
            return 'This month · '.Carbon::today()->format('F Y');
        }

        if ($range === 'previous') {
            return 'Previous months';
        }

        if (preg_match('/^\d{4}-\d{2}$/', $range) === 1) {
            [$year, $month] = array_map('intval', explode('-', $range));

            return Carbon::create($year, $month, 1)->format('F Y');
        }

        if ($range === 'custom' && $from && $to) {
            return Carbon::parse($from)->format('M j, Y').' – '.Carbon::parse($to)->format('M j, Y');
        }

        if ($range === 'custom') {
            return 'Choose a start and end date';
        }

        return 'All recorded sales';
    }
}
