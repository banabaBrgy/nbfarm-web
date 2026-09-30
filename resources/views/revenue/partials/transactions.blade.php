<div class="transactions-card">

 <form method="GET" action="{{ route(auth()->user()->role . '.revenue') }}" class="transactions-header-row">
    <div class="transactions-title">Transactions</div>

    <div class="transactions-controls">
      <div class="search-bar">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search customer..." />
      </div>

      <select name="sort" class="sort-select" data-auto-submit>
        <option value="date_desc" {{ ($filters['sort'] ?? 'date_desc') === 'date_desc' ? 'selected' : '' }}>Newest First</option>
        <option value="date_asc" {{ ($filters['sort'] ?? '') === 'date_asc' ? 'selected' : '' }}>Oldest First</option>
        <option value="name_asc" {{ ($filters['sort'] ?? '') === 'name_asc' ? 'selected' : '' }}>Customer A–Z</option>
        <option value="name_desc" {{ ($filters['sort'] ?? '') === 'name_desc' ? 'selected' : '' }}>Customer Z–A</option>
      </select>
    </div>
  </form>

  <div class="transactions-table-wrap">
    <table class="transactions-table">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Qty</th>
          <th>Price</th>
          <th>Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="transactionsBody">
        @forelse ($transactions as $t)
          <tr>
            <td class="tx-customer">{{ $t->customer }}</td>
            <td>{{ $t->quantity }}</td>
            <td>₱{{ number_format($t->price, 2) }}</td>
            <td>{{ $t->transaction_date->format('M j, Y') }}</td>
            <td><span class="status-pill status-{{ strtolower($t->status) }}">{{ $t->status }}</span></td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="transactions-empty">No transactions found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="transactions-pagination">
    <div class="pagination-info">
      Showing {{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }}
    </div>
    <div class="pagination-controls">
      @if ($transactions->onFirstPage())
        <span class="page-btn disabled">Prev</span>
      @else
        <a href="{{ $transactions->previousPageUrl() }}" class="page-btn">Prev</a>
      @endif

      <span class="page-current">Page {{ $transactions->currentPage() }} of {{ max($transactions->lastPage(), 1) }}</span>

      @if ($transactions->hasMorePages())
        <a href="{{ $transactions->nextPageUrl() }}" class="page-btn">Next</a>
      @else
        <span class="page-btn disabled">Next</span>
      @endif
    </div>
  </div>

</div>