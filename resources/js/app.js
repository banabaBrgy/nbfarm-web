import './bootstrap';
import './sidebar';

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

    const toggle = document.querySelector('.sidebar-toggle');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const sidebarLinks = document.querySelectorAll('.sidebar nav a');
    const mobileNavBar = document.querySelector('.mobile-nav-bar');
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');

    // Show mobile nav bar on mobile, hide on desktop
    const updateMobileNav = () => {
        if (window.innerWidth <= 768) {
            if (mobileNavBar) mobileNavBar.style.display = 'flex';
        } else {
            if (mobileNavBar) mobileNavBar.style.display = 'none';
        }
    };

    // Initial check
    updateMobileNav();
    window.addEventListener('resize', updateMobileNav);

    // Mobile menu toggle functionality
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', (e) => {
            e.preventDefault();
            const isOpen = document.body.classList.toggle('sidebar-open');
            
            if (backdrop) {
                if (isOpen) {
                    backdrop.style.display = 'block';
                } else {
                    backdrop.style.display = 'none';
                }
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', () => {
            document.body.classList.remove('sidebar-open');
            backdrop.style.display = 'none';
        });
    }

    // Close sidebar when clicking on a mobile nav item (if it's not the menu toggle)
    const mobileNavItems = document.querySelectorAll('.mobile-nav-item:not(.mobile-menu-toggle)');
    mobileNavItems.forEach((item) => {
        item.addEventListener('click', () => {
            document.body.classList.remove('sidebar-open');
            if (backdrop) backdrop.style.display = 'none';
        });
    });

    // Close sidebar when clicking on sidebar links
    if (sidebarLinks && backdrop) {
        sidebarLinks.forEach((link) => {
            link.addEventListener('click', () => {
                document.body.classList.remove('sidebar-open');
                backdrop.style.display = 'none';
            });
        });
    }

    // Close sidebar when screen resizes to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            document.body.classList.remove('sidebar-open');
            if (backdrop) backdrop.style.display = 'none';
        }
    });

});