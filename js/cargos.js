document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('cargosTable');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr[data-id]')) : [];

    const formTitle = document.getElementById('formTitle');
    const cargoId = document.getElementById('cargoId');
    const nombre = document.getElementById('nombre');
    const descripcion = document.getElementById('descripcion');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');

    // Buscar en la tabla.
    searchInput?.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        rows.forEach((tr) => {
            tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    // Editar: pasa los datos al formulario.
    rows.forEach((tr) => {
        const editBtn = tr.querySelector('.edit-btn');
        if (!editBtn || !cargoId) return;
        editBtn.addEventListener('click', () => {
            cargoId.value = tr.getAttribute('data-id') || '0';
            nombre.value = tr.getAttribute('data-nombre') || '';
            descripcion.value = tr.getAttribute('data-descripcion') || '';
            formTitle.textContent = 'Editar cargo';
            submitBtn.textContent = 'Actualizar';
            nombre.focus();
            document.getElementById('cargoForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Limpiar formulario.
    resetBtn?.addEventListener('click', () => {
        cargoId.value = '0';
        nombre.value = '';
        descripcion.value = '';
        formTitle.textContent = 'Nuevo cargo';
        submitBtn.textContent = 'Guardar';
        nombre.focus();
    });
});
