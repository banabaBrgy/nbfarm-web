import './bootstrap';
import './sidebar';
import './weather';
import './alerts';
import './count-up';

// Bootstrap
import 'bootstrap/dist/css/bootstrap.min.css';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Auto close alerts after 3 seconds
document.addEventListener('DOMContentLoaded', () => {

    const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');

    alerts.forEach((alert) => {

        setTimeout(() => {

            alert.classList.add('fade');

            setTimeout(() => {

                alert.remove();

            }, 300);

        }, 3000);

    });

});


// Sidebar active effect
document.addEventListener('DOMContentLoaded', () => {

    const menuLinks = document.querySelectorAll('.sidebar nav ul li a');

    menuLinks.forEach(link => {

        link.addEventListener('click', () => {

            menuLinks.forEach(item => {
                item.parentElement.classList.remove('active');
            });

            link.parentElement.classList.add('active');

        });

    });

});

// Simple card hover animation
document.addEventListener('DOMContentLoaded', () => {

    const cards = document.querySelectorAll('.card-box');

    cards.forEach(card => {

        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-5px)';
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
        });

    });

});

// Responsive sidebar toggle
document.addEventListener('DOMContentLoaded', () => {

    const menuBtn   = document.getElementById('headerMenuBtn');
    const closeBtn  = document.getElementById('sidebarCloseBtn');
    const backdrop  = document.getElementById('sidebarBackdrop');
    const navLinks  = document.querySelectorAll('.sidebar nav a');

    if (!menuBtn) return;

    const isDesktop = () => window.innerWidth > 768;

    const updateMenuButton = (isOpen) => {
        menuBtn.setAttribute('aria-expanded', String(isOpen));
        menuBtn.setAttribute('aria-label', isOpen ? 'Hide navigation' : 'Open navigation');
    };

    const openMobile = () => {
        document.body.classList.add('sidebar-open');
        updateMenuButton(true);
        menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    };

    const closeMobile = () => {
        document.body.classList.remove('sidebar-open');
        updateMenuButton(false);
        menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    };

    const collapseDesktop = () => {
        document.body.classList.add('sidebar-collapsed');
        updateMenuButton(false);
    };

    const expandDesktop = () => {
        document.body.classList.remove('sidebar-collapsed');
        updateMenuButton(true);
    };

    updateMenuButton(isDesktop() ? !document.body.classList.contains('sidebar-collapsed') : document.body.classList.contains('sidebar-open'));

    menuBtn.addEventListener('click', () => {
        if (isDesktop()) {
            document.body.classList.contains('sidebar-collapsed')
                ? expandDesktop()
                : collapseDesktop();
            return;
        }

        document.body.classList.contains('sidebar-open') ? closeMobile() : openMobile();
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            isDesktop() ? collapseDesktop() : closeMobile();
        });
    }

    if (backdrop) backdrop.addEventListener('click', closeMobile);

    navLinks.forEach(link => link.addEventListener('click', () => {
        if (!isDesktop()) closeMobile();
    }));

    window.addEventListener('resize', () => {
        if (isDesktop()) {
            document.body.classList.remove('sidebar-open');
        } else {
            document.body.classList.remove('sidebar-collapsed');
            closeMobile();
        }
    });

});

// Filter dropdowns update the current page in place (AJAX) instead of a full reload.
(function () {
    let suppressAutoSubmit = false;
    let requestId = 0;

    function isGetForm(form) {
        return (form.getAttribute('method') || 'get').toLowerCase() === 'get';
    }

    function isEggSummaryForm(form) {
        return form.matches('[data-egg-summary-form]') || Boolean(form.closest('[data-egg-summary-form]'));
    }

    function buildGetUrl(form) {
        const url = new URL(form.getAttribute('action') || window.location.pathname, window.location.origin);
        const current = new URL(window.location.href);

        if (url.pathname === current.pathname) {
            url.search = current.search;
        }

        new FormData(form).forEach((value, key) => {
            url.searchParams.set(key, value);
        });
        url.searchParams.delete('page');

        return url;
    }

    function runScripts(root) {
        root.querySelectorAll('script').forEach((oldScript) => {
            const script = document.createElement('script');

            [...oldScript.attributes].forEach((attr) => {
                script.setAttribute(attr.name, attr.value);
            });
            script.async = false;
            script.textContent = oldScript.textContent;
            oldScript.replaceWith(script);
        });
    }

    function replaceRegion(selector, nextDoc) {
        const current = document.querySelector(selector);
        const next = nextDoc.querySelector(selector);

        if (!current || !next) {
            return false;
        }

        current.replaceWith(next);
        runScripts(next);

        return true;
    }

    function ajaxNavigate(form) {
        const url = buildGetUrl(form);
        const body = document.querySelector('[data-page-body]');

        if (!body) {
            window.location.assign(url);
            return;
        }

        const id = ++requestId;
        const scrollX = window.scrollX;
        const scrollY = window.scrollY;

        body.setAttribute('aria-busy', 'true');

        fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Filter request failed');
                }

                return response.text();
            })
            .then((html) => {
                if (id !== requestId) {
                    return;
                }

                const doc = new DOMParser().parseFromString(html, 'text/html');

                if (!replaceRegion('[data-page-body]', doc)) {
                    window.location.assign(url);
                    return;
                }

                replaceRegion('[data-page-scripts]', doc);
                document.title = doc.title;
                history.replaceState(null, '', url);
                window.scrollTo(scrollX, scrollY);
            })
            .catch(() => {
                if (id !== requestId) {
                    return;
                }

                window.location.assign(url);
            })
            .finally(() => {
                const current = document.querySelector('[data-page-body]');

                if (current) {
                    current.removeAttribute('aria-busy');
                }
            });
    }

    document.addEventListener('pointerdown', (event) => {
        const link = event.target.closest('a[href]');
        if (link && link.getAttribute('href') && link.target !== '_blank') {
            suppressAutoSubmit = true;
            return;
        }

        const button = event.target.closest('button');
        if (button && button.type !== 'submit') {
            suppressAutoSubmit = true;
        }
    }, true);

    document.addEventListener('change', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
            return;
        }
        if (!field.matches('[data-auto-submit]') || !field.form || suppressAutoSubmit) {
            return;
        }
        if (isEggSummaryForm(field.form) || !isGetForm(field.form)) {
            return;
        }
        ajaxNavigate(field.form);
    });

    document.addEventListener('submit', (event) => {
        if (suppressAutoSubmit && !event.submitter) {
            event.preventDefault();
            return;
        }

        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
            return;
        }
        if (!isGetForm(form) || isEggSummaryForm(form) || !form.querySelector('[data-auto-submit]')) {
            return;
        }

        event.preventDefault();
        ajaxNavigate(form);
    });

    const release = () => {
        setTimeout(() => {
            suppressAutoSubmit = false;
        }, 0);
    };
    window.addEventListener('pointerup', release, true);
    window.addEventListener('pointercancel', release, true);
})();
// Preserve scroll position when dropdown filters submit their forms.
(function () {
    const storageKey = 'nb_poultry_scroll_position';

    function saveScrollPosition() {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify({
                x: window.scrollX,
                y: window.scrollY,
                path: window.location.pathname
            }));
        } catch (error) {
            console.warn('Unable to save scroll position.', error);
        }
    }

    // Save before dropdown-triggered form submissions.
    document.addEventListener('change', function (event) {
        const field = event.target;

        if (
            field instanceof HTMLSelectElement &&
            field.form
        ) {
            saveScrollPosition();
        }
    }, true);

    // Also save before normal form submissions.
    document.addEventListener('submit', function (event) {
        if (!event.defaultPrevented) {
            saveScrollPosition();
        }
    }, true);

    // Restore the previous scroll position after page navigation.
    window.addEventListener('pageshow', function () {
        try {
            const savedPosition = sessionStorage.getItem(storageKey);

            if (!savedPosition) return;

            sessionStorage.removeItem(storageKey);

            const position = JSON.parse(savedPosition);

            // Restore only when returning to the same page.
            if (position.path !== window.location.pathname) return;

            requestAnimationFrame(function () {
                window.scrollTo(position.x, position.y);
            });

        } catch (error) {
            console.warn('Unable to restore scroll position.', error);
            sessionStorage.removeItem(storageKey);
        }
    });
})();