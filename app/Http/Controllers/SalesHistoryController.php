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
        $status = $this->normalizeStatus($request->query('status'));
        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));

        if ($range === 'custom' && $from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $salesQuery = Transaction::query()->forSalesRange($range, $from, $to);
        if ($status !== '') {
            $salesQuery->where('status', $status);
        }

        $sales = (clone $salesQuery)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(5)
            ->withQueryString();

        $periodTotal = (float) (clone $salesQuery)->sum('price');

        $view = $request->boolean('modal')
            ? 'sales.partials.history-content'
            : 'sales.history';

        return view($view, [
            'sales' => $sales,
            'periodTotal' => $periodTotal,
            'filters' => [
                'range' => $range,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'label' => $this->periodLabel($range, $from, $to),
            ],
        ]);
    }

    private function normalizeRange(mixed $range): string
    {
        $range = is_string($range) ? $range : 'all';

        if (in_array($range, ['all', 'today', 'week', 'month', 'previous', 'custom'], true)) {
            return $range;
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $range) === 1) {
            return $range;
        }

        return 'all';
    }

    private function normalizeStatus(mixed $status): string
    {
        $status = is_string($status) ? $status : '';

        return in_array($status, ['Paid', 'Pending'], true) ? $status : '';
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

        if ($range === 'week') {
            return 'This week · '.Carbon::today()->startOfWeek()->format('M j').'–'.Carbon::today()->endOfWeek()->format('M j, Y');
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
