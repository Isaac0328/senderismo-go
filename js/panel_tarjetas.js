document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-pt-form]');
    if (!form) return;

    // Orden dentro de cada grupo: se guarda como 10, 20, 30... segun la posicion en pantalla.
    const renumerar = (lista) => {
        Array.from(lista.querySelectorAll('[data-pt-row]')).forEach((fila, indice) => {
            fila.querySelector('[data-pt-orden]').value = (indice + 1) * 10;
        });
    };

    form.querySelectorAll('[data-pt-list]').forEach((lista) => {
        lista.addEventListener('click', (evento) => {
            const subir = evento.target.closest('[data-pt-up]');
            const bajar = evento.target.closest('[data-pt-down]');
            if (!subir && !bajar) return;
            const fila = evento.target.closest('[data-pt-row]');
            const destino = subir ? fila.previousElementSibling : fila.nextElementSibling;
            if (!destino) return;
            if (subir) lista.insertBefore(fila, destino);
            else lista.insertBefore(destino, fila);
            fila.classList.add('is-moved');
            setTimeout(() => fila.classList.remove('is-moved'), 600);
            renumerar(lista);
        });
        renumerar(lista);
    });

    // Visible u oculta.
    form.querySelectorAll('[data-pt-activo]').forEach((casilla) => {
        casilla.addEventListener('change', () => {
            const fila = casilla.closest('[data-pt-row]');
            fila.classList.toggle('is-off', !casilla.checked);
            fila.querySelector('[data-pt-estado]').textContent = casilla.checked ? 'Visible' : 'Oculta';
        });
    });

    // Todos los roles o solo algunos.
    form.querySelectorAll('[data-pt-alcance]').forEach((selector) => {
        selector.addEventListener('change', () => {
            const lista = selector.closest('.pt-roles').querySelector('[data-pt-role-list]');
            lista.hidden = selector.value !== 'elegidos';
        });
    });

    form.addEventListener('submit', (evento) => {
        const boton = evento.submitter;
        // Restablecer pide confirmacion.
        if (boton && boton.hasAttribute('data-pt-restablecer')) {
            if (!window.confirm('Restablecer el panel? Se mostrara todo, en su orden original y para todos los roles. Se pierde la configuracion actual.')) {
                evento.preventDefault();
            }
            return;
        }
        // Una pieza limitada a roles debe tener al menos un rol.
        const sinRoles = Array.from(form.querySelectorAll('[data-pt-row]')).find((fila) => {
            const alcance = fila.querySelector('[data-pt-alcance]');
            return alcance && alcance.value === 'elegidos' && !fila.querySelector('[data-pt-role-list] input:checked');
        });
        if (sinRoles) {
            evento.preventDefault();
            sinRoles.scrollIntoView({ behavior: 'smooth', block: 'center' });
            sinRoles.classList.add('is-error');
            window.alert('Elige al menos un rol para "' + sinRoles.querySelector('.pt-info strong').textContent + '" o dejala para todos los roles.');
            return;
        }
        if (!window.confirm('Guardar los cambios? El panel se vera asi para todos los usuarios segun su rol.')) {
            evento.preventDefault();
        }
    });
});
