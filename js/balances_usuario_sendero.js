// "Cargar a usuario": cambia la persona o entidad por un usuario registrado.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cargar-usuario]').forEach((casilla) => {
        const form = casilla.closest('form');
        const bloqueUsuario = form.querySelector('[data-cu-usuario]');
        const usuario = bloqueUsuario.querySelector('select');
        const tercero = form.querySelector('select[name="tercero_id"]');
        const aplicar = () => {
            const activo = casilla.checked;
            bloqueUsuario.hidden = !activo;
            usuario.disabled = !activo;
            usuario.required = activo;
            if (tercero) {
                tercero.closest('label').hidden = activo;
                tercero.disabled = activo;
            }
        };
        casilla.addEventListener('change', aplicar);
        aplicar();
    });
});
