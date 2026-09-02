<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function index(Request $request): View
{
    return view('owner.revenue', [
        'stats'         => $this->buildStats(),
        'chartData'     => Transaction::last7DaysTotals(),
        'transactions'  => $this->filteredTransactions($request),
        'topCustomers'  => $this->buildTopCustomers(),
        'filters'       => [
            'search' => $request->query('search'),
            'sort'   => $request->query('sort', 'date_desc'),
        ],
    ]);
}

private function buildTopCustomers()
{
    return Transaction::selectRaw('customer, COUNT(*) as purchase_count, SUM(price) as total_spent')
        ->groupBy('customer')
        ->orderByDesc('total_spent')
        ->limit(5)
        ->get();
}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
    'customer'         => ['required', 'string', 'max:255'],
    'transaction_date' => ['required', 'date'],
    'quantity'         => ['nullable', 'numeric', 'min:1'],
    'price'            => ['required', 'numeric', 'min:0.01'],
    'status'           => ['required', 'in:Paid,Pending,Partial'],
]);

        Transaction::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Transaction saved successfully!',
            ], 201);
        }

        return redirect()
            ->route('revenue')
            ->with('success', 'Transaction saved successfully!');
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->query('term');

        $results = Transaction::search($term)
            ->orderByDesc('transaction_date')
            ->limit(20)
            ->get(['id', 'customer', 'transaction_date', 'customer_ranking', 'price', 'status']);

        return response()->json(['results' => $results]);
    }

    private function buildStats(): array
    {
        return [
            'today'   => (float) Transaction::today()->sum('price'),
            'week'    => (float) Transaction::thisWeek()->sum('price'),
            'month'   => (float) Transaction::thisMonth()->sum('price'),
            'pending' => (float) Dispatch::query()
                ->whereIn('status', ['Pending', 'On Transit'])
                ->sum('price'),
        ];
    }

    private function filteredTransactions(Request $request)
    {
        $query = Transaction::search($request->query('search'));

        switch ($request->query('sort', 'date_desc')) {
            case 'date_asc':
                $query->orderBy('transaction_date')->orderBy('id');
                break;
            case 'name_asc':
                $query->orderBy('customer');
                break;
            case 'name_desc':
                $query->orderByDesc('customer');
                break;
            case 'date_desc':
            default:
                $query->orderByDesc('transaction_date')->orderByDesc('id');
                break;
        }

        return $query->paginate(10)->withQueryString();
    }
}