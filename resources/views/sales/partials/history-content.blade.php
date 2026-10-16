@php
    $range = $filters['range'] ?? 'all';
    $isModal = request()->boolean('modal');
    $historyRoute = route(
        \App\Models\User::routeRole().'.sales.history',
        $isModal ? ['modal' => 1] : []
    );
@endphp

<form method="GET" action="{{ $historyRoute }}" class="sales-history-filters">
    <label class="sales-history-field sales-history-range-field">
        <span>Date</span>
        <select name="range" data-auto-submit aria-label="Sales date filter">
            <option value="all" @selected($range === 'all')>All sales</option>
            <option value="today" @selected($range === 'today')>Today</option>
            <option value="week" @selected($range === 'week')>This week</option>
            <option value="month" @selected($range === 'month')>This month</option>
            <option value="previous" @selected($range === 'previous')>All previous months</option>
            <option value="custom" @selected($range === 'custom')>Custom range</option>
        </select>
    </label>

    @if($range === 'custom')
        <label class="sales-history-field">
            <span>From</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" aria-label="Start date">
        </label>
        <label class="sales-history-field">
            <span>To</span>
            <input type="date" name="to" value="{{ $filters['to'] }}" aria-label="End date">
        </label>
        <button type="submit" class="sales-history-apply">Apply</button>
    @endif
</form>

<div class="sales-history-card">
    <div class="sales-history-summary">
        <div>
            <span class="sales-history-summary-label">{{ $filters['label'] }}</span>
            <strong>{{ number_format($sales->total()) }} {{ $sales->total() === 1 ? 'sale' : 'sales' }}</strong>
        </div>
        <div class="sales-history-summary-total">
            <span>Total</span>
            <strong>₱{{ number_format($periodTotal, 2) }}</strong>
        </div>
    </div>

    <div class="sales-history-table-wrap">
        <table class="sales-history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="num">Qty</th>
                    <th class="num">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td data-label="Date">{{ $sale->transaction_date->format('M j, Y') }}</td>
                        <td class="sales-history-customer" data-label="Customer">{{ $sale->customer }}</td>
                        <td class="num" data-label="Qty">{{ number_format((int) $sale->quantity) }}</td>
                        <td class="num" data-label="Amount">₱{{ number_format($sale->price, 2) }}</td>
                        <td data-label="Status">
                            <span class="sales-status sales-status-{{ strtolower($sale->status) }}">{{ $sale->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="sales-history-empty">
                            @if($range === 'custom' && (empty($filters['from']) || empty($filters['to'])))
                                Choose a start and end date to review that range.
                            @else
                                No sales recorded for this period.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sales->total() > 0)
        <div class="sales-history-pagination">
            <div>Showing {{ $sales->firstItem() }}–{{ $sales->lastItem() }} of {{ $sales->total() }}</div>
            <div class="sales-history-pages">
                @if($sales->onFirstPage())
                    <span class="sales-page-btn disabled">Prev</span>
                @else
                    <a href="{{ $sales->previousPageUrl() }}" class="sales-page-btn">Prev</a>
                @endif
                <span>Page {{ $sales->currentPage() }} of {{ $sales->lastPage() }}</span>
                @if($sales->hasMorePages())
                    <a href="{{ $sales->nextPageUrl() }}" class="sales-page-btn">Next</a>
                @else
                    <span class="sales-page-btn disabled">Next</span>
                @endif
            </div>
        </div>
    @endif
</div>
