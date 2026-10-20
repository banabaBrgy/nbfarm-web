import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';
import 'swiper/css';

const researcherCarousel = document.querySelector('[data-researchers-carousel]');
const researcherControls = researcherCarousel?.parentElement?.querySelector('[data-researchers-controls]');

if (researcherCarousel) {
    new Swiper(researcherCarousel, {
        modules: [Navigation],
        slidesPerView: 1,
        spaceBetween: 16,
        speed: 650,
        grabCursor: true,
        watchOverflow: true,
        navigation: {
            nextEl: researcherControls?.querySelector('[data-carousel-next]'),
            prevEl: researcherControls?.querySelector('[data-carousel-prev]'),
        },
        breakpoints: {
            768: {
                slidesPerView: 4,
                spaceBetween: 28,
                allowTouchMove: false,
                grabCursor: false,
            },
        },
    });
}
