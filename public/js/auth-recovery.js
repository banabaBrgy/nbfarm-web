document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const wrap = btn.closest('.password-container');
        const input = wrap ? wrap.querySelector('input') : null;
        if (!input) {
            return;
        }

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        input.focus();
    });
});

document.querySelectorAll('form[data-loading-form]').forEach((form) => {
    const btn = form.querySelector('.login-btn[type="submit"]');
    if (!btn) {
        return;
    }

    form.addEventListener('submit', (event) => {
        if (btn.disabled) {
            event.preventDefault();
            return;
        }

        const required = [...form.querySelectorAll('[required]')];
        const missing = required.find((el) => !String(el.value || '').trim());
        if (missing) {
            event.preventDefault();
            missing.focus();
            return;
        }

        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');
        btn.textContent = btn.dataset.loading || 'Please wait…';
    });
});
