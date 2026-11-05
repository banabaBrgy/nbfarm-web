<form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search item..." value="{{ $search }}">

    <select name="status" data-auto-submit aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="LOW" {{ $statusFilter === 'LOW' ? 'selected' : '' }}>Low</option>
        <option value="WATCH" {{ $statusFilter === 'WATCH' ? 'selected' : '' }}>Watch</option>
        <option value="OK" {{ $statusFilter === 'OK' ? 'selected' : '' }}>OK</option>
    </select>
</form>
