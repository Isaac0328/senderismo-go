// Prepara las tablas del panel para verse como tarjetas en telefonos
// (css/admin_comun.css): cada celda recibe como etiqueta el titulo de su
// columna y la celda con botones o formularios se marca como "acciones".
document.addEventListener('DOMContentLoaded', () => {
    // Formularios que piden confirmacion antes de enviarse (por ejemplo, el cuadre de un sendero).
    document.querySelectorAll('form.sg-cuadre-sendero[data-confirm]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            if (!window.confirm(formulario.dataset.confirm)) evento.preventDefault();
        });
    });

    const cajas = '.fin-catalog-table-wrap, .bal-table-wrap, .passport-table-wrap, .roles-table-wrap, .usuarios-page .table-wrap, .table-wrap-report';

    const etiquetar = (tabla) => {
        const titulos = [...tabla.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        tabla.querySelectorAll('tbody tr, tfoot tr').forEach((fila) => {
            [...fila.children].forEach((celda, indice) => {
                if (celda.hasAttribute('colspan')) return;
                const esUltima = indice === fila.children.length - 1;
                if (esUltima && indice > 0 && celda.querySelector('form, button, a')) {
                    celda.dataset.acciones = '';
                } else if (indice > 0 && titulos[indice]) {
                    celda.dataset.label = titulos[indice];
                }
            });
        });
    };

    document.querySelectorAll(cajas).forEach((caja) => {
        caja.querySelectorAll('table').forEach((tabla) => {
            etiquetar(tabla);
            // Algunas pantallas agregan o cambian filas despues de cargar.
            const cuerpo = tabla.querySelector('tbody');
            if (cuerpo) new MutationObserver(() => etiquetar(tabla)).observe(cuerpo, { childList: true });
        });
    });
});
