document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('passportForm');
    if (!form) return;

    const title = document.getElementById('passportFormTitle');
    const fields = {
        id: form.querySelector('[data-passport-id]'),
        nombre: form.querySelector('[data-passport-nombre]'),
        descripcion: form.querySelector('[data-passport-descripcion]'),
        icono: form.querySelector('[data-passport-icono]'),
        color: form.querySelector('[data-passport-color]'),
        senderos: form.querySelector('[data-passport-senderos]'),
        km: form.querySelector('[data-passport-km]'),
        orden: form.querySelector('[data-passport-orden]'),
        activo: form.querySelector('[data-passport-activo]'),
        submit: form.querySelector('[data-passport-submit]')
    };

    function resetForm() {
        form.reset();
        if (fields.id) fields.id.value = '0';
        if (fields.color) fields.color.value = '#0f7a3f';
        if (fields.senderos) fields.senderos.value = '0';
        if (fields.km) fields.km.value = '0';
        if (fields.orden) fields.orden.value = '0';
        if (fields.activo) fields.activo.checked = true;
        if (fields.submit) fields.submit.textContent = 'Guardar nivel';
        if (title) title.textContent = 'Crear clasificacion';
    }

    document.querySelectorAll('[data-passport-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (fields.id) fields.id.value = button.dataset.id || '0';
            if (fields.nombre) fields.nombre.value = button.dataset.nombre || '';
            if (fields.descripcion) fields.descripcion.value = button.dataset.descripcion || '';
            if (fields.icono) fields.icono.value = button.dataset.icono || 'map';
            if (fields.color) fields.color.value = button.dataset.color || '#0f7a3f';
            if (fields.senderos) fields.senderos.value = button.dataset.minSenderos || '0';
            if (fields.km) fields.km.value = button.dataset.minKm || '0';
            if (fields.orden) fields.orden.value = button.dataset.orden || '0';
            if (fields.activo) fields.activo.checked = (button.dataset.activo || '1') === '1';
            if (fields.submit) fields.submit.textContent = 'Actualizar nivel';
            if (title) title.textContent = 'Editar clasificacion';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (fields.nombre) fields.nombre.focus();
        });
    });

    const resetButton = document.querySelector('[data-passport-reset]');
    if (resetButton) {
        resetButton.addEventListener('click', resetForm);
    }
});
