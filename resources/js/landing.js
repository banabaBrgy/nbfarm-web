import Swiper from 'swiper';
import 'swiper/css';

const researcherCarousel = document.querySelector('[data-researchers-carousel]');

if (researcherCarousel) {
    new Swiper(researcherCarousel, {
        slidesPerView: 1,
        spaceBetween: 16,
        speed: 650,
        grabCursor: true,
        watchOverflow: true,
        allowTouchMove: true,
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
