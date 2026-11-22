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
                <p>Scan one size at a time. On Production, tap Review list to edit trays and eggs, then Confirm &amp; Save.</p>
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
                    <h3 id="scanner-title">Camera scanner</h3>
                </div>
                <span class="scanner-status" id="scannerStatus">Ready to scan</span>
            </div>

            <div class="scanner-frame">
                <div id="reader"></div>
            </div>

            <p class="scanner-instruction">
                <i class="fas fa-lightbulb"></i>
                <span>Finish this size first. Then open Production, tap Review list, and Confirm &amp; Save.</span>
            </p>
        </section>

        <section class="scan-result-card" aria-labelledby="result-title">
            <div class="scan-result-header">
                <div class="scanner-card-title">
                    <span class="scan-result-header-icon" aria-hidden="true">
                        <i class="fas fa-clipboard-check"></i>
                    </span>
                    <h3 id="result-title">Scan result</h3>
                </div>
            </div>

            <div class="scan-result-body">
                <table class="scan-result-table">
                    <tbody>
                        <tr>
                            <th scope="row">QR code</th>
                            <td id="qrCode"><span class="scan-value-muted">Not scanned</span></td>
                        </tr>
                        <tr>
                            <th scope="row">Batch</th>
                            <td id="batch"><span class="scan-value-muted">Not scanned</span></td>
                        </tr>
                        <tr>
                            <th scope="row">Egg size</th>
                            <td id="size"><span class="scan-value-muted">Not scanned</span></td>
                        </tr>
                        <tr>
                            <th scope="row">This scan</th>
                            <td id="eggs"><span class="scan-value-muted">Not scanned</span></td>
                        </tr>
                        <tr>
                            <th scope="row">Pending review</th>
                            <td id="reviewTotal"><span class="scan-value-muted">None</span></td>
                        </tr>
                        <tr>
                            <th scope="row">Status</th>
                            <td id="status">
                                <span class="scan-status-badge scan-status-badge--waiting">
                                    <i class="fas fa-clock"></i>
                                    Waiting
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

@endsection


@push('styles')
    @vite('resources/css/scan-qr.css')
@endpush

@push('scripts')

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" integrity="sha384-c9d8RFSL+u3exBOJ4Yp3HUJXS4znl9f+z66d1y54ig+ea249SpqR+w1wyvXz/lk+" crossorigin="anonymous"></script>

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

function countLabel(today) {
    if (!today) {
        return 'None';
    }

    return `${Number(today.scans || 0).toLocaleString('en-US')} stacks · ${Number(today.trays || 0).toLocaleString('en-US')} trays · ${Number(today.eggs || 0).toLocaleString('en-US')} eggs`;
}

function pendingReviewLabel(res) {
    if (Array.isArray(res.groups) && res.groups.length) {
        return countLabel({
            scans: res.groups.reduce((n, group) => n + Number(group.scans || 0), 0),
            trays: res.groups.reduce((n, group) => n + Number(group.trays || 0), 0),
            eggs: res.groups.reduce((n, group) => n + Number(group.eggs || 0), 0)
        });
    }

    return res.summary ? countLabel(res.summary) : 'None';
}

function muted(text) {
    return `<span class="scan-value-muted">${escapeScanHtml(text)}</span>`;
}

function fillResult(res, statusHtml) {
    document.getElementById('qrCode').innerHTML = res.qr_code
        ? escapeScanHtml(res.qr_code)
        : muted('Not scanned');
    document.getElementById('batch').innerHTML = res.batch
        ? escapeScanHtml(res.batch)
        : muted('Not scanned');
    document.getElementById('size').innerHTML = res.size
        ? escapeScanHtml(res.size)
        : muted('Not scanned');
    document.getElementById('eggs').innerHTML = res.trays
        ? `${Number(res.trays).toLocaleString('en-US')} trays · ${Number(res.eggs || 0).toLocaleString('en-US')} eggs`
        : muted('Not scanned');
    document.getElementById('reviewTotal').textContent = pendingReviewLabel(res);
    document.getElementById('status').innerHTML = statusHtml;
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

function showScanError(message) {
    document.getElementById('status').innerHTML = `
        <span class="scan-status-badge scan-status-badge--error">
            <i class="fas fa-circle-exclamation"></i>
            Scan failed
        </span>
    `;

    Swal.fire({
        icon: 'error',
        title: 'Scan Failed',
        text: message || 'Scan failed.',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(() => {
        resumeAfterDialog();
    });
}

function toast(title) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title,
        timer: 1800,
        showConfirmButton: false
    });
}

function isCameraScanning() {
    const video = document.querySelector('#reader video');

    return Boolean(video && video.srcObject);
}

function pauseScannerIfNeeded() {
    if (!isCameraScanning()) {
        return;
    }

    try {
        html5QrcodeScanner.pause(true);
    } catch (error) {
        console.warn('Scanner pause skipped:', error);
    }
}

function resumeScannerIfNeeded() {
    if (!isCameraScanning()) {
        return;
    }

    try {
        html5QrcodeScanner.resume();
    } catch (error) {
        console.warn('Scanner resume skipped:', error);
    }
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
                showScanError(res.message);
                return;
            }

            lastCommittedCode = String(res.qr_code || decodedText);
            lastCommittedAt = Date.now();

            fillResult(res, `
                <span class="scan-status-badge scan-status-badge--waiting">
                    <i class="fas fa-list"></i>
                    On review list
                </span>
            `);
            toast(res.message || 'Added to the Production review list');
            resumeAfterDialog();
        })
        .catch((error) => {
            console.log(error);
            document.getElementById('status').innerHTML = `
                <span class="scan-status-badge scan-status-badge--error">
                    <i class="fas fa-triangle-exclamation"></i>
                    Connection error
                </span>
            `;

            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Something went wrong.',
                confirmButtonText: 'OK',
                allowOutsideClick: false
            }).then(() => {
                resumeAfterDialog();
            });
        });
}

let html5QrcodeScanner = new Html5QrcodeScanner(

    "reader",

    {
        fps: 10,
        qrbox: (viewfinderWidth, viewfinderHeight) => {
            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
            const size = Math.max(180, Math.floor(minEdge * 0.7));

            return { width: size, height: size };
        },
        aspectRatio: 1.0,
        videoConstraints: {
            facingMode: { ideal: "environment" }
        }
    },

    false

);

html5QrcodeScanner.render(onScanSuccess);

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

let scannerUiPolished = false;

function polishScannerUi() {
    if (scannerUiPolished) {
        return;
    }

    const cameraLabel = document.querySelector('#reader__dashboard_section_csr > span');
    const startBtn = document.getElementById('html5-qrcode-button-camera-start');
    const swapLink = document.getElementById('reader__dashboard_section_swaplink');

    if (!startBtn && !swapLink) {
        return;
    }

    if (cameraLabel && cameraLabel.textContent !== 'Camera source') {
        cameraLabel.textContent = 'Camera source';
    }

    if (startBtn && startBtn.textContent !== 'Start Camera') {
        startBtn.textContent = 'Start Camera';
    }

    const stopBtn = document.getElementById('html5-qrcode-button-camera-stop');
    if (stopBtn && stopBtn.textContent !== 'Stop Camera') {
        stopBtn.textContent = 'Stop Camera';
    }

    if (swapLink && !swapLink.dataset.polished) {
        swapLink.dataset.polished = '1';
        swapLink.innerHTML = '<i class="fas fa-image"></i> Upload QR Image';
        swapLink.removeAttribute('style');
    }

    const statusSpan = document.getElementById('reader__status_span');
    if (statusSpan && statusSpan.textContent.toLowerCase().includes('error')) {
        statusSpan.classList.add('scanner-error');
    }

    scannerUiPolished = true;
}

function watchForActiveCamera() {
    const video = document.querySelector('#reader video');

    if (video && video.srcObject && !video.dataset.statusWatched) {
        video.dataset.statusWatched = '1';
        updateScannerStatus('scanning');

        video.addEventListener('pause', () => {
            if (!scannerPaused) {
                updateScannerStatus('ready');
            }
        });

        video.addEventListener('play', () => {
            if (!scannerPaused) {
                updateScannerStatus('scanning');
            }
        });
    }
}

let polishAttempts = 0;
const polishTimer = setInterval(() => {
    polishScannerUi();
    watchForActiveCamera();

    if (scannerUiPolished || ++polishAttempts >= 15) {
        clearInterval(polishTimer);
    }
}, 200);

</script>
@endpush
