// En telefonos cada fila se muestra como tarjeta: cada celda toma como etiqueta
// el titulo de su columna (la primera y la de acciones no la necesitan).
document.addEventListener('DOMContentLoaded', () => {
    const search = document.querySelector('[data-participant-search]');
    if (search) {
        const input = search.querySelector('input');
        const popup = search.querySelector('.cu-suggestions');
        const list = search.querySelector('[role="listbox"]');
        const heading = search.querySelector('[role="status"]');
        const form = search.closest('form');
        const people = JSON.parse(search.querySelector('script').textContent);
        const fold = (value) => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        let matches = [];
        let active = -1;
        const close = () => {
            popup.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        };
        const select = (person) => {
            const url = new URL(form.action, window.location.href);
            const params = new URLSearchParams(new FormData(form));
            params.set('cuenta', person.key);
            params.set('q', '');
            params.set('tipo', 'todos');
            params.set('situacion', 'todos');
            params.set('pagina', '1');
            url.search = params.toString();
            window.location.assign(url);
        };
        const activate = (index) => {
            active = index;
            [...list.children].forEach((row, i) => row.setAttribute('aria-selected', String(i === active)));
            if (active >= 0) {
                input.setAttribute('aria-activedescendant', list.children[active].id);
                list.children[active].scrollIntoView({ block: 'nearest' });
            }
        };
        const render = () => {
            const terms = fold(input.value.trim()).split(/\s+/).filter(Boolean);
            const type = form.elements.tipo.value;
            matches = people.filter((person) => (type === 'todos' || type === person.tipo)
                && terms.every((term) => fold([person.nombre, person.usuario, person.email, person.telefono].join(' ')).includes(term)));
            active = -1;
            input.removeAttribute('aria-activedescendant');
            list.replaceChildren();
            heading.textContent = matches.length ? `${matches.length} participantes` : 'No se encontraron participantes';
            matches.forEach((person, index) => {
                const row = document.createElement('div');
                row.id = `cu-person-${index}`;
                row.className = 'cu-suggestion';
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', 'false');
                const avatar = document.createElement('span');
                avatar.className = 'cu-suggestion-avatar';
                avatar.setAttribute('aria-hidden', 'true');
                avatar.textContent = person.nombre.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
                const info = document.createElement('div');
                info.className = 'cu-suggestion-info';
                const name = document.createElement('strong');
                name.textContent = person.nombre;
                const contact = document.createElement('small');
                contact.textContent = [person.usuario ? `@${person.usuario}` : '', person.email, person.telefono].filter(Boolean).join(' · ');
                info.append(name, contact);
                const badge = document.createElement('span');
                badge.className = 'cu-suggestion-type';
                badge.textContent = person.tipo === 'temporal' ? `Temporal #${person.id}` : 'Registrado';
                row.append(avatar, info, badge);
                row.addEventListener('mousedown', (event) => event.preventDefault());
                row.addEventListener('click', () => select(person));
                list.append(row);
            });
            popup.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };
        input.addEventListener('focus', render);
        input.addEventListener('input', render);
        input.addEventListener('search', render);
        input.addEventListener('blur', close);
        form.elements.tipo.addEventListener('change', close);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { event.preventDefault(); close(); }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (popup.hidden) { render(); }
                if (matches.length) { activate((active + (event.key === 'ArrowDown' ? 1 : active < 0 ? 0 : -1) + matches.length) % matches.length); }
            }
            if (event.key === 'Enter' && !popup.hidden && active >= 0) {
                event.preventDefault();
                select(matches[active]);
            }
            if (event.key === 'Tab') { close(); }
        });
    }
    document.querySelectorAll('.cu-table-wrap table').forEach((table) => {
        const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        table.querySelectorAll('tbody tr, tfoot tr').forEach((row) => {
            [...row.children].forEach((cell, index) => {
                if (index > 0 && headers[index] && !cell.classList.contains('cu-empty')) {
                    cell.dataset.label = headers[index];
                }
            });
        });
    });
});
