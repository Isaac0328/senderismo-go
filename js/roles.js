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
    const permissionSelect = document.getElementById('permissionRoleSelect');
    const permissionGrid = document.querySelector('[data-role-permissions]');
    const permissionBoxes = permissionGrid ? Array.from(permissionGrid.querySelectorAll('[data-permission-box]')) : [];
    const checkAllButton = document.querySelector('[data-permission-check-all]');
    const clearButton = document.querySelector('[data-permission-clear]');
    const permissionGroupToggles = Array.from(document.querySelectorAll('[data-permission-group-toggle]'));
    let rolePermissions = {};

    if (permissionGrid) {
        try {
            rolePermissions = JSON.parse(permissionGrid.dataset.rolePermissions || '{}');
        } catch (error) {
            rolePermissions = {};
        }
    }

    const syncPermissionBoxes = () => {
        if (!permissionSelect) return;

        const roleId = permissionSelect.value || '0';
        const selected = new Set(rolePermissions[roleId] || []);
        const isAdmin = roleId === '1';

        permissionBoxes.forEach((box) => {
            box.checked = isAdmin || selected.has(box.value);
            box.disabled = isAdmin;
        });

        if (clearButton) clearButton.disabled = isAdmin;
        if (checkAllButton) checkAllButton.disabled = isAdmin;
    };

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

    if (permissionSelect) {
        permissionSelect.addEventListener('change', syncPermissionBoxes);
        syncPermissionBoxes();
    }

    if (checkAllButton) {
        checkAllButton.addEventListener('click', () => {
            permissionBoxes.forEach((box) => {
                if (!box.disabled) box.checked = true;
            });
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', () => {
            permissionBoxes.forEach((box) => {
                if (!box.disabled) box.checked = false;
            });
        });
    }

    permissionGroupToggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const group = toggle.closest('[data-permission-group]');
            const list = group?.querySelector('.permission-list');
            if (!group || !list) return;

            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            group.classList.toggle('is-collapsed', expanded);
            list.hidden = expanded;
        });
    });
});
