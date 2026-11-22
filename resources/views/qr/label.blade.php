@extends('layouts.sidebar')

@section('title', 'Print label')
@section('page-label', 'Print label')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/qr-print.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/qr-generate.css') }}">
@endpush

@section('content')
@php
    $role = \App\Models\User::routeRole();
    $copies = max(1, min(50, (int) ($copies ?? 1)));
@endphp

<div class="batch-label-page">
    <div class="batch-label-toolbar no-print">
        <a href="{{ route($role.'.qr.create', ['record' => $record->id]) }}" class="qr-back">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
        <button type="button" class="qr-btn qr-btn-print" onclick="window.print()">
            <i class="fas fa-print"></i>
            Print {{ $copies }} {{ $copies === 1 ? 'copy' : 'copies' }}
        </button>
    </div>

    @foreach(range(1, $copies) as $copy)
        <article class="batch-label">
            <div class="batch-label-brand">
                <i class="fas fa-leaf" aria-hidden="true"></i>
                NB Poultry Farm
            </div>

            <div class="batch-label-body">
                <div class="batch-label-image">
                    {!! QrCode::size(720)->margin(1)->generate($record->qr_code) !!}
                </div>
                <p class="batch-label-code">{{ $record->qr_code }}</p>

                <dl class="batch-label-meta">
                    <div>
                        <dt>Batch</dt>
                        <dd>{{ $record->batch_id }}</dd>
                    </div>
                    <div>
                        <dt>Size</dt>
                        <dd><span class="qr-size qr-size-{{ \Illuminate\Support\Str::slug($record->egg_size) }}">{{ $record->egg_size }}</span></dd>
                    </div>
                    <div>
                        <dt>Each scan</dt>
                        <dd>{{ number_format($record->tray_count) }} trays</dd>
                    </div>
                    <div class="batch-label-meta-wide">
                        <dt>Quantity</dt>
                        <dd>{{ number_format($record->eggCount()) }} eggs</dd>
                    </div>
                </dl>
            </div>
        </article>
    @endforeach
</div>

@if(request()->boolean('print'))
<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>
@endif
@endsection
