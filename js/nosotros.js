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

    const lightbox = document.querySelector('[data-team-lightbox]');
    const lightboxImage = lightbox?.querySelector('[data-team-lightbox-image]');
    const lightboxTitle = lightbox?.querySelector('[data-team-lightbox-title]');
    const lightboxSubtitle = lightbox?.querySelector('[data-team-lightbox-subtitle]');
    const lightboxClose = lightbox?.querySelector('[data-team-lightbox-close]');
    let lastTrigger = null;

    const closeLightbox = () => {
        if (!lightbox?.open) return;
        lightbox.close();
    };

    document.querySelectorAll('[data-team-image]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!lightbox || !lightboxImage) return;
            lastTrigger = button;
            lightboxImage.src = button.dataset.imageSrc || '';
            lightboxImage.alt = button.dataset.imageTitle || 'Imagen ampliada';
            if (lightboxTitle) lightboxTitle.textContent = button.dataset.imageTitle || '';
            if (lightboxSubtitle) lightboxSubtitle.textContent = button.dataset.imageSubtitle || '';
            lightbox.showModal();
            document.body.classList.add('team-lightbox-open');
            lightboxClose?.focus();
        });
    });

    lightboxClose?.addEventListener('click', closeLightbox);
    lightbox?.addEventListener('click', (event) => {
        if (event.target === lightbox) closeLightbox();
    });
    lightbox?.addEventListener('close', () => {
        document.body.classList.remove('team-lightbox-open');
        lightboxImage?.removeAttribute('src');
        lastTrigger?.focus();
    });
});
