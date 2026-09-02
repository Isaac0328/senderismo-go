(function () {
    const list = document.querySelector('[data-question-list]');
    const template = document.getElementById('survey-question-template');
    const addButton = document.querySelector('[data-add-question]');
    const searchInput = document.querySelector('[data-survey-search]');
    const tabButtons = Array.from(document.querySelectorAll('[data-survey-tab]'));
    const tabPanels = Array.from(document.querySelectorAll('[data-survey-panel]'));
    const saveDialog = document.querySelector('[data-survey-save-dialog]');
    let pendingBuilderForm = null;

    function optionTypes(type) {
        return ['radio', 'checkbox', 'select'].includes(type);
    }

    function scaleText(value, label) {
        const cleanValue = String(value || '').trim();
        const cleanLabel = String(label || '').trim();
        return [cleanValue, cleanLabel].filter(Boolean).join(' ');
    }

    function typeLabel(type) {
        return {
            texto: 'Respuesta corta',
            textarea: 'Parrafo',
            radio: 'Una opcion',
            checkbox: 'Varias opciones',
            select: 'Lista desplegable',
            escala: 'Rango / escala',
            numero: 'Numero'
        }[type] || 'Respuesta corta';
    }

    function setQuestionCollapsed(card, collapsed) {
        card.classList.toggle('is-collapsed', collapsed);
        const toggle = card.querySelector('[data-toggle-question]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('title', collapsed ? 'Desplegar pregunta' : 'Plegar pregunta');
        }
    }

    function updateQuestionSummary(card) {
        const summary = card.querySelector('[data-question-summary]');
        const questionInput = card.querySelector('[name$="[pregunta]"], input[data-name="pregunta"]');
        const typeInput = card.querySelector('[data-question-type]');
        if (!summary) return;

        const question = questionInput ? questionInput.value.trim() : '';
        const type = typeInput ? typeLabel(typeInput.value) : 'Pregunta';
        summary.textContent = question || type;
    }

    function createOptionRow(value = '') {
        const row = document.createElement('div');
        row.className = 'survey-option-row';
        row.dataset.optionRow = '';
        row.innerHTML = `
            <span class="survey-option-number" data-option-number></span>
            <input type="text" data-option-input>
            <button type="button" class="survey-option-mini add" data-add-option aria-label="Agregar opcion">+</button>
            <button type="button" class="survey-option-mini remove" data-remove-option aria-label="Quitar opcion">&times;</button>
        `;
        const input = row.querySelector('[data-option-input]');
        if (input) input.value = value;
        return row;
    }

    function syncOptionsBuilder(card) {
        const listBox = card.querySelector('[data-options-list]');
        const hidden = card.querySelector('[data-options-hidden]');
        if (!listBox || !hidden) return;

        const rows = Array.from(listBox.querySelectorAll('[data-option-row]'));
        rows.forEach((row, index) => {
            const number = row.querySelector('[data-option-number]');
            const input = row.querySelector('[data-option-input]');
            if (number) number.textContent = `${index + 1} -`;
            if (input) input.placeholder = `Opcion ${index + 1}`;
        });

        hidden.value = rows
            .map((row) => row.querySelector('[data-option-input]')?.value.trim() || '')
            .filter(Boolean)
            .join('\n');
    }

    function updateScalePreview(card) {
        const minInput = card.querySelector('[data-scale-min]');
        const maxInput = card.querySelector('[data-scale-max]');
        const minLabel = card.querySelector('[data-scale-min-label]');
        const maxLabel = card.querySelector('[data-scale-max-label]');
        const preview = card.querySelector('[data-scale-preview]');
        const minText = card.querySelector('[data-scale-preview-min]');
        const maxText = card.querySelector('[data-scale-preview-max]');
        const scoreMax = card.querySelector('[data-score-max]');
        if (!minInput || !maxInput || !preview) return;

        let min = parseInt(minInput.value || '1', 10);
        let max = parseInt(maxInput.value || '5', 10);
        if (!Number.isFinite(min)) min = 1;
        if (!Number.isFinite(max)) max = 5;
        if (max <= min) max = min + 1;

        preview.min = String(min);
        preview.max = String(max);
        preview.value = String(min);
        if (scoreMax && card.querySelector('[data-question-type]')?.value === 'escala') {
            scoreMax.value = String(max);
        }
        if (minText) minText.textContent = scaleText(min, minLabel ? minLabel.value : '');
        if (maxText) maxText.textContent = scaleText(max, maxLabel ? maxLabel.value : '');
    }

    function refreshQuestions() {
        if (!list) return;
        const cards = Array.from(list.querySelectorAll('[data-question-card]'));
        cards.forEach((card, index) => {
            const number = card.querySelector('[data-question-number]');
            if (number) number.textContent = String(index + 1);
            card.querySelectorAll('[name], [data-name]').forEach((field) => {
                const key = field.dataset.name || (field.name.match(/\[([^\]]+)\]$/) || [])[1];
                if (!key) return;
                field.name = `preguntas[${index}][${key}]`;
            });
            updateQuestionSummary(card);
            setQuestionCollapsed(card, card.classList.contains('is-collapsed'));
        });
        updateOptionVisibility();
        document.querySelectorAll('[data-question-card]').forEach(updateScalePreview);
        document.querySelectorAll('[data-question-card]').forEach(syncOptionsBuilder);
        if (window.feather) window.feather.replace();
    }

    function syncBuilderBeforeSubmit(form) {
        form.querySelectorAll('[data-question-card]').forEach(syncOptionsBuilder);
    }

    function clearBuilderErrors(form) {
        form.querySelectorAll('.survey-client-error, .survey-question-error').forEach((node) => node.remove());
        form.querySelectorAll('.survey-field-error').forEach((field) => field.classList.remove('survey-field-error'));
    }

    function showBuilderError(form, message) {
        let error = form.querySelector('.survey-client-error');
        if (!error) {
            error = document.createElement('div');
            error.className = 'survey-client-error';
            const head = form.querySelector('.survey-card-head');
            if (head) {
                head.insertAdjacentElement('afterend', error);
            } else {
                form.prepend(error);
            }
        }
        error.textContent = message;
    }

    function showQuestionError(card, message) {
        const error = document.createElement('div');
        error.className = 'survey-question-error';
        error.textContent = message;
        const body = card.querySelector('[data-question-body]');
        if (body) {
            body.insertAdjacentElement('afterbegin', error);
        }
    }

    function revealField(field) {
        const card = field.closest('[data-question-card]');
        if (card) {
            setQuestionCollapsed(card, false);
        }

        window.setTimeout(() => {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof field.focus === 'function') field.focus();
        }, 80);
    }

    function validateBuilder(form) {
        clearBuilderErrors(form);
        syncBuilderBeforeSubmit(form);

        const title = form.querySelector('[name="titulo"]');
        if (!title || title.value.trim() === '') {
            showBuilderError(form, 'Coloca el titulo de la encuesta antes de guardar.');
            if (title) {
                title.classList.add('survey-field-error');
                revealField(title);
            }
            return false;
        }

        const cards = Array.from(form.querySelectorAll('[data-question-card]'));
        if (!cards.length) {
            showBuilderError(form, 'Agrega al menos una pregunta.');
            return false;
        }

        for (let index = 0; index < cards.length; index++) {
            const card = cards[index];
            const questionInput = card.querySelector('[name$="[pregunta]"], input[data-name="pregunta"]');
            const typeInput = card.querySelector('[data-question-type]');
            const type = typeInput ? typeInput.value : 'texto';

            if (!questionInput || questionInput.value.trim() === '') {
                const message = `La pregunta ${index + 1} necesita un texto.`;
                showBuilderError(form, message);
                showQuestionError(card, message);
                if (questionInput) questionInput.classList.add('survey-field-error');
                revealField(questionInput || card);
                return false;
            }

            if (optionTypes(type)) {
                const hidden = card.querySelector('[data-options-hidden]');
                const firstOption = card.querySelector('[data-option-input]');
                if (!hidden || hidden.value.trim() === '') {
                    const message = `La pregunta ${index + 1} necesita al menos una opcion de respuesta.`;
                    showBuilderError(form, message);
                    showQuestionError(card, message);
                    if (firstOption) firstOption.classList.add('survey-field-error');
                    revealField(firstOption || card);
                    return false;
                }
            }

            if (type === 'escala') {
                const minInput = card.querySelector('[data-scale-min]');
                const maxInput = card.querySelector('[data-scale-max]');
                const min = parseInt(minInput ? minInput.value : '1', 10);
                const max = parseInt(maxInput ? maxInput.value : '5', 10);
                if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) {
                    const message = `La escala de la pregunta ${index + 1} necesita un valor final mayor al inicial.`;
                    showBuilderError(form, message);
                    showQuestionError(card, message);
                    if (maxInput) maxInput.classList.add('survey-field-error');
                    revealField(maxInput || card);
                    return false;
                }
                if ((max - min) > 50) {
                    const message = `La escala de la pregunta ${index + 1} no debe pasar de 50 niveles.`;
                    showBuilderError(form, message);
                    showQuestionError(card, message);
                    if (maxInput) maxInput.classList.add('survey-field-error');
                    revealField(maxInput || card);
                    return false;
                }
            }
        }

        return true;
    }

    function submitBuilder(form) {
        if (form.dataset.submitting === '1') return;
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
        HTMLFormElement.prototype.submit.call(form);
    }

    function requestBuilderConfirmation(form) {
        pendingBuilderForm = form;
        if (saveDialog && typeof saveDialog.showModal === 'function') {
            saveDialog.showModal();
            return;
        }

        if (confirm('¿Deseas guardar los cambios realizados en esta encuesta?')) {
            submitBuilder(form);
        } else {
            pendingBuilderForm = null;
        }
    }

    function updateOptionVisibility() {
        document.querySelectorAll('[data-question-card]').forEach((card) => {
            const select = card.querySelector('[data-question-type]');
            const wrap = card.querySelector('[data-options-wrap]');
            const scaleWrap = card.querySelector('[data-scale-wrap]');
            if (!select) return;
            if (wrap) wrap.hidden = !optionTypes(select.value);
            if (scaleWrap) scaleWrap.hidden = select.value !== 'escala';
            updateScalePreview(card);
        });
    }

    function activateSurveyTab(tabName) {
        tabButtons.forEach((button) => {
            const active = button.dataset.surveyTab === tabName;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        tabPanels.forEach((panel) => {
            const active = panel.dataset.surveyPanel === tabName;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
    }

    function reloadCleanBuilder(form) {
        const url = form.dataset.clearUrl || window.location.pathname;
        window.location.href = url;
    }

    function resetBuilderOnManualRefresh() {
        const navigation = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
        if (!navigation || navigation.type !== 'reload') {
            return false;
        }

        const forms = document.querySelectorAll('.survey-builder-card');
        const mustOpenCleanBuilder = Array.from(forms).some((form) => (
            form.dataset.isEditing === '1' || form.dataset.hasRestoredState === '1'
        ));

        if (mustOpenCleanBuilder && forms[0]) {
            reloadCleanBuilder(forms[0]);
            return true;
        }

        forms.forEach((form) => {
            form.reset();
            clearBuilderErrors(form);
        });
        refreshQuestions();
        return false;
    }

    if (tabButtons.length && tabPanels.length) {
        const initialTab = document.querySelector('[data-survey-tab].active')?.dataset.surveyTab || 'crear';
        activateSurveyTab(initialTab);

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                activateSurveyTab(button.dataset.surveyTab || 'crear');
            });
        });
    }

    if (addButton && list && template) {
        addButton.addEventListener('click', () => {
            list.querySelectorAll('[data-question-card]').forEach((card) => setQuestionCollapsed(card, true));
            const node = template.content.firstElementChild.cloneNode(true);
            list.appendChild(node);
            setQuestionCollapsed(node, false);
            refreshQuestions();
            node.querySelector('input[data-name="pregunta"]')?.focus();
        });
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.survey-item-menu')) {
            document.querySelectorAll('.survey-item-menu[open]').forEach((menu) => {
                menu.removeAttribute('open');
            });
        }

        const remove = event.target.closest('[data-remove-question]');
        if (remove) {
            const cards = list ? list.querySelectorAll('[data-question-card]') : [];
            if (cards.length <= 1) {
                alert('La encuesta debe tener al menos una pregunta.');
                return;
            }
            remove.closest('[data-question-card]')?.remove();
            refreshQuestions();
            return;
        }

        const clear = event.target.closest('[data-clear-survey]');
        if (clear) {
            const form = clear.closest('.survey-builder-card');
            if (!form) return;
            const message = form.dataset.isEditing === '1'
                ? 'Salir de la edicion y abrir una encuesta nueva en blanco?'
                : 'Limpiar el formulario de la encuesta?';
            if (confirm(message)) {
                reloadCleanBuilder(form);
            }
            return;
        }

        const toggle = event.target.closest('[data-toggle-question]');
        if (toggle) {
            const card = toggle.closest('[data-question-card]');
            if (card) {
                setQuestionCollapsed(card, !card.classList.contains('is-collapsed'));
            }
            return;
        }

        const addOption = event.target.closest('[data-add-option]');
        if (addOption) {
            const card = addOption.closest('[data-question-card]');
            const listBox = card ? card.querySelector('[data-options-list]') : null;
            if (!card || !listBox) return;
            const row = createOptionRow('');
            listBox.appendChild(row);
            syncOptionsBuilder(card);
            row.querySelector('[data-option-input]')?.focus();
            return;
        }

        const removeOption = event.target.closest('[data-remove-option]');
        if (removeOption) {
            const card = removeOption.closest('[data-question-card]');
            const listBox = card ? card.querySelector('[data-options-list]') : null;
            if (!card || !listBox) return;
            const rows = listBox.querySelectorAll('[data-option-row]');
            if (rows.length <= 1) {
                const input = rows[0]?.querySelector('[data-option-input]');
                if (input) input.value = '';
                syncOptionsBuilder(card);
                input?.focus();
                return;
            }
            removeOption.closest('[data-option-row]')?.remove();
            syncOptionsBuilder(card);
            return;
        }

    });

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-question-type]')) {
            updateOptionVisibility();
            const card = event.target.closest('[data-question-card]');
            if (card) updateQuestionSummary(card);
        }
    });

    document.addEventListener('input', (event) => {
        if (event.target.matches('[data-scale-min], [data-scale-max], [data-scale-min-label], [data-scale-max-label]')) {
            const card = event.target.closest('[data-question-card]');
            if (card) updateScalePreview(card);
        }

        if (event.target.matches('[data-option-input]')) {
            const card = event.target.closest('[data-question-card]');
            if (card) syncOptionsBuilder(card);
        }

        if (event.target.matches('[name$="[pregunta]"], input[data-name="pregunta"]')) {
            const card = event.target.closest('[data-question-card]');
            if (card) updateQuestionSummary(card);
        }
    });

    saveDialog?.addEventListener('cancel', () => {
        pendingBuilderForm = null;
    });

    document.querySelector('[data-cancel-survey-save]')?.addEventListener('click', () => {
        pendingBuilderForm = null;
        saveDialog?.close();
    });

    document.querySelector('[data-confirm-survey-save]')?.addEventListener('click', () => {
        const form = pendingBuilderForm;
        pendingBuilderForm = null;
        saveDialog?.close();
        if (form) submitBuilder(form);
    });

    document.addEventListener('submit', (event) => {
        if (event.target.matches('form[data-confirm]') && !event.target.dataset.confirmed) {
            event.preventDefault();
            if (confirm(event.target.dataset.confirm || 'Confirmar accion?')) {
                event.target.dataset.confirmed = '1';
                HTMLFormElement.prototype.submit.call(event.target);
            }
            return;
        }

        if (event.target.matches('.survey-builder-card')) {
            event.preventDefault();
            if (validateBuilder(event.target)) {
                if (event.target.dataset.isEditing === '1') {
                    requestBuilderConfirmation(event.target);
                } else {
                    submitBuilder(event.target);
                }
            }
        }
    }, true);

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const term = searchInput.value.trim().toLowerCase();
            document.querySelectorAll('[data-survey-row]').forEach((row) => {
                row.hidden = term !== '' && !row.textContent.toLowerCase().includes(term);
            });
        });
    }

    if (!resetBuilderOnManualRefresh()) {
        refreshQuestions();
    }
})();
