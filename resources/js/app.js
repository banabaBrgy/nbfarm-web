import './bootstrap';
import './sidebar';
import './weather';
import './alerts';

// Bootstrap
import 'bootstrap/dist/css/bootstrap.min.css';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Auto close alerts after 3 seconds
document.addEventListener('DOMContentLoaded', () => {

    const alerts = document.querySelectorAll('.alert');

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

// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', () => {

    const menuBtn   = document.getElementById('headerMenuBtn');
    const closeBtn  = document.getElementById('sidebarCloseBtn');
    const backdrop  = document.getElementById('sidebarBackdrop');
    const navLinks  = document.querySelectorAll('.sidebar nav a');

    if (!menuBtn) return;

    const open = () => {
        document.body.classList.add('sidebar-open');
        menuBtn.setAttribute('aria-expanded', 'true');
        menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    };

    const close = () => {
        document.body.classList.remove('sidebar-open');
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
    };

    menuBtn.addEventListener('click', () => {
        document.body.classList.contains('sidebar-open') ? close() : open();
    });

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (backdrop)  backdrop.addEventListener('click', close);

    navLinks.forEach(link => link.addEventListener('click', () => {
        if (window.innerWidth <= 768) close();
    }));

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) close();
    });

});