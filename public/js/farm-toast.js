(function () {
    const kinds = ['success', 'error', 'warning', 'info'];

    function fallback(message, type) {
        let el = document.getElementById('nb-farm-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'nb-farm-toast';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }

        el.className = 'nb-farm-toast is-' + type + ' is-show';
        el.textContent = message;
        clearTimeout(el._hideTimer);
        el._hideTimer = setTimeout(function () {
            el.classList.remove('is-show');
        }, type === 'error' || type === 'warning' ? 4200 : 2600);
    }

    window.nbToast = function (message, type) {
        const text = String(message || '').trim();
        if (!text) {
            return;
        }

        const kind = kinds.indexOf(type) === -1 ? 'info' : type;

        if (!window.Swal) {
            fallback(text, kind);
            return;
        }

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: kind,
            title: text,
            showConfirmButton: false,
            timer: kind === 'error' || kind === 'warning' ? 4200 : 2600,
            timerProgressBar: true,
            customClass: { popup: 'nb-toast' }
        });
    };
})();
