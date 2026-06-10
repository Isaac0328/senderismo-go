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

            actionInput.value = 'save';
            formTitle.textContent = 'Nuevo Usuario';
            submitBtn.textContent = 'Guardar';
            nombre.focus();
        });
    }
});
