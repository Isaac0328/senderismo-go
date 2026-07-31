document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('senderosTable');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr')) : [];
    const galleryInput = document.getElementById('galeria');
    const galleryHelp = document.getElementById('galleryHelp');
    const dateInput = document.getElementById('fecha_sendero');
    const datePreview = document.getElementById('fechaSenderoPreview');
    const investmentWrap = document.getElementById('investmentOptionsAdmin');
    const investmentTemplate = document.getElementById('investmentOptionTemplate');
    const addInvestmentButton = document.getElementById('addInvestmentOption');
    const senderoForm = document.querySelector('form.senderos-form');
    const senderoSections = Array.from(document.querySelectorAll('[data-sendero-section]'));

    const openSenderoSection = (element) => {
        const section = element?.closest('[data-sendero-section]');
        if (!section) return;
        section.classList.remove('is-collapsed');
        const toggle = section.querySelector('[data-sendero-section-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
    };

    const reportSenderoFieldError = (field, message) => {
        if (!field) return;
        field.setCustomValidity(message);
        openSenderoSection(field);
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(() => field.reportValidity(), 180);
    };

    if (senderoForm && investmentWrap) {
        senderoForm.addEventListener('input', (event) => {
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement) {
                event.target.setCustomValidity('');
            }
        });

        senderoForm.addEventListener('submit', (event) => {
            const state = senderoForm.querySelector('[name="estado"]')?.value || 'pendiente';
            const investmentCards = Array.from(investmentWrap.querySelectorAll('[data-investment-card]'));
            const activeCards = investmentCards.filter((card) => card.querySelector('input[name$="[activo]"]')?.checked);

            if (state === 'pendiente' && activeCards.length === 0) {
                event.preventDefault();
                const activeField = investmentCards[0]?.querySelector('input[name$="[activo]"]');
                reportSenderoFieldError(activeField, 'Activa al menos una inversion para este sendero.');
                return;
            }

            for (const card of activeCards) {
                const amountField = card.querySelector('input[name$="[monto]"]');
                const amount = Number(amountField?.value || 0);
                if (!amountField || !Number.isFinite(amount) || amount <= 0) {
                    event.preventDefault();
                    reportSenderoFieldError(amountField, 'Coloca un monto mayor a cero para esta inversion activa.');
                    return;
                }
            }

            if (state === 'pendiente') {
                const senderoDate = senderoForm.querySelector('[name="fecha_sendero"]');
                if (!senderoDate?.value) {
                    event.preventDefault();
                    reportSenderoFieldError(senderoDate, 'Selecciona la fecha del proximo sendero.');
                    return;
                }

                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const selectedDate = new Date(`${senderoDate.value}T00:00:00`);
                if (selectedDate < today) {
                    event.preventDefault();
                    reportSenderoFieldError(senderoDate, 'La fecha debe ser de hoy o posterior.');
                }
            }
        });
    }

    const sortableGallery = document.querySelector('[data-sortable-gallery]');
    const galleryOrderForm = document.querySelector('[data-gallery-order-form]');
    const gallerySaveOrder = document.querySelector('[data-gallery-save-order]');
    const galleryOrderStatus = document.querySelector('[data-gallery-order-status]');

    if (sortableGallery && galleryOrderForm) {
        let draggedItem = null;

        const galleryItems = () => Array.from(sortableGallery.querySelectorAll('[data-gallery-item]'));

        const syncGalleryOrder = () => {
            const items = galleryItems();
            items.forEach((item, index) => {
                const number = item.querySelector('[data-gallery-order-number]');
                const left = item.querySelector('[data-gallery-move="left"]');
                const right = item.querySelector('[data-gallery-move="right"]');
                if (number) number.textContent = String(index + 1);
                if (left) left.disabled = index === 0;
                if (right) right.disabled = index === items.length - 1;
            });
        };

        const markGalleryDirty = () => {
            galleryOrderForm.classList.add('is-dirty');
            if (gallerySaveOrder) gallerySaveOrder.disabled = false;
            if (galleryOrderStatus) galleryOrderStatus.textContent = 'Hay cambios pendientes. Guarda el nuevo orden.';
            syncGalleryOrder();
        };

        sortableGallery.addEventListener('dragstart', (event) => {
            const item = event.target.closest('[data-gallery-item]');
            if (!item) return;
            draggedItem = item;
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.imageId || '');
        });

        sortableGallery.addEventListener('dragover', (event) => {
            if (!draggedItem) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            const target = event.target.closest('[data-gallery-item]');
            galleryItems().forEach((item) => item.classList.toggle('is-drag-target', item === target && item !== draggedItem));
        });

        sortableGallery.addEventListener('drop', (event) => {
            event.preventDefault();
            const target = event.target.closest('[data-gallery-item]');
            if (!draggedItem || !target || target === draggedItem) return;

            const items = galleryItems();
            const fromIndex = items.indexOf(draggedItem);
            const targetIndex = items.indexOf(target);
            sortableGallery.insertBefore(draggedItem, fromIndex < targetIndex ? target.nextSibling : target);
            markGalleryDirty();
        });

        sortableGallery.addEventListener('dragend', () => {
            galleryItems().forEach((item) => item.classList.remove('is-dragging', 'is-drag-target'));
            draggedItem = null;
        });

        sortableGallery.addEventListener('click', (event) => {
            const moveButton = event.target.closest('[data-gallery-move]');
            if (moveButton) {
                const item = moveButton.closest('[data-gallery-item]');
                if (!item) return;
                if (moveButton.dataset.galleryMove === 'left' && item.previousElementSibling) {
                    sortableGallery.insertBefore(item, item.previousElementSibling);
                    markGalleryDirty();
                }
                if (moveButton.dataset.galleryMove === 'right' && item.nextElementSibling) {
                    sortableGallery.insertBefore(item.nextElementSibling, item);
                    markGalleryDirty();
                }
                return;
            }

            const deleteButton = event.target.closest('[data-gallery-delete-form]');
            if (!deleteButton) return;
            const deleteForm = document.getElementById(deleteButton.dataset.galleryDeleteForm || '');
            if (deleteForm && window.confirm('¿Quitar esta imagen de la galeria?')) {
                deleteForm.submit();
            }
        });

        galleryOrderForm.addEventListener('submit', () => {
            if (gallerySaveOrder) {
                gallerySaveOrder.disabled = true;
                gallerySaveOrder.textContent = 'Guardando...';
            }
        });

        syncGalleryOrder();
    }

    senderoSections.forEach((section) => {
        const toggle = section.querySelector('[data-sendero-section-toggle]');
        if (!toggle) return;

        const syncSectionState = () => {
            toggle.setAttribute('aria-expanded', section.classList.contains('is-collapsed') ? 'false' : 'true');
        };

        syncSectionState();

        toggle.addEventListener('click', () => {
            section.classList.toggle('is-collapsed');
            syncSectionState();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();

            rows.forEach((tr) => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    if (galleryInput && galleryHelp) {
        galleryInput.addEventListener('change', () => {
            const count = galleryInput.files ? galleryInput.files.length : 0;
            galleryHelp.textContent = count > 0
                ? `${count} imagen(es) seleccionada(s).`
                : 'Puedes cargar varias imagenes a la vez.';
        });
    }

    if (dateInput && datePreview) {
        if (dateInput.type === 'date') {
            const updateNativeDatePreview = () => {
                if (!dateInput.value) {
                    datePreview.textContent = 'Obligatoria solo para proximos senderos.';
                    return;
                }

                const [year, month, day] = dateInput.value.split('-').map(Number);
                const parsedDate = new Date(year, month - 1, day);

                datePreview.textContent = `Fecha seleccionada: ${parsedDate.toLocaleDateString('es-DO', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })}`;
            };

            dateInput.addEventListener('change', updateNativeDatePreview);
            dateInput.addEventListener('input', updateNativeDatePreview);
            updateNativeDatePreview();
        } else {

        const pad = (value) => String(value).padStart(2, '0');

        const parseVisualDate = (value) => {
            const match = value.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (!match) return null;

            const day = Number(match[1]);
            const month = Number(match[2]);
            const year = Number(match[3]);
            const date = new Date(year, month - 1, day);

            if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                return null;
            }

            return { date, iso: `${year}-${pad(month)}-${pad(day)}` };
        };

        const updateDatePreview = () => {
            if (!dateInput.value) {
                datePreview.textContent = 'Selecciona la fecha del sendero.';
                dateHiddenInput.value = '';
                return;
            }

            const parsed = parseVisualDate(dateInput.value);
            if (!parsed) {
                dateHiddenInput.value = '';
                datePreview.textContent = 'Usa el formato dia/mes/año. Ej: 03/08/2026.';
                return;
            }

            dateHiddenInput.value = parsed.iso;
            datePreview.textContent = `Fecha seleccionada: ${parsed.date.toLocaleDateString('es-DO', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })}`;
        };

        dateInput.addEventListener('change', updateDatePreview);
        dateInput.addEventListener('input', updateDatePreview);
        updateDatePreview();
        }
    }

    document.querySelectorAll('[data-duration-group]').forEach((group) => {
        const hoursInput = group.querySelector('[data-duration-hours]');
        const minutesInput = group.querySelector('[data-duration-minutes]');
        const totalInput = group.querySelector('[data-duration-total]');

        if (!hoursInput || !minutesInput || !totalInput) return;

        const updateTotal = () => {
            const hours = Math.max(0, Number.parseInt(hoursInput.value || '0', 10) || 0);
            const minutes = Math.min(59, Math.max(0, Number.parseInt(minutesInput.value || '0', 10) || 0));

            if (String(minutesInput.value) !== String(minutes)) {
                minutesInput.value = String(minutes);
            }

            const total = (hours * 60) + minutes;
            totalInput.value = total > 0 ? String(total) : '';
        };

        hoursInput.addEventListener('input', updateTotal);
        minutesInput.addEventListener('input', updateTotal);
        updateTotal();
    });

    function syncMeetingPoint(select) {
        const card = select.closest('.meeting-card');
        if (!card) return;

        const selectedOption = select.options[select.selectedIndex];
        const addressInput = card.querySelector('[data-meeting-address]');
        const mapInput = card.querySelector('[data-meeting-map]');

        if (addressInput) {
            addressInput.value = selectedOption?.dataset.direccion || '';
        }
        if (mapInput) {
            mapInput.value = selectedOption?.dataset.url || '';
        }
    }

    document.querySelectorAll('.meeting-point-select').forEach((select) => {
        select.addEventListener('change', () => syncMeetingPoint(select));
        syncMeetingPoint(select);
    });

    function prepareInvestmentCard(card, index) {
        const order = index + 1;

        const replaceTokenAttribute = (element, attribute) => {
            const value = element.getAttribute(attribute);
            if (!value) return;

            element.setAttribute(
                attribute,
                value
                    .replaceAll('__INDEX__', String(index))
                    .replaceAll('__ORDER__', String(order))
            );
        };

        card.querySelectorAll('[id]').forEach((element) => replaceTokenAttribute(element, 'id'));
        card.querySelectorAll('[for]').forEach((element) => replaceTokenAttribute(element, 'for'));
        card.querySelectorAll('[data-modal-target]').forEach((element) => replaceTokenAttribute(element, 'data-modal-target'));
        card.querySelectorAll('[data-count-target]').forEach((element) => replaceTokenAttribute(element, 'data-count-target'));

        card.querySelectorAll('[data-name-template]').forEach((field) => {
            const template = field.dataset.nameTemplate || '';
            field.name = template
                .replaceAll('__INDEX__', String(index))
                .replaceAll('__ORDER__', String(order));
            field.removeAttribute('data-name-template');
        });

        const title = card.querySelector('[data-investment-title]');
        if (title) {
            title.textContent = `Inversion ${order}`;
        }

        const modalTitle = card.querySelector('[data-investment-modal-title]');
        if (modalTitle) {
            modalTitle.textContent = `Incluye - Inversion ${order}`;
        }

        const orderInput = card.querySelector(`input[name="inversiones[${index}][orden]"]`);
        if (orderInput) {
            orderInput.value = String(order);
        }
    }

    function refreshInvestmentCards() {
        if (!investmentWrap) return;

        investmentWrap.querySelectorAll('[data-investment-card]').forEach((card, index) => {
            prepareInvestmentCard(card, index);
            updateModalCountFromCard(card);
        });
    }

    function updateModalCountFromCard(card) {
        const modal = card.querySelector('.detail-modal');
        if (modal) {
            updateModalCount(modal);
        }
    }

    if (investmentWrap) {
        refreshInvestmentCards();
    }

    if (addInvestmentButton && investmentWrap && investmentTemplate) {
        addInvestmentButton.addEventListener('click', () => {
            const index = investmentWrap.querySelectorAll('[data-investment-card]').length;
            const fragment = investmentTemplate.content.cloneNode(true);
            const card = fragment.querySelector('[data-investment-card]');
            if (!card) return;

            prepareInvestmentCard(card, index);
            investmentWrap.appendChild(card);
            enableSortableModalLists(card);
            const firstInput = card.querySelector('input[type="text"]');
            firstInput?.focus();
            updateModalCountFromCard(card);
        });
    }

    function getDragAfterElement(list, y) {
        const items = [...list.querySelectorAll('.modal-check-item:not(.is-dragging)')];

        return items.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            }

            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    function enableSortableModalList(list) {
        if (!list || list.dataset.sortReady === '1') return;
        list.dataset.sortReady = '1';

        let draggedItem = null;
        let pressTimer = null;
        let startX = 0;
        let startY = 0;
        let isDragging = false;
        let activePointerId = null;

        function clearPressTimer() {
            if (pressTimer) {
                window.clearTimeout(pressTimer);
                pressTimer = null;
            }
        }

        function finishDrag(event) {
            clearPressTimer();

            if (draggedItem) {
                draggedItem.classList.remove('is-dragging');
                if (activePointerId !== null && draggedItem.hasPointerCapture?.(activePointerId)) {
                    draggedItem.releasePointerCapture(activePointerId);
                }
            }

            draggedItem = null;
            isDragging = false;
            activePointerId = null;
        }

        function startDrag() {
            if (!draggedItem || isDragging) return;

            isDragging = true;
            draggedItem.classList.add('is-dragging');

            if (activePointerId !== null) {
                try {
                    draggedItem.setPointerCapture?.(activePointerId);
                } catch (error) {
                    // Some browsers only allow capture on the original target; reordering still works without it.
                }
            }
        }

        list.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('.modal-drag-handle');
            const item = handle?.closest('.modal-check-item');
            if (!handle || !item || !list.contains(item)) return;

            event.preventDefault();
            draggedItem = item;
            activePointerId = event.pointerId;
            startX = event.clientX;
            startY = event.clientY;

            pressTimer = window.setTimeout(startDrag, 180);
        });

        list.addEventListener('pointermove', (event) => {
            if (!draggedItem) return;

            const moved = Math.hypot(event.clientX - startX, event.clientY - startY);
            if (!isDragging && moved > 4) {
                startDrag();
            }

            if (!isDragging) return;

            event.preventDefault();
            const afterElement = getDragAfterElement(list, event.clientY);

            if (afterElement == null) {
                list.appendChild(draggedItem);
            } else {
                list.insertBefore(draggedItem, afterElement);
            }
        });

        list.addEventListener('pointerup', finishDrag);
        list.addEventListener('pointercancel', finishDrag);
        list.addEventListener('lostpointercapture', finishDrag);
        list.addEventListener('dragstart', (event) => event.preventDefault());
        list.addEventListener('click', (event) => {
            if (event.target.closest('.modal-drag-handle')) {
                event.preventDefault();
            }
        });
    }

    function enableSortableModalLists(scope = document) {
        scope.querySelectorAll('[data-sortable-modal-list]').forEach(enableSortableModalList);
    }

    enableSortableModalLists();

    function updateModalCount(modal) {
        const list = modal.querySelector('[data-count-target]');
        if (!list) return;

        const targetId = list.dataset.countTarget;
        const target = document.getElementById(targetId);
        const checked = list.querySelectorAll('input[type="checkbox"]:checked').length;

        if (target) {
            target.textContent = String(checked);
        }
    }

    document.querySelectorAll('.detail-modal').forEach((modal) => {
        updateModalCount(modal);
    });

    document.addEventListener('change', (event) => {
        const checkbox = event.target.closest('.detail-modal input[type="checkbox"]');
        if (!checkbox) return;

        const modal = checkbox.closest('.detail-modal');
        if (modal) {
            updateModalCount(modal);
        }
    });

    document.addEventListener('click', (event) => {
        const moveButton = event.target.closest('[data-modal-move]');
        if (!moveButton) return;

        event.preventDefault();
        const item = moveButton.closest('.modal-check-item');
        const list = item?.closest('[data-sortable-modal-list]');
        if (!item || !list) return;

        if (moveButton.dataset.modalMove === 'up' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        }

        if (moveButton.dataset.modalMove === 'down' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }

        item.classList.add('is-reordered');
        window.setTimeout(() => item.classList.remove('is-reordered'), 220);
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.detail-modal-trigger');
        if (!trigger) return;

        const modalId = trigger.dataset.modalTarget;
        const modal = modalId ? document.getElementById(modalId) : null;

        if (!modal) return;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    });

    document.addEventListener('click', (event) => {
        const closeButton = event.target.closest('[data-modal-close]');
        if (!closeButton) return;

        const modal = closeButton.closest('.detail-modal');

        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        updateModalCount(modal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('.detail-modal.is-open').forEach((modal) => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            updateModalCount(modal);
        });
    });
});
