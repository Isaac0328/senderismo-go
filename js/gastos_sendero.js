// Gastos por sendero: secciones por categoria del catalogo y "Otros gastos" en grupos libres.
// Se edita todo en pantalla y se guarda con el boton "Guardar gastos". El servidor
// (componentes/gastos_sendero.php) vuelve a validar cada linea.
document.addEventListener('DOMContentLoaded', () => {
    const app = document.querySelector('[data-gastos]');
    const fuente = document.getElementById('gs-datos');
    if (!app || !fuente) return;
    const datos = JSON.parse(fuente.textContent || 'null');
    if (!datos) return;

    const dinero = (n) => 'RD$ ' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const numero = (v) => {
        const n = parseFloat(String(v ?? '').replace(/,/g, '').trim());
        return Number.isFinite(n) ? n : 0;
    };
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const totalLinea = (l) => Math.round(Math.max(0, numero(l.cantidad)) * Math.max(0, numero(l.costo)) * 100) / 100;

    const articulos = new Map(datos.articulos.map((a) => [a.id, a]));
    const categorias = [...datos.categorias];
    let uid = 0;
    const nuevoUid = () => 'n' + (++uid);

    // ---------- Estado ----------
    const grupos = [];   // otros gastos: [{uid, nombre, abierto}]
    const lineas = datos.lineas.map((l) => {
        const base = {
            uid: nuevoUid(), id: l.id, cantidad: String(l.cantidad), costo: l.costo.toFixed(2), nota: l.nota,
            contabilizado: l.contabilizado, error: '',
        };
        if (l.gasto_id) {
            const articulo = articulos.get(l.gasto_id);
            return { ...base, tipo: 'catalogo', gasto_id: l.gasto_id, categoria: articulo ? articulo.categoria_id : 0 };
        }
        let grupo = grupos.find((g) => g.nombre === l.grupo);
        if (!grupo) {
            grupo = { uid: nuevoUid(), nombre: l.grupo || 'Sin nombre' };
            grupos.push(grupo);
        }
        return { ...base, tipo: 'otro', grupo: grupo.uid, descripcion: l.descripcion };
    });

    // Secciones: categorias activas, las que usan las lineas guardadas y "Sin categoria".
    const conLineas = new Set(lineas.filter((l) => l.tipo === 'catalogo').map((l) => l.categoria));
    const usadas = new Set(conLineas);
    datos.articulos.forEach((a) => { if (a.activo) usadas.add(a.categoria_id); });
    // Activas siempre; inactivas solo si este sendero ya tiene gastos en ellas.
    const secciones = categorias.filter((c) => c.activo || conLineas.has(c.id));
    conLineas.forEach((id) => {
        if (id !== 0 && !secciones.some((c) => c.id === id)) secciones.push({ id, nombre: 'Otra categoria' });
    });
    if (usadas.has(0)) secciones.push({ id: 0, nombre: 'Sin categoria' });
    const abiertas = new Set(secciones.filter((s) => lineas.some((l) => l.tipo === 'catalogo' && l.categoria === s.id)).map((s) => s.id));

    let sucio = false;
    const estado = app.querySelector('[data-estado]');
    const marcarSucio = () => {
        sucio = true;
        estado.textContent = 'Cambios sin guardar';
        estado.dataset.tipo = 'pendiente';
    };
    window.addEventListener('beforeunload', (e) => {
        if (!sucio) return;
        e.preventDefault();
        e.returnValue = '';
    });

    // ---------- Validacion (igual que el servidor) ----------
    const validar = (l) => {
        if (l.tipo === 'otro') {
            const grupo = grupos.find((g) => g.uid === l.grupo);
            if (!grupo || !grupo.nombre.trim()) return 'Ponle nombre al grupo de otros gastos.';
            if (!String(l.descripcion || '').trim()) return 'Escribe en que se gasto.';
        } else if (!articulos.has(l.gasto_id)) {
            return 'Elige el gasto del catalogo.';
        }
        if (numero(l.cantidad) <= 0) return 'La cantidad debe ser mayor que cero.';
        if (numero(l.costo) < 0) return 'El costo no puede ser negativo.';
        if (totalLinea(l) <= 0) return 'Indica el costo: el total no puede quedar en cero.';
        return '';
    };

    // ---------- Pintar ----------
    const contSecciones = app.querySelector('[data-secciones]');
    const contGrupos = app.querySelector('[data-grupos]');

    const filaHtml = (l) => {
        const comun = `
            <td class="gs-num"><input type="text" inputmode="decimal" data-campo="cantidad" value="${esc(l.cantidad)}" placeholder="Cantidad" aria-label="Cantidad"></td>
            <td class="gs-num"><input type="text" inputmode="decimal" data-campo="costo" value="${esc(l.costo)}" placeholder="Costo c/u" aria-label="Costo unitario"></td>
            <td class="gs-num"><strong data-total-linea>${dinero(totalLinea(l))}</strong></td>
            <td><input type="text" data-campo="nota" value="${esc(l.nota)}" maxlength="255" placeholder="Opcional" aria-label="Nota"></td>
            <td class="gs-accion">
                ${l.contabilizado ? '<span class="gs-insignia" title="Ya esta asignada a una cuenta en Balances">Balances</span>' : ''}
                <button type="button" class="gs-quitar" data-quitar title="Quitar esta linea" aria-label="Quitar linea"><i data-feather="trash-2"></i></button>
            </td>`;
        if (l.tipo === 'otro') {
            return `<tr data-linea="${l.uid}" class="${l.error ? 'has-error' : ''}" title="${esc(l.error)}">
                <td><input type="text" data-campo="descripcion" value="${esc(l.descripcion)}" maxlength="160" placeholder="Ej.: Peaje, comida del guia" aria-label="Descripcion"></td>
                ${comun}</tr>`;
        }
        const opciones = datos.articulos
            .filter((a) => a.categoria_id === l.categoria && (a.activo || a.id === l.gasto_id))
            .map((a) => `<option value="${a.id}" ${a.id === l.gasto_id ? 'selected' : ''}>${esc(a.nombre)}</option>`).join('');
        const unidad = articulos.get(l.gasto_id)?.unidad || '';
        return `<tr data-linea="${l.uid}" class="${l.error ? 'has-error' : ''}" title="${esc(l.error)}">
            <td><select data-campo="gasto_id" aria-label="Gasto"><option value="">Elige un gasto...</option>${opciones}</select>
                ${unidad ? `<small class="gs-unidad">por ${esc(unidad)}</small>` : ''}</td>
            ${comun}</tr>`;
    };

    const tablaHtml = (filas, primera) => `
        <div class="gs-tabla-wrap"><table class="gs-tabla">
            <thead><tr><th>${primera}</th><th class="gs-num">Cantidad</th><th class="gs-num">Costo unitario</th><th class="gs-num">Total</th><th>Nota</th><th></th></tr></thead>
            <tbody>${filas.map(filaHtml).join('')}</tbody>
        </table></div>`;

    const pintar = () => {
        contSecciones.innerHTML = secciones.map((s) => {
            const suyas = lineas.filter((l) => l.tipo === 'catalogo' && l.categoria === s.id);
            const abierta = abiertas.has(s.id);
            const hayArticulos = datos.articulos.some((a) => a.categoria_id === s.id && a.activo);
            return `<section class="fin-card gs-seccion ${abierta ? 'is-abierta' : ''}" data-seccion="${s.id}">
                <header class="gs-seccion-head">
                    <button type="button" class="gs-plegar" data-plegar aria-expanded="${abierta}">
                        <i data-feather="chevron-right"></i>
                        <h3>${esc(s.nombre)}</h3>
                        <span class="gs-cuenta">${suyas.length ? suyas.length + ' gasto(s)' : 'Sin gastos'}</span>
                    </button>
                    <strong data-subtotal>${dinero(suyas.reduce((t, l) => t + totalLinea(l), 0))}</strong>
                </header>
                <div class="gs-cuerpo" ${abierta ? '' : 'hidden'}>
                    ${suyas.length ? tablaHtml(suyas, 'Gasto') : ''}
                    ${hayArticulos
                        ? `<button type="button" class="gs-btn is-suave" data-agregar-catalogo="${s.id}"><i data-feather="plus"></i>Agregar gasto de ${esc(s.nombre.toLowerCase())}</button>`
                        : '<p class="gs-vacio">No hay gastos activos en esta categoria. Agregalos en el catalogo.</p>'}
                </div>
            </section>`;
        }).join('');

        contGrupos.innerHTML = grupos.length ? grupos.map((g) => {
            const suyas = lineas.filter((l) => l.tipo === 'otro' && l.grupo === g.uid);
            return `<div class="gs-grupo" data-grupo="${g.uid}">
                <div class="gs-grupo-head">
                    <input type="text" data-nombre-grupo value="${esc(g.nombre)}" maxlength="120" placeholder="Nombre del grupo, ej.: Exploracion" aria-label="Nombre del grupo">
                    <strong data-subtotal>${dinero(suyas.reduce((t, l) => t + totalLinea(l), 0))}</strong>
                    <button type="button" class="gs-quitar" data-quitar-grupo title="Quitar el grupo y sus lineas" aria-label="Quitar grupo"><i data-feather="trash-2"></i></button>
                </div>
                ${suyas.length ? tablaHtml(suyas, 'Descripcion') : '<p class="gs-vacio">Este grupo aun no tiene gastos.</p>'}
                <button type="button" class="gs-btn is-suave" data-agregar-otro="${g.uid}"><i data-feather="plus"></i>Agregar gasto a este grupo</button>
            </div>`;
        }).join('') : '<p class="gs-vacio">No hay otros gastos. Crea un grupo para registrar, por ejemplo, la exploracion.</p>';

        if (window.feather) window.feather.replace();
        totales();
    };

    // Totales, resumen y reparto sin volver a pintar las filas (no se pierde el foco).
    const totales = () => {
        lineas.forEach((l) => {
            const fila = app.querySelector(`[data-linea="${l.uid}"] [data-total-linea]`);
            if (fila) fila.textContent = dinero(totalLinea(l));
        });
        const porSeccion = secciones.map((s) => ({
            nombre: s.nombre,
            total: lineas.filter((l) => l.tipo === 'catalogo' && l.categoria === s.id).reduce((t, l) => t + totalLinea(l), 0),
            el: contSecciones.querySelector(`[data-seccion="${s.id}"] [data-subtotal]`),
        }));
        porSeccion.forEach((s) => { if (s.el) s.el.textContent = dinero(s.total); });
        let totalOtros = 0;
        grupos.forEach((g) => {
            const t = lineas.filter((l) => l.tipo === 'otro' && l.grupo === g.uid).reduce((s, l) => s + totalLinea(l), 0);
            totalOtros += t;
            const el = contGrupos.querySelector(`[data-grupo="${g.uid}"] [data-subtotal]`);
            if (el) el.textContent = dinero(t);
        });
        app.querySelector('[data-otros-total]').textContent = dinero(totalOtros);
        const total = porSeccion.reduce((t, s) => t + s.total, 0) + totalOtros;
        app.querySelector('[data-total-general]').textContent = dinero(total);

        // Resumen
        const kpis = [['Total de gastos', dinero(total), '', 'is-main']];
        if (datos.ingreso !== null) {
            const resultado = datos.ingreso - total;
            kpis.push(['Ingreso del sendero', dinero(datos.ingreso), 'Lo cobrado que se quedo + credito usado', '']);
            kpis.push([resultado >= 0 ? 'Ganancia' : 'Perdida', dinero(Math.abs(resultado)),
                datos.ingreso > 0 ? 'Margen ' + Math.round(resultado / datos.ingreso * 100) + '%' : '', resultado >= 0 ? 'is-ok' : 'is-alert']);
        }
        if (datos.asistieron > 0) kpis.push(['Costo por asistente', dinero(total / datos.asistieron), datos.asistieron + ' asistieron', '']);
        app.querySelector('[data-kpis]').innerHTML = kpis.map(([t, v, n, c]) =>
            `<article class="gs-kpi ${c}"><span>${esc(t)}</span><strong>${esc(v)}</strong>${n ? `<small>${esc(n)}</small>` : ''}</article>`).join('');

        const partes = porSeccion.filter((s) => s.total > 0).map((s) => [s.nombre, s.total]);
        if (totalOtros > 0) partes.push(['Otros gastos', totalOtros]);
        const reparto = app.querySelector('[data-reparto]');
        reparto.innerHTML = total > 0 ? `
            <div class="gs-barra" role="img" aria-label="Reparto de los gastos por tipo">
                ${partes.map(([n, t], i) => `<span style="width:${(t / total * 100).toFixed(2)}%" class="gs-color-${i % 8}" title="${esc(n)}: ${dinero(t)}"></span>`).join('')}
            </div>
            <ul class="gs-leyenda">
                ${partes.map(([n, t], i) => `<li><i class="gs-color-${i % 8}"></i>${esc(n)} <b>${dinero(t)}</b> <em>${Math.round(t / total * 100)}%</em></li>`).join('')}
            </ul>` : '';
    };

    // ---------- Eventos ----------
    const lineaDe = (el) => lineas.find((l) => l.uid === el.closest('[data-linea]')?.dataset.linea);

    app.addEventListener('input', (e) => {
        const campo = e.target.dataset.campo;
        if (campo) {
            const l = lineaDe(e.target);
            if (!l) return;
            l[campo] = campo === 'gasto_id' ? parseInt(e.target.value || '0', 10) : e.target.value;
            if (l.error && !validar(l)) {
                l.error = '';
                e.target.closest('tr').classList.remove('has-error');
                e.target.closest('tr').title = '';
            }
            marcarSucio();
            totales();
            return;
        }
        if (e.target.matches('[data-nombre-grupo]')) {
            const g = grupos.find((x) => x.uid === e.target.closest('[data-grupo]').dataset.grupo);
            if (g) g.nombre = e.target.value;
            marcarSucio();
        }
    });

    app.addEventListener('change', (e) => {
        if (e.target.dataset.campo !== 'gasto_id') return;
        const l = lineaDe(e.target);
        if (!l) return;
        l.gasto_id = parseInt(e.target.value || '0', 10);
        // Si el catalogo tiene costo y la linea no, se propone.
        const articulo = articulos.get(l.gasto_id);
        if (articulo && articulo.costo > 0 && numero(l.costo) <= 0) l.costo = articulo.costo.toFixed(2);
        marcarSucio();
        pintar();
    });

    // Montos con dos decimales al salir del campo.
    app.addEventListener('focusout', (e) => {
        if (e.target.dataset.campo !== 'costo' || e.target.value.trim() === '') return;
        const l = lineaDe(e.target);
        if (!l) return;
        l.costo = Math.max(0, numero(e.target.value)).toFixed(2);
        e.target.value = l.costo;
    });

    app.addEventListener('click', (e) => {
        const plegar = e.target.closest('[data-plegar]');
        if (plegar) {
            const id = parseInt(plegar.closest('[data-seccion]').dataset.seccion, 10);
            if (abiertas.has(id)) abiertas.delete(id); else abiertas.add(id);
            pintar();
            return;
        }
        const agregarCat = e.target.closest('[data-agregar-catalogo]');
        if (agregarCat) {
            const categoria = parseInt(agregarCat.dataset.agregarCatalogo, 10);
            const l = { uid: nuevoUid(), id: 0, tipo: 'catalogo', gasto_id: 0, categoria, cantidad: '1', costo: '', nota: '', contabilizado: false, error: '' };
            lineas.push(l);
            abiertas.add(categoria);
            marcarSucio();
            pintar();
            app.querySelector(`[data-linea="${l.uid}"] select`)?.focus();
            return;
        }
        const agregarOtro = e.target.closest('[data-agregar-otro]');
        if (agregarOtro) {
            const l = { uid: nuevoUid(), id: 0, tipo: 'otro', grupo: agregarOtro.dataset.agregarOtro, descripcion: '', cantidad: '1', costo: '', nota: '', contabilizado: false, error: '' };
            lineas.push(l);
            marcarSucio();
            pintar();
            app.querySelector(`[data-linea="${l.uid}"] [data-campo="descripcion"]`)?.focus();
            return;
        }
        if (e.target.closest('[data-nuevo-grupo]')) {
            const g = { uid: nuevoUid(), nombre: '' };
            grupos.push(g);
            lineas.push({ uid: nuevoUid(), id: 0, tipo: 'otro', grupo: g.uid, descripcion: '', cantidad: '1', costo: '', nota: '', contabilizado: false, error: '' });
            marcarSucio();
            pintar();
            contGrupos.querySelector(`[data-grupo="${g.uid}"] [data-nombre-grupo]`)?.focus();
            return;
        }
        const quitar = e.target.closest('[data-quitar]');
        if (quitar) {
            const l = lineaDe(quitar);
            if (!l) return;
            if (l.contabilizado && !window.confirm('Esta linea ya esta asignada a una cuenta en Balances. Si la quitas, alla quedara como "origen eliminado" para regularizar. ¿Quitarla?')) return;
            lineas.splice(lineas.indexOf(l), 1);
            marcarSucio();
            pintar();
            return;
        }
        const quitarGrupo = e.target.closest('[data-quitar-grupo]');
        if (quitarGrupo) {
            const uidGrupo = quitarGrupo.closest('[data-grupo]').dataset.grupo;
            const suyas = lineas.filter((l) => l.tipo === 'otro' && l.grupo === uidGrupo);
            const g = grupos.find((x) => x.uid === uidGrupo);
            if (suyas.length && !window.confirm('¿Quitar el grupo "' + (g?.nombre || 'sin nombre') + '" y sus ' + suyas.length + ' gasto(s)?'
                + (suyas.some((l) => l.contabilizado) ? '\n\nAlgunos ya estan asignados en Balances.' : ''))) return;
            suyas.forEach((l) => lineas.splice(lineas.indexOf(l), 1));
            grupos.splice(grupos.indexOf(g), 1);
            marcarSucio();
            pintar();
        }
    });

    // ---------- Guardar ----------
    const botonGuardar = app.querySelector('[data-guardar]');
    const listaErrores = app.querySelector('[data-errores]');
    const mostrarErrores = (mensajes) => {
        listaErrores.innerHTML = mensajes.map((m) => `<li>${esc(m)}</li>`).join('');
        listaErrores.hidden = mensajes.length === 0;
        if (mensajes.length) listaErrores.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    // Orden de guardado: secciones del catalogo y luego los grupos de otros gastos.
    const ordenadas = () => [
        ...secciones.flatMap((s) => lineas.filter((l) => l.tipo === 'catalogo' && l.categoria === s.id)),
        ...grupos.flatMap((g) => lineas.filter((l) => l.tipo === 'otro' && l.grupo === g.uid)),
    ];

    botonGuardar.addEventListener('click', async () => {
        // Grupos sin nombre y sin lineas no cuentan.
        const lista = ordenadas();
        let hayError = false;
        lista.forEach((l) => {
            l.error = validar(l);
            if (l.error) hayError = true;
        });
        const vacios = grupos.filter((g) => !lineas.some((l) => l.grupo === g.uid));
        if (hayError) {
            lista.filter((l) => l.error && l.tipo === 'catalogo').forEach((l) => abiertas.add(l.categoria));
            pintar();
            mostrarErrores(['Revisa las lineas marcadas en rojo (pasa el cursor sobre ellas para ver que falta).']);
            return;
        }
        if (vacios.length) {
            vacios.forEach((g) => grupos.splice(grupos.indexOf(g), 1));
        }
        mostrarErrores([]);
        botonGuardar.disabled = true;
        estado.textContent = 'Guardando...';
        estado.dataset.tipo = 'guardando';
        const cuerpo = new FormData();
        cuerpo.append('csrf_token', app.dataset.csrf);
        cuerpo.append('sendero_id', app.dataset.sendero);
        cuerpo.append('version', datos.version);
        cuerpo.append('lineas', JSON.stringify(lista.map((l) => ({
            id: l.id || 0,
            tipo: l.tipo,
            gasto_id: l.tipo === 'catalogo' ? l.gasto_id : 0,
            grupo: l.tipo === 'otro' ? (grupos.find((g) => g.uid === l.grupo)?.nombre || '') : '',
            descripcion: l.tipo === 'otro' ? l.descripcion : '',
            cantidad: numero(l.cantidad).toFixed(2),
            costo: numero(l.costo).toFixed(2),
            nota: l.nota,
        }))));
        let r;
        try {
            const respuesta = await fetch(app.dataset.endpoint, { method: 'POST', body: cuerpo, credentials: 'same-origin' });
            r = await respuesta.json();
        } catch (e) {
            r = { ok: false, mensaje: 'No se pudo guardar. Revisa tu conexion o recarga la pagina (la sesion pudo expirar).' };
        }
        botonGuardar.disabled = false;
        if (r.ok) {
            sucio = false;
            window.location.reload();
            return;
        }
        estado.textContent = 'No se guardo';
        estado.dataset.tipo = 'error';
        Object.entries(r.errores || {}).forEach(([indice, mensaje]) => {
            const l = lista[parseInt(indice, 10)];
            if (l) {
                l.error = mensaje;
                if (l.tipo === 'catalogo') abiertas.add(l.categoria);
            }
        });
        pintar();
        mostrarErrores([r.mensaje || 'No se pudieron guardar los gastos.']);
    });

    pintar();
});
