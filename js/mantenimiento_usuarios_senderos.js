document.addEventListener('DOMContentLoaded', () => {
    const participantModal = document.querySelector('[data-participante-modal]');
    const participantOpen = document.querySelector('[data-open-participante]');
    const participantClose = document.querySelectorAll('[data-close-participante]');
    const participantModes = document.querySelectorAll('input[name="tipo_participante"]');
    const existingField = document.querySelector('.mus-existing-field');
    const newFields = document.querySelector('.mus-new-fields');
    const minorsRoot = document.querySelector('[data-minors-root]');
    const minorsList = document.querySelector('[data-minors-list]');
    const addMinorButton = document.querySelector('[data-add-minor]');
    const minorTemplate = document.getElementById('musMinorTemplate');

    const openDialog = (dialog) => {
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', 'open');
    };

    const closeDialog = (dialog) => {
        if (!dialog) return;
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
    };

    const syncParticipantMode = () => {
        const selected = document.querySelector('input[name="tipo_participante"]:checked');
        const isNew = selected?.value === 'nuevo';
        if (existingField) existingField.style.display = isNew ? 'none' : 'grid';
        if (newFields) newFields.style.display = isNew ? 'grid' : 'none';
    };

    const initUserSearch = () => {
        const root = document.querySelector('[data-user-search-root]');
        if (!root) return;
        const input = root.querySelector('[data-user-search-input]');
        const hidden = root.querySelector('[data-user-id-input]');
        const empty = root.querySelector('[data-user-empty]');
        const options = [...root.querySelectorAll('[data-user-option]')];
        if (!input || !hidden) return;

        const render = (clearSelection = false) => {
            const term = input.value.trim().toLowerCase();
            let visible = 0;
            if (clearSelection) hidden.value = '';
            options.forEach((option) => {
                const matches = term === '' || (option.dataset.userSearch || '').includes(term);
                option.hidden = !matches;
                if (matches) visible += 1;
            });
            if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
        };

        options.forEach((option) => option.addEventListener('click', () => {
            hidden.value = option.dataset.userId || '';
            input.value = option.dataset.userLabel || option.textContent.trim();
            options.forEach((item) => { item.hidden = item !== option; });
            if (empty) empty.style.display = 'none';
        }));
        input.addEventListener('input', () => render(true));
        input.addEventListener('focus', () => render(false));
        render(false);
    };

    participantOpen?.addEventListener('click', () => openDialog(participantModal));
    participantClose.forEach((button) => button.addEventListener('click', () => closeDialog(participantModal)));
    participantModes.forEach((radio) => radio.addEventListener('change', syncParticipantMode));
    syncParticipantMode();
    initUserSearch();

    const refreshMinorNames = () => {
        if (!minorsList) return;
        [...minorsList.querySelectorAll('[data-minor-card]')].forEach((card, index) => {
            const title = card.querySelector('[data-minor-title]');
            if (title) title.textContent = `Menor ${index + 1}`;
            card.querySelectorAll('[data-minor-name]').forEach((field) => {
                field.name = `menores[${index}][${field.dataset.minorName}]`;
            });
        });
    };

    const syncMinorAllergy = (card) => {
        const allergy = card.querySelector('[data-minor-allergy]');
        const detail = card.querySelector('[data-minor-allergy-detail]');
        if (!allergy || !detail) return;
        const required = allergy.value === '1';
        detail.required = required;
        detail.closest('.mus-field')?.classList.toggle('is-required', required);
    };

    const addMinorCard = () => {
        if (!minorTemplate || !minorsList) return;
        const fragment = minorTemplate.content.cloneNode(true);
        const card = fragment.querySelector('[data-minor-card]');
        minorsList.append(fragment);
        if (!card) return;
        card.querySelector('[data-remove-minor]')?.addEventListener('click', () => {
            card.remove();
            refreshMinorNames();
        });
        card.querySelector('[data-minor-allergy]')?.addEventListener('change', () => syncMinorAllergy(card));
        syncMinorAllergy(card);
        refreshMinorNames();
        if (window.feather) window.feather.replace();
    };

    addMinorButton?.addEventListener('click', addMinorCard);
    minorsRoot?.closest('form')?.addEventListener('reset', () => {
        if (minorsList) minorsList.replaceChildren();
    });

    const detailsModal = document.querySelector('[data-user-detail-modal]');
    const detailsSource = document.getElementById('musUserDetailsData');
    const details = (() => {
        try { return JSON.parse(detailsSource?.textContent || '{}'); }
        catch (error) { return {}; }
    })();

    const fieldCard = (label, value) => {
        const card = document.createElement('div');
        const title = document.createElement('span');
        const content = document.createElement('strong');
        title.textContent = label;
        content.textContent = String(value || '').trim() || 'No registrado';
        card.append(title, content);
        return card;
    };

    const fillGrid = (selector, fields) => {
        const grid = detailsModal?.querySelector(selector);
        if (!grid) return;
        grid.replaceChildren(...fields.map(([label, value]) => fieldCard(label, value)));
    };

    document.querySelectorAll('[data-user-detail-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            const data = details[button.dataset.userDetailTrigger || ''];
            if (!data || !detailsModal) return;

            detailsModal.querySelector('[data-detail-name]').textContent = data.nombre || 'Detalles del usuario';
            detailsModal.querySelector('[data-detail-account]').textContent = data.es_temporal
                ? 'Participante temporal de este sendero'
                : `@${data.usuario || 'sin usuario'} | ID ${data.usuario_id}`;

            fillGrid('[data-detail-contact]', [
                ['Telefono', data.telefono], ['Correo', data.email], ['Identificacion', data.identificacion],
                ['Edad', data.rango_edad], ['Inversion', data.inversion], ['Comprobante', data.comprobante], ['Talla de chaleco', data.chaleco_talla], ['Fecha de registro', data.registro]
            ]);
            fillGrid('[data-detail-health]', [
                ['Grupo sanguineo', data.grupo_sanguineo], ['Alergico', data.es_alergico],
                ['Detalle de alergias', data.alergias_detalle], ['Enfermedad', data.enfermedad],
                ['Seguro medico', data.seguro_medico], ['Experiencia', data.experiencia_senderismo],
                ['Como nos conocio', data.via_entero], ['Referido por', data.referido_nombre]
            ]);
            fillGrid('[data-detail-emergency]', [
                ['Nombre', data.emergencia_nombre], ['Parentesco', data.emergencia_parentesco],
                ['Telefono', data.emergencia_telefono]
            ]);

            const maintenanceLink = detailsModal.querySelector('[data-user-maintenance-link]');
            if (maintenanceLink) {
                maintenanceLink.hidden = Boolean(data.es_temporal || !data.usuario_id);
                maintenanceLink.href = data.usuario_id
                    ? `${maintenanceLink.dataset.baseUrl}?edit=${encodeURIComponent(data.usuario_id)}`
                    : '#';
            }
            openDialog(detailsModal);
        });
    });
    document.querySelectorAll('[data-close-user-detail]').forEach((button) => {
        button.addEventListener('click', () => closeDialog(detailsModal));
    });

    const deleteModal = document.querySelector('[data-delete-user-modal]');
    const deleteName = deleteModal?.querySelector('[data-delete-user-name]');
    let pendingDeleteForm = null;
    document.querySelectorAll('[data-delete-user-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            pendingDeleteForm = form;
            if (deleteName) deleteName.textContent = form.dataset.userName || 'este usuario';
            openDialog(deleteModal);
        });
    });
    deleteModal?.querySelector('[data-cancel-delete-user]')?.addEventListener('click', () => {
        pendingDeleteForm = null;
        closeDialog(deleteModal);
    });
    deleteModal?.querySelector('[data-confirm-delete-user]')?.addEventListener('click', () => {
        const form = pendingDeleteForm;
        pendingDeleteForm = null;
        closeDialog(deleteModal);
        form?.submit();
    });

    [participantModal, detailsModal, deleteModal].forEach((dialog) => {
        dialog?.addEventListener('click', (event) => {
            if (event.target === dialog) closeDialog(dialog);
        });
    });
});
