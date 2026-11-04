@php
    $range = $dateFilters['range'] ?? 'all';
@endphp
<form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search item..." value="{{ $search }}">

    <select name="status" data-auto-submit aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="LOW" {{ $statusFilter === 'LOW' ? 'selected' : '' }}>Low</option>
        <option value="WATCH" {{ $statusFilter === 'WATCH' ? 'selected' : '' }}>Watch</option>
        <option value="OK" {{ $statusFilter === 'OK' ? 'selected' : '' }}>OK</option>
    </select>

    <select name="range" data-auto-submit aria-label="Inventory date range">
        <option value="all" @selected($range === 'all')>All dates</option>
        <option value="daily" @selected($range === 'daily')>Daily</option>
        <option value="weekly" @selected($range === 'weekly')>Weekly</option>
        <option value="monthly" @selected($range === 'monthly')>Monthly</option>
        <option value="custom" @selected($range === 'custom')>Custom Date Range</option>
    </select>

    @if($range === 'custom')
        <input type="date" name="from" value="{{ $dateFilters['from'] }}" aria-label="Start date">
        <input type="date" name="to" value="{{ $dateFilters['to'] }}" aria-label="End date">
        <button type="submit" class="filter-apply">Apply</button>
    @endif

    <input type="hidden" name="year" value="{{ $selectedYear }}">
    <input type="hidden" name="month" value="{{ $selectedMonth }}">
</form>
