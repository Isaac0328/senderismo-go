document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.catalog-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const catalog = button.dataset.toggleCatalog;
            const card = catalog
                ? document.querySelector(`[data-catalog-card="${catalog}"]`)
                : button.closest('.catalog-card');

            if (!card) return;

            const collapsed = card.classList.toggle('is-collapsed');
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    });

    document.querySelectorAll('.edit-detail').forEach((button) => {
        button.addEventListener('click', () => {
            const catalog = button.dataset.catalog;
            const id = button.dataset.id || '0';
            const nombre = button.dataset.nombre || '';
            const descripcion = button.dataset.descripcion || '';
            const nivel = button.dataset.nivel || '50';
            const orden = button.dataset.orden || '0';
            const activo = button.dataset.activo === '1';

            const idField = document.querySelector(`[data-id-field="${catalog}"]`);
            const nameField = document.querySelector(`[data-name-field="${catalog}"]`);
            const descField = document.querySelector(`[data-desc-field="${catalog}"]`);
            const levelField = document.querySelector(`[data-level-field="${catalog}"]`);
            const orderField = document.querySelector(`[data-order-field="${catalog}"]`);
            const activeField = document.querySelector(`[data-active-field="${catalog}"]`);
            const submit = document.querySelector(`[data-submit-field="${catalog}"]`);

            if (idField) idField.value = id;
            if (nameField) nameField.value = nombre;
            if (descField) descField.value = descripcion;
            if (levelField) levelField.value = nivel;
            if (orderField) orderField.value = orden;
            if (activeField) activeField.checked = activo;
            if (submit) submit.textContent = 'Actualizar';

            const card = document.querySelector(`[data-catalog-card="${catalog}"]`);
            const toggle = document.querySelector(`[data-toggle-catalog="${catalog}"]`);
            card?.classList.remove('is-collapsed');
            toggle?.setAttribute('aria-expanded', 'true');

            nameField?.focus();
        });
    });

    document.querySelectorAll('.reset-catalog').forEach((button) => {
        button.addEventListener('click', () => {
            const catalog = button.dataset.catalog;
            const idField = document.querySelector(`[data-id-field="${catalog}"]`);
            const nameField = document.querySelector(`[data-name-field="${catalog}"]`);
            const descField = document.querySelector(`[data-desc-field="${catalog}"]`);
            const levelField = document.querySelector(`[data-level-field="${catalog}"]`);
            const orderField = document.querySelector(`[data-order-field="${catalog}"]`);
            const activeField = document.querySelector(`[data-active-field="${catalog}"]`);
            const submit = document.querySelector(`[data-submit-field="${catalog}"]`);

            if (idField) idField.value = '0';
            if (nameField) nameField.value = '';
            if (descField) descField.value = '';
            if (levelField) levelField.value = '50';
            if (orderField) orderField.value = '0';
            if (activeField) activeField.checked = true;
            if (submit) submit.textContent = 'Guardar';

            nameField?.focus();
        });
    });
});
