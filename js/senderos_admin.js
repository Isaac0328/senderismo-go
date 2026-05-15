document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('senderosTable');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr')) : [];
    const galleryInput = document.getElementById('galeria');
    const galleryHelp = document.getElementById('galleryHelp');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();

            rows.forEach((tr) => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    if (galleryInput && galleryHelp) {
        galleryInput.addEventListener('change', () => {
            const count = galleryInput.files ? galleryInput.files.length : 0;
            galleryHelp.textContent = count > 0
                ? `${count} imagen(es) seleccionada(s).`
                : 'Puedes cargar varias imagenes a la vez.';
        });
    }

    function updateModalCount(modal) {
        const list = modal.querySelector('[data-count-target]');
        if (!list) return;

        const targetId = list.dataset.countTarget;
        const target = document.getElementById(targetId);
        const checked = list.querySelectorAll('input[type="checkbox"]:checked').length;

        if (target) {
            target.textContent = String(checked);
        }
    }

    document.querySelectorAll('.detail-modal').forEach((modal) => {
        updateModalCount(modal);

        modal.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => updateModalCount(modal));
        });
    });

    document.querySelectorAll('.detail-modal-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            const modalId = button.dataset.modalTarget;
            const modal = modalId ? document.getElementById(modalId) : null;

            if (!modal) return;

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = button.closest('.detail-modal');

            if (!modal) return;

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            updateModalCount(modal);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('.detail-modal.is-open').forEach((modal) => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            updateModalCount(modal);
        });
    });
});
