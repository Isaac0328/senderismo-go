document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('senderosTable');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr')) : [];
    const galleryInput = document.getElementById('galeria');
    const galleryHelp = document.getElementById('galleryHelp');
    const dateInput = document.getElementById('fecha_sendero');
    const datePreview = document.getElementById('fechaSenderoPreview');

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

    if (dateInput && datePreview) {
        if (dateInput.type === 'date') {
            const updateNativeDatePreview = () => {
                if (!dateInput.value) {
                    datePreview.textContent = 'Obligatoria solo para proximos senderos.';
                    return;
                }

                const [year, month, day] = dateInput.value.split('-').map(Number);
                const parsedDate = new Date(year, month - 1, day);

                datePreview.textContent = `Fecha seleccionada: ${parsedDate.toLocaleDateString('es-DO', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })}`;
            };

            dateInput.addEventListener('change', updateNativeDatePreview);
            dateInput.addEventListener('input', updateNativeDatePreview);
            updateNativeDatePreview();
        } else {

        const pad = (value) => String(value).padStart(2, '0');

        const parseVisualDate = (value) => {
            const match = value.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (!match) return null;

            const day = Number(match[1]);
            const month = Number(match[2]);
            const year = Number(match[3]);
            const date = new Date(year, month - 1, day);

            if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                return null;
            }

            return { date, iso: `${year}-${pad(month)}-${pad(day)}` };
        };

        const updateDatePreview = () => {
            if (!dateInput.value) {
                datePreview.textContent = 'Selecciona la fecha del sendero.';
                dateHiddenInput.value = '';
                return;
            }

            const parsed = parseVisualDate(dateInput.value);
            if (!parsed) {
                dateHiddenInput.value = '';
                datePreview.textContent = 'Usa el formato dia/mes/año. Ej: 03/08/2026.';
                return;
            }

            dateHiddenInput.value = parsed.iso;
            datePreview.textContent = `Fecha seleccionada: ${parsed.date.toLocaleDateString('es-DO', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })}`;
        };

        dateInput.addEventListener('change', updateDatePreview);
        dateInput.addEventListener('input', updateDatePreview);
        updateDatePreview();
        }
    }

    document.querySelectorAll('[data-duration-group]').forEach((group) => {
        const hoursInput = group.querySelector('[data-duration-hours]');
        const minutesInput = group.querySelector('[data-duration-minutes]');
        const totalInput = group.querySelector('[data-duration-total]');

        if (!hoursInput || !minutesInput || !totalInput) return;

        const updateTotal = () => {
            const hours = Math.max(0, Number.parseInt(hoursInput.value || '0', 10) || 0);
            const minutes = Math.min(59, Math.max(0, Number.parseInt(minutesInput.value || '0', 10) || 0));

            if (String(minutesInput.value) !== String(minutes)) {
                minutesInput.value = String(minutes);
            }

            const total = (hours * 60) + minutes;
            totalInput.value = total > 0 ? String(total) : '';
        };

        hoursInput.addEventListener('input', updateTotal);
        minutesInput.addEventListener('input', updateTotal);
        updateTotal();
    });

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
