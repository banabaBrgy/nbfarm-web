document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('alertPanel');
    if (!panel) {
        return;
    }

    const bells = document.querySelectorAll('[data-alert-bell]');
    const list = panel.querySelector('[data-alert-list]');
    const statusEl = panel.querySelector('[data-push-status]');
    const hint = panel.querySelector('[data-push-hint]');
    const readAllBtn = panel.querySelector('[data-alert-read-all]');

    const syncReadAllBtn = (count) => {
        if (!readAllBtn) {
            return;
        }
        readAllBtn.disabled = count < 1;
        readAllBtn.classList.toggle('is-disabled', count < 1);
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const indexUrl = panel.dataset.index;
    const readBase = panel.dataset.readBase;
    const pushUrl = panel.dataset.push;
    const subscribeUrl = panel.dataset.subscribe;
    const unsubscribeUrl = panel.dataset.unsubscribe;

    let vapidPublic = '';
    let pushBusy = false;
    let pushReady = false;
    let lastSyncedPerm = '';

    const headers = () => ({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    });

    const setBadge = (count) => {
        document.querySelectorAll('[data-alert-badge]').forEach((badge) => {
            if (count < 1) {
                badge.hidden = true;
                badge.textContent = '0';
                return;
            }
            badge.hidden = false;
            badge.textContent = count > 9 ? '9+' : String(count);
        });
    };

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    };

    const permissionLabel = (perm) => {
        if (perm === 'granted') {
            return 'Allow';
        }
        if (perm === 'denied') {
            return 'Block';
        }
        return 'Ask';
    };

    const currentPermission = () => (
        ('Notification' in window) ? Notification.permission : 'denied'
    );

    const setPermissionUi = () => {
        const perm = ('Notification' in window) ? Notification.permission : null;
        if (statusEl) {
            statusEl.textContent = perm ? permissionLabel(perm) : 'Unavailable';
        }
        if (!hint) {
            return;
        }
        if (!perm) {
            hint.textContent = 'This browser cannot show desktop alerts.';
            return;
        }
        if (perm === 'granted') {
            hint.textContent = 'Desktop toasts can appear even if this tab is closed.';
            return;
        }
        if (perm === 'denied') {
            hint.textContent = 'Blocked. Open the padlock in the address bar to allow them.';
            return;
        }
        hint.textContent = 'Click the bell to let Chrome ask Allow or Block.';
    };

    const render = (payload) => {
        setBadge(payload.unread || 0);
        vapidPublic = payload.vapid_public || vapidPublic;
        setPermissionUi();
        syncReadAllBtn(payload.unread || 0);

        const alerts = payload.alerts || [];
        if (alerts.length === 0) {
            list.innerHTML = '<p class="alert-empty">No alerts.</p>';
            return;
        }

        list.innerHTML = alerts.map((row) => `
            <button type="button" class="alert-item ${row.read ? '' : 'is-unread'}" data-alert-id="${row.id}" data-alert-url="${escapeHtml(row.url || '')}">
                <span class="alert-item-title-row">
                        ${row.read ? '' : '<span class="alert-dot" aria-hidden="true"></span>'}
                        <span class="alert-item-title">${escapeHtml(row.title || 'Notification')}</span>
                        ${row.read ? '' : '<span class="alert-new">New</span>'}
                    </span>
                    <span class="alert-item-body">${escapeHtml(row.body || '')}</span>
                    <span class="alert-item-when">${escapeHtml(row.when || '')}</span>
            </button>
        `).join('');
    };

    const load = async () => {
        const res = await fetch(indexUrl, { headers: headers(), credentials: 'same-origin' });
        if (!res.ok) {
            return;
        }
        render(await res.json());
    };

    const closePanel = () => {
        panel.hidden = true;
        bells.forEach((btn) => {
            btn.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        });
    };

    const positionPanel = (anchor) => {
        const rect = anchor.getBoundingClientRect();
        const width = Math.min(400, window.innerWidth - 24);
        let left = rect.right - width;
        if (left < 12) {
            left = 12;
        }
        if (left + width > window.innerWidth - 12) {
            left = window.innerWidth - width - 12;
        }

        let top = rect.bottom + 8;
        if (top < 12) {
            top = 12;
        }
        panel.style.left = `${left}px`;
        panel.style.top = `${top}px`;
        panel.hidden = false;

        const box = panel.getBoundingClientRect();
        if (box.bottom > window.innerHeight - 12) {
            top = Math.max(12, rect.top - box.height - 8);
            panel.style.top = `${top}px`;
        }
    };

    const openPanel = async (anchor) => {
        positionPanel(anchor);
        bells.forEach((btn) => {
            const open = btn === anchor;
            btn.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        await load();
        await syncFromBrowser({ prompt: currentPermission() === 'default' });
        positionPanel(anchor);
    };

    bells.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (!panel.hidden && btn.classList.contains('is-open')) {
                closePanel();
                return;
            }
            openPanel(btn);
        });
    });

    document.addEventListener('click', (event) => {
        if (panel.hidden) {
            return;
        }
        if (panel.contains(event.target) || event.target.closest('[data-alert-bell]')) {
            return;
        }
        closePanel();
    });

    window.addEventListener('resize', () => {
        if (!panel.hidden) {
            closePanel();
        }
    });

    list.addEventListener('click', async (event) => {
        const item = event.target.closest('[data-alert-id]');
        if (!item) {
            return;
        }

        const id = item.getAttribute('data-alert-id');
        const fallback = item.getAttribute('data-alert-url');
        const res = await fetch(`${readBase}/${id}/read`, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
        });

        let url = fallback;
        if (res.ok) {
            const data = await res.json();
            url = data.url || fallback;
            setBadge(data.unread || 0);
            syncReadAllBtn(data.unread || 0);
        }

        closePanel();
        if (url) {
            window.location.href = url;
        }
    });

    if (readAllBtn) {
        readAllBtn.addEventListener('click', async () => {
            if (readAllBtn.disabled) {
                return;
            }
            readAllBtn.disabled = true;
            try {
                const res = await fetch(`${readBase}/read-all`, {
                    method: 'POST',
                    headers: headers(),
                    credentials: 'same-origin',
                });
                if (res.ok) {
                    const data = await res.json();
                    setBadge(data.unread || 0);
                    await load();
                } else {
                    readAllBtn.disabled = false;
                }
            } catch (_e) {
                readAllBtn.disabled = false;
            }
        });
    }

    const urlBase64ToUint8Array = (base64String) => {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const output = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i += 1) {
            output[i] = raw.charCodeAt(i);
        }
        return output;
    };

    const currentSubscription = async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return null;
        }
        const reg = await navigator.serviceWorker.getRegistration('/');
        return reg ? reg.pushManager.getSubscription() : null;
    };

    const enablePush = async () => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            throw new Error('Push is not supported in this browser.');
        }
        if (!window.isSecureContext) {
            throw new Error('Push needs HTTPS or localhost.');
        }
        if (!vapidPublic) {
            throw new Error('Push keys are not set on the server.');
        }
        if (Notification.permission !== 'granted') {
            throw new Error('Browser permission was not allowed.');
        }

        const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;

        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublic),
            });
        }

        const payload = sub.toJSON();
        const res = await fetch(subscribeUrl, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify({
                endpoint: payload.endpoint,
                keys: payload.keys,
                contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0],
            }),
        });

        if (!res.ok) {
            throw new Error('Could not save the push subscription.');
        }

        await fetch(pushUrl, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify({ enabled: true }),
        });
    };

    const disablePush = async () => {
        if ('serviceWorker' in navigator) {
            const regs = await navigator.serviceWorker.getRegistrations();
            for (const reg of regs) {
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    await fetch(unsubscribeUrl, {
                        method: 'POST',
                        headers: headers(),
                        credentials: 'same-origin',
                        body: JSON.stringify({ endpoint: sub.endpoint }),
                    });
                    try {
                        await sub.unsubscribe();
                    } catch (_e) {
                        // already gone
                    }
                }

                const script = reg.active?.scriptURL || reg.installing?.scriptURL || '';
                if (script.includes('/sw.js')) {
                    await reg.unregister();
                }
            }
        }

        await fetch(pushUrl, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify({ enabled: false }),
        });
    };

    const syncFromBrowser = async ({ prompt = false } = {}) => {
        if (pushBusy) {
            return;
        }
        setPermissionUi();

        if (!('Notification' in window)) {
            return;
        }

        let perm = Notification.permission;
        if (prompt && perm === 'default') {
            try {
                perm = await Notification.requestPermission();
            } catch (_e) {
                perm = Notification.permission;
            }
            setPermissionUi();
        }

        if (perm === 'granted') {
            lastSyncedPerm = 'granted';
            if (pushReady || !vapidPublic) {
                return;
            }
            pushBusy = true;
            try {
                await enablePush();
                pushReady = true;
            } catch (_e) {
                pushReady = false;
            } finally {
                pushBusy = false;
                setPermissionUi();
            }
            return;
        }

        if (perm !== 'denied') {
            lastSyncedPerm = perm;
            return;
        }

        if (lastSyncedPerm === 'denied') {
            setPermissionUi();
            return;
        }

        pushBusy = true;
        try {
            await disablePush();
            pushReady = false;
            lastSyncedPerm = 'denied';
        } catch (_e) {
            // already off
        } finally {
            pushBusy = false;
            setPermissionUi();
        }
    };

    load()
        .then(() => syncFromBrowser({ prompt: false }))
        .catch(() => {});

    setInterval(() => {
        load().catch(() => {});
        syncFromBrowser({ prompt: false }).catch(() => {});
    }, 45000);
});
