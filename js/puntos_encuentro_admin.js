document.addEventListener('DOMContentLoaded', () => {
    const idField = document.querySelector('[data-point-id]');
    const nameField = document.querySelector('[data-point-name]');
    const addressField = document.querySelector('[data-point-address]');
    const mapField = document.querySelector('[data-point-map]');
    const activeField = document.querySelector('[data-point-active]');
    const submitButton = document.querySelector('[data-point-submit]');
    const resetButton = document.querySelector('[data-point-reset]');

    const resetForm = () => {
        if (idField) idField.value = '0';
        if (nameField) nameField.value = '';
        if (addressField) addressField.value = '';
        if (mapField) mapField.value = '';
        if (activeField) activeField.checked = true;
        if (submitButton) submitButton.textContent = 'Guardar punto';
        nameField?.focus();
    };

    document.querySelectorAll('.edit-point').forEach((button) => {
        button.addEventListener('click', () => {
            if (idField) idField.value = button.dataset.id || '0';
            if (nameField) nameField.value = button.dataset.nombre || '';
            if (addressField) addressField.value = button.dataset.direccion || '';
            if (mapField) mapField.value = button.dataset.url || '';
            if (activeField) activeField.checked = button.dataset.activo === '1';
            if (submitButton) submitButton.textContent = 'Actualizar punto';
            nameField?.focus();
        });
    });

    resetButton?.addEventListener('click', resetForm);
});
