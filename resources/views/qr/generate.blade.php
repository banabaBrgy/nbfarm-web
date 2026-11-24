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
        ? number_format($trayCount * $eggsPerTray).' eggs'
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
                        <span class="batch-qr-scan" aria-hidden="true"></span>
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
                <span class="batch-qr-form-head-icon" aria-hidden="true"><i class="fas fa-circle-info"></i></span>
                <span>Batch Details</span>
            </header>

            <div class="batch-qr-fields">
                <div class="batch-qr-field">
                    <label for="batch_id">Batch No</label>
                    <div class="batch-qr-select" data-select-hint="Choose batch">
                        <select id="batch_id" name="batch_id" required>
                            @foreach($batches as $id)
                                <option value="{{ $id }}" @selected($selectedBatch === (int) $id)>B{{ $id }} – Batch {{ $id }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @error('batch_id')
                    <p class="batch-qr-error">{{ $message }}</p>
                @enderror

                <div class="batch-qr-field">
                    <label for="egg_size">Size</label>
                    <div class="batch-qr-select" data-select-hint="Choose size">
                        <select id="egg_size" name="egg_size" required>
                            @foreach($sizes as $size)
                                <option value="{{ $size }}" @selected($selectedSize === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @error('egg_size')
                    <p class="batch-qr-error">{{ $message }}</p>
                @enderror

                <div class="batch-qr-field">
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
                    <label for="quantity">Eggs</label>
                    <input
                        id="quantity"
                        type="text"
                        value="{{ $quantityLabel }}"
                        placeholder="0 eggs"
                        readonly
                        tabindex="-1">
                </div>
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

        quantity.value = (count * perTray).toLocaleString('en-US') + ' eggs';
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

    var openSelect = null;

    var closeSelect = function (wrap) {
        if (!wrap) {
            return;
        }

        wrap.classList.remove('is-open');
        var toggle = wrap.querySelector('.batch-qr-select-toggle');
        var menu = wrap.querySelector('.batch-qr-select-menu');

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }

        if (menu) {
            menu.hidden = true;
        }

        if (openSelect === wrap) {
            openSelect = null;
        }
    };

    var closeAllSelects = function () {
        form.querySelectorAll('.batch-qr-select.is-open').forEach(closeSelect);
    };

    var selectedOption = function (select) {
        return select.options[select.selectedIndex] || select.options[0];
    };

    var syncSelectUi = function (wrap) {
        var select = wrap.querySelector('select');
        var valueEl = wrap.querySelector('.batch-qr-select-value');
        var option = selectedOption(select);

        if (valueEl && option) {
            valueEl.textContent = option.textContent;
        }

        wrap.querySelectorAll('[role="option"]').forEach(function (item) {
            var isSelected = item.getAttribute('data-value') === select.value;
            item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            item.classList.toggle('is-selected', isSelected);
            item.classList.toggle('is-active', isSelected);
        });
    };

    var openSelectMenu = function (wrap) {
        if (openSelect && openSelect !== wrap) {
            closeSelect(openSelect);
        }

        var toggle = wrap.querySelector('.batch-qr-select-toggle');
        var menu = wrap.querySelector('.batch-qr-select-menu');

        var spaceBelow = window.innerHeight - wrap.getBoundingClientRect().bottom;
        wrap.classList.toggle('opens-up', spaceBelow < 240);
        wrap.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        menu.hidden = false;
        openSelect = wrap;

        var selected = menu.querySelector('[aria-selected="true"]') || menu.querySelector('[role="option"]');

        if (selected) {
            selected.classList.add('is-active');
            selected.focus();
        }
    };

    var moveActiveOption = function (wrap, direction) {
        var options = Array.prototype.slice.call(wrap.querySelectorAll('[role="option"]'));

        if (!options.length) {
            return;
        }

        var current = wrap.querySelector('[role="option"].is-active');
        var index = Math.max(0, options.indexOf(current));
        var next = options[(index + direction + options.length) % options.length];

        options.forEach(function (item) {
            item.classList.toggle('is-active', item === next);
        });

        next.focus();
    };

    var chooseOption = function (wrap, optionEl) {
        var select = wrap.querySelector('select');
        select.value = optionEl.getAttribute('data-value');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncSelectUi(wrap);
        closeSelect(wrap);
        wrap.querySelector('.batch-qr-select-toggle').focus();
    };

    form.querySelectorAll('.batch-qr-select').forEach(function (wrap) {
        var select = wrap.querySelector('select');

        if (!select || wrap.querySelector('.batch-qr-select-toggle')) {
            return;
        }

        var menuId = select.id + '_menu';
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'batch-qr-select-toggle';
        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', menuId);
        var valueEl = document.createElement('span');
        valueEl.className = 'batch-qr-select-value';
        var chevron = document.createElement('i');
        chevron.className = 'fas fa-chevron-down';
        chevron.setAttribute('aria-hidden', 'true');
        toggle.appendChild(valueEl);
        toggle.appendChild(chevron);

        var menu = document.createElement('div');
        menu.id = menuId;
        menu.className = 'batch-qr-select-menu';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;

        var hint = wrap.getAttribute('data-select-hint');

        if (hint) {
            var heading = document.createElement('div');
            heading.className = 'batch-qr-select-hint';
            heading.textContent = hint;
            menu.appendChild(heading);
        }

        Array.prototype.forEach.call(select.options, function (option) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'batch-qr-select-option';
            item.setAttribute('role', 'option');
            item.setAttribute('data-value', option.value);
            var badge = document.createElement('span');
            badge.className = 'batch-qr-select-badge';
            var optionText = option.textContent.trim();
            badge.textContent = /^\d+$/.test(option.value)
                ? 'B' + option.value
                : (optionText === 'Extra Large' ? 'XL' : (optionText.length <= 3 ? optionText : optionText.charAt(0)));

            var label = document.createElement('span');
            label.className = 'batch-qr-select-label';
            label.textContent = option.textContent;

            var check = document.createElement('i');
            check.className = 'fas fa-check';
            check.setAttribute('aria-hidden', 'true');

            item.appendChild(badge);
            item.appendChild(label);
            item.appendChild(check);
            menu.appendChild(item);
        });

        wrap.appendChild(toggle);
        wrap.appendChild(menu);
        wrap.classList.add('is-enhanced');
        select.setAttribute('tabindex', '-1');
        syncSelectUi(wrap);

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (wrap.classList.contains('is-open')) {
                closeSelect(wrap);
                return;
            }

            openSelectMenu(wrap);
        });

        toggle.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openSelectMenu(wrap);
            }
        });

        menu.addEventListener('click', function (event) {
            var optionEl = event.target.closest('[role="option"]');

            if (!optionEl) {
                return;
            }

            event.preventDefault();
            chooseOption(wrap, optionEl);
        });

        wrap.addEventListener('keydown', function (event) {
            if (!wrap.classList.contains('is-open')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeSelect(wrap);
                toggle.focus();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveActiveOption(wrap, 1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveActiveOption(wrap, -1);
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                var active = wrap.querySelector('[role="option"].is-active');

                if (active) {
                    event.preventDefault();
                    chooseOption(wrap, active);
                }
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (openSelect && !openSelect.contains(event.target)) {
            closeAllSelects();
        }
    });
});
</script>
@endpush
