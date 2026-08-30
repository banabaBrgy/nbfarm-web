(function () {
    const form = document.getElementById('login-form');
    if (!form) return;

    const submitBtn = document.getElementById('login-submit');
    const toggleBtn = document.getElementById('toggle-password');
    const capsHint = document.getElementById('password-caps');

    const fields = {
        username: {
            wrap: form.querySelector('[data-field="username"]'),
            input: document.getElementById('username'),
            error: document.getElementById('username-error'),
            validate() {
                const value = this.input.value.trim();
                if (!value) return 'Username is required.';
                if (value.length < 3) return 'Username must be at least 3 characters.';
                if (value.length > 50) return 'Username may not be greater than 50 characters.';
                return '';
            },
        },
        password: {
            wrap: form.querySelector('[data-field="password"]'),
            input: document.getElementById('password'),
            error: document.getElementById('password-error'),
            validate() {
                const value = this.input.value;
                if (!value) return 'Password is required.';
                if (value.length < 6) return 'Password must be at least 6 characters.';
                return '';
            },
        },
        terms: {
            wrap: form.querySelector('[data-field="terms"]'),
            input: document.getElementById('terms'),
            error: document.getElementById('terms-error'),
            validate() {
                return this.input.checked
                    ? ''
                    : 'You must agree to the Terms and Conditions before logging in.';
            },
        },
        recaptcha: {
            wrap: form.querySelector('[data-field="recaptcha"]'),
            input: null,
            error: document.getElementById('recaptcha-error'),
            validate() {
                if (typeof grecaptcha === 'undefined') {
                    return 'reCAPTCHA failed to load. Please refresh the page.';
                }
                return grecaptcha.getResponse()
                    ? ''
                    : 'Please verify that you are not a robot.';
            },
        },
    };

    function setFieldState(field, message, { showValid = false } = {}) {
        const hasError = Boolean(message);
        field.error.textContent = message || '';
        field.wrap.classList.toggle('is-invalid', hasError);
        field.wrap.classList.toggle('is-valid', showValid && !hasError);

        if (field.input) {
            field.input.setAttribute('aria-invalid', hasError ? 'true' : 'false');
        }
    }

    function validateField(name, { showValid = false } = {}) {
        const field = fields[name];
        const message = field.validate();
        setFieldState(field, message, { showValid });
        return !message;
    }

    function firstInvalidField() {
        return ['username', 'password', 'terms', 'recaptcha'].find((name) => fields[name].validate());
    }

    function validateAll() {
        let valid = true;
        ['username', 'password', 'terms', 'recaptcha'].forEach((name) => {
            if (!validateField(name)) valid = false;
        });
        return valid;
    }

    fields.username.input.addEventListener('blur', () => {
        fields.username.input.value = fields.username.input.value.trim();
        validateField('username', { showValid: true });
    });
    fields.username.input.addEventListener('input', () => {
        if (fields.username.wrap.classList.contains('is-invalid')) {
            validateField('username');
        }
    });

    fields.password.input.addEventListener('blur', () => validateField('password', { showValid: true }));
    fields.password.input.addEventListener('input', () => {
        if (fields.password.wrap.classList.contains('is-invalid')) {
            validateField('password');
        }
    });

    fields.terms.input.addEventListener('change', () => validateField('terms', { showValid: true }));

    form.querySelector('.terms a')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    function updateCapsLock(event) {
        if (!capsHint) return;
        const on = event.getModifierState && event.getModifierState('CapsLock');
        capsHint.hidden = !on;
        capsHint.classList.toggle('is-visible', Boolean(on));
    }

    fields.password.input.addEventListener('keydown', updateCapsLock);
    fields.password.input.addEventListener('keyup', updateCapsLock);
    fields.password.input.addEventListener('focus', updateCapsLock);
    fields.password.input.addEventListener('blur', () => {
        if (capsHint) {
            capsHint.hidden = true;
            capsHint.classList.remove('is-visible');
        }
    });

    toggleBtn.addEventListener('click', () => {
        const showing = fields.password.input.type === 'text';
        fields.password.input.type = showing ? 'password' : 'text';
        toggleBtn.textContent = showing ? 'Show' : 'Hide';
        toggleBtn.setAttribute('aria-pressed', showing ? 'false' : 'true');
        toggleBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        fields.password.input.focus();
    });

    window.onLoginCaptcha = function () {
        setFieldState(fields.recaptcha, '', { showValid: true });
    };

    window.onLoginCaptchaExpired = function () {
        setFieldState(fields.recaptcha, 'Please verify that you are not a robot.');
    };

    form.addEventListener('submit', (event) => {
        fields.username.input.value = fields.username.input.value.trim();

        if (!validateAll()) {
            event.preventDefault();
            const invalid = firstInvalidField();
            const target = invalid && fields[invalid].input;
            if (target) target.focus();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
        submitBtn.textContent = 'Signing in…';
    });
})();
