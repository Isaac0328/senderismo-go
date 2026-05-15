document.addEventListener('DOMContentLoaded', function () {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    const modal = document.getElementById('galleryModal');
    const modalImage = document.getElementById('galleryModalImage');
    const modalClose = document.getElementById('galleryModalClose');
    const modalPrev = document.getElementById('galleryPrev');
    const modalNext = document.getElementById('galleryNext');
    const modalCounter = document.getElementById('galleryCounter');
    const galleryButtons = Array.from(document.querySelectorAll('[data-gallery-src]'));
    const galleryImages = galleryButtons.map((button) => button.dataset.gallerySrc || '');
    let currentIndex = 0;

    function showImage(index) {
        if (!modalImage || galleryImages.length === 0) return;

        currentIndex = (index + galleryImages.length) % galleryImages.length;
        modalImage.src = galleryImages[currentIndex];

        if (modalCounter) {
            modalCounter.textContent = `${currentIndex + 1} / ${galleryImages.length}`;
        }
    }

    function openModal(index) {
        if (!modal || !modalImage) return;

        showImage(index);
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function nextImage() {
        showImage(currentIndex + 1);
    }

    function prevImage() {
        showImage(currentIndex - 1);
    }

    galleryButtons.forEach((button, index) => {
        button.addEventListener('click', () => {
            const requestedIndex = Number.parseInt(button.dataset.galleryIndex || `${index}`, 10);
            openModal(Number.isNaN(requestedIndex) ? index : requestedIndex);
        });
    });

    function closeModal() {
        if (!modal || !modalImage) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modalImage.src = '';
    }

    modalClose?.addEventListener('click', closeModal);
    modalNext?.addEventListener('click', (event) => {
        event.stopPropagation();
        nextImage();
    });
    modalPrev?.addEventListener('click', (event) => {
        event.stopPropagation();
        prevImage();
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
        if (!modal?.classList.contains('is-open')) return;

        if (event.key === 'ArrowRight') {
            nextImage();
        }

        if (event.key === 'ArrowLeft') {
            prevImage();
        }
    });
});
