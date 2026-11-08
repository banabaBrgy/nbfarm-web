@php
    $totalEggProduction = $totalEggProduction ?? 0;
    $availableEggStock = $availableEggStock ?? 0;
    $totalDispatchedEggs = $totalDispatchedEggs ?? 0;
    $totalRevenue = $totalRevenue ?? 0;
@endphp

<div class="stats-grid stats-grid--kpis">
    <div class="stat-card stat-card--eggs">
        <div class="stat-icon">
            <i class="fas fa-egg"></i>
        </div>
        <div class="stat-info">
            <span class="stat-title">Total Egg Production</span>
            <h2>{{ number_format($totalEggProduction) }}</h2>
            <small>Eggs produced</small>
        </div>
    </div>

    <div class="stat-card stat-card--inventory">
        <div class="stat-icon">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-info">
            <span class="stat-title">Available Inventory</span>
            <h2>{{ number_format($availableEggStock) }}</h2>
            <small>Available egg stocks</small>
        </div>
    </div>

    <div class="stat-card stat-card--dispatch">
        <div class="stat-icon">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-info">
            <span class="stat-title">Total Dispatch</span>
            <h2>{{ number_format($totalDispatchedEggs) }}</h2>
            <small>Dispatched eggs</small>
        </div>
    </div>

    <div class="stat-card stat-card--revenue">
        <div class="stat-icon">
            <i class="fas fa-peso-sign"></i>
        </div>
        <div class="stat-info">
            <span class="stat-title">Total Revenue</span>
            <h2>₱{{ number_format($totalRevenue, 2) }}</h2>
            <small>Recorded sales revenue</small>
        </div>
    </div>
</div>
