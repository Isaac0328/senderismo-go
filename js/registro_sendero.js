document.addEventListener('DOMContentLoaded', () => {
    const alergiaRadios = document.querySelectorAll('input[name="es_alergico"]');
    const alergiaDetalle = document.querySelector('input[name="alergias_detalle"]');
    const viaSelect = document.querySelector('select[name="via_entero"]');
    const referidoInput = document.querySelector('input[name="referido_nombre"]');

    const syncAlergia = () => {
        if (!alergiaDetalle) return;
        const checked = document.querySelector('input[name="es_alergico"]:checked');
        const requiereDetalle = checked && checked.value === '1';
        alergiaDetalle.required = requiereDetalle;
        alergiaDetalle.closest('.field')?.classList.toggle('is-required', requiereDetalle);
    };

    const syncReferido = () => {
        if (!viaSelect || !referidoInput) return;
        const requiereReferido = viaSelect.value === 'Amigos';
        referidoInput.required = requiereReferido;
        referidoInput.closest('.field')?.classList.toggle('is-required', requiereReferido);
    };

    alergiaRadios.forEach((radio) => radio.addEventListener('change', syncAlergia));
    viaSelect?.addEventListener('change', syncReferido);

    document.querySelectorAll('.consent-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('aria-controls');
            const target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;

            const isOpen = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isOpen));
            button.classList.toggle('is-open', !isOpen);
            target.hidden = isOpen;
            button.querySelector('span').textContent = isOpen ? 'Leer consentimiento completo' : 'Ocultar consentimiento';
        });
    });

    const minorsRoot = document.querySelector('[data-minors-root]');
    const minorsModal = document.querySelector('[data-minors-modal]');
    const minorsEditor = document.querySelector('[data-minors-editor]');
    const minorsTemplate = document.getElementById('minorFormTemplate');
    const minorsSummary = document.querySelector('[data-minors-summary]');
    const minorsFields = document.querySelector('[data-minors-fields]');
    const minorsCount = document.querySelector('[data-minors-count]');
    const openMinorsBtn = document.querySelector('[data-open-minors-modal]');
    const addMinorBtn = document.querySelector('[data-add-minor]');
    const saveMinorsBtn = document.querySelector('[data-save-minors]');
    const closeMinorsBtns = document.querySelectorAll('[data-close-minors-modal]');
    let minors = [];

    const minorFields = [
        'nombre',
        'apellido',
        'telefono',
        'inversion_id',
        'rango_edad',
        'es_alergico',
        'alergias_detalle',
        'grupo_sanguineo',
        'enfermedad',
        'seguro_medico',
        'experiencia_senderismo',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono',
    ];

    const normalizeMinor = (minor = {}) => ({
        nombre: minor.nombre || '',
        apellido: minor.apellido || '',
        telefono: minor.telefono || '',
        inversion_id: minor.inversion_id || '',
        rango_edad: minor.rango_edad || '',
        es_alergico: String(minor.es_alergico ?? '0') === '1' ? '1' : '0',
        alergias_detalle: minor.alergias_detalle || '',
        grupo_sanguineo: minor.grupo_sanguineo || '',
        enfermedad: minor.enfermedad || '',
        seguro_medico: minor.seguro_medico || '',
        experiencia_senderismo: minor.experiencia_senderismo || '',
        emergencia_nombre: minor.emergencia_nombre || '',
        emergencia_parentesco: minor.emergencia_parentesco || '',
        emergencia_telefono: minor.emergencia_telefono || '',
    });

    const refreshFeather = () => {
        if (window.feather) {
            window.feather.replace();
        }
    };

    const syncAlergiaMenorCard = (card) => {
        const alergico = card.querySelector('[data-field="es_alergico"]');
        const detalle = card.querySelector('[data-field="alergias_detalle"]');
        if (!alergico || !detalle) return;
        const requiere = alergico.value === '1';
        detalle.required = requiere;
        detalle.closest('.field')?.classList.toggle('is-required', requiere);
    };

    const collectEditorMinors = () => {
        if (!minorsEditor) return [];
        return [...minorsEditor.querySelectorAll('[data-minor-card]')].map((card) => {
            const minor = {};
            minorFields.forEach((field) => {
                const input = card.querySelector(`[data-field="${field}"]`);
                minor[field] = input ? input.value.trim() : '';
            });
            return normalizeMinor(minor);
        });
    };

    const validateEditorMinors = () => {
        if (!minorsEditor) return true;
        const cards = [...minorsEditor.querySelectorAll('[data-minor-card]')];
        for (const card of cards) {
            syncAlergiaMenorCard(card);
            const requiredFields = [...card.querySelectorAll('[required]')];
            for (const field of requiredFields) {
                if (!field.value.trim()) {
                    field.focus();
                    field.reportValidity?.();
                    return false;
                }
            }
        }
        return true;
    };

    const renderHiddenMinors = () => {
        if (!minorsFields) return;
        minorsFields.innerHTML = '';
        minors.forEach((minor, index) => {
            minorFields.forEach((field) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `menores[${index}][${field}]`;
                input.value = minor[field] ?? '';
                minorsFields.appendChild(input);
            });
        });
    };

    const renderSummary = () => {
        if (!minorsSummary || !minorsCount) return;
        minorsCount.textContent = `${minors.length} ${minors.length === 1 ? 'menor agregado' : 'menores agregados'}`;
        if (!minors.length) {
            minorsSummary.innerHTML = `
                <div class="minors-empty">
                    <i data-feather="user-plus"></i>
                    <span>No has agregado menores para este sendero.</span>
                </div>
            `;
            refreshFeather();
            return;
        }

        minorsSummary.innerHTML = minors.map((minor, index) => `
            <article class="minor-summary-card">
                <span>${index + 1}</span>
                <div>
                    <strong>${escapeHtml(`${minor.nombre} ${minor.apellido}`.trim())}</strong>
                    <small>${escapeHtml(minor.grupo_sanguineo || 'Sangre no indicada')} | ${escapeHtml(minor.experiencia_senderismo || 'Experiencia no indicada')}</small>
                </div>
            </article>
        `).join('');
    };

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const renderHiddenAndSummary = () => {
        renderHiddenMinors();
        renderSummary();
    };

    const createMinorCard = (minor = {}) => {
        if (!minorsTemplate || !minorsEditor) return;
        const fragment = minorsTemplate.content.cloneNode(true);
        const card = fragment.querySelector('[data-minor-card]');
        const data = normalizeMinor(minor);

        minorFields.forEach((field) => {
            const input = card.querySelector(`[data-field="${field}"]`);
            if (input) input.value = data[field] ?? '';
        });

        card.querySelector('[data-remove-minor]')?.addEventListener('click', () => {
            card.remove();
            updateCardTitles();
        });
        card.querySelector('[data-field="es_alergico"]')?.addEventListener('change', () => syncAlergiaMenorCard(card));

        minorsEditor.appendChild(fragment);
        syncAlergiaMenorCard(card);
        updateCardTitles();
        refreshFeather();
    };

    function updateCardTitles() {
        if (!minorsEditor) return;
        minorsEditor.querySelectorAll('[data-minor-title]').forEach((title, index) => {
            title.textContent = `Menor ${index + 1}`;
        });
    }

    const openMinorsModal = () => {
        if (!minorsModal || !minorsEditor) return;
        minorsEditor.innerHTML = '';
        const editableMinors = minors.length ? minors : [normalizeMinor()];
        editableMinors.forEach((minor) => createMinorCard(minor));
        minorsModal.hidden = false;
        document.body.classList.add('modal-open');
    };

    const closeMinorsModal = () => {
        if (!minorsModal) return;
        minorsModal.hidden = true;
        document.body.classList.remove('modal-open');
    };

    if (minorsRoot) {
        try {
            const parsed = JSON.parse(minorsRoot.dataset.minors || '[]');
            minors = Array.isArray(parsed) ? parsed.map(normalizeMinor) : [];
        } catch (error) {
            minors = [];
        }
        renderHiddenAndSummary();
    }

    openMinorsBtn?.addEventListener('click', openMinorsModal);
    closeMinorsBtns.forEach((button) => button.addEventListener('click', closeMinorsModal));
    addMinorBtn?.addEventListener('click', () => createMinorCard(normalizeMinor()));
    saveMinorsBtn?.addEventListener('click', () => {
        if (!validateEditorMinors()) return;
        minors = collectEditorMinors().filter((minor) => minor.nombre || minor.apellido);
        renderHiddenAndSummary();
        closeMinorsModal();
    });

    syncAlergia();
    syncReferido();
});
