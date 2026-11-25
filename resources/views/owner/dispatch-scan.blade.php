@extends('layouts.sidebar')

@section('title', 'Scan for Dispatch')
@section('page-label', 'Dispatch Scan')

@push('late-styles')
<link rel="stylesheet" href="{{ versioned_asset('css/dispatch-scan.css') }}">
@endpush

@section('content')
@php
    $stockBySize = $stockBySize ?? [];
    $pendingOrders = $pendingOrders ?? [];
    $sizeIcons = [
        'Small' => 'fa-egg',
        'Medium' => 'fa-egg',
        'Large' => 'fa-egg',
        'Extra Large' => 'fa-egg',
    ];
@endphp

<div class="dispatch-scan-page"
     data-resolve-url="{{ $resolveUrl }}"
     data-apply-url="{{ $applyUrl }}"
     data-csrf="{{ csrf_token() }}">
    <div class="dispatch-scan-header">
        <div class="dispatch-scan-heading">
            <span class="dispatch-scan-heading-icon" aria-hidden="true">
                <i class="fas fa-qrcode"></i>
            </span>
            <div>
                <h2>Scan for Dispatch</h2>
                <p>Scan a size QR, enter trays, then attach them to a Pending customer order. Stock deducts on scan. Full orders become On Transit.</p>
            </div>
        </div>
        <a href="{{ $dispatchUrl }}" class="dispatch-scan-back">
            <i class="fas fa-arrow-left"></i>
            Back to Dispatch
        </a>
    </div>

    <section class="dispatch-scan-stock" aria-label="Egg stock by size" id="stockCards">
        @foreach($stockBySize as $size => $stock)
            <article class="dispatch-scan-stock-card" data-size="{{ $size }}">
                <span class="dispatch-scan-stock-icon" aria-hidden="true">
                    <i class="fas {{ $sizeIcons[$size] ?? 'fa-egg' }}"></i>
                </span>
                <div>
                    <div class="label">{{ $size }}</div>
                    <div class="trays"><span class="tray-count">{{ number_format($stock['trays']) }}</span> trays</div>
                    <div class="eggs"><span class="egg-count">{{ number_format($stock['eggs']) }}</span> eggs on hand</div>
                </div>
            </article>
        @endforeach
    </section>

    <div class="dispatch-scan-grid">
        <section class="dispatch-scan-card" aria-labelledby="scanner-title">
            <div class="dispatch-scan-card-header">
                <div class="dispatch-scan-card-title">
                    <span class="dispatch-scan-card-title-icon" aria-hidden="true">
                        <i class="fas fa-camera"></i>
                    </span>
                    <h3 id="scanner-title">Camera Scanner</h3>
                </div>
                <span class="dispatch-scan-status" id="scanStatus">Ready to scan</span>
            </div>

            <div class="dispatch-scan-viewfinder" id="scanViewfinder">
                <div id="dispatchReader"></div>
            </div>
            <div id="qrFileReader" class="qr-file-reader" aria-hidden="true"></div>

            <div class="dispatch-scan-actions">
                <button type="button" class="dispatch-scan-primary" id="startCameraBtn">
                    <i class="fas fa-play" aria-hidden="true"></i>
                    <span id="cameraBtnLabel">Start Camera</span>
                </button>
                <span class="dispatch-scan-upload-or">or</span>
                <button type="button" class="dispatch-scan-upload" id="uploadQrBtn">
                    <i class="fas fa-image" aria-hidden="true"></i>
                    Upload QR
                </button>
                <input type="file" id="qrFileInput" accept="image/*" hidden>
            </div>
            <p class="dispatch-scan-hint">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>One reusable QR per size. After scanning, enter how many trays to load onto the selected Pending order.</span>
            </p>
        </section>

        <section class="dispatch-scan-card" aria-labelledby="attach-title">
            <div class="dispatch-scan-card-header">
                <div class="dispatch-scan-card-title">
                    <span class="dispatch-scan-card-title-icon" aria-hidden="true">
                        <i class="fas fa-truck"></i>
                    </span>
                    <h3 id="attach-title">Attach to Pending Order</h3>
                </div>
            </div>

            <form class="dispatch-scan-form" id="dispatchScanForm">
                <div class="dispatch-scan-meta" id="scanMeta">
                    <div class="dispatch-scan-meta-row">
                        <i class="fas fa-qrcode" aria-hidden="true"></i>
                        <span>QR</span>
                        <strong id="metaCode">Not scanned</strong>
                    </div>
                    <div class="dispatch-scan-meta-row">
                        <i class="fas fa-tag" aria-hidden="true"></i>
                        <span>Size</span>
                        <strong id="metaSize">Not scanned</strong>
                    </div>
                    <div class="dispatch-scan-meta-row">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                        <span>Batch</span>
                        <strong id="metaBatch">Not scanned</strong>
                    </div>
                </div>

                <label>
                    Trays to dispatch
                    <input type="number" id="trayInput" name="trays" min="1" max="999" value="1" required disabled>
                </label>

                <label>
                    Pending customer order
                    <select id="orderSelect" name="dispatch_id" required disabled>
                        <option value="">Select a Pending order</option>
                        @foreach($pendingOrders as $order)
                            <option value="{{ $order['id'] }}"
                                data-sizes="{{ e(json_encode($order['items'])) }}">
                                {{ $order['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" id="applyScanBtn" disabled>Add trays to order</button>
                <p class="dispatch-scan-message" id="applyMessage"></p>
            </form>

            <div class="dispatch-scan-orders-title">Pending orders</div>
            <ul class="dispatch-scan-orders" id="pendingList">
                @forelse($pendingOrders as $order)
                    <li class="dispatch-scan-order" data-id="{{ $order['id'] }}">
                        <div class="dispatch-scan-order-name">{{ $order['recipient'] }}</div>
                        <div class="dispatch-scan-order-meta">{{ $order['label'] }}</div>
                    </li>
                @empty
                    <li class="dispatch-scan-empty">No Pending orders yet. Ask Manager to create one first.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('js/html5-qrcode.min.js') }}"></script>
<script src="{{ versioned_asset('js/qr-camera.js') }}"></script>
<script src="{{ versioned_asset('js/farm-toast.js') }}"></script>
<script>
(() => {
    const root = document.querySelector('.dispatch-scan-page');
    if (!root) return;

    const notify = (message, type) => {
        if (window.nbToast) {
            window.nbToast(message, type);
        }
    };

    if (!window.NbFarmQr || !NbFarmQr.libraryReady()) {
        const statusEl = document.getElementById('scanStatus');
        if (statusEl) {
            statusEl.textContent = 'Scanner failed to load. Refresh the page.';
            statusEl.classList.add('is-error');
        }
        window.addEventListener('load', () => notify('Scanner failed to load. Refresh the page.', 'error'));
        return;
    }

    const resolveUrl = root.dataset.resolveUrl;
    const applyUrl = root.dataset.applyUrl;
    const csrf = root.dataset.csrf;

    const statusEl = document.getElementById('scanStatus');
    const applyMessage = document.getElementById('applyMessage');
    const trayInput = document.getElementById('trayInput');
    const orderSelect = document.getElementById('orderSelect');
    const applyBtn = document.getElementById('applyScanBtn');
    const form = document.getElementById('dispatchScanForm');
    const viewfinder = document.getElementById('scanViewfinder');
    const cameraBtn = document.getElementById('startCameraBtn');
    const cameraBtnLabel = document.getElementById('cameraBtnLabel');
    const pendingList = document.getElementById('pendingList');

    let currentQr = null;
    let cameraScanner = null;
    let scanning = false;
    let lastCode = '';
    let lastAt = 0;

    const setStatus = (text, ok = null) => {
        statusEl.textContent = text;
        statusEl.classList.toggle('is-error', ok === false);
        statusEl.classList.toggle('is-ok', ok === true);
    };

    const setApplyMessage = (text, ok = null) => {
        applyMessage.textContent = text;
        applyMessage.classList.toggle('is-error', ok === false);
        applyMessage.classList.toggle('is-ok', ok === true);
    };

    const setCameraLive = (live) => {
        scanning = live;
        viewfinder.classList.toggle('is-live', live);
        cameraBtn.classList.toggle('is-live', live);
        cameraBtnLabel.textContent = live ? 'Stop Camera' : 'Start Camera';
        cameraBtn.querySelector('i').className = live ? 'fas fa-stop' : 'fas fa-play';
    };

    const renderStock = (stockBySize) => {
        Object.keys(stockBySize || {}).forEach((size) => {
            const card = document.querySelector(`.dispatch-scan-stock-card[data-size="${size}"]`);
            if (!card) return;
            card.querySelector('.tray-count').textContent = Number(stockBySize[size].trays || 0).toLocaleString();
            card.querySelector('.egg-count').textContent = Number(stockBySize[size].eggs || 0).toLocaleString();
        });
    };

    const renderOrders = (orders) => {
        const selected = orderSelect.value;
        orderSelect.innerHTML = '<option value="">Select a Pending order</option>';
        pendingList.innerHTML = '';

        if (!orders || !orders.length) {
            pendingList.innerHTML = '<li class="dispatch-scan-empty">No Pending orders yet. Ask Manager to create one first.</li>';
            return;
        }

        orders.forEach((order) => {
            const option = document.createElement('option');
            option.value = String(order.id);
            option.textContent = order.label;
            option.dataset.sizes = JSON.stringify(order.items || []);
            if (String(order.id) === selected) option.selected = true;
            orderSelect.appendChild(option);

            const li = document.createElement('li');
            li.className = 'dispatch-scan-order' + (String(order.id) === selected ? ' active' : '');
            li.dataset.id = String(order.id);
            li.innerHTML = '<div class="dispatch-scan-order-name"></div><div class="dispatch-scan-order-meta"></div>';
            li.querySelector('.dispatch-scan-order-name').textContent = order.recipient || order.label;
            li.querySelector('.dispatch-scan-order-meta').textContent = order.label;
            pendingList.appendChild(li);
        });
    };

    const enableForm = (enabled) => {
        trayInput.disabled = !enabled;
        orderSelect.disabled = !enabled;
        applyBtn.disabled = !enabled;
    };

    const showQr = (qr) => {
        currentQr = qr;
        document.getElementById('metaCode').textContent = qr.qr_code;
        document.getElementById('metaSize').textContent = qr.size;
        document.getElementById('metaBatch').textContent = qr.batch_id ?? '—';
        trayInput.value = Math.max(1, Number(qr.trays_per_scan || 1));
        enableForm(true);
        filterOrdersForSize(qr.size);
    };

    const filterOrdersForSize = (size) => {
        Array.from(orderSelect.options).forEach((option, index) => {
            if (index === 0) return;
            try {
                const items = JSON.parse(option.dataset.sizes || '[]');
                const match = items.find((item) => item.size === size && Number(item.remaining) > 0);
                option.hidden = !match;
                if (!match && option.selected) orderSelect.value = '';
            } catch (e) {
                option.hidden = false;
            }
        });
        syncActiveOrder();
    };

    const syncActiveOrder = () => {
        const id = orderSelect.value;
        pendingList.querySelectorAll('.dispatch-scan-order').forEach((li) => {
            li.classList.toggle('active', li.dataset.id === id);
        });
    };

    const resolveCode = async (code) => {
        const now = Date.now();
        if (code === lastCode && now - lastAt < 2500) return;
        lastCode = code;
        lastAt = now;

        setStatus('Resolving QR…');
        try {
            const response = await fetch(resolveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ qr_code: code }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) {
                enableForm(false);
                currentQr = null;
                const message = data.message || 'Invalid QR code.';
                setStatus(message, false);
                notify(message, 'error');
                return;
            }
            showQr(data.qr);
            renderStock(data.page.stockBySize);
            renderOrders(data.page.pendingOrders);
            filterOrdersForSize(data.qr.size);
            setStatus('Scanned ' + data.qr.size, true);
            notify('Scanned ' + data.qr.size, 'success');
        } catch (error) {
            setStatus('Could not verify that QR code.', false);
            notify('Could not verify that QR code.', 'error');
        }
    };

    const onScanSuccess = (decodedText) => {
        resolveCode(String(decodedText || '').trim());
    };

    const stopCamera = async () => {
        if (cameraScanner && scanning) {
            await NbFarmQr.resetScanner(cameraScanner);
            cameraScanner = null;
            setCameraLive(false);
            setStatus('Camera stopped.');
        }
    };

    cameraBtn.addEventListener('click', async () => {
        try {
            if (scanning) {
                await stopCamera();
                return;
            }
            cameraBtn.disabled = true;
            setCameraLive(true);
            setStatus('Starting camera…');
            cameraScanner = await NbFarmQr.start('dispatchReader', onScanSuccess, cameraScanner);
            setStatus('Scanning… hold the QR in view', true);
        } catch (error) {
            await NbFarmQr.resetScanner(cameraScanner);
            cameraScanner = null;
            setCameraLive(false);
            setStatus('Could not start camera. Try Upload QR instead.', false);
            notify('Could not start camera. Try Upload QR instead.', 'error');
        } finally {
            cameraBtn.disabled = false;
        }
    });

    document.getElementById('uploadQrBtn').addEventListener('click', () => {
        document.getElementById('qrFileInput').click();
    });

    document.getElementById('qrFileInput').addEventListener('change', async (event) => {
        const file = event.target.files && event.target.files[0];
        if (!file) return;
        try {
            const decoded = await NbFarmQr.scanFile('qrFileReader', file);
            await resolveCode(String(decoded || '').trim());
        } catch (error) {
            setStatus('Could not read QR from image.', false);
            notify('Could not read QR from image.', 'error');
        } finally {
            event.target.value = '';
        }
    });

    orderSelect.addEventListener('change', syncActiveOrder);

    pendingList.addEventListener('click', (event) => {
        const item = event.target.closest('.dispatch-scan-order');
        if (!item || orderSelect.disabled) return;
        const option = Array.from(orderSelect.options).find((row) => row.value === item.dataset.id && !row.hidden);
        if (!option) return;
        orderSelect.value = option.value;
        syncActiveOrder();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentQr) {
            setApplyMessage('Scan a QR code first.', false);
            notify('Scan a QR code first.', 'warning');
            return;
        }
        if (!orderSelect.value) {
            setApplyMessage('Select a Pending order.', false);
            notify('Select a Pending order.', 'warning');
            return;
        }
        const trays = Number(trayInput.value);
        if (!Number.isInteger(trays) || trays < 1 || trays > 999) {
            setApplyMessage('Enter 1 to 999 trays.', false);
            notify('Enter 1 to 999 trays.', 'warning');
            return;
        }
        setApplyMessage('Saving…');
        try {
            const response = await fetch(applyUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    qr_code: currentQr.qr_code,
                    dispatch_id: Number(orderSelect.value),
                    trays,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (data.page) {
                renderStock(data.page.stockBySize);
                renderOrders(data.page.pendingOrders);
                if (currentQr) filterOrdersForSize(currentQr.size);
            }
            if (!response.ok || !data.ok) {
                const message = data.message || 'Could not apply scan.';
                setApplyMessage(message, false);
                notify(message, 'error');
                return;
            }
            setApplyMessage(data.result.message, true);
            notify(data.result.message, 'success');
            if (data.result.completed) {
                enableForm(false);
                currentQr = null;
                document.getElementById('metaCode').textContent = 'Not scanned';
                document.getElementById('metaSize').textContent = 'Not scanned';
                document.getElementById('metaBatch').textContent = 'Not scanned';
            }
        } catch (error) {
            setApplyMessage('Could not save that scan.', false);
            notify('Could not save that scan.', 'error');
        }
    });
})();
</script>
@endpush
