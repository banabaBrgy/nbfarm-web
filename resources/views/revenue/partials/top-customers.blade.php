<div class="top-customers-card">
    <div class="top-customers-header">
        <h3>Top Customers</h3>
        <span class="top-customers-sub">Ranked by total purchases</span>
    </div>

    <ul class="top-customers-list">
        @forelse($topCustomers as $index => $customer)
            <li class="top-customer-item">
                <div class="rank-badge rank-{{ $index + 1 }}">{{ $index + 1 }}</div>
                <div class="customer-info">
                    <span class="customer-name">{{ $customer->customer }}</span>
                    <span class="customer-sub">{{ $customer->purchase_count }} transaction{{ $customer->purchase_count > 1 ? 's' : '' }}</span>
                </div>
                <div class="customer-total">₱{{ number_format($customer->total_spent, 2) }}</div>
            </li>
        @empty
            <li class="top-customer-empty">No transactions yet.</li>
        @endforelse
    </ul>
</div>