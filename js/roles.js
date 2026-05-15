document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('rolesTable');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr')) : [];

    const formTitle = document.getElementById('formTitle');
    const actionInput = document.getElementById('action');
    const roleId = document.getElementById('roleId');
    const nombre = document.getElementById('nombre');
    const descripcion = document.getElementById('descripcion');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');

    // Buscar en tabla
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();

            rows.forEach(tr => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // Editar: pasar data al formulario
    rows.forEach(tr => {
        const editBtn = tr.querySelector('.edit-btn');
        if (!editBtn) return;

        editBtn.addEventListener('click', () => {
            const id = tr.getAttribute('data-id') || '0';
            const n = tr.getAttribute('data-nombre') || '';
            const d = tr.getAttribute('data-descripcion') || '';

            roleId.value = id;
            nombre.value = n;
            descripcion.value = d;

            actionInput.value = 'save';
            formTitle.textContent = 'Editar Rol';
            submitBtn.textContent = 'Actualizar';

            // pequeño efecto
            nombre.focus();
            document.getElementById('roleForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Limpiar formulario
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            roleId.value = '0';
            nombre.value = '';
            descripcion.value = '';
            actionInput.value = 'save';
            formTitle.textContent = 'Nuevo Rol';
            submitBtn.textContent = 'Guardar';
            nombre.focus();
        });
    }
});
