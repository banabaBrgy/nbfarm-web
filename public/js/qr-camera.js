window.NbFarmQr = (function () {
    function libraryReady() {
        return typeof Html5Qrcode !== 'undefined';
    }

    function isVirtualCamera(label) {
        return /obs|virtual|snap camera|manycam|droidcam|iriun|nvidia broadcast|xsplit|ndi/i.test(label || '');
    }

    function pickPreferredCamera(cameras) {
        const list = Array.isArray(cameras) ? cameras : [];
        const real = list.filter((camera) => !isVirtualCamera(camera.label));
        const pool = real.length ? real : list;
        const rear = pool.find((camera) => /back|rear|environment/i.test(camera.label || ''));

        return rear || pool[0] || list[0] || null;
    }

    function uniqueAttempts(attempts) {
        const seen = new Set();
        const out = [];

        attempts.forEach((attempt) => {
            const key = typeof attempt === 'string' ? attempt : JSON.stringify(attempt);
            if (seen.has(key)) {
                return;
            }
            seen.add(key);
            out.push(attempt);
        });

        return out;
    }

    const nativeStops = new WeakMap();

    function watchWithBarcodeDetector(elementId, onSuccess) {
        if (typeof window.BarcodeDetector !== 'function') {
            return function () {};
        }

        let detector;
        try {
            detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        } catch (_error) {
            return function () {};
        }

        const root = document.getElementById(elementId);
        const video = root ? root.querySelector('video') : null;
        if (!video) {
            return function () {};
        }

        let stopped = false;
        let timer = 0;

        const tick = async () => {
            if (stopped) {
                return;
            }

            if (video.readyState >= 2 && video.videoWidth > 0) {
                try {
                    const codes = await detector.detect(video);
                    const value = codes && codes[0] && codes[0].rawValue;
                    if (value) {
                        onSuccess(String(value));
                    }
                } catch (_error) {
                    // A single frame can fail while the camera is still warming up.
                }
            }

            if (!stopped) {
                timer = window.setTimeout(tick, 200);
            }
        };

        timer = window.setTimeout(tick, 300);

        return function stop() {
            stopped = true;
            window.clearTimeout(timer);
        };
    }

    async function resetScanner(scanner) {
        if (!scanner) {
            return;
        }

        const stopNative = nativeStops.get(scanner);
        if (stopNative) {
            stopNative();
            nativeStops.delete(scanner);
        }

        try {
            await scanner.stop();
        } catch (_error) {
            // Already stopped.
        }

        try {
            await scanner.clear();
        } catch (_error) {
            // Nothing to clear.
        }
    }

    async function start(elementId, onSuccess, existingScanner) {
        if (!libraryReady()) {
            throw new Error('QR library failed to load');
        }

        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

        const cameras = await Html5Qrcode.getCameras().catch(() => []);
        const preferred = pickPreferredCamera(cameras);
        const attempts = uniqueAttempts([
            preferred ? preferred.id : null,
            { facingMode: 'environment' },
            { facingMode: 'user' },
        ].filter(Boolean));

        // Scan the whole camera frame. A center box clips the QR corners
        // when the label fills the viewfinder, so the preview stays on and
        // the code is never decoded.
        const config = {
            fps: 10,
        };

        let scanner = existingScanner || new Html5Qrcode(elementId);
        let lastError = new Error('Could not start camera');

        for (const cameraId of attempts) {
            try {
                await scanner.start(cameraId, config, onSuccess);
                nativeStops.set(scanner, watchWithBarcodeDetector(elementId, onSuccess));
                return scanner;
            } catch (error) {
                lastError = error;
                await resetScanner(scanner);
                scanner = new Html5Qrcode(elementId);
            }
        }

        throw lastError;
    }

    async function scanFile(elementId, file) {
        if (!libraryReady()) {
            throw new Error('QR library failed to load');
        }

        const scanner = new Html5Qrcode(elementId);

        try {
            return await scanner.scanFile(file, false);
        } finally {
            await resetScanner(scanner);
        }
    }

    return {
        libraryReady,
        pickPreferredCamera,
        start,
        scanFile,
        resetScanner,
    };
})();
