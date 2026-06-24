document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-catalog-table-search]').forEach(function (input) {
        const card = input.closest('.fin-card');
        const tableWrap = card ? card.querySelector('.fin-catalog-table-wrap') : null;
        const table = tableWrap ? tableWrap.querySelector('table') : null;
        const tbody = table ? table.querySelector('tbody') : null;
        const rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];

        if (!tbody || rows.length === 0) {
            return;
        }

        const emptyRow = document.createElement('tr');
        emptyRow.className = 'fin-filter-empty-row';
        emptyRow.hidden = true;
        emptyRow.innerHTML = '<td colspan="' + (table.querySelectorAll('thead th').length || 1) + '">No hay resultados para esta busqueda.</td>';
        tbody.appendChild(emptyRow);

        input.addEventListener('input', function () {
            const term = input.value.trim().toLocaleLowerCase();
            let visible = 0;

            rows.forEach(function (row) {
                const matches = !term || row.textContent.toLocaleLowerCase().includes(term);
                row.hidden = !matches;
                if (matches) {
                    visible += 1;
                }
            });

            emptyRow.hidden = visible > 0;
        });
    });
});
