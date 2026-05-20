document.addEventListener('DOMContentLoaded', () => {
    const animatedItems = document.querySelectorAll('.about-stats article, .value-card, .team-card, .process-list article');

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.16 });

        animatedItems.forEach((item) => observer.observe(item));
    } else {
        animatedItems.forEach((item) => item.classList.add('is-visible'));
    }

    const hero = document.getElementById('nosotros-hero');
    const heroImage = hero?.querySelector('.about-hero-img');

    if (hero && heroImage) {
        window.addEventListener('scroll', () => {
            const offset = Math.min(window.scrollY * 0.16, 90);
            heroImage.style.transform = `translateY(${offset}px) scale(1.04)`;
        }, { passive: true });
    }
});
