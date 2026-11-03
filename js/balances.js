document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.bal-page');
    if (!page) return;
    const flash = sessionStorage.getItem('balances-message');
    if (flash) {
        const feedback = page.querySelector('[data-bal-registro-feedback]') || document.getElementById('bal-feedback');
        feedback.textContent = flash;
        feedback.classList.toggle('success', feedback.matches('[data-bal-registro-feedback]'));
        feedback.hidden = false;
        sessionStorage.removeItem('balances-message');
        if (feedback.matches('[data-bal-registro-feedback]')) feedback.scrollIntoView({block: 'nearest'});
    }
    // Los montos se completan solos con dos decimales al salir del campo: 2000 pasa a 2000.00.
    page.addEventListener('change', event => {
        const campo = event.target;
        if (!(campo instanceof HTMLInputElement) || campo.type !== 'number' || campo.step !== '0.01' || campo.value === '') return;
        const valor = Number(campo.value);
        if (Number.isFinite(valor)) campo.value = valor.toFixed(2);
    });
    page.addEventListener('click', event => {
        const open = event.target.closest('[data-open]');
        if (open) document.getElementById(open.dataset.open)?.showModal();
        if (event.target.closest('[data-close]')) event.target.closest('dialog')?.close();
    });
    page.querySelectorAll('.bal-form').forEach(form => {
        const accountType = form.querySelector('[data-account-type]');
        if (accountType) {
            const limits = {titular:160, numero_cuenta:50, ubicacion:200};
            Object.entries(limits).forEach(([name, limit]) => { if (form.elements[name]) form.elements[name].maxLength = limit; });
            const updateAccountFields = () => {
                const kind = accountType.selectedOptions[0]?.dataset.class;
                form.querySelectorAll('[data-account-fields]').forEach(group => {
                    const show = kind && (group.dataset.accountFields === 'banco' ? kind === 'banco' : kind !== 'banco');
                    group.hidden = !show;
                    group.disabled = !show;
                });
            };
            accountType.addEventListener('change', updateAccountFields);
            updateAccountFields();
        }
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!form.reportValidity() || form.dataset.saving) return;
            if (form.dataset.confirmar && !window.confirm(form.dataset.confirmar)) return;
            const error = form.querySelector('.bal-form-error');
            const button = form.querySelector('[type=submit]');
            const data = new FormData(form);
            form.dataset.saving = '1';
            button.disabled = true;
            error.hidden = true;
            try {
                const response = await fetch(form.getAttribute('action'), {method: 'POST', body: data, headers: {'Accept':'application/json'}});
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('La sesion expiro o el servidor no respondio correctamente. Conserva los datos y vuelve a iniciar sesion.');
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo completar la operacion.');
                sessionStorage.setItem('balances-message', result.message);
                if (form.dataset.destino) location.assign(form.dataset.destino); else location.reload();
            } catch (reason) {
                error.textContent = reason.message;
                error.hidden = false;
                error.focus();
                button.disabled = false;
                delete form.dataset.saving;
            }
        });
    });
    // Acciones directas de una fila (por ejemplo, cancelar un gasto): confirman, envian y recargan.
    page.querySelectorAll('form[data-bal-accion]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.saving || !window.confirm(form.dataset.confirm)) return;
            const button = form.querySelector('[type=submit]');
            form.dataset.saving = '1';
            button.disabled = true;
            try {
                const response = await fetch(form.getAttribute('action'), {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}});
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('La sesion expiro o el servidor no respondio correctamente. Vuelve a iniciar sesion.');
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo completar la operacion.');
                sessionStorage.setItem('balances-message', result.message);
                location.reload();
            } catch (reason) {
                const feedback = document.getElementById('bal-feedback');
                feedback.textContent = reason.message;
                feedback.hidden = false;
                feedback.scrollIntoView({block: 'center'});
                button.disabled = false;
                delete form.dataset.saving;
            }
        });
    });
    page.querySelectorAll('[data-registro]').forEach(form => {
        const gasto = form.dataset.naturaleza === 'salida';
        const monto = form.querySelector('[data-monto]');
        const abonoInicial = form.querySelector('[data-abono-inicial]');
        const obligacion = form.querySelector('[data-obligacion]');
        const tercero = form.elements.tercero_id;
        const resumen = form.querySelector('[data-resumen]');
        const pago = form.querySelector('[data-pago]');
        const boton = form.querySelector('[data-boton]');
        const pesos = value => 'RD$ ' + (Math.round(value * 100) / 100).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const modo = () => form.querySelector('[name=modo]:checked')?.value || 'contado';
        const update = () => {
            const actual = modo();
            form.querySelectorAll('[data-modo-campos]').forEach(group => {
                const show = group.dataset.modoCampos.split(' ').includes(actual);
                group.hidden = !show;
                if (group.tagName === 'FIELDSET') group.disabled = !show;
                else group.querySelectorAll('input,select').forEach(field => { field.disabled = !show; });
            });
            if (tercero) tercero.required = actual === 'credito';
            form.querySelectorAll('[data-solo-credito]').forEach(help => { help.hidden = actual !== 'credito'; });
            const total = parseFloat(monto.value) || 0;
            const inicial = parseFloat(abonoInicial.value) || 0;
            // En credito solo se toca una cuenta si hay abono inicial.
            const mueveDinero = actual !== 'credito' || inicial > 0;
            pago.hidden = !mueveDinero;
            pago.disabled = !mueveDinero;
            abonoInicial.max = total > 0 ? total : '';
            const pendiente = parseFloat(obligacion?.selectedOptions[0]?.dataset.pendiente) || 0;
            monto.max = actual === 'abono' && pendiente > 0 ? pendiente : '';
            let texto = '';
            if (actual === 'credito' && total > 0) {
                texto = 'Se crea una cuenta por ' + (gasto ? 'pagar' : 'cobrar') + ' de <strong>' + pesos(total) + '</strong>'
                    + (inicial > 0 ? ', con abono de <strong>' + pesos(Math.min(inicial, total)) + '</strong>; queda pendiente <strong>' + pesos(Math.max(total - inicial, 0)) + '</strong>.' : '. No se mueve dinero hoy.');
            } else if (actual === 'abono' && pendiente > 0) {
                texto = 'Pendiente: <strong>' + pesos(pendiente) + '</strong>' + (total > 0 ? '. Despues de este abono queda <strong>' + pesos(Math.max(pendiente - total, 0)) + '</strong>.' : '.');
            }
            resumen.innerHTML = texto;
            if (form.dataset.confirmar) return;
            boton.textContent = actual === 'credito' ? 'Registrar cuenta por ' + (gasto ? 'pagar' : 'cobrar') : actual === 'abono' ? 'Registrar abono' : 'Registrar ' + (gasto ? 'gasto' : 'ingreso');
        };
        form.querySelectorAll('[name=modo]').forEach(radio => radio.addEventListener('change', update));
        // Sugerencias de concepto y referencia segun lo usado antes con la misma persona o entidad.
        let historial = {};
        try { historial = JSON.parse(form.querySelector('[data-sugeridos]')?.textContent || '{}'); } catch (e) { historial = {}; }
        const sugerir = [['concepto', 'conceptos', 'Usados antes'], ['referencia', 'referencias', 'Referencias anteriores']].map(([campo, lista, titulo]) => {
            const input = form.elements[campo];
            if (!input) return null;
            const datalist = form.querySelector('#bal-sugeridos-' + campo + '-' + form.dataset.naturaleza);
            if (datalist) input.setAttribute('list', datalist.id);
            input.setAttribute('autocomplete', 'off');
            const caja = document.createElement('div');
            caja.className = 'bal-sugeridos';
            caja.hidden = true;
            input.closest('label').after(caja);
            return {input, datalist, caja, lista, titulo};
        }).filter(Boolean);
        const terceroActual = () => modo() === 'abono' ? (obligacion?.selectedOptions[0]?.dataset.tercero || '') : (tercero?.value || '');
        const pintarSugeridos = () => {
            const datos = historial[terceroActual()] || {};
            sugerir.forEach(({input, datalist, caja, lista, titulo}) => {
                const valores = datos[lista] || [];
                if (datalist) datalist.replaceChildren(...valores.map(v => Object.assign(document.createElement('option'), {value: v})));
                caja.replaceChildren();
                caja.hidden = valores.length === 0 || input.disabled;
                if (caja.hidden) return;
                caja.append(Object.assign(document.createElement('span'), {textContent: titulo + ':'}));
                valores.forEach(v => {
                    const chip = Object.assign(document.createElement('button'), {type: 'button', textContent: v, title: 'Usar: ' + v});
                    chip.addEventListener('click', () => { input.value = v; input.dispatchEvent(new Event('input', {bubbles: true})); input.focus(); });
                    caja.append(chip);
                });
            });
        };
        tercero?.addEventListener('change', pintarSugeridos);
        pintarSugeridos();
        obligacion?.addEventListener('change', pintarSugeridos);
        form.querySelectorAll('[name=modo]').forEach(radio => radio.addEventListener('change', pintarSugeridos));
        obligacion?.addEventListener('change', () => {
            const pendiente = obligacion.selectedOptions[0]?.dataset.pendiente;
            if (pendiente) monto.value = pendiente;
            update();
        });
        [monto, abonoInicial].forEach(field => field.addEventListener('input', update));
        update();
    });
    const bulkForm = page.querySelector('.bal-bulk-form');
    if (bulkForm) {
        const items = [...page.querySelectorAll('[data-bulk-item]')];
        const count = bulkForm.querySelector('[data-bulk-count]');
        const submit = bulkForm.querySelector('[data-bulk-submit]');
        const account = bulkForm.querySelector('[name=cuenta_id]');
        const update = () => { const selected = items.filter(item => item.checked); count.textContent = selected.length; submit.disabled = selected.length === 0 || (account !== null && !account.value); };
        items.forEach(item => item.addEventListener('change', update));
        bulkForm.querySelector('[data-bulk-all]')?.addEventListener('click', () => { items.forEach(item => { item.checked = true; }); update(); });
        bulkForm.querySelector('[data-bulk-clear]')?.addEventListener('click', () => { items.forEach(item => { item.checked = false; }); update(); });
        bulkForm.querySelector('[name=cuenta_id]')?.addEventListener('change', update);
        bulkForm.addEventListener('submit', async event => {
            event.preventDefault();
            const selected = items.filter(item => item.checked);
            if (!selected.length || !bulkForm.reportValidity()) return;
            const question = bulkForm.dataset.confirm ? bulkForm.dataset.confirm.replace('{n}', selected.length) : 'Se asignaran ' + selected.length + ' registros a la cuenta seleccionada. Deseas continuar?';
            if (!window.confirm(question)) return;
            const data = new FormData(bulkForm);
            selected.forEach(item => { data.append('ids[]', item.value); data.append('origenes[]', item.dataset.origen); });
            submit.disabled = true;
            try {
                const response = await fetch(bulkForm.getAttribute('action'), {method:'POST',body:data,headers:{'Accept':'application/json'}});
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo completar la operacion masiva.');
                sessionStorage.setItem('balances-message', result.message);
                location.reload();
            } catch (reason) { const feedback=document.getElementById('bal-feedback'); feedback.textContent=reason.message; feedback.hidden=false; submit.disabled=false; }
        });
        update();
    }
});
