(function () {
    const tabButtons = Array.from(document.querySelectorAll('[data-results-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-results-panel]'));
    const search = document.querySelector('[data-response-search]');

    function activateTab(name) {
        tabButtons.forEach((button) => {
            const active = button.dataset.resultsTab === name;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            panel.hidden = panel.dataset.resultsPanel !== name;
        });
    }

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => activateTab(button.dataset.resultsTab || 'resumen'));
    });

    document.querySelectorAll('[data-response-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('[data-response-item]');
            const body = item?.querySelector('[data-response-body]');
            if (!body) return;

            const expanded = button.getAttribute('aria-expanded') === 'true';
            body.hidden = expanded;
            button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            button.setAttribute('aria-label', expanded ? 'Desplegar respuesta' : 'Plegar respuesta');
            button.setAttribute('title', expanded ? 'Desplegar respuesta' : 'Plegar respuesta');
        });
    });

    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('[data-response-item]').forEach((item) => {
            const hayCoincidencia = term === '' || (item.dataset.searchText || '').includes(term);
            item.hidden = !hayCoincidencia;
            if (hayCoincidencia) visible++;
        });

        const empty = document.querySelector('[data-response-empty]');
        if (empty) empty.hidden = visible > 0;
    });
})();
