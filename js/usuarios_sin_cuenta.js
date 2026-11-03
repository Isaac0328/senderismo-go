document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.psc-page');
    if (!page) {
        return;
    }
    const fold = (text) => text.toLocaleLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Filtro por estado y busqueda en la lista de personas.
    const cards = Array.from(page.querySelectorAll('.psc-person'));
    const empty = page.querySelector('[data-psc-empty]');
    const search = page.querySelector('[data-psc-search]');
    let estado = 'todas';
    const applyFilters = () => {
        const term = fold(search ? search.value.trim() : '');
        let visibles = 0;
        cards.forEach((card) => {
            const show = (estado === 'todas' || card.dataset.estado === estado) && (!term || card.dataset.buscar.includes(term));
            card.hidden = !show;
            visibles += show ? 1 : 0;
        });
        if (empty && cards.length) {
            empty.hidden = visibles > 0;
        }
    };
    page.querySelectorAll('[data-psc-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            estado = button.dataset.pscFilter;
            page.querySelectorAll('[data-psc-filter]').forEach((other) => {
                other.classList.toggle('is-active', other === button);
                other.setAttribute('aria-selected', other === button ? 'true' : 'false');
            });
            applyFilters();
        });
    });
    search?.addEventListener('input', applyFilters);

    // Ventanas: se abren solas cuando la pagina llega con una accion, y al cerrarlas se limpia la direccion.
    const base = page.dataset.pscBase;
    const openDialog = (dialog) => {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        dialog.querySelector('input:not([type=hidden]):not([type=radio])')?.focus();
    };
    page.querySelectorAll('dialog.psc-dialog').forEach((dialog) => {
        dialog.addEventListener('close', () => {
            if (base && window.location.search) {
                window.history.replaceState(null, '', base);
            }
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
        dialog.querySelectorAll('[data-psc-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
        if (dialog.hasAttribute('data-psc-autoopen')) {
            openDialog(dialog);
        }
    });
    page.querySelectorAll('[data-psc-open]').forEach((link) => {
        const dialog = document.getElementById(link.dataset.pscOpen);
        if (!dialog) {
            return;
        }
        link.addEventListener('click', (event) => {
            event.preventDefault();
            openDialog(dialog);
        });
    });

    // Unir y reconciliar: elegir en la lista llena la tarjeta de la derecha y el resumen.
    page.querySelectorAll('[data-psc-pick]').forEach((form) => {
        const unir = form.dataset.pscMode === 'unir';
        const target = form.querySelector('[data-psc-target]');
        const compare = form.querySelector('[data-psc-compare]');
        const result = form.querySelector('[data-psc-result]');
        const submit = form.querySelector('[data-psc-submit]');
        const keep = form.querySelector('[data-psc-keep]');
        const fromName = form.dataset.pscFrom;
        const fromCount = Number(form.dataset.pscCount || 0);
        const labelFrom = form.querySelector('[data-psc-label-from]');
        const labelTo = form.querySelector('[data-psc-label-to]');

        const update = () => {
            const chosen = form.querySelector('input[type=radio]:checked');
            submit.disabled = !chosen;
            target.classList.toggle('is-empty', !chosen);
            if (!chosen) {
                return;
            }
            const avatar = target.querySelector('[data-psc-target-avatar]');
            avatar.textContent = chosen.dataset.iniciales;
            avatar.classList.remove('psc-avatar-ghost');
            target.querySelector('[data-psc-target-name]').textContent = chosen.dataset.nombre;
            target.querySelector('[data-psc-target-detail]').textContent = chosen.dataset.detalle;
            if (unir) {
                const kept = keep && keep.value === 'esta' ? fromName : chosen.dataset.nombre;
                const total = fromCount + Number(chosen.dataset.senderos || 0);
                result.innerHTML = 'Queda una sola ficha: <strong>' + escapeHtml(kept) + '</strong> con ' + total + ' senderos.';
            } else {
                result.innerHTML = '<strong>' + fromCount + (fromCount === 1 ? ' sendero pasa' : ' senderos pasan') + '</strong> a la cuenta de <strong>' + escapeHtml(chosen.dataset.nombre) + '</strong>.';
            }
        };
        form.addEventListener('change', update);

        form.querySelector('[data-psc-swap]')?.addEventListener('click', () => {
            const swapped = keep.value !== 'esta';
            keep.value = swapped ? 'esta' : 'destino';
            compare.classList.toggle('is-swapped', swapped);
            labelFrom.textContent = swapped ? 'Se conserva' : 'Se elimina';
            labelTo.textContent = swapped ? 'Se elimina' : 'Se conserva';
            update();
        });

        const pickSearch = form.querySelector('[data-psc-pick-search]');
        const pickEmpty = form.querySelector('[data-psc-pick-empty]');
        pickSearch?.addEventListener('input', () => {
            const term = fold(pickSearch.value.trim());
            let visibles = 0;
            form.querySelectorAll('[data-psc-option]').forEach((option) => {
                const show = !term || option.dataset.buscar.includes(term);
                option.hidden = !show;
                visibles += show ? 1 : 0;
            });
            form.querySelectorAll('[data-psc-group]').forEach((title) => {
                let next = title.nextElementSibling;
                let any = false;
                while (next && next.matches('[data-psc-option]')) {
                    any = any || !next.hidden;
                    next = next.nextElementSibling;
                }
                title.hidden = !any;
            });
            pickEmpty.hidden = visibles > 0;
        });
        // Enter en la busqueda no envia el formulario.
        pickSearch?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });

    page.querySelectorAll('form[data-psc-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.pscConfirm)) {
                event.preventDefault();
            }
        });
    });
});
