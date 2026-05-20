document.addEventListener('DOMContentLoaded', () => {
    const alergiaRadios = document.querySelectorAll('input[name="es_alergico"]');
    const alergiaDetalle = document.querySelector('input[name="alergias_detalle"]');
    const viaSelect = document.querySelector('select[name="via_entero"]');
    const referidoInput = document.querySelector('input[name="referido_nombre"]');

    const syncAlergia = () => {
        if (!alergiaDetalle) return;
        const checked = document.querySelector('input[name="es_alergico"]:checked');
        const requiereDetalle = checked && checked.value === '1';
        alergiaDetalle.required = requiereDetalle;
        alergiaDetalle.closest('.field')?.classList.toggle('is-required', requiereDetalle);
    };

    const syncReferido = () => {
        if (!viaSelect || !referidoInput) return;
        const requiereReferido = viaSelect.value === 'Amigos';
        referidoInput.required = requiereReferido;
        referidoInput.closest('.field')?.classList.toggle('is-required', requiereReferido);
    };

    alergiaRadios.forEach((radio) => radio.addEventListener('change', syncAlergia));
    viaSelect?.addEventListener('change', syncReferido);

    document.querySelectorAll('.consent-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('aria-controls');
            const target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;

            const isOpen = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isOpen));
            button.classList.toggle('is-open', !isOpen);
            target.hidden = isOpen;
            button.querySelector('span').textContent = isOpen ? 'Leer consentimiento completo' : 'Ocultar consentimiento';
        });
    });

    syncAlergia();
    syncReferido();
});
