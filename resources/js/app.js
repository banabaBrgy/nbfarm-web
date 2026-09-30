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
        menuBtn.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
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

// Filter controls submit their form on change. Blur runs before a click, so
// that submit was cancelling sidebar, pagination, and other links.
(function () {
    let suppressAutoSubmit = false;

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
        field.form.requestSubmit();
    });

    document.addEventListener('submit', (event) => {
        if (!suppressAutoSubmit || event.submitter) {
            return;
        }
        event.preventDefault();
    }, true);

    const release = () => {
        setTimeout(() => {
            suppressAutoSubmit = false;
        }, 0);
    };
    window.addEventListener('pointerup', release, true);
    window.addEventListener('pointercancel', release, true);
})();