@extends('layouts.sidebar')

@section('title','Scan QR')

@section('content')

<div class="scan-page">
    <div class="scan-page-header">
        <div class="scan-page-heading">
            <span class="scan-page-icon" aria-hidden="true">
                <i class="fas fa-qrcode"></i>
            </span>
            <div>
                <h2>QR Code Scanner</h2>
                <p>Scan one size at a time. Tap Review List to double-check, then Confirm &amp; Save.</p>
            </div>
        </div>

        <a href="{{ $productionUrl }}" class="scan-back-button">
            <i class="fas fa-arrow-left"></i>
            Back to production
        </a>
    </div>

    <div class="scan-content-grid">
        <section class="scanner-card" aria-labelledby="scanner-title">
            <div class="scanner-card-header">
                <div class="scanner-card-title">
                    <span class="scanner-card-title-icon" aria-hidden="true">
                        <i class="fas fa-camera"></i>
                    </span>
                    <h3 id="scanner-title">Camera Scanner</h3>
                </div>
                <span class="scanner-status" id="scannerStatus">Ready to scan</span>
            </div>

            <div class="scanner-frame">
                <div class="scanner-viewfinder">
                    <div id="reader"></div>
                </div>
                <button type="button" class="scanner-toggle-btn" id="scannerToggleBtn">
                    <i class="fas fa-play" aria-hidden="true"></i>
                    Start Camera
                </button>

                <div class="scanner-upload">
                    <span class="scanner-upload-or">or</span>
                    <button type="button" class="scanner-upload-btn" id="scannerUploadBtn">
                        <i class="fas fa-image" aria-hidden="true"></i>
                        Upload QR
                    </button>
                    <input
                        type="file"
                        id="qrFileInput"
                        accept="image/*"
                        hidden>
                </div>
                <div id="qrFileReader" class="qr-file-reader" aria-hidden="true"></div>
            </div>

            <p class="scanner-instruction">
                <i class="fas fa-circle-info"></i>
                <span>Finish this size first. Then tap Review List and Confirm &amp; Save.</span>
            </p>
        </section>

        <section class="scan-result-card" aria-labelledby="result-title">
            <div class="scan-result-header">
                <div class="scanner-card-title">
                    <span class="scan-result-header-icon" aria-hidden="true">
                        <i class="fas fa-clipboard-list"></i>
                    </span>
                    <h3 id="result-title">Scan Result</h3>
                </div>
            </div>

            <div class="scan-result-body">
                <ul class="scan-result-list">
                    <li class="scan-result-row">
                        <span class="scan-result-icon" aria-hidden="true"><i class="fas fa-qrcode"></i></span>
                        <span class="scan-result-label">QR Code</span>
                        <span class="scan-result-value" id="qrCode"><span class="scan-value-muted">Not scanned</span></span>
                    </li>
                    <li class="scan-result-row">
                        <span class="scan-result-icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                        <span class="scan-result-label">Batch</span>
                        <span class="scan-result-value" id="batch"><span class="scan-value-muted">Not scanned</span></span>
                    </li>
                    <li class="scan-result-row">
                        <span class="scan-result-icon" aria-hidden="true"><i class="fas fa-tag"></i></span>
                        <span class="scan-result-label">Size</span>
                        <span class="scan-result-value" id="size"><span class="scan-value-muted">Not scanned</span></span>
                    </li>
                    <li class="scan-result-row">
                        <span class="scan-result-icon" aria-hidden="true"><i class="fas fa-table-cells"></i></span>
                        <span class="scan-result-label">Trays</span>
                        <span class="scan-result-value" id="trays"><span class="scan-value-muted">Not scanned</span></span>
                    </li>
                    <li class="scan-result-row">
                        <span class="scan-result-icon" aria-hidden="true"><i class="fas fa-egg"></i></span>
                        <span class="scan-result-label">Eggs</span>
                        <span class="scan-result-value" id="eggs"><span class="scan-value-muted">Not scanned</span></span>
                    </li>
                </ul>

                <div class="scan-result-status" id="status" hidden></div>

                <button type="button" class="scan-review-button" id="scanReviewBtn">
                    <i class="fas fa-list" aria-hidden="true"></i>
                    Review List
                    <span
                        class="scan-review-count"
                        id="scanReviewCount"
                        @if((int) ($reviewState['summary']['scans'] ?? 0) < 1) hidden @endif>
                        {{ number_format((int) ($reviewState['summary']['scans'] ?? 0)) }}
                    </span>
                </button>
            </div>
        </section>
    </div>

    @include('production.review-list')
</div>

@endsection


@push('styles')
    @vite('resources/css/scan-qr.css')
@endpush

@push('scripts')

<script src="{{ versioned_asset('js/html5-qrcode.min.js') }}"></script>
<script src="{{ versioned_asset('js/qr-camera.js') }}"></script>
<script src="{{ versioned_asset('js/farm-toast.js') }}"></script>

<script>

let scannerPaused = false;
let lastCommittedCode = '';
let lastCommittedAt = 0;
const SCAN_DEBOUNCE_MS = 2000;
const scanStoreUrl = "{{ $scanStoreUrl }}";

function escapeScanHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '{{ csrf_token() }}';
}

function muted(text) {
    return `<span class="scan-value-muted">${escapeScanHtml(text)}</span>`;
}

function formatBatch(batch) {
    const text = String(batch ?? '').trim();

    if (!text) {
        return '';
    }

    return /^b/i.test(text) ? text.toUpperCase() : `B${text}`;
}

function setStatus(html) {
    const statusEl = document.getElementById('status');

    if (!statusEl) {
        return;
    }

    if (html) {
        statusEl.innerHTML = html;
        statusEl.hidden = false;
        return;
    }

    statusEl.innerHTML = '';
    statusEl.hidden = true;
}

function fillResult(res, statusHtml) {
    document.getElementById('qrCode').innerHTML = res.qr_code
        ? escapeScanHtml(res.qr_code)
        : muted('Not scanned');
    document.getElementById('batch').innerHTML = res.batch
        ? escapeScanHtml(formatBatch(res.batch))
        : muted('Not scanned');
    document.getElementById('size').innerHTML = res.size
        ? escapeScanHtml(res.size)
        : muted('Not scanned');
    document.getElementById('trays').innerHTML = res.trays != null && res.trays !== ''
        ? escapeScanHtml(Number(res.trays).toLocaleString('en-US'))
        : muted('Not scanned');
    document.getElementById('eggs').innerHTML = res.eggs != null && res.eggs !== ''
        ? escapeScanHtml(Number(res.eggs).toLocaleString('en-US'))
        : muted('Not scanned');
    setStatus(statusHtml);
}

function jsonRequest(url, method, body) {
    const options = {
        method,
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        }
    };

    if (body !== undefined) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    return fetch(url, options).then((response) => response.json());
}

function resumeAfterDialog() {
    scannerPaused = false;
    updateScannerStatus(document.querySelector('#reader video') ? 'scanning' : 'ready');
    resumeScannerIfNeeded();
}

function toast(title, type = 'success') {
    if (window.nbToast) {
        window.nbToast(title, type);
    }
}

function showScanError(message) {
    setStatus(`
        <span class="scan-status-badge scan-status-badge--error">
            <i class="fas fa-circle-exclamation"></i>
            Scan failed
        </span>
    `);
    toast(message || 'Scan failed.', 'error');
    resumeAfterDialog();
}

let html5Qrcode = window.NbFarmQr && NbFarmQr.libraryReady()
    ? new Html5Qrcode('reader')
    : null;
let cameraRunning = false;
const scannerToggleBtn = document.getElementById('scannerToggleBtn');
const scannerUploadBtn = document.getElementById('scannerUploadBtn');
const qrFileInput = document.getElementById('qrFileInput');

function isCameraScanning() {
    return cameraRunning;
}

function setCameraLive(live) {
    document.querySelector('.scanner-frame')?.classList.toggle('is-live', live);

    if (!scannerToggleBtn) {
        return;
    }

    scannerToggleBtn.innerHTML = live
        ? '<i class="fas fa-stop" aria-hidden="true"></i> Stop Camera'
        : '<i class="fas fa-play" aria-hidden="true"></i> Start Camera';
}

function pauseScannerIfNeeded() {
    if (!cameraRunning) {
        return;
    }

    try {
        html5Qrcode.pause(true);
    } catch (error) {
        console.warn('Scanner pause skipped:', error);
    }
}

function resumeScannerIfNeeded() {
    if (!cameraRunning) {
        return;
    }

    try {
        html5Qrcode.resume();
    } catch (error) {
        console.warn('Scanner resume skipped:', error);
    }
}

function startCamera() {
    if (cameraRunning || !scannerToggleBtn) {
        return;
    }

    if (!window.NbFarmQr || !NbFarmQr.libraryReady()) {
        toast('Scanner failed to load. Refresh the page.', 'error');
        return;
    }

    scannerToggleBtn.disabled = true;
    updateScannerStatus('processing');
    setCameraLive(true);

    NbFarmQr.start('reader', onScanSuccess, html5Qrcode)
        .then((scanner) => {
            html5Qrcode = scanner;
            cameraRunning = true;
            scannerToggleBtn.disabled = false;
            setCameraLive(true);
            updateScannerStatus('scanning');
        }).catch((error) => {
            console.warn(error);
            scannerToggleBtn.disabled = false;
            cameraRunning = false;
            setCameraLive(false);
            updateScannerStatus('ready');
            toast('Could not start the camera. Check permissions or use Upload QR.', 'error');
        });
}

function stopCamera() {
    if (!cameraRunning) {
        setCameraLive(false);
        updateScannerStatus('ready');
        return Promise.resolve();
    }

    if (scannerToggleBtn) {
        scannerToggleBtn.disabled = true;
    }

    return NbFarmQr.resetScanner(html5Qrcode).then(() => {
        html5Qrcode = new Html5Qrcode('reader');
        cameraRunning = false;
        scannerPaused = false;
        if (scannerToggleBtn) {
            scannerToggleBtn.disabled = false;
        }
        setCameraLive(false);
        updateScannerStatus('ready');
    }).catch((error) => {
        console.warn(error);
        cameraRunning = false;
        if (scannerToggleBtn) {
            scannerToggleBtn.disabled = false;
        }
        setCameraLive(false);
        updateScannerStatus('ready');
    });
}

function onScanSuccess(decodedText) {
    if (scannerPaused) {
        return;
    }

    if (decodedText === lastCommittedCode && (Date.now() - lastCommittedAt) < SCAN_DEBOUNCE_MS) {
        return;
    }

    scannerPaused = true;
    updateScannerStatus('processing');
    pauseScannerIfNeeded();

    jsonRequest(scanStoreUrl, 'POST', { qr_code: decodedText })
        .then((res) => {
            if (!res.success) {
                lastCommittedCode = String(decodedText);
                lastCommittedAt = Date.now();
                showScanError(res.message);
                return;
            }

            lastCommittedCode = String(res.qr_code || decodedText);
            lastCommittedAt = Date.now();

            fillResult(res);
            if (typeof window.applyReviewListing === 'function') {
                window.applyReviewListing(res);
            }
            toast(res.message || 'Added to the review list', 'success');
            resumeAfterDialog();
        })
        .catch((error) => {
            console.log(error);
            lastCommittedCode = String(decodedText);
            lastCommittedAt = Date.now();
            setStatus(`
                <span class="scan-status-badge scan-status-badge--error">
                    <i class="fas fa-triangle-exclamation"></i>
                    Connection error
                </span>
            `);
            toast('Something went wrong. Try the scan again.', 'error');
            resumeAfterDialog();
        });
}

function updateScannerStatus(state) {
    const badge = document.getElementById('scannerStatus');
    if (!badge) {
        return;
    }

    badge.classList.remove('scanner-status--active');

    if (state === 'scanning') {
        badge.textContent = 'Scanning';
        badge.classList.add('scanner-status--active');
    } else if (state === 'processing') {
        badge.textContent = 'Processing';
    } else {
        badge.textContent = 'Ready to scan';
    }
}

function setUploadBusy(busy) {
    if (scannerUploadBtn) {
        scannerUploadBtn.disabled = busy;
    }
}

function scanUploadedFile(file) {
    if (!file) {
        return;
    }

    setUploadBusy(true);
    updateScannerStatus('processing');

    NbFarmQr.scanFile('qrFileReader', file)
        .then((decodedText) => {
            setUploadBusy(false);
            onScanSuccess(decodedText);
        })
        .catch((error) => {
            console.warn(error);
            setUploadBusy(false);
            updateScannerStatus(cameraRunning ? 'scanning' : 'ready');
            showScanError('No QR code found in that image. Try another photo of the farm QR label.');
        });
}

if (scannerToggleBtn) {
    scannerToggleBtn.addEventListener('click', () => {
        if (cameraRunning) {
            stopCamera();
            return;
        }

        startCamera();
    });
}

scannerUploadBtn?.addEventListener('click', () => {
    qrFileInput?.click();
});

qrFileInput?.addEventListener('change', function () {
    const file = this.files && this.files[0];
    this.value = '';
    scanUploadedFile(file);
});

setCameraLive(false);
updateScannerStatus('ready');

if (!html5Qrcode) {
    const badge = document.getElementById('scannerStatus');
    if (badge) {
        badge.textContent = 'Scanner failed to load';
    }
}

</script>
@endpush
