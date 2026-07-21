<div class="stats-grid" id="statsGrid">
  <div class="stat-card">
    <div class="stat-label">Today</div>
    <div class="stat-value" id="stat-today">₱{{ number_format($stats['today'], 2) }}</div>
    <div class="stat-sub">Today's total</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">This Week</div>
    <div class="stat-value" id="stat-week">₱{{ number_format($stats['week'], 2) }}</div>
    <div class="stat-sub">7-day total</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">This Month</div>
    <div class="stat-value" id="stat-month">₱{{ number_format($stats['month'], 2) }}</div>
    <div class="stat-sub">{{ now()->format('F Y') }}</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Pending</div>
    <div class="stat-value amber" id="stat-pending">₱{{ number_format($stats['pending'], 2) }}</div>
    <div class="stat-sub">Awaiting payment</div>
  </div>
</div>
