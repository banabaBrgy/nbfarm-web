@extends('layouts.sidebar')

@section('title', 'Batch QR Code')
@section('page-label', 'Batch QR Code')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/qr-print.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/qr-generate.css') }}">
@endpush

@section('content')
@php
    $role = \App\Models\User::routeRole();
    $selectedBatch = (int) old('batch_id', $batchId);
    $selectedSize = old('egg_size', $eggSize);
    $selectedTrays = old('trays', $trays);
    $selectedCopies = (int) old('copies', $copies ?? 1);
    $trayCount = is_numeric($selectedTrays) ? (int) $selectedTrays : 0;
    $quantityLabel = $trayCount > 0
        ? number_format($trayCount * $eggsPerTray).' eggs per scan'
        : '';
@endphp

<div class="batch-qr-page">
    <a href="{{ route($role.'.production') }}" class="qr-back no-print">
        <i class="fas fa-arrow-left"></i>
        Back to Production
    </a>

    <header class="batch-qr-heading">
        <span class="batch-qr-mark" aria-hidden="true"><i class="fas fa-qrcode"></i></span>
        <div>
            <h1>Batch QR Code</h1>
            <p>One QR per size per batch. Print copies and scan the same code on every matching stack.</p>
        </div>
    </header>

    @if(session('success'))
        <div class="qr-alert">{{ session('success') }}</div>
    @endif

    <div class="batch-qr-layout">
        <section class="batch-qr-preview" aria-live="polite">
            <header class="batch-qr-preview-head">
                <span>
                    <i class="fas fa-qrcode" aria-hidden="true"></i>
                    QR Code Preview
                </span>
            </header>

            <div class="batch-qr-preview-body">
                @if($record)
                    <div class="batch-qr-image">
                        {!! QrCode::size(480)->margin(1)->generate($record->qr_code) !!}
                    </div>
                @else
                    <div class="batch-qr-placeholder">
                        <i class="fas fa-qrcode" aria-hidden="true"></i>
                        <span>QR code</span>
                    </div>
                @endif

                <div class="batch-qr-copies">
                    <label for="print_copies">Print copies</label>
                    <input
                        id="print_copies"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="50"
                        step="1"
                        value="{{ $selectedCopies }}"
                        @disabled(! $record)>
                </div>

                <div class="batch-qr-actions">
                    @if($record)
                        <a href="{{ route($role.'.qr.download', $record) }}" class="batch-qr-btn batch-qr-btn-ghost">
                            <i class="fas fa-download"></i>
                            Download QR
                        </a>
                        <a
                            href="{{ route($role.'.qr.label', ['record' => $record, 'print' => 1, 'copies' => $selectedCopies]) }}"
                            class="batch-qr-btn batch-qr-btn-solid"
                            id="printLabelLink"
                            target="_blank"
                            rel="noopener">
                            <i class="fas fa-print"></i>
                            Print Label
                        </a>
                    @else
                        <button type="button" class="batch-qr-btn batch-qr-btn-ghost" disabled>
                            <i class="fas fa-download"></i>
                            Download QR
                        </button>
                        <button type="button" class="batch-qr-btn batch-qr-btn-solid" disabled>
                            <i class="fas fa-print"></i>
                            Print Label
                        </button>
                    @endif
                </div>
            </div>
        </section>

        <form
            method="POST"
            action="{{ route($role.'.qr.generate') }}"
            class="batch-qr-form"
            id="batchQrForm"
            data-eggs-per-tray="{{ $eggsPerTray }}">
            @csrf

            <header class="batch-qr-form-head">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                Batch Details
            </header>

            <div class="batch-qr-fields">
                <div class="batch-qr-field">
                    <span class="batch-qr-field-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
                    <label for="batch_id">Batch No</label>
                    <select id="batch_id" name="batch_id" required>
                        @foreach($batches as $id)
                            <option value="{{ $id }}" @selected($selectedBatch === (int) $id)>{{ $id }}</option>
                        @endforeach
                    </select>
                </div>
                @error('batch_id')
                    <p class="batch-qr-error">{{ $message }}</p>
                @enderror

                <div class="batch-qr-field">
                    <span class="batch-qr-field-icon" aria-hidden="true"><i class="fas fa-egg"></i></span>
                    <label for="quantity">Each scan</label>
                    <input
                        id="quantity"
                        type="text"
                        value="{{ $quantityLabel }}"
                        placeholder="0 eggs"
                        readonly
                        tabindex="-1">
                </div>

                <div class="batch-qr-field">
                    <span class="batch-qr-field-icon" aria-hidden="true"><i class="fas fa-border-all"></i></span>
                    <label for="trays">Trays</label>
                    <input
                        id="trays"
                        name="trays"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="999"
                        step="1"
                        value="{{ $selectedTrays }}"
                        placeholder="0"
                        required>
                </div>
                @error('trays')
                    <p class="batch-qr-error">{{ $message }}</p>
                @enderror

                <div class="batch-qr-field">
                    <span class="batch-qr-field-icon" aria-hidden="true"><i class="fas fa-tag"></i></span>
                    <label for="egg_size">Size</label>
                    <select id="egg_size" name="egg_size" required>
                        @foreach($sizes as $size)
                            <option value="{{ $size }}" @selected($selectedSize === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                @error('egg_size')
                    <p class="batch-qr-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="batch-qr-generate">
                <i class="fas fa-qrcode"></i>
                Generate
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('batchQrForm');
    var trays = document.getElementById('trays');
    var quantity = document.getElementById('quantity');
    var copies = document.getElementById('print_copies');
    var printLink = document.getElementById('printLabelLink');

    if (copies && printLink) {
        var syncCopies = function () {
            var count = parseInt(copies.value, 10);

            if (!Number.isFinite(count) || count < 1) {
                count = 1;
            }

            if (count > 50) {
                count = 50;
            }

            copies.value = String(count);

            var url = new URL(printLink.href);
            url.searchParams.set('copies', String(count));
            url.searchParams.set('print', '1');
            printLink.href = url.toString();
        };

        copies.addEventListener('change', syncCopies);
        copies.addEventListener('input', syncCopies);
        syncCopies();
    }

    if (!form || !trays || !quantity) {
        return;
    }

    var perTray = parseInt(form.getAttribute('data-eggs-per-tray'), 10) || 30;

    var refresh = function () {
        var count = parseInt(trays.value, 10);

        if (!Number.isFinite(count) || count < 1) {
            quantity.value = '';
            return;
        }

        quantity.value = (count * perTray).toLocaleString('en-US') + ' eggs per scan';
    };

    trays.addEventListener('input', refresh);
    refresh();

    form.addEventListener('submit', function () {
        var button = form.querySelector('button[type="submit"]');

        window.setTimeout(function () {
            if (button) {
                button.disabled = true;
            }
        }, 0);
    });
});
</script>
@endpush
