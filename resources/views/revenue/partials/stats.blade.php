<div class="stats-grid" id="statsGrid">
  <div class="stat-card stat-card--eggs">
    <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
    <div class="stat-info">
      <span class="stat-title">Today</span>
      <h2 id="stat-today">₱{{ number_format($stats['today'], 2) }}</h2>
      <small>Today's total</small>
    </div>
  </div>
  <div class="stat-card stat-card--week">
    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
    <div class="stat-info">
      <span class="stat-title">This Week</span>
      <h2 id="stat-week">₱{{ number_format($stats['week'], 2) }}</h2>
      <small>7-day total</small>
    </div>
  </div>
  <div class="stat-card stat-card--dispatch">
    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
    <div class="stat-info">
      <span class="stat-title">This Month</span>
      <h2 id="stat-month">₱{{ number_format($stats['month'], 2) }}</h2>
      <small>{{ now()->format('F Y') }}</small>
    </div>
  </div>
  <div class="stat-card stat-card--pending">
    <div class="stat-icon"><i class="fas fa-clock"></i></div>
    <div class="stat-info">
      <span class="stat-title">Pending</span>
      <h2 id="stat-pending">₱{{ number_format($stats['pending'], 2) }}</h2>
      <small>Awaiting payment</small>
    </div>
  </div>
</div>
