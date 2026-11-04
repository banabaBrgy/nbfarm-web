@extends('layouts.sidebar')

@section('title', 'Batch Identification')
@section('page-label', 'Batch Identification')

@push('styles')
<link rel="stylesheet" href="/css/qr-print.css">
@endpush

@section('content')
@php
    $role = \App\Models\User::routeRole();
    $printRoute = $role . '.qr.print';
    $filterQuery = array_filter([
        'egg_size' => $eggSize,
        'batch_id' => $batchId,
    ], fn ($value) => $value !== null && $value !== '');
    $totalLabels = $records->count();
    $totalGroups = $groups->count();
@endphp

<div class="qr-print-page">

    @if(session('success'))
        <div class="qr-alert no-print">{{ session('success') }}</div>
    @endif

    <div class="qr-print-toolbar no-print">
        <a href="{{ route($role . '.production') }}" class="qr-back">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
    </div>

    <div class="qr-print-header no-print">
        <div class="qr-print-header-copy">
            <h1>Batch Identification</h1>
            <p>
                @if($totalLabels > 0)
                    {{ number_format($totalGroups) }} size group{{ $totalGroups === 1 ? '' : 's' }}
                    · {{ number_format($totalLabels) }} QR code{{ $totalLabels === 1 ? '' : 's' }}
                    (10 per egg size per batch).
                @else
                    Generate QR codes from Production to identify trays by batch and egg size.
                @endif
            </p>
        </div>

        <div class="qr-print-actions">
            @if($printAll)
                <a href="{{ route($printRoute, $filterQuery) }}" class="qr-btn qr-btn-ghost">
                    <i class="fas fa-th-large"></i>
                    View screen
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
                <a href="{{ route($printRoute, array_merge($filterQuery, ['all' => 1, 'autoprint' => 1])) }}" class="qr-btn qr-btn-print">
                    <i class="fas fa-print"></i>
                    Print all
                </a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route($printRoute) }}" class="qr-filter-bar no-print">
        @if($printAll)
            <input type="hidden" name="all" value="1">
        @endif

        <div class="qr-filter-group">
            <label for="egg_size">Egg size</label>
            <select id="egg_size" name="egg_size" class="qr-filter-select" onchange="this.form.submit()">
                <option value="">All sizes</option>
                @foreach($sizes as $size)
                    <option value="{{ $size }}" @selected($eggSize === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="qr-filter-group">
            <label for="batch_id">Batch</label>
            <select id="batch_id" name="batch_id" class="qr-filter-select" onchange="this.form.submit()">
                <option value="">All batches</option>
                @foreach($batches as $id)
                    <option value="{{ $id }}" @selected($batchId === $id)>Batch {{ $id }}</option>
                @endforeach
            </select>
        </div>

        @if($eggSize || $batchId)
            <a href="{{ route($printRoute, $printAll ? ['all' => 1] : []) }}" class="qr-filter-clear">
                Clear filters
            </a>
        @endif
    </form>

    @if($groups->isEmpty())
        <div class="qr-empty no-print">
            <i class="fas fa-qrcode"></i>
            <p>No QR labels match this filter</p>
            <span>Generate QR codes from Production, or clear the filters above.</span>
        </div>
    @else
        <div class="qr-batch-groups">
            @foreach($groups as $group)
                <section class="qr-batch-group">
                    <header class="qr-batch-group-header">
                        <div>
                            <h2>{{ $group['title'] }}</h2>
                            <p>{{ $group['records']->count() }} trays / {{ $group['records']->count() }} QR Codes</p>
                        </div>
                        <span class="qr-size qr-size-{{ \Illuminate\Support\Str::slug($group['egg_size']) }}">
                            {{ $group['egg_size'] }}
                        </span>
                    </header>

                    <div class="qr-label-grid">
                        @foreach($group['records'] as $record)
                            <article class="qr-label">
                                <div class="qr-label-brand">
                                    <i class="fas fa-leaf"></i>
                                    NB Poultry Farm
                                </div>

                                <div class="qr-label-body">
                                    <div class="qr-label-image">
                                        {!! QrCode::size(140)->margin(1)->generate($record->qr_code) !!}
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
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
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
