document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-report-user-modal]');
    const dataSource = document.getElementById('reportUserDetailsData');
    const detailsGrid = document.querySelector('[data-report-user-grid]');
    const minorsWrap = document.querySelector('[data-report-user-minors-wrap]');
    const minorsList = document.querySelector('[data-report-user-minors]');
    const nameNode = document.querySelector('[data-report-user-name]');
    const accountNode = document.querySelector('[data-report-user-account]');

    if (!modal || !dataSource || !detailsGrid || !nameNode || !accountNode) return;

    const details = (() => {
        try {
            return JSON.parse(dataSource.textContent || '{}');
        } catch (error) {
            return {};
        }
    })();

    const openDialog = () => {
        if (typeof modal.showModal === 'function') modal.showModal();
        else modal.setAttribute('open', 'open');
    };

    const closeDialog = () => {
        if (typeof modal.close === 'function') modal.close();
        else modal.removeAttribute('open');
    };

    const valueOrDefault = (value) => {
        const text = String(value || '').trim();
        return text || 'No registrado';
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

    const makeMinor = (minor) => {
        const card = document.createElement('article');
        const name = document.createElement('strong');
        name.textContent = valueOrDefault(minor.nombre);
        card.append(name);

        [
            `Edad / sangre: ${valueOrDefault(minor.edad)} / ${valueOrDefault(minor.grupo_sanguineo)}`,
            `Inversion: ${valueOrDefault(minor.inversion)} - ${valueOrDefault(minor.monto)}`,
            `Salud: ${valueOrDefault(minor.alergias)} | ${valueOrDefault(minor.enfermedad)}`,
            `Seguro: ${valueOrDefault(minor.seguro)}`,
            `Emergencia: ${valueOrDefault(minor.emergencia)}`
        ].forEach((line) => {
            const span = document.createElement('span');
            span.textContent = line;
            card.append(span);
        });

        return card;
    };

    document.querySelectorAll('[data-report-user]').forEach((button) => {
        button.addEventListener('click', () => {
            const data = details[button.dataset.reportUser || ''];
            if (!data) return;

            nameNode.textContent = valueOrDefault(data.nombre);
            accountNode.textContent = `@${valueOrDefault(data.usuario)} | ID ${valueOrDefault(data.usuario_id)} | Registro ${valueOrDefault(data.registro)}`;

            detailsGrid.replaceChildren(
                makeField('Telefono', data.telefono),
                makeField('Correo', data.email),
                makeField('Identificacion', data.identificacion),
                makeField('Edad', data.edad),
                makeField('Grupo sanguineo', data.grupo_sanguineo),
                makeField('Inversion', `${valueOrDefault(data.inversion)} - ${valueOrDefault(data.monto)}`),
                makeField('Alergias', data.alergias),
                makeField('Enfermedad', data.enfermedad),
                makeField('Seguro medico', data.seguro),
                makeField('Experiencia', data.experiencia),
                makeField('Via de contacto', data.via),
                makeField('Referido por', data.referido),
                makeField('Emergencia', data.emergencia)
            );

            if (minorsWrap && minorsList) {
                const minors = Array.isArray(data.menores) ? data.menores : [];
                minorsWrap.hidden = minors.length === 0;
                minorsList.replaceChildren(...minors.map(makeMinor));
            }

            openDialog();
            if (window.feather) window.feather.replace();
        });
    });

    document.querySelectorAll('[data-report-user-close]').forEach((button) => {
        button.addEventListener('click', closeDialog);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeDialog();
    });
});
