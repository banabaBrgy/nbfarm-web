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
            <p>Today’s QR codes, with batch, size, quantity, and whether each one is scanned or still available.</p>
        </div>
        <a href="{{ route($role . '.production') }}" class="scan-back-button">
            <i class="fas fa-arrow-left"></i>
            Back to production
        </a>
    </div>

    <form method="GET" action="{{ route($overviewRoute) }}" class="qr-filter-bar">
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
                    <option value="{{ $size }}" @selected($eggSize === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="qr-filter-group">
            <label for="batch_id">By batch</label>
            <select id="batch_id" name="batch_id" class="qr-filter-select" aria-label="By batch">
                <option value="">All batches</option>
                @foreach($batches as $id)
                    <option value="{{ $id }}" @selected($batchId === $id)>Batch {{ $id }}</option>
                @endforeach
            </select>
        </div>

        <div class="qr-filter-actions">
            <button type="submit" class="qr-btn qr-btn-print">
                <i class="fas fa-search"></i>
                Search
            </button>

            @if($hasFilters)
                <a href="{{ route($overviewRoute) }}" class="qr-btn qr-btn-ghost">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <div class="qr-overview-table-wrap">
        <table class="qr-overview-table">
            <colgroup>
                <col class="qr-overview-col-code">
                <col class="qr-overview-col-batch">
                <col class="qr-overview-col-size">
                <col class="qr-overview-col-qty">
                <col class="qr-overview-col-status">
            </colgroup>
            <thead>
                <tr>
                    <th>QR Code</th>
                    <th>Batch ID</th>
                    <th>Size</th>
                    <th>Quantity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['qr_code'] }}</td>
                        <td>{{ $row['batch_id'] }}</td>
                        <td>{{ $row['egg_size'] }}</td>
                        <td>{{ number_format($row['quantity']) }}</td>
                        <td>
                            <span class="qr-overview-status qr-overview-status--{{ strtolower($row['status']) }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="qr-overview-empty">
                            @if($hasFilters)
                                No QR records match this search.
                            @else
                                No QR records yet. Generate QR codes from Production.
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
