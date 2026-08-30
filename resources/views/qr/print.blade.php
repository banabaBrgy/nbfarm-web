@extends('layouts.sidebar')

@section('title', 'Print QR')
@section('page-label', 'Print QR')

@push('styles')
<link rel="stylesheet" href="/css/qr-print.css">
@endpush

@section('content')
@php
    $role = \App\Models\User::routeRole();
    $printRoute = $role . '.qr.print';
    $isPaginated = !$printAll && $records instanceof \Illuminate\Pagination\AbstractPaginator;
    $total = $isPaginated ? $records->total() : $records->count();
@endphp

<div class="qr-print-page">

    @if(session('success'))
        <div class="qr-alert">{{ session('success') }}</div>
    @endif

    <div class="qr-print-toolbar no-print">
        <a href="{{ route($role . '.production') }}" class="qr-back">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
    </div>

    <div class="qr-print-header no-print">
        <div class="qr-print-header-copy">
            <h1>Print QR Labels</h1>
            <p>
                @if($printAll)
                    All {{ number_format($total) }} labels — ready to print.
                @elseif($isPaginated && $total > 0)
                    Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ number_format($total) }} labels.
                @else
                    Ready-to-print labels for each batch and egg size.
                @endif
            </p>
        </div>

        <div class="qr-print-actions">
            @if($printAll)
                <a href="{{ route($printRoute) }}" class="qr-btn qr-btn-ghost">
                    <i class="fas fa-th-large"></i>
                    View pages
                </a>
                <button type="button" onclick="window.print()" class="qr-btn qr-btn-print">
                    <i class="fas fa-print"></i>
                    Print all
                </button>
            @else
                <button type="button" onclick="window.print()" class="qr-btn qr-btn-ghost">
                    <i class="fas fa-print"></i>
                    Print page
                </button>
                <a href="{{ route($printRoute, ['all' => 1, 'autoprint' => 1]) }}" class="qr-btn qr-btn-print">
                    <i class="fas fa-print"></i>
                    Print all
                </a>
            @endif
        </div>
    </div>

    @if($records->isEmpty())
        <div class="qr-empty no-print">
            <i class="fas fa-qrcode"></i>
            <p>No QR labels yet</p>
            <span>Generate QR codes from Production to print labels here.</span>
        </div>
    @else
        <div class="qr-label-grid">
            @foreach($records as $record)
                <article class="qr-label">
                    <div class="qr-label-brand">
                        <i class="fas fa-leaf"></i>
                        NB Poultry Farm
                    </div>

                    <div class="qr-label-body">
                        <div class="qr-label-image">
                            {!! QrCode::size(168)->margin(1)->generate($record->qr_code) !!}
                        </div>

                        <div class="qr-label-code">{{ $record->qr_code }}</div>

                        <div class="qr-label-meta">
                            <div>
                                <span>Batch</span>
                                <strong>{{ $record->batch_id }}</strong>
                            </div>
                            <div>
                                <span>Size</span>
                                <strong>
                                    <span class="qr-size qr-size-{{ \Illuminate\Support\Str::slug($record->egg_size) }}">
                                        {{ $record->egg_size }}
                                    </span>
                                </strong>
                            </div>
                            <div>
                                <span>Trays</span>
                                <strong>{{ $record->tray_count }}</strong>
                            </div>
                            <div>
                                <span>Eggs</span>
                                <strong>{{ number_format($record->tray_count * $record->eggs_per_tray) }}</strong>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($isPaginated && $records->total() > 0)
            @php
                $current = $records->currentPage();
                $last = $records->lastPage();
                $pages = [];

                if ($last <= 7) {
                    $pages = range(1, $last);
                } else {
                    $pages[] = 1;
                    $start = max(2, $current - 1);
                    $end = min($last - 1, $current + 1);
                    if ($start > 2) {
                        $pages[] = '...';
                    }
                    foreach (range($start, $end) as $page) {
                        $pages[] = $page;
                    }
                    if ($end < $last - 1) {
                        $pages[] = '...';
                    }
                    $pages[] = $last;
                }
            @endphp

            <div class="qr-pagination no-print">
                <div class="qr-pagination-info">
                    Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ $records->total() }} labels
                </div>

                @if($last > 1)
                    <div class="qr-pagination-controls">
                        @if($records->onFirstPage())
                            <span class="qr-page-btn disabled">Prev</span>
                        @else
                            <a href="{{ $records->previousPageUrl() }}" class="qr-page-btn">Prev</a>
                        @endif

                        @foreach($pages as $page)
                            @if($page === '...')
                                <span class="qr-page-ellipsis">…</span>
                            @elseif($page === $current)
                                <span class="qr-page-num active">{{ $page }}</span>
                            @else
                                <a href="{{ $records->url($page) }}" class="qr-page-num">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($records->hasMorePages())
                            <a href="{{ $records->nextPageUrl() }}" class="qr-page-btn">Next</a>
                        @else
                            <span class="qr-page-btn disabled">Next</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>

@if($printAll && request()->boolean('autoprint'))
<script>
    window.addEventListener('load', function () {
        window.print();

        var url = new URL(window.location.href);
        url.searchParams.delete('autoprint');
        window.history.replaceState({}, '', url.pathname + url.search);
    });
</script>
@endif
@endsection
