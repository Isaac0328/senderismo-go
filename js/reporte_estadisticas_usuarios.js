document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-user-stats-modal]');
    const dataSource = document.getElementById('userStatsDetailsData');
    const grid = document.querySelector('[data-user-stats-grid]');
    const routesWrap = document.querySelector('[data-user-stats-routes-wrap]');
    const routesList = document.querySelector('[data-user-stats-routes]');
    const maintenanceLink = document.querySelector('[data-user-stats-maintenance]');
    const nodes = {
        name: document.querySelector('[data-user-stats-name]'),
        account: document.querySelector('[data-user-stats-account]'),
        senderos: document.querySelector('[data-user-stats-senderos]'),
        km: document.querySelector('[data-user-stats-km]'),
        registros: document.querySelector('[data-user-stats-registros]'),
        menores: document.querySelector('[data-user-stats-menores]')
    };

    if (!modal || !dataSource || !grid || !nodes.name || !nodes.account) return;

    const details = (() => {
        try {
            return JSON.parse(dataSource.textContent || '{}');
        } catch (error) {
            return {};
        }
    })();

    const valueOrDefault = (value) => {
        const text = String(value || '').trim();
        return text || 'No registrado';
    };

    const openDialog = () => {
        if (typeof modal.showModal === 'function') modal.showModal();
        else modal.setAttribute('open', 'open');
    };

    const closeDialog = () => {
        if (typeof modal.close === 'function') modal.close();
        else modal.removeAttribute('open');
    };

    const makeField = (label, value) => {
        const card = document.createElement('div');
        const labelNode = document.createElement('span');
        const valueNode = document.createElement('strong');
        labelNode.textContent = label;
        valueNode.textContent = valueOrDefault(value);
        card.append(labelNode, valueNode);
        return card;
    };

    const makeRoute = (route) => {
        const card = document.createElement('article');
        const title = document.createElement('strong');
        const meta = document.createElement('span');
        const state = document.createElement('span');
        title.textContent = valueOrDefault(route.nombre);
        meta.textContent = `${valueOrDefault(route.fecha)} / ${valueOrDefault(route.km)}`;
        state.textContent = valueOrDefault(route.asistio);
        card.append(title, meta, state);

        if (route.url) {
            const link = document.createElement('a');
            link.href = route.url;
            link.textContent = 'Ver sendero';
            card.append(link);
        }

        return card;
    };

    document.querySelectorAll('[data-user-stats-detail]').forEach((button) => {
        button.addEventListener('click', () => {
            const data = details[button.dataset.userStatsDetail || ''];
            if (!data) return;

            nodes.name.textContent = valueOrDefault(data.nombre);
            nodes.account.textContent = `@${valueOrDefault(data.usuario)} | ${valueOrDefault(data.rol)} | ${valueOrDefault(data.estado)}`;
            nodes.senderos.textContent = valueOrDefault(data.senderos);
            nodes.km.textContent = valueOrDefault(data.km);
            nodes.registros.textContent = valueOrDefault(data.registros);
            nodes.menores.textContent = valueOrDefault(data.menores);

            grid.replaceChildren(
                makeField('Telefono', data.telefono),
                makeField('Correo', data.email),
                makeField('Identificacion', data.identificacion),
                makeField('Edad', data.edad),
                makeField('Grupo sanguineo', data.grupo_sanguineo),
                makeField('Alergias', data.alergias),
                makeField('Enfermedad', data.enfermedad),
                makeField('Seguro medico', data.seguro),
                makeField('Experiencia', data.experiencia),
                makeField('Via de contacto', data.via),
                makeField('Referido por', data.referido),
                makeField('Emergencia', data.emergencia),
                makeField('Fecha de registro', data.creado),
                makeField('Ultimo login', data.ultimo_login),
                makeField('Ultima asistencia', data.ultima_asistencia)
            );

            if (routesWrap && routesList) {
                const routes = Array.isArray(data.rutas) ? data.rutas : [];
                routesWrap.hidden = routes.length === 0;
                routesList.replaceChildren(...routes.map(makeRoute));
            }

            if (maintenanceLink) {
                maintenanceLink.href = data.mantenimiento_url || '#';
            }

            openDialog();
            if (window.feather) window.feather.replace();
        });
    });

    document.querySelectorAll('[data-user-stats-close]').forEach((button) => {
        button.addEventListener('click', closeDialog);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeDialog();
    });
});
