document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
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
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            rows.forEach(tr => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

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

            nombre.focus();
            form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Limpiar
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            userId.value = '0';
            nombre.value = '';
            apellido.value = '';
            user.value = '';
            email.value = '';
            rol_id.value = '';
            password.value = '';
            detailFields.forEach(field => {
                const input = document.getElementById(field);
                if (input) input.value = field === 'es_alergico' ? '0' : '';
            });
            renderUserMinorsEditor([]);

            actionInput.value = 'save';
            formTitle.textContent = 'Nuevo Usuario';
            submitBtn.textContent = 'Guardar';
            nombre.focus();
        });
    }

    addMinorBtn?.addEventListener('click', () => createUserMinorCard({}));

    form?.addEventListener('submit', () => {
        userMinors = collectUserMinors();
        renderHiddenUserMinors();
    });

    renderUserMinorsEditor([]);
});
