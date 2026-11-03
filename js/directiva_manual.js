// Al agregar a mano a un miembro de la directiva, la inversion del adulto no aplica: se oculta y no se exige.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-participante-form]');
    if (!form) return;
    const hidden = form.querySelector('[data-user-id-input]');
    const campo = form.querySelector('[data-adult-investment]');
    const select = campo?.querySelector('select[name="inversion_id"]');
    if (!hidden || !campo || !select) return;

    const nota = document.createElement('p');
    nota.textContent = 'Miembro de la directiva: su participacion no tiene cobro y no necesita inversion. Si lleva menores, ellos si pagan.';
    nota.style.cssText = 'margin:0;padding:10px 12px;border-radius:8px;background:#f0faf3;border:1px solid #b7e1c3;color:#14532d;font-size:.86rem;font-weight:700;line-height:1.4';
    nota.hidden = true;
    campo.after(nota);

    let aplicado = false;
    const sincronizar = () => {
        const modo = form.querySelector('input[name="tipo_participante"]:checked')?.value;
        const opcion = Array.from(form.querySelectorAll('[data-user-option]')).find((o) => o.dataset.userId === hidden.value);
        const directiva = modo === 'existente' && Boolean(hidden.value) && opcion?.dataset.directiva === '1';
        if (directiva) {
            campo.style.display = 'none';
            select.disabled = true;
            select.required = false;
            select.value = '';
            nota.hidden = false;
            aplicado = true;
        } else if (aplicado) {
            nota.hidden = true;
            aplicado = false;
            if (modo !== 'registrado') {
                campo.style.display = '';
                select.disabled = false;
                select.required = true;
            }
        }
    };

    // Despues de que la pagina actualice sus propios campos.
    const luego = () => setTimeout(sincronizar, 0);
    form.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-user-option]')) luego();
    });
    form.querySelector('[data-user-search-input]')?.addEventListener('input', luego);
    form.querySelectorAll('input[name="tipo_participante"]').forEach((radio) => radio.addEventListener('change', luego));
});