// Ingresos por sendero: lista de inscritos, panel de cobro y acciones para varios.
// El calculo replica ic_calcular() de componentes/ingresos_cobro.php: si cambias
// una regla o un mensaje alla, cambialo aqui tambien. El servidor siempre vuelve
// a validar antes de guardar.
document.addEventListener('DOMContentLoaded', () => {
    const raiz = document.querySelector('[data-ingresos]');
    const cuerpoTabla = raiz?.querySelector('[data-filas]');
    if (!raiz || !cuerpoTabla) return;

    const endpoint = raiz.dataset.endpoint;
    const senderoId = raiz.dataset.sendero;
    const csrf = raiz.dataset.csrf;
    const hoy = raiz.dataset.hoy;

    // ---------- Dinero (centavos) ----------
    const centavos = (valor) => {
        const numero = parseFloat(String(valor ?? '').replace(/,/g, '').trim());
        return Number.isFinite(numero) ? Math.round(numero * 100) : 0;
    };
    const dinero = (c) => 'RD$ ' + (c / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const decimal = (c) => (c / 100).toFixed(2);
    const campoDinero = (c) => (c > 0 ? decimal(c) : '');

    // ---------- Calculo (igual que ic_calcular) ----------
    const calcular = (e, ctx) => {
        const errores = [];
        const error = (campo, mensaje) => errores.push({ campo, mensaje });
        const tipo = ['cortesia', 'exento'].includes(e.tipo) ? e.tipo : 'automatico';
        const asistio = !!e.asistio;
        const valor = Math.max(0, ctx.valor | 0);
        const registrado = !!ctx.registrado;
        const usable = Math.max(0, ctx.credito_usable | 0);
        const yaUsado = Math.max(0, ctx.generado_ya_usado | 0);

        ['descuento', 'credito', 'recibido', 'a_credito', 'devuelto'].forEach((campo) => {
            if ((e[campo] | 0) < 0) error(campo, 'El monto no puede ser negativo.');
        });
        let descuento = Math.max(0, e.descuento | 0);
        const credito = Math.max(0, e.credito | 0);
        const recibido = Math.max(0, e.recibido | 0);
        const aCredito = Math.max(0, e.a_credito | 0);
        const devuelto = Math.max(0, e.devuelto | 0);
        let metodoId = Math.max(0, e.metodo_id | 0);
        let fecha = String(e.fecha || '').trim();

        if (tipo !== 'automatico') {
            descuento = 0;
            if (credito > 0) error('credito', 'En cortesia o exento no se usa credito. Ponlo en 0.');
        } else if (descuento > valor) {
            error('descuento', 'El descuento no puede ser mayor que el valor (' + dinero(valor) + ').');
        }
        const debePagar = tipo === 'automatico' ? Math.max(0, valor - descuento) : 0;

        if (credito > 0 && !registrado) {
            error('credito', 'Solo un usuario registrado puede usar credito.');
        } else if (credito > usable) {
            error('credito', 'Solo tiene ' + dinero(usable) + ' de credito disponible.');
        } else if (credito > debePagar && tipo === 'automatico') {
            error('credito', 'El credito no puede pasar de lo que debe pagar (' + dinero(debePagar) + ').');
        }
        // Parte del cobro que asume otro usuario (cuenta por cobrar a su nombre): cuenta como cubierta.
        const cargado = Math.max(0, e.cargado | 0);
        let cargadoA = Math.max(0, e.cargado_a | 0);
        if (cargado > 0 && tipo !== 'automatico') error('cargado', 'En cortesia o exento no se carga nada a otro usuario.');
        else if (cargado > 0 && cargadoA <= 0) error('cargado_a', 'Elige a que usuario se le carga.');
        else if (cargado > Math.max(0, debePagar - Math.min(credito, debePagar))) error('cargado', 'Lo que se carga a otro usuario no puede pasar de lo que falta por pagar (' + dinero(Math.max(0, debePagar - Math.min(credito, debePagar))) + ').');
        if (cargado <= 0) cargadoA = 0;

        if (aCredito > 0 && !registrado) error('a_credito', 'Solo un usuario registrado puede quedar con credito. Devuelvelo.');
        if (aCredito + devuelto > recibido) error('a_credito', 'Lo que queda en credito y lo que se devuelve suman mas de lo recibido (' + dinero(recibido) + ').');
        if (aCredito < yaUsado) error('a_credito', 'De este credito ya se usaron ' + dinero(yaUsado) + '; no puede quedar en menos.');

        const necesario = Math.max(0, debePagar - Math.min(credito, debePagar) - cargado);
        const retenido = Math.max(0, recibido - aCredito - devuelto);
        const exceso = Math.max(0, recibido - necesario);
        const sinRepartir = Math.max(0, retenido - necesario);
        if (sinRepartir > 0) error('reparto', 'Recibio ' + dinero(sinRepartir) + ' de mas: indica cuanto queda en credito y cuanto se devuelve.');
        else if (tipo === 'automatico' && asistio && aCredito + devuelto > exceso) {
            error('reparto', exceso > 0
                ? 'Asistio: solo se puede pasar a credito o devolver lo que pago de mas (' + dinero(exceso) + '). Si el resto es un beneficio, ponlo como descuento.'
                : 'Asistio y no pago de mas: para dejarle credito o devolverle dinero, ese monto va como descuento.');
        }

        if (recibido > 0) {
            const recibidoCambio = !('recibido_guardado' in ctx) || recibido !== ctx.recibido_guardado;
            if (metodoId <= 0 && recibidoCambio) error('metodo', 'Indica el metodo de pago.');
            if (fecha === '' && recibidoCambio) fecha = ctx.hoy || hoy;
            if (fecha !== '') {
                if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) error('fecha', 'La fecha de pago no es valida.');
                else if (fecha > (ctx.hoy || hoy)) error('fecha', 'La fecha de pago no puede ser futura.');
            }
        } else {
            fecha = '';
            metodoId = 0;
        }

        const retenidoEfectivo = Math.min(retenido, necesario);
        const falta = Math.max(0, debePagar - Math.min(credito, debePagar) - cargado - retenidoEfectivo);
        const modo = asistio ? 'asistio' : (ctx.sendero_paso ? 'no_asistio' : 'pendiente');
        const saldo = modo === 'no_asistio' || tipo !== 'automatico' ? 0 : falta;

        const cubierto = () => {
            if (credito > 0 && retenido <= 0 && descuento <= 0) return 'credito_aplicado';
            if (credito > 0) return 'pagado_con_credito';
            if (descuento > 0) return 'descuento';
            return 'pagado';
        };
        let estado;
        if (tipo !== 'automatico') estado = tipo;
        else if (valor <= 0) estado = ctx.directiva ? 'exento' : 'cortesia';
        else if (modo === 'no_asistio') estado = retenido + credito + cargado > 0 ? cubierto() : 'no_asistio_sin_pago';
        else if (retenido + credito + descuento + cargado >= valor) estado = cubierto();
        else if (retenido + credito + descuento + cargado > 0) estado = 'parcial';
        else if (asistio) estado = 'deuda';
        else estado = 'pendiente';

        return {
            tipo, asistio, modo, valor, descuento, debePagar, credito, recibido, aCredito, devuelto, cargado, cargadoA,
            retenido, exceso, necesario, falta, saldo, ingreso: retenido + credito, estado, metodoId, fecha, errores,
        };
    };

    // ---------- Avisos ----------
    const aviso = raiz.querySelector('[data-aviso]');
    let avisoTimer = null;
    const avisar = (texto, tipo = 'ok') => {
        if (!aviso) return;
        aviso.textContent = texto;
        aviso.dataset.tipo = tipo;
        aviso.hidden = false;
        clearTimeout(avisoTimer);
        avisoTimer = setTimeout(() => { aviso.hidden = true; }, tipo === 'error' ? 9000 : 4500);
    };

    // ---------- Comunicacion con el servidor ----------
    // Indicador de guardado automatico (cada accion se guarda al momento).
    const estadoGuardado = raiz.querySelector('[data-estado-guardado]');
    const marcarEstado = (estado, texto) => {
        if (!estadoGuardado) return;
        estadoGuardado.dataset.estado = estado;
        estadoGuardado.querySelector('[data-estado-texto]').textContent = texto;
    };
    const horaActual = () => new Date().toLocaleTimeString('es-DO', { hour: 'numeric', minute: '2-digit' });

    const enviar = async (datos) => {
        marcarEstado('guardando', 'Guardando...');
        const r = await enviarSinEstado(datos);
        if (r.ok) marcarEstado('listo', 'Guardado ' + horaActual());
        else marcarEstado('error', 'No se guardo el ultimo cambio');
        return r;
    };
    const enviarSinEstado = async (datos) => {
        const cuerpo = new FormData();
        cuerpo.append('csrf_token', csrf);
        cuerpo.append('sendero_id', senderoId);
        Object.entries(datos).forEach(([clave, valor]) => {
            if (Array.isArray(valor)) valor.forEach((v) => cuerpo.append(clave + '[]', v));
            else cuerpo.append(clave, valor);
        });
        let respuesta;
        try {
            respuesta = await fetch(endpoint, { method: 'POST', body: cuerpo, credentials: 'same-origin' });
        } catch (e) {
            return { ok: false, mensaje: 'No hay conexion con el servidor. Revisa tu internet e intenta de nuevo.' };
        }
        try {
            return await respuesta.json();
        } catch (e) {
            return { ok: false, mensaje: 'Tu sesion expiro o hubo un error. Recarga la pagina.' };
        }
    };

    const aplicarRespuesta = (r) => {
        Object.entries(r.filas || {}).forEach(([id, html]) => {
            const actual = cuerpoTabla.querySelector(`[data-registro="${id}"]`);
            if (!actual) return;
            const plantilla = document.createElement('tbody');
            plantilla.innerHTML = html.trim();
            const nueva = plantilla.firstElementChild;
            if (nueva) actual.replaceWith(nueva);
        });
        if (r.resumen) {
            const resumen = raiz.querySelector('[data-resumen]');
            if (resumen) {
                const abierto = resumen.querySelector('details')?.open;
                resumen.innerHTML = r.resumen;
                if (abierto) resumen.querySelector('details').open = true;
            }
        }
        if (r.grupos) {
            Object.entries(r.grupos).forEach(([grupo, total]) => {
                const cuenta = raiz.querySelector(`[data-cuenta="${grupo}"]`);
                if (cuenta) cuenta.textContent = total;
            });
        }
        if (typeof r.revisar === 'number') {
            const boton = raiz.querySelector('[data-filtro="revisar"]');
            const cuenta = raiz.querySelector('[data-cuenta="revisar"]');
            if (cuenta) cuenta.textContent = r.revisar;
            if (boton) boton.hidden = r.revisar === 0 && !boton.classList.contains('is-active');
        }
        if (window.feather) window.feather.replace();
        filtrar();
    };

    // ---------- Filtros y busqueda ----------
    const buscador = raiz.querySelector('[data-buscar]');
    const sinResultados = raiz.querySelector('[data-sin-resultados]');
    let filtroActivo = 'todos';
    const normalizar = (texto) => String(texto || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const filas = () => [...cuerpoTabla.querySelectorAll('[data-fila]')];
    const filasVisibles = () => filas().filter((f) => !f.hidden);

    function filtrar() {
        const termino = normalizar(buscador?.value);
        let visibles = 0;
        filas().forEach((fila) => {
            const coincideFiltro = filtroActivo === 'todos'
                || (filtroActivo === 'revisar' ? fila.dataset.revisar === '1' : fila.dataset.grupo === filtroActivo);
            const coincideTexto = !termino || normalizar(fila.dataset.buscar).includes(termino);
            fila.hidden = !(coincideFiltro && coincideTexto);
            if (!fila.hidden) visibles++;
        });
        if (sinResultados) sinResultados.hidden = visibles > 0;
        const textoAsistencia = raiz.querySelector('[data-asistencia-texto]');
        if (textoAsistencia) textoAsistencia.textContent = modoAsistencia() === 'desmarcar' ? 'Desmarcar asistencia' : 'Marcar asistencia';
        const textoCobro = raiz.querySelector('[data-cobro-texto]');
        if (textoCobro) textoCobro.textContent = modoCobro() === 'deshacer' ? 'Deshacer cobros' : 'Cobro completo';
    }

    // Quien puede recibir el cobro completo y quien ya tiene algo registrado.
    const puedeCobroCompleto = (d) => d.tipo === 'automatico' && d.recibido === 0 && d.credito === 0 && !(d.cargado > 0) && Math.max(0, d.valor - d.descuento) > 0;
    const tieneCobro = (d) => d.tipo === 'automatico' && (d.recibido + d.credito + d.descuento + d.a_credito + d.devuelto + (d.cargado || 0)) > 0;
    // Si ya no queda nadie visible por cobrar y hay cobros, el boton pasa a deshacerlos.
    function modoCobro() {
        const visibles = filasVisibles().map(datosFila).filter(Boolean);
        return !visibles.some(puedeCobroCompleto) && visibles.some(tieneCobro) ? 'deshacer' : 'cobrar';
    }

    // Si todas las personas visibles ya estan marcadas, el boton masivo pasa a desmarcarlas.
    function modoAsistencia() {
        const visibles = filasVisibles().map(datosFila).filter(Boolean);
        return visibles.length > 0 && visibles.every((d) => d.asistio) ? 'desmarcar' : 'marcar';
    }
    buscador?.addEventListener('input', filtrar);
    raiz.querySelectorAll('[data-filtro]').forEach((boton) => {
        boton.addEventListener('click', () => {
            filtroActivo = boton.dataset.filtro;
            raiz.querySelectorAll('[data-filtro]').forEach((b) => {
                b.classList.toggle('is-active', b === boton);
                b.setAttribute('aria-pressed', b === boton ? 'true' : 'false');
            });
            filtrar();
        });
    });

    const datosFila = (fila) => {
        try { return JSON.parse(fila.dataset.cobro); } catch (e) { return null; }
    };

    // ---------- Asistencia en la lista ----------
    cuerpoTabla.addEventListener('change', async (evento) => {
        const casilla = evento.target.closest('[data-asistencia]');
        if (!casilla) return;
        const fila = casilla.closest('[data-fila]');
        casilla.disabled = true;
        const r = await enviar({ accion: 'asistencia', registro_ids: [fila.dataset.registro], asistio: casilla.checked ? '1' : '0' });
        if (!r.ok) {
            casilla.checked = !casilla.checked;
            casilla.disabled = false;
            avisar(r.mensaje || 'No se pudo guardar la asistencia.', 'error');
            return;
        }
        aplicarRespuesta(r);
        if (r.omitidos?.length) avisar(r.omitidos.join(' ') + ' Abre su cobro para corregirlo.', 'error');
        else avisar(r.mensaje);
    });

    // ---------- Pago rapido en la lista ----------
    // El metodo elegido se recuerda en este navegador para no escogerlo cada vez.
    const metodoRapido = raiz.querySelector('[data-metodo-rapido]');
    if (metodoRapido) {
        try {
            const guardado = localStorage.getItem('ingresos_metodo_rapido');
            if (guardado && metodoRapido.querySelector(`option[value="${guardado}"]`)) metodoRapido.value = guardado;
        } catch (e) { /* sin almacenamiento: queda el primero */ }
        metodoRapido.addEventListener('change', () => {
            try { localStorage.setItem('ingresos_metodo_rapido', metodoRapido.value); } catch (e) { /* nada */ }
        });
    }

    cuerpoTabla.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('[data-pago-rapido]');
        if (!boton) return;
        const fila = boton.closest('[data-fila]');
        const datos = datosFila(fila);
        if (!metodoRapido || !metodoRapido.value) {
            avisar('Primero crea o elige un metodo de pago para el pago rapido.', 'error');
            return;
        }
        boton.disabled = true;
        const r = await enviar({ accion: 'cobro_completo', registro_ids: [fila.dataset.registro], metodo_id: metodoRapido.value, fecha: hoy });
        if (!r.ok || r.omitidos?.length) {
            boton.disabled = false;
            avisar(r.omitidos?.length ? r.omitidos.join(' ') : (r.mensaje || 'No se pudo registrar el pago.'), 'error');
            if (r.filas) aplicarRespuesta(r);
            return;
        }
        aplicarRespuesta(r);
        const metodo = metodoRapido.options[metodoRapido.selectedIndex].textContent;
        avisar('Pago de ' + (datos?.nombre || 'la persona') + ' registrado en ' + metodo + '. Para cambiarlo usa Editar.');
    });

    // ---------- Panel de cobro ----------
    const panel = raiz.querySelector('[data-panel]');
    const formulario = panel.querySelector('[data-panel-form]');
    const campo = (nombre) => formulario.elements[nombre];
    const p = (selector) => panel.querySelector(selector);
    let actual = null;          // datos de la fila abierta
    let descEnPorcentaje = false;
    // Credito y devolucion tambien se pueden escribir como porcentaje de lo recibido.
    const enPorcentaje = { a_credito: false, devuelto: false };
    let huboCambios = false;
    let guardando = false;

    const confirmarSalida = (evento) => {
        if (!huboCambios && !guardando) return true;
        if (guardando) {
            evento?.preventDefault();
            avisar('Espera a que termine de guardarse el cobro antes de salir.', 'error');
            return false;
        }
        if (window.confirm('Tienes cambios sin guardar en este cobro. ¿Deseas salir y descartarlos?')) return true;
        evento?.preventDefault();
        return false;
    };
    raiz.querySelectorAll('[data-confirmar-salida]').forEach((enlace) => enlace.addEventListener('click', confirmarSalida));
    window.addEventListener('beforeunload', (evento) => {
        if (!huboCambios && !guardando) return;
        evento.preventDefault();
        evento.returnValue = '';
    });

    const porcentajeDe = (nombre, base) => {
        const pct = Math.min(100, Math.max(0, parseFloat(campo(nombre).value) || 0));
        return Math.round(base * pct / 100);
    };
    const montoReparto = (nombre, recibido) => (enPorcentaje[nombre] ? porcentajeDe(nombre, recibido) : centavos(campo(nombre).value));
    // Antes de escribir un monto en pesos (atajos, abrir el panel), el campo vuelve a RD$.
    const modoPesos = (nombre) => {
        enPorcentaje[nombre] = false;
        const boton = p(`[data-p-modo="${nombre}"]`);
        if (boton) boton.textContent = 'RD$';
    };

    const casillaCargo = p('[data-p-cargar]');
    const leerEntrada = () => {
        const asistioMarcado = formulario.querySelector('input[name="asistio"]:checked');
        const tipoMarcado = formulario.querySelector('input[name="tipo"]:checked');
        let descuento = centavos(campo('descuento').value);
        if (descEnPorcentaje) {
            const porcentaje = Math.min(100, Math.max(0, parseFloat(campo('descuento').value) || 0));
            descuento = Math.round(actual.valor * porcentaje / 100);
        }
        const recibido = centavos(campo('recibido').value);
        return {
            tipo: tipoMarcado ? tipoMarcado.value : 'automatico',
            asistio: asistioMarcado ? asistioMarcado.value === '1' : false,
            descuento,
            credito: centavos(campo('credito').value),
            recibido,
            a_credito: montoReparto('a_credito', recibido),
            devuelto: montoReparto('devuelto', recibido),
            cargado: casillaCargo && casillaCargo.checked && (tipoMarcado ? tipoMarcado.value : 'automatico') === 'automatico' ? centavos(campo('cargado').value) : 0,
            cargado_a: casillaCargo && casillaCargo.checked ? parseInt(campo('cargado_a').value || '0', 10) : 0,
            metodo_id: parseInt(campo('metodo_id').value || '0', 10),
            fecha: campo('fecha').value,
        };
    };

    const contexto = () => ({
        valor: actual.valor,
        registrado: actual.registrado,
        credito_usable: actual.credito_usable,
        generado_ya_usado: actual.generado_ya_usado,
        sendero_paso: actual.sendero_paso,
        recibido_guardado: actual.recibido_guardado,
        directiva: !!actual.directiva,
        hoy: actual.hoy || hoy,
    });

    // Explicacion en palabras de lo que va a quedar guardado.
    const explicar = (c) => {
        if (c.tipo !== 'automatico') {
            let texto = (c.tipo === 'cortesia' ? 'Cortesia' : 'Exento') + ': no paga nada por este sendero.';
            if (c.recibido > 0) texto += ' De lo recibido (' + dinero(c.recibido) + ')' + partesReparto(c) + '.';
            return texto;
        }
        const frases = [];
        if (c.modo === 'no_asistio') {
            frases.push('No asistio.');
            if (c.recibido > 0) frases.push('Se recibieron ' + dinero(c.recibido) + partesReparto(c, true) + '.');
            if (c.credito > 0) frases.push('El credito usado (' + dinero(c.credito) + ') no se le devuelve; ponlo en 0 si debe volver a su cuenta.');
            if (c.recibido <= 0 && c.credito <= 0) frases.push('No pago nada y no queda debiendo.');
            return frases.join(' ');
        }
        frases.push('Le toca pagar ' + dinero(c.debePagar) + '.');
        const cubre = [];
        if (c.credito > 0) cubre.push(dinero(c.credito) + ' con credito');
        if (Math.min(c.retenido, c.necesario) > 0) cubre.push(dinero(Math.min(c.retenido, c.necesario)) + ' en efectivo');
        if (c.cargado > 0) cubre.push(dinero(c.cargado) + ' cargados a otro usuario');
        if (cubre.length) frases.push('Cubre ' + cubre.join(' y ') + '.');
        if (c.exceso > 0) frases.push('Pago ' + dinero(c.exceso) + ' de mas' + partesReparto(c) + '.');
        if (c.saldo > 0) frases.push((c.modo === 'asistio' ? 'Queda debiendo ' : 'Le falta pagar ') + dinero(c.saldo) + '.');
        else frases.push('Queda al dia.');
        return frases.join(' ');
    };
    const partesReparto = (c, incluirRetenido = false) => {
        const partes = [];
        if (incluirRetenido && c.retenido > 0) partes.push('se retienen ' + dinero(c.retenido));
        if (c.aCredito > 0) partes.push(dinero(c.aCredito) + ' quedan en credito');
        if (c.devuelto > 0) partes.push('se devuelven ' + dinero(c.devuelto));
        return partes.length ? ': ' + partes.join(', ') : '';
    };

    const actualizarPanel = () => {
        if (!actual) return null;
        const e = leerEntrada();
        const c = calcular(e, contexto());
        const automatico = c.tipo === 'automatico';

        p('[data-p-valor]').textContent = dinero(actual.valor);
        p('[data-p-debe]').textContent = dinero(c.debePagar);
        p('[data-p-desc-ayuda]').textContent = descEnPorcentaje && c.descuento > 0 ? '= ' + dinero(c.descuento) : '';
        p('[data-p-ayuda="a_credito"]').textContent = enPorcentaje.a_credito ? '= ' + dinero(c.aCredito) + ' de ' + dinero(c.recibido) + ' recibidos' : '';
        p('[data-p-ayuda="devuelto"]').textContent = enPorcentaje.devuelto ? '= ' + dinero(c.devuelto) + ' de ' + dinero(c.recibido) + ' recibidos' : '';
        campo('descuento').disabled = !automatico;
        p('[data-p-desc-modo]').disabled = !automatico;
        p('[data-p-no-asistio]').textContent = actual.sendero_paso ? 'No asistio' : 'Aun no asiste';

        const ayudaTipo = p('[data-p-ayuda-tipo]');
        ayudaTipo.hidden = automatico && !(actual.directiva && actual.valor <= 0);
        ayudaTipo.textContent = automatico ? 'Miembro de la directiva: su participacion no se cobra (queda como Exento).' : c.tipo === 'cortesia'
            ? 'Cortesia: el sendero se le regala. No se cobra ni se usa credito.'
            : 'Exento: no tiene valor para esta persona. No se cobra ni se usa credito.';

        // Cortesia y exento no cobran nada: se ocultan descuento y forma de pago, salvo que ya haya dinero
        // recibido (entonces se muestra para ver cuanto es y repartirlo en credito o devolucion).
        // Un miembro de la directiva sin menores tampoco paga: su cobro ya es Exento.
        const sinCobro = !automatico || (!!actual.directiva && actual.valor <= 0);
        p('[data-p-precio]').hidden = sinCobro;
        p('[data-p-pago]').hidden = sinCobro && c.recibido <= 0;
        if (sinCobro) {
            campo('credito').value = '';
            campo('descuento').value = '';
        }

        // Cargo a otro usuario: los campos aparecen solo con la casilla marcada.
        if (casillaCargo) {
            p('[data-p-cargo-campos]').hidden = !casillaCargo.checked;
            p('[data-p-bloque-cargo]').hidden = sinCobro;
        }

        // Credito a favor.
        // Sin credito disponible ni usado, el campo solo estorba.
        p('[data-p-bloque-credito]').hidden = !actual.registrado || (actual.credito_usable <= 0 && c.credito <= 0);
        p('[data-p-sin-cuenta]').hidden = actual.registrado;
        p('[data-p-usable]').textContent = '(tiene ' + dinero(actual.credito_usable) + ')';
        campo('credito').disabled = !automatico || actual.credito_usable <= 0 && c.credito <= 0;
        p('[data-p-usar-credito]').disabled = campo('credito').disabled;

        // Reparto del dinero recibido.
        const repartible = c.modo === 'no_asistio' || !automatico ? c.recibido : c.exceso;
        const mostrarReparto = c.recibido > 0 && (c.exceso > 0 || c.modo === 'no_asistio' || !automatico || c.aCredito > 0 || c.devuelto > 0);
        p('[data-p-reparto]').hidden = !mostrarReparto;
        if (mostrarReparto) {
            let titulo;
            let ayuda;
            if (!automatico) {
                titulo = 'Que se hace con lo recibido';
                ayuda = 'No paga este sendero: lo recibido (' + dinero(c.recibido) + ') queda como credito o se le devuelve.';
            } else if (c.modo === 'no_asistio') {
                titulo = 'No asistio: que se hace con lo recibido';
                ayuda = 'De ' + dinero(c.recibido) + ', lo que no quede en credito ni se devuelva se queda para el sendero (hasta ' + dinero(c.necesario) + ').';
            } else {
                titulo = 'Pago ' + dinero(c.exceso) + ' de mas';
                ayuda = 'Reparte el excedente: una parte en credito, otra se devuelve, o todo a un solo lado.';
            }
            p('[data-p-reparto-titulo]').textContent = titulo;
            p('[data-p-reparto-ayuda]').textContent = ayuda;
            const sinDecidir = Math.max(0, c.retenido - c.necesario);
            p('[data-p-retenido]').textContent = sinDecidir > 0
                ? dinero(c.necesario) + ' (faltan ' + dinero(sinDecidir) + ' por decidir)'
                : dinero(c.retenido);
            campo('a_credito').disabled = !actual.registrado;
            p('[data-p-atajo="credito"]').disabled = !actual.registrado || repartible <= 0;
            p('[data-p-atajo="devolver"]').disabled = repartible <= 0;
            // Asistio y se le quiere dejar credito o devolver mas de lo que pago de mas: esa
            // diferencia es un beneficio, se registra como descuento.
            const comoDescuento = p('[data-p-como-descuento]');
            const faltaDescuento = automatico && c.asistio ? Math.max(0, c.aCredito + c.devuelto - c.exceso) : 0;
            comoDescuento.hidden = faltaDescuento <= 0 || c.descuento + faltaDescuento > actual.valor;
            comoDescuento.textContent = 'Convertir ' + dinero(faltaDescuento) + ' en descuento';
            comoDescuento.dataset.monto = String(faltaDescuento);
            p('[data-p-atajo="retener"]').hidden = !(automatico && c.modo === 'no_asistio' && c.recibido <= c.necesario);
        }

        // Resultado y errores.
        p('[data-p-resultado]').textContent = explicar(c);
        const lista = p('[data-p-errores]');
        lista.innerHTML = '';
        c.errores.forEach((err) => {
            const li = document.createElement('li');
            li.textContent = err.mensaje;
            lista.appendChild(li);
        });
        const conError = new Set(c.errores.map((err) => (err.campo === 'reparto' ? 'a_credito' : err.campo === 'metodo' ? 'metodo_id' : err.campo)));
        ['descuento', 'credito', 'recibido', 'a_credito', 'devuelto', 'metodo_id', 'fecha'].forEach((nombre) => {
            campo(nombre).setAttribute('aria-invalid', conError.has(nombre) ? 'true' : 'false');
        });
        p('[data-p-guardar]').disabled = guardando || c.errores.length > 0;
        return c;
    };

    const abrirPanel = (fila) => {
        const datos = datosFila(fila);
        if (!datos) return;
        actual = datos;
        p('[data-p-deshacer]').hidden = !tieneCobro(datos);
        descEnPorcentaje = false;
        huboCambios = false;
        p('[data-p-desc-modo]').textContent = 'RD$';
        p('[data-p-nombre]').textContent = datos.nombre;
        p('[data-p-detalle]').textContent = datos.detalle;
        formulario.querySelectorAll('input[name="asistio"]').forEach((r) => { r.checked = (r.value === '1') === !!datos.asistio; });
        formulario.querySelectorAll('input[name="tipo"]').forEach((r) => { r.checked = r.value === datos.tipo; });
        campo('descuento').value = campoDinero(datos.descuento);
        campo('credito').value = campoDinero(datos.credito);
        campo('recibido').value = campoDinero(datos.recibido);
        modoPesos('a_credito');
        modoPesos('devuelto');
        campo('a_credito').value = campoDinero(datos.a_credito);
        campo('devuelto').value = campoDinero(datos.devuelto);
        if (casillaCargo) {
            casillaCargo.checked = datos.cargado > 0;
            campo('cargado').value = campoDinero(datos.cargado);
            campo('cargado_a').value = datos.cargado_a > 0 ? String(datos.cargado_a) : '';
        }
        campo('metodo_id').value = datos.metodo_id > 0 ? String(datos.metodo_id) : '';
        campo('fecha').value = datos.fecha || '';
        campo('nota').value = datos.nota || '';
        actualizarPanel();
        panel.showModal();
        (datos.recibido > 0 || datos.credito > 0 ? campo('recibido') : formulario.querySelector('input[name="asistio"]:checked') || campo('recibido')).focus();
    };

    // Deshacer el cobro de esta persona: mismo efecto que "Deshacer cobros" para varios
    // (devuelve el credito usado, anula el credito generado; la asistencia no cambia).
    const botonDeshacer = p('[data-p-deshacer]');
    botonDeshacer.addEventListener('click', async () => {
        if (!actual) return;
        if (!window.confirm('¿Deshacer el cobro de ' + actual.nombre + '?\n\nSe borra lo recibido, el descuento y la devolucion; '
            + 'se le devuelve el credito que uso y se anula el credito que se le genero. La asistencia no cambia.')) return;
        const nombre = actual.nombre;
        botonDeshacer.disabled = true;
        const r = await enviar({ accion: 'deshacer_cobro', registro_ids: [actual.registro_id] });
        botonDeshacer.disabled = false;
        if (r.ok && !r.omitidos?.length) {
            aplicarRespuesta(r);
            cerrarPanel(true);
            avisar('Cobro de ' + nombre + ' deshecho.');
            return;
        }
        if (r.filas) aplicarRespuesta({ filas: r.filas });
        const lista = p('[data-p-errores]');
        lista.innerHTML = '';
        const li = document.createElement('li');
        li.textContent = r.omitidos?.length ? r.omitidos.join(' ') : (r.mensaje || 'No se pudo deshacer el cobro.');
        lista.appendChild(li);
    });

    const cerrarPanel = (forzar = false) => {
        if (!forzar && huboCambios && !window.confirm('Tienes cambios sin guardar en este cobro. ¿Descartarlos?')) return;
        panel.close();
        actual = null;
        huboCambios = false;
    };

    cuerpoTabla.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-abrir-cobro]');
        if (boton) abrirPanel(boton.closest('[data-fila]'));
    });
    panel.addEventListener('cancel', (evento) => {
        evento.preventDefault();
        cerrarPanel();
    });
    panel.querySelectorAll('[data-cerrar]').forEach((b) => b.addEventListener('click', () => cerrarPanel()));

    formulario.addEventListener('input', () => {
        huboCambios = true;
        // Al escribir lo recibido por primera vez se propone la fecha de hoy.
        if (centavos(campo('recibido').value) > 0 && !campo('fecha').value) campo('fecha').value = hoy;
        actualizarPanel();
    });
    formulario.addEventListener('change', (evento) => {
        huboCambios = true;
        if (evento.target.name === 'tipo' && evento.target.value !== 'automatico') {
            campo('descuento').value = '';
            campo('credito').value = '';
        }
        actualizarPanel();
    });
    // Los montos se muestran siempre con dos decimales al salir del campo.
    formulario.addEventListener('focusout', (evento) => {
        const entrada = evento.target;
        if (!entrada.matches('[data-dinero]') || entrada.value.trim() === '') return;
        if (entrada.name === 'descuento' && descEnPorcentaje) return;
        if (enPorcentaje[entrada.name]) return;
        entrada.value = campoDinero(Math.max(0, centavos(entrada.value))) || '0.00';
    });

    p('[data-p-desc-modo]').addEventListener('click', () => {
        const c = actualizarPanel();
        descEnPorcentaje = !descEnPorcentaje;
        p('[data-p-desc-modo]').textContent = descEnPorcentaje ? '%' : 'RD$';
        if (descEnPorcentaje) {
            campo('descuento').value = actual.valor > 0 && c.descuento > 0 ? String(Math.round(c.descuento / actual.valor * 10000) / 100) : '';
        } else {
            campo('descuento').value = campoDinero(c.descuento);
        }
        actualizarPanel();
    });
    panel.querySelectorAll('[data-p-modo]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const nombre = boton.dataset.pModo;
            const c = actualizarPanel();
            const monto = nombre === 'a_credito' ? c.aCredito : c.devuelto;
            enPorcentaje[nombre] = !enPorcentaje[nombre];
            boton.textContent = enPorcentaje[nombre] ? '%' : 'RD$';
            campo(nombre).value = enPorcentaje[nombre]
                ? (c.recibido > 0 && monto > 0 ? String(Math.round(monto / c.recibido * 10000) / 100) : '')
                : campoDinero(monto);
            campo(nombre).focus();
            actualizarPanel();
        });
    });
    p('[data-p-usar-credito]').addEventListener('click', () => {
        const c = calcular(leerEntrada(), contexto());
        campo('credito').value = campoDinero(Math.min(actual.credito_usable, c.debePagar));
        huboCambios = true;
        actualizarPanel();
    });
    p('[data-p-cargo-resto]')?.addEventListener('click', () => {
        const c = calcular(leerEntrada(), contexto());
        campo('cargado').value = campoDinero(Math.max(0, c.debePagar - Math.min(c.credito, c.debePagar) - c.recibido)) || '';
        huboCambios = true;
        actualizarPanel();
    });
    p('[data-p-completar]').addEventListener('click', () => {
        const c = calcular(leerEntrada(), contexto());
        campo('recibido').value = campoDinero(c.necesario + c.aCredito + c.devuelto) || '0.00';
        if (!campo('fecha').value) campo('fecha').value = hoy;
        huboCambios = true;
        actualizarPanel();
    });
    p('[data-p-como-descuento]').addEventListener('click', (evento) => {
        const c = calcular(leerEntrada(), contexto());
        const monto = parseInt(evento.currentTarget.dataset.monto || '0', 10);
        descEnPorcentaje = false;
        p('[data-p-desc-modo]').textContent = 'RD$';
        campo('descuento').value = campoDinero(c.descuento + monto);
        huboCambios = true;
        actualizarPanel();
    });
    panel.querySelectorAll('[data-p-atajo]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const c = calcular(leerEntrada(), contexto());
            const repartible = c.modo === 'no_asistio' || c.tipo !== 'automatico' ? c.recibido : c.exceso;
            const accion = boton.dataset.pAtajo;
            modoPesos('a_credito');
            modoPesos('devuelto');
            campo('a_credito').value = accion === 'credito' ? campoDinero(repartible) : '';
            campo('devuelto').value = accion === 'devolver' ? campoDinero(repartible) : '';
            huboCambios = true;
            actualizarPanel();
        });
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const c = actualizarPanel();
        if (!c || c.errores.length || guardando) return;
        guardando = true;
        p('[data-p-guardar]').disabled = true;
        p('[data-p-guardar]').textContent = 'Guardando...';
        const r = await enviar({
            accion: 'guardar',
            registro_id: actual.registro_id,
            version: actual.version,
            tipo: c.tipo,
            asistio: c.asistio ? '1' : '0',
            descuento: decimal(c.descuento),
            credito: decimal(c.credito),
            recibido: decimal(c.recibido),
            a_credito: decimal(c.aCredito),
            devuelto: decimal(c.devuelto),
            cargado: decimal(c.cargado),
            cargado_a: String(c.cargadoA || ''),
            metodo_id: String(c.metodoId || ''),
            fecha: c.fecha,
            nota: campo('nota').value,
        });
        guardando = false;
        p('[data-p-guardar]').textContent = 'Guardar cobro';
        if (r.ok) {
            aplicarRespuesta(r);
            cerrarPanel(true);
            avisar(r.mensaje);
            return;
        }
        // Error: se queda abierto con lo escrito y el mensaje del servidor.
        if (r.filas) aplicarRespuesta({ filas: r.filas });
        const lista = p('[data-p-errores]');
        lista.innerHTML = '';
        const li = document.createElement('li');
        li.textContent = r.mensaje || 'No se pudo guardar el cobro.';
        lista.appendChild(li);
        p('[data-p-guardar]').disabled = false;
    });

    // ---------- Acciones para varios ----------
    // Cada boton cambia segun lo que se ve: marcar/desmarcar asistencia y cobrar/deshacer cobros.
    // Siempre se aplican solo a las filas visibles y piden confirmacion con la lista de nombres.
    const masivo = raiz.querySelector('[data-masivo]');
    const masivoForm = masivo.querySelector('[data-masivo-form]');
    const confirmar = masivo.querySelector('[data-m-confirmar]');
    let masivoEnvio = null;

    const abrirMasivo = (tipo) => {
        let modo;
        let candidatas;
        if (tipo === 'asistencia') {
            modo = modoAsistencia() === 'desmarcar' ? 'desmarcar' : 'marcar';
            candidatas = filasVisibles().filter((f) => {
                const d = datosFila(f);
                return d && (modo === 'desmarcar' ? d.asistio : !d.asistio);
            });
        } else {
            modo = modoCobro() === 'deshacer' ? 'deshacer' : 'cobrar';
            candidatas = filasVisibles().filter((f) => {
                const d = datosFila(f);
                return d && (modo === 'deshacer' ? tieneCobro(d) : puedeCobroCompleto(d));
            });
        }
        const ids = candidatas.map((f) => f.dataset.registro);
        const datos = candidatas.map((f) => datosFila(f));
        const nombres = masivo.querySelector('[data-m-nombres]');
        nombres.innerHTML = '';
        let total = 0;
        datos.forEach((d) => {
            const li = document.createElement('li');
            if (modo === 'cobrar') {
                const debe = Math.max(0, d.valor - d.descuento);
                total += debe;
                li.textContent = d.nombre + ' · ' + dinero(debe);
            } else if (modo === 'deshacer') {
                total += d.recibido;
                const partes = [];
                if (d.recibido > 0) partes.push('recibido ' + dinero(d.recibido));
                if (d.credito > 0) partes.push('credito usado ' + dinero(d.credito));
                if (d.a_credito > 0) partes.push('paso a credito ' + dinero(d.a_credito));
                if (d.descuento > 0) partes.push('descuento ' + dinero(d.descuento));
                li.textContent = d.nombre + (partes.length ? ' · ' + partes.join(', ') : '');
            } else {
                li.textContent = d.nombre;
            }
            nombres.appendChild(li);
        });

        const alcance = filasVisibles().length !== filas().length ? ' (solo las que ves con el filtro o la busqueda actual)' : '';
        const titulos = {
            marcar: 'Marcar asistencia',
            desmarcar: 'Desmarcar asistencia',
            cobrar: 'Registrar cobro completo',
            deshacer: 'Deshacer cobros',
        };
        const textos = {
            marcar: 'Se marcaran como asistieron ' + ids.length + ' persona(s)' + alcance + ':',
            desmarcar: 'Se quitara la asistencia a ' + ids.length + ' persona(s)' + alcance + ':',
            cobrar: 'Se registrara el pago completo de ' + ids.length + ' persona(s)' + alcance + ', por ' + dinero(total) + ' en total. Solo a quien no tiene nada registrado:',
            deshacer: 'Se borrara el cobro de ' + ids.length + ' persona(s)' + alcance + ' (' + dinero(total) + ' recibidos). '
                + 'A cada una se le devuelve a su cuenta el credito que uso, se anula el credito que se le habia generado '
                + 'y quedan como "Debe pagar" o "Sin pagar". La asistencia no cambia. '
                + 'Si a alguien ya le usaron el credito generado en otro sendero, esa persona no se toca y se te avisa:',
        };
        const vacios = {
            marcar: 'Todas las personas visibles ya tienen la asistencia marcada.',
            desmarcar: 'Ninguna persona visible tiene la asistencia marcada.',
            cobrar: 'Ninguna persona visible esta pendiente de pago sin nada registrado.',
            deshacer: 'Ninguna persona visible tiene un cobro registrado.',
        };
        masivo.querySelector('[data-m-titulo]').textContent = titulos[modo];
        masivo.querySelector('[data-m-texto]').textContent = ids.length ? textos[modo] : vacios[modo];
        masivo.querySelector('[data-m-pago]').hidden = modo !== 'cobrar';
        if (modo === 'cobrar' && metodoRapido?.value && !masivoForm.elements.metodo_id.value) {
            masivoForm.elements.metodo_id.value = metodoRapido.value;
        }
        masivo.querySelector('[data-m-errores]').innerHTML = '';
        confirmar.disabled = ids.length === 0;
        confirmar.textContent = modo === 'deshacer' ? 'Deshacer cobros' : 'Confirmar';
        confirmar.classList.toggle('is-danger', modo === 'deshacer');

        masivoEnvio = { modo, ids };
        masivo.showModal();
    };
    raiz.querySelector('[data-masivo-asistencia]')?.addEventListener('click', () => abrirMasivo('asistencia'));
    raiz.querySelector('[data-masivo-cobro]')?.addEventListener('click', () => abrirMasivo('cobro'));
    masivo.querySelectorAll('[data-cerrar]').forEach((b) => b.addEventListener('click', () => masivo.close()));

    masivoForm.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!masivoEnvio || !masivoEnvio.ids.length) return;
        const errores = masivo.querySelector('[data-m-errores]');
        errores.innerHTML = '';
        const { modo, ids } = masivoEnvio;
        const datos = { registro_ids: ids };
        if (modo === 'marcar' || modo === 'desmarcar') {
            datos.accion = 'asistencia';
            datos.asistio = modo === 'marcar' ? '1' : '0';
        } else if (modo === 'deshacer') {
            datos.accion = 'deshacer_cobro';
        } else {
            datos.accion = 'cobro_completo';
            datos.metodo_id = masivoForm.elements.metodo_id.value;
            datos.fecha = masivoForm.elements.fecha.value;
            if (!datos.metodo_id) {
                errores.innerHTML = '<li>Indica el metodo de pago.</li>';
                return;
            }
        }
        confirmar.disabled = true;
        const r = await enviar(datos);
        confirmar.disabled = false;
        if (!r.ok) {
            const li = document.createElement('li');
            li.textContent = r.mensaje || 'No se pudo completar la accion.';
            errores.appendChild(li);
            return;
        }
        aplicarRespuesta(r);
        masivo.close();
        avisar(r.omitidos?.length ? r.mensaje + ' No se aplico a: ' + r.omitidos.join(' ') : r.mensaje, r.omitidos?.length ? 'error' : 'ok');
    });

    filtrar();
});
