document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const minorFilter = document.getElementById('minorFilter');
    const table = document.getElementById('usuariosTable');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr')) : [];

    const formTitle = document.getElementById('formTitle');
    const actionInput = document.getElementById('action');
    const userId = document.getElementById('userId');

    const nombre = document.getElementById('nombre');
    const apellido = document.getElementById('apellido');
    const user = document.getElementById('user');
    const email = document.getElementById('email');
    const rol_id = document.getElementById('rol_id');
    // Casilla de directiva (aparte de rol y cargos).
    const marcarDirectiva = (activa) => {
        const casilla = document.getElementById('es_directiva');
        if (casilla) casilla.checked = activa;
    };
    // Cargos (casillas aparte del rol): se marcan los del usuario que se edita.
    const marcarCargos = (ids) => {
        document.querySelectorAll('[data-cargos-lista] input[type=checkbox]').forEach((casilla) => {
            casilla.checked = ids.includes(casilla.value);
        });
    };
    const password = document.getElementById('password');
    const detailFields = [
        'telefono',
        'rango_edad',
        'identificacion',
        'es_alergico',
        'alergias_detalle',
        'grupo_sanguineo',
        'enfermedad',
        'seguro_medico',
        'experiencia_senderismo',
        'via_entero',
        'referido_nombre',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono'
    ];

    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    const form = document.getElementById('userForm');
    const formCard = document.querySelector('[data-user-form-card]');
    const formToggle = document.querySelector('[data-user-form-toggle]');
    const minorTemplate = document.getElementById('userMinorTemplate');
    const minorsEditor = document.querySelector('[data-user-minors-editor]');
    const minorsFields = document.querySelector('[data-user-minors-fields]');
    const minorsCount = document.querySelector('[data-user-minors-count]');
    const addMinorBtn = document.querySelector('[data-add-user-minor]');
    let userMinors = [];

    const minorFields = [
        'menor_usuario_id',
        'nombre',
        'apellido',
        'telefono',
        'rango_edad',
        'grupo_sanguineo',
        'es_alergico',
        'alergias_detalle',
        'experiencia_senderismo',
        'enfermedad',
        'seguro_medico',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono',
        'activo'
    ];

    const normalizeMinor = (minor = {}) => ({
        menor_usuario_id: minor.menor_usuario_id || minor.id || '',
        nombre: minor.nombre || '',
        apellido: minor.apellido || '',
        telefono: minor.telefono || '',
        rango_edad: minor.rango_edad || '',
        grupo_sanguineo: minor.grupo_sanguineo || '',
        es_alergico: String(minor.es_alergico ?? '0') === '1' ? '1' : '0',
        alergias_detalle: minor.alergias_detalle || '',
        experiencia_senderismo: minor.experiencia_senderismo || '',
        enfermedad: minor.enfermedad || '',
        seguro_medico: minor.seguro_medico || '',
        emergencia_nombre: minor.emergencia_nombre || '',
        emergencia_parentesco: minor.emergencia_parentesco || '',
        emergencia_telefono: minor.emergencia_telefono || '',
        activo: String(minor.activo ?? '1') === '0' ? '0' : '1'
    });

    // Crear y Consultar: dos vistas de la misma pagina.
    const vistaTabs = Array.from(document.querySelectorAll('[data-vista-tab]'));
    const vistaPaneles = Array.from(document.querySelectorAll('[data-vista-panel]'));
    const setVista = (vista) => {
        vistaPaneles.forEach((panel) => { panel.hidden = panel.dataset.vistaPanel !== vista; });
        vistaTabs.forEach((tab) => {
            tab.setAttribute('aria-selected', tab.dataset.vistaTab === vista ? 'true' : 'false');
            tab.classList.toggle('is-active', tab.dataset.vistaTab === vista);
        });
        const url = new URL(window.location.href);
        url.searchParams.set('vista', vista);
        url.searchParams.delete('edit');
        window.history.replaceState(null, '', url);
    };
    vistaTabs.forEach((tab) => tab.addEventListener('click', () => setVista(tab.dataset.vistaTab)));
    document.querySelectorAll('[data-vista-ir]').forEach((boton) => boton.addEventListener('click', () => { resetBtn?.click(); setVista(boton.dataset.vistaIr); }));
    setVista(document.querySelector('[data-vista-inicial]')?.dataset.vistaInicial === 'consultar' ? 'consultar' : 'crear');
    const cancelEditBtn = document.getElementById('cancelEditBtn');


    const collectUserMinors = () => {
        if (!minorsEditor) return [];
        return [...minorsEditor.querySelectorAll('[data-user-minor-card]')].map((card) => {
            const minor = {};
            minorFields.forEach((field) => {
                const input = card.querySelector(`[data-minor-field="${field}"]`);
                minor[field] = input ? input.value.trim() : '';
            });
            return normalizeMinor(minor);
        }).filter((minor) => minor.nombre || minor.apellido);
    };

    const renderHiddenUserMinors = () => {
        if (!minorsFields) return;
        minorsFields.innerHTML = '';
        userMinors.forEach((minor, index) => {
            minorFields.forEach((field) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `menores_usuario[${index}][${field}]`;
                input.value = minor[field] ?? '';
                minorsFields.appendChild(input);
            });
        });
    };

    const updateUserMinorTitles = () => {
        if (!minorsEditor) return;
        const cards = [...minorsEditor.querySelectorAll('[data-user-minor-card]')];
        cards.forEach((card, index) => {
            const title = card.querySelector('[data-user-minor-title]');
            const active = card.querySelector('[data-minor-field="activo"]');
            if (title) title.textContent = `Menor ${index + 1}`;
            card.classList.toggle('is-inactive', active && active.value === '0');
        });
        if (minorsCount) {
            const activeCount = cards.filter((card) => (card.querySelector('[data-minor-field="activo"]')?.value || '1') === '1').length;
            minorsCount.textContent = `${activeCount} ${activeCount === 1 ? 'menor activo' : 'menores activos'}`;
        }
    };

    const createUserMinorCard = (minor = {}) => {
        if (!minorTemplate || !minorsEditor) return;
        const fragment = minorTemplate.content.cloneNode(true);
        const card = fragment.querySelector('[data-user-minor-card]');
        const data = normalizeMinor(minor);

        minorFields.forEach((field) => {
            const input = card.querySelector(`[data-minor-field="${field}"]`);
            if (input) input.value = data[field] ?? '';
        });

        card.querySelector('[data-remove-user-minor]')?.addEventListener('click', () => {
            const idInput = card.querySelector('[data-minor-field="menor_usuario_id"]');
            const activeInput = card.querySelector('[data-minor-field="activo"]');
            if (idInput && idInput.value && activeInput) {
                activeInput.value = '0';
                card.classList.add('is-inactive');
            } else {
                card.remove();
            }
            updateUserMinorTitles();
        });
        card.querySelector('[data-minor-field="activo"]')?.addEventListener('change', updateUserMinorTitles);

        minorsEditor.appendChild(fragment);
        updateUserMinorTitles();
    };

    const renderUserMinorsEditor = (minors = []) => {
        if (!minorsEditor) return;
        minorsEditor.innerHTML = '';
        minors.map(normalizeMinor).forEach((minor) => createUserMinorCard(minor));
        userMinors = minors.map(normalizeMinor);
        renderHiddenUserMinors();
        updateUserMinorTitles();
    };

    // Buscar
    const normalizeSearch = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
    const filterUsers = () => {
        const q = normalizeSearch(searchInput?.value || '');
        const mode = minorFilter?.value || 'all';
        rows.forEach(tr => {
            if (!tr.dataset.id) return;
            const count = Number(tr.dataset.minorCount || 0);
            const matchesMinorFilter = mode === 'all' || (mode === 'with' ? count > 0 : count === 0);
            const text = normalizeSearch(`${tr.innerText} ${tr.dataset.minorSearch || ''}`);
            tr.hidden = !matchesMinorFilter || !text.includes(q);
        });
    };
    searchInput?.addEventListener('input', filterUsers);
    minorFilter?.addEventListener('change', filterUsers);

    // Editar
    rows.forEach(tr => {
        const editBtn = tr.querySelector('.edit-btn');
        if (!editBtn) return;

        editBtn.addEventListener('click', () => {
            userId.value = tr.getAttribute('data-id') || '0';
            nombre.value = tr.getAttribute('data-nombre') || '';
            apellido.value = tr.getAttribute('data-apellido') || '';
            user.value = tr.getAttribute('data-user') || '';
            email.value = tr.getAttribute('data-email') || '';
            rol_id.value = tr.getAttribute('data-rol_id') || '';
            marcarCargos((tr.getAttribute('data-cargos') || '').split(',').filter(Boolean));
            marcarDirectiva(tr.getAttribute('data-es_directiva') === '1');
            password.value = ''; // nunca cargar pass
            detailFields.forEach(field => {
                const input = document.getElementById(field);
                if (input) input.value = tr.getAttribute(`data-${field}`) || '';
            });
            try {
                const minors = JSON.parse(tr.getAttribute('data-menores') || '[]');
                renderUserMinorsEditor(Array.isArray(minors) ? minors : []);
            } catch (error) {
                renderUserMinorsEditor([]);
            }

            actionInput.value = 'save';
            formTitle.textContent = 'Editar Usuario';
            submitBtn.textContent = 'Actualizar';

            if (cancelEditBtn) cancelEditBtn.hidden = false;
            setVista('crear');
            nombre.focus();
            formCard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    const requestedEditId = new URLSearchParams(window.location.search).get('edit');
    if (requestedEditId) {
        const requestedRow = rows.find((row) => row.getAttribute('data-id') === requestedEditId);
        requestedRow?.querySelector('.edit-btn')?.click();
    }

    // Limpiar
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            userId.value = '0';
            nombre.value = '';
            apellido.value = '';
            user.value = '';
            email.value = '';
            rol_id.value = '';
            marcarCargos([]);
            marcarDirectiva(false);
            password.value = '';
            detailFields.forEach(field => {
                const input = document.getElementById(field);
                if (input) input.value = field === 'es_alergico' ? '0' : '';
            });
            renderUserMinorsEditor([]);

            actionInput.value = 'save';
            formTitle.textContent = 'Nuevo Usuario';
            submitBtn.textContent = 'Guardar';
            if (cancelEditBtn) cancelEditBtn.hidden = true;
        });
    }

    addMinorBtn?.addEventListener('click', () => createUserMinorCard({}));

    form?.addEventListener('submit', (event) => {
        // Al actualizar un usuario existente se pide confirmacion.
        if (userId.value !== '0' && !window.confirm('¿Seguro que deseas actualizar a ' + (nombre.value + ' ' + apellido.value).trim() + '? Se cambiarán sus datos.')) {
            event.preventDefault();
            return;
        }
        userMinors = collectUserMinors();
        renderHiddenUserMinors();
    });

    // Inactivar, activar y eliminar piden confirmacion.
    document.querySelectorAll('form[data-confirmar]').forEach((formulario) => {
        formulario.addEventListener('submit', (event) => {
            if (!window.confirm(formulario.dataset.confirmar)) event.preventDefault();
        });
    });

    renderUserMinorsEditor([]);
});
