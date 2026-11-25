
@extends('layouts.sidebar')

@section('title', 'Scanned QR Overview')
@section('page-label', 'Scanned QR Overview')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/qr-print.css') }}">
@endpush

@section('content')
@php
    $role = \App\Models\User::routeRole();
    $overviewRoute = $role . '.qr.overview';
    $hasFilters = $search !== '' || $eggSize || $batchId;
@endphp

<div class="qr-print-page">
    <div class="page-header">
        <div>
            <h2>Scanned QR Overview</h2>
            <p>Today’s saved tally for each size QR — trays and eggs after Confirm &amp; Save. Pending review lists are not counted yet.</p>
        </div>
        <a href="{{ route($role . '.production') }}" class="scan-back-button">
            <i class="fas fa-arrow-left"></i>
            Back to production
        </a>
    </div>

    <form method="GET" action="{{ route($overviewRoute) }}" class="qr-filter-bar" id="qrOverviewFilterForm">
        <div class="qr-filter-group qr-filter-group--search">
            <label for="qr-overview-search">Search</label>
            <input
                id="qr-overview-search"
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search QR code"
                class="qr-filter-input"
                maxlength="80">
        </div>

        <div class="qr-filter-group">
            <label for="egg_size">By size</label>
            <select id="egg_size" name="egg_size" class="qr-filter-select" aria-label="By size">
                <option value="">All sizes</option>
                @foreach($sizes as $size)
                    <option value="{{ $size }}" @selected($eggSize === $size)>
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="qr-filter-group">
            <label for="batch_id">By batch</label>
            <select id="batch_id" name="batch_id" class="qr-filter-select" aria-label="By batch">
                <option value="">All batches</option>
                @foreach($batches as $id)
                    <option value="{{ $id }}" @selected($batchId === $id)>
                        Batch {{ $id }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="qr-overview-table-wrap">
        <table class="qr-overview-table">
            <colgroup>
                <col class="qr-overview-col-code">
                <col class="qr-overview-col-batch">
                <col class="qr-overview-col-size">
                <col class="qr-overview-col-trays">
                <col class="qr-overview-col-eggs">
            </colgroup>
            <thead>
                <tr>
                    <th>QR Code</th>
                    <th>Batch</th>
                    <th>Size</th>
                    <th>Trays today</th>
                    <th>Eggs today</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['qr_code'] }}</td>
                        <td>{{ $row['batch_id'] }}</td>
                        <td>{{ $row['egg_size'] }}</td>
                        <td>{{ number_format($row['trays']) }}</td>
                        <td>{{ number_format($row['eggs']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="qr-overview-empty">
                            @if($hasFilters)
                                No QR records match this search.
                            @else
                                No size QR codes yet. Generate one QR per size from Production.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="details-pagination">
            <div class="details-pagination-info">
                Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}
            </div>
            <div class="details-pagination-controls">
                @if ($rows->onFirstPage())
                    <span class="details-page-btn disabled">Prev</span>
                @else
                    <a href="{{ $rows->previousPageUrl() }}" class="details-page-btn">Prev</a>
                @endif

                <span class="details-page-number">
                    Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
                </span>

                @if ($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}" class="details-page-btn">Next</a>
                @else
                    <span class="details-page-btn disabled">Next</span>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('qrOverviewFilterForm');
    const searchInput = document.getElementById('qr-overview-search');
    const sizeFilter = document.getElementById('egg_size');
    const batchFilter = document.getElementById('batch_id');

    if (!form) return;


    // Automatically update when selecting egg size.
    sizeFilter.addEventListener('change', function () {
        form.requestSubmit();
    });

    // Automatically update when selecting batch.
    batchFilter.addEventListener('change', function () {
        form.requestSubmit();
    });

    // Search only when Enter is pressed.
searchInput.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        form.requestSubmit();
    }
}); 
// Reset filters and search when the browser reload button is used.
const navigation = performance.getEntriesByType('navigation')[0];

if (navigation && navigation.type === 'reload') {
    const url = new URL(window.location.href);

    if (url.search) {
        url.search = '';
        window.location.replace(url.toString());
    }
}
});
</script>
@endpush
