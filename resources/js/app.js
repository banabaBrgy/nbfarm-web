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