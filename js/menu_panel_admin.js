document.addEventListener('DOMContentLoaded', () => {
  const shell = document.querySelector('[data-menu-panel]');
  if (!shell) return;

  const cards = () => [...shell.querySelectorAll('[data-section-card]')];
  const cardOf = (element) => element.closest('[data-section-card]');
  const refreshIcons = () => { if (window.feather) window.feather.replace(); };
  const hasUnsaved = () => shell.querySelector('[data-section-card].is-dirty') !== null;
  let leaving = false; // true cuando la pagina se va a recargar a proposito

  /* ---------- Avisos breves ---------- */
  const toastElement = document.querySelector('[data-menu-toast]');
  let toastTimer;
  const toast = (message, type = 'ok') => {
    if (!toastElement) return;
    toastElement.textContent = message;
    toastElement.dataset.type = type;
    toastElement.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => { toastElement.hidden = true; }, type === 'error' ? 6000 : 2600);
  };

  /* ---------- Abrir y contraer secciones ---------- */
  const OPEN_KEY = 'sg-menu-panel-abiertas';
  const isOpen = (card) => card.classList.contains('is-open');
  const toggleAll = shell.querySelector('[data-toggle-all]');
  const syncToggleAll = () => {
    if (!toggleAll) return;
    const allOpen = cards().length > 0 && cards().every(isOpen);
    toggleAll.setAttribute('aria-pressed', String(allOpen));
    toggleAll.querySelector('span').textContent = allOpen ? 'Contraer todo' : 'Expandir todo';
  };
  const rememberOpen = () => {
    try { sessionStorage.setItem(OPEN_KEY, JSON.stringify(cards().filter(isOpen).map((card) => card.dataset.sectionId))); } catch (error) { /* sin almacenamiento */ }
  };
  const setOpen = (card, open, remember = true) => {
    const button = card.querySelector('[data-section-toggle]');
    const name = card.querySelector('[data-section-name]').textContent.trim();
    card.classList.toggle('is-open', open);
    card.querySelector('[data-section-form]').hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-label', `${open ? 'Contraer' : 'Expandir'} ${name}`);
    if (remember) rememberOpen();
    syncToggleAll();
  };

  /* ---------- Estado de cada seccion ---------- */
  const syncLeaving = (row) => {
    const select = row.querySelector('[data-module-destination]');
    const note = row.querySelector('[data-module-leaving]');
    if (!select || !note) return;
    const moving = select.value !== row.dataset.section;
    row.classList.toggle('is-leaving', moving);
    note.hidden = !moving;
    note.textContent = moving ? `Pasara a «${select.selectedOptions[0].textContent.trim()}» al guardar` : '';
  };

  // Ajusta contadores, el grupo de cada modulo y si la seccion puede eliminarse.
  const syncSection = (card) => {
    let modules = 0;
    card.querySelectorAll('[data-sort-list][data-group-key]').forEach((list) => {
      const rows = [...list.children].filter((child) => child.matches('[data-module-row]'));
      rows.forEach((row) => { row.querySelector('[data-module-group]').value = list.dataset.groupKey; });
      list.closest('[data-group]').querySelector('[data-group-count]').textContent = String(rows.length);
      modules += rows.length;
    });
    const subsections = card.querySelectorAll('[data-subsection]');
    card.querySelector('[data-count-modules]').textContent = String(modules);
    card.querySelector('[data-count-subs]').textContent = String(card.querySelectorAll('[data-subsection]:not(.is-deleted)').length);

    const dirty = card.classList.contains('is-dirty');
    const empty = modules === 0 && subsections.length === 0;
    const deleteButton = card.querySelector('[data-section-delete]');
    const lockNote = card.querySelector('[data-section-lock]');
    if (deleteButton) deleteButton.hidden = !(empty && !dirty);
    if (lockNote) {
      lockNote.hidden = empty && !dirty;
      lockNote.lastChild.textContent = empty ? 'Guarda los cambios y podras eliminarla.' : 'Para eliminarla, primero dejala sin modulos ni subsecciones.';
    }
  };
  const markDirty = (card) => {
    card.classList.add('is-dirty');
    syncSection(card);
  };
  const flash = (element) => {
    element.classList.add('is-moved');
    window.setTimeout(() => element.classList.remove('is-moved'), 650);
  };

  /* ---------- Guardar ---------- */
  const post = async (body) => {
    const response = await fetch(shell.dataset.processUrl, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    const result = await response.json().catch(() => null);
    if (!result) throw new Error('No se pudo guardar. Recarga la pagina e intenta de nuevo.');
    if (!result.ok) throw new Error(result.message || 'No se pudo guardar.');
    return result;
  };

  // Deja la tarjeta igual a como quedo en el servidor, sin recargar la pagina.
  const applySaved = (card, created) => {
    card.querySelectorAll('[data-subsection].is-deleted').forEach((subsection) => subsection.remove());
    Object.entries(created).forEach(([key, id]) => {
      const subsection = card.querySelector(`[data-subsection][data-group="${key}"]`);
      if (!subsection) return;
      subsection.dataset.group = String(id);
      subsection.querySelector('[data-sort-list]').dataset.groupKey = String(id);
      subsection.querySelectorAll(`[name^="subs[${key}]"]`).forEach((field) => {
        field.name = field.name.replace(`subs[${key}]`, `subs[${id}]`);
      });
    });
    card.querySelectorAll('[data-module-row].is-leaving').forEach((row) => {
      const targetId = row.querySelector('[data-module-destination]').value;
      const target = shell.querySelector(`[data-section-card][data-section-id="${targetId}"]`);
      if (!target) { row.remove(); return; }
      target.querySelector('[data-group="direct"] [data-sort-list]').appendChild(row);
      row.dataset.section = targetId;
      syncLeaving(row);
      syncSection(target);
    });
    card.classList.remove('is-dirty');
    syncSection(card);
  };

  const saveSection = async (form) => {
    const card = cardOf(form);
    if (card.classList.contains('is-saving')) return;
    const button = form.querySelector('[data-section-save]');
    card.classList.add('is-saving');
    button.disabled = true;
    try {
      const result = await post(new FormData(form));
      applySaved(card, result.subs || {});
      toast(result.message);
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      card.classList.remove('is-saving');
      button.disabled = false;
    }
  };

  const saveSectionOrder = async () => {
    const body = new FormData();
    body.set('csrf_token', shell.dataset.csrf);
    body.set('action', 'reorder_sections');
    cards().forEach((card) => body.append('orden[]', card.dataset.sectionId));
    try {
      toast((await post(body)).message);
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  // Recupera del servidor solo la seccion indicada, sin tocar las demas.
  const discardSection = async (card) => {
    try {
      const response = await fetch(window.location.pathname, { credentials: 'same-origin' });
      const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById(card.id);
      if (!fresh) throw new Error('sin seccion');
      const open = isOpen(card);
      card.replaceWith(fresh);
      refreshIcons();
      syncSection(fresh);
      setOpen(fresh, open, false);
      toast('Cambios descartados.');
    } catch (error) {
      leaving = true;
      window.location.reload();
    }
  };

  /* ---------- Mover elementos ---------- */
  const sortItems = (list, except) => [...list.children].filter((child) => (
    child.matches('[data-sort-item]') && child !== except && !child.hidden && !child.classList.contains('is-deleted')
  ));
  const afterMove = (item, group) => {
    flash(item);
    if (group === 'sections') { saveSectionOrder(); return; }
    markDirty(cardOf(item));
  };

  let drag = null;
  const moveDragged = () => {
    drag.item.style.left = `${drag.x - drag.dx}px`;
    drag.item.style.top = `${drag.y - drag.dy}px`;
  };
  const placeSlot = () => {
    const under = document.elementFromPoint(drag.x, drag.y);
    let list = under ? under.closest('[data-sort-list]') : null;
    while (list && list.dataset.sortGroup !== drag.group) {
      list = list.parentElement ? list.parentElement.closest('[data-sort-list]') : null;
    }
    // Soltar sobre el encabezado de un grupo cuenta como soltar dentro de el.
    if (!list && under && drag.group.startsWith('mod-')) {
      const group = under.closest('[data-group]:not(.is-deleted)');
      list = group ? group.querySelector(`[data-sort-list][data-sort-group="${drag.group}"]`) : null;
    }
    if (!list) return;
    const before = sortItems(list, drag.item).find((child) => {
      const rect = child.getBoundingClientRect();
      return drag.y < rect.top + rect.height / 2;
    });
    if (before) {
      if (drag.slot.nextElementSibling !== before) list.insertBefore(drag.slot, before);
    } else if (list.lastElementChild !== drag.slot) {
      list.appendChild(drag.slot);
    }
  };
  // Cerca de los bordes de la pantalla, la pagina se desplaza sola.
  const dragTick = () => {
    if (!drag || !drag.active) return;
    const step = drag.y < 150 ? -14 : (drag.y > window.innerHeight - 70 ? 14 : 0);
    if (step !== 0) {
      window.scrollBy(0, step);
      placeSlot();
    }
    drag.frame = window.requestAnimationFrame(dragTick);
  };
  const startDrag = () => {
    const { item } = drag;
    const grabbed = item.getBoundingClientRect();
    drag.dx = drag.startX - grabbed.left;
    drag.dy = drag.startY - grabbed.top;
    if (drag.group === 'sections') {
      // Las secciones se mueven contraidas para que quepan en pantalla.
      drag.reopen = cards().filter(isOpen);
      drag.reopen.forEach((card) => setOpen(card, false, false));
    }
    const rect = item.getBoundingClientRect();
    drag.slot = document.createElement('div');
    drag.slot.className = 'menu-drop-slot';
    drag.slot.style.height = `${rect.height}px`;
    item.before(drag.slot);
    item.style.width = `${rect.width}px`;
    item.classList.add('is-dragging');
    document.body.classList.add('menu-is-dragging');
    drag.active = true;
    moveDragged();
    drag.frame = window.requestAnimationFrame(dragTick);
  };
  const finishDrag = (commit) => {
    const current = drag;
    drag = null;
    current.handle.removeEventListener('pointermove', onDragMove);
    current.handle.removeEventListener('pointerup', onDragUp);
    current.handle.removeEventListener('pointercancel', onDragCancel);
    if (!current.active) return;
    window.cancelAnimationFrame(current.frame);
    if (commit) current.slot.replaceWith(current.item);
    else { current.slot.remove(); current.from.insertBefore(current.item, current.next); }
    current.item.classList.remove('is-dragging');
    ['width', 'left', 'top'].forEach((property) => current.item.style.removeProperty(property));
    document.body.classList.remove('menu-is-dragging');
    (current.reopen || []).forEach((card) => setOpen(card, true, false));
    if (current.item.parentElement !== current.from || current.item.nextElementSibling !== current.next) {
      afterMove(current.item, current.group);
    }
  };
  function onDragMove(event) {
    if (!drag) return;
    drag.x = event.clientX;
    drag.y = event.clientY;
    if (!drag.active) {
      if (Math.hypot(drag.x - drag.startX, drag.y - drag.startY) < 5) return;
      startDrag();
    }
    event.preventDefault();
    moveDragged();
    placeSlot();
  }
  function onDragUp() { if (drag) finishDrag(true); }
  function onDragCancel() { if (drag) finishDrag(false); }

  shell.addEventListener('pointerdown', (event) => {
    const handle = event.target.closest('[data-drag-handle]');
    if (!handle || handle.disabled || event.button > 0 || drag) return;
    const item = handle.closest('[data-sort-item]');
    const list = item.parentElement;
    drag = {
      handle, item, group: list.dataset.sortGroup, from: list, next: item.nextElementSibling,
      startX: event.clientX, startY: event.clientY, x: event.clientX, y: event.clientY, active: false,
    };
    try { handle.setPointerCapture(event.pointerId); } catch (error) { /* puntero ya liberado */ }
    handle.addEventListener('pointermove', onDragMove);
    handle.addEventListener('pointerup', onDragUp);
    handle.addEventListener('pointercancel', onDragCancel);
  });

  // Flechas: alternativa a arrastrar, tambien desde el teclado.
  const moveWithArrow = (button) => {
    const item = button.closest('[data-sort-item]');
    const list = item.parentElement;
    const group = list.dataset.sortGroup;
    const direction = Number(button.dataset.move);
    const items = sortItems(list, null);
    const neighbour = items[items.indexOf(item) + direction];
    if (neighbour) {
      if (direction < 0) list.insertBefore(item, neighbour);
      else list.insertBefore(neighbour, item);
    } else if (group.startsWith('mod-')) {
      // En el borde de un grupo, el modulo salta al grupo vecino.
      const lists = [...cardOf(item).querySelectorAll(`[data-sort-list][data-sort-group="${group}"]`)]
        .filter((candidate) => !candidate.closest('.is-deleted'));
      const other = lists[lists.indexOf(list) + direction];
      if (!other) return;
      if (direction < 0) other.appendChild(item);
      else other.insertBefore(item, other.firstElementChild);
    } else {
      return;
    }
    afterMove(item, group);
    button.focus();
  };

  /* ---------- Subsecciones ---------- */
  let newSubsections = 0;
  const addSubsection = (card) => {
    const holder = document.createElement('div');
    holder.innerHTML = card.querySelector('[data-subsection-template]').innerHTML.replaceAll('__KEY__', `new-${++newSubsections}`);
    const subsection = holder.firstElementChild;
    card.querySelector('[data-subsection-list]').appendChild(subsection);
    refreshIcons();
    markDirty(card);
    subsection.querySelector('[data-sub-name]').focus();
  };
  const setSubsectionDeleted = (subsection, deleted) => {
    const card = cardOf(subsection);
    if (deleted && subsection.querySelector('[data-module-row]')) {
      toast('Mueve sus modulos a otro grupo antes de eliminarla.', 'error');
      return;
    }
    if (deleted && subsection.dataset.group.startsWith('new-')) {
      subsection.remove();
    } else {
      subsection.classList.toggle('is-deleted', deleted);
      subsection.querySelector('[data-sub-delete-flag]').value = deleted ? '1' : '0';
      subsection.querySelector('[data-sub-name]').disabled = deleted;
    }
    markDirty(card);
  };

  /* ---------- Buscador ---------- */
  const search = shell.querySelector('[data-module-search]');
  const emptySearch = shell.querySelector('[data-module-empty]');
  const normalize = (text) => text.toLocaleLowerCase('es').normalize('NFD').replace(/[̀-ͯ]/g, '');
  const filterModules = () => {
    const term = normalize(search.value.trim());
    let visible = 0;
    cards().forEach((card) => {
      let matches = 0;
      card.querySelectorAll('[data-module-row]').forEach((row) => {
        const subsection = row.closest('[data-subsection]');
        const subsectionName = subsection ? subsection.querySelector('[data-sub-name]').value : '';
        const hit = term === '' || normalize(`${row.querySelector('.menu-module-text').textContent} ${subsectionName}`).includes(term);
        row.hidden = !hit;
        if (hit) matches++;
      });
      card.hidden = term !== '' && matches === 0;
      if (term !== '' && matches > 0) setOpen(card, true, false);
      visible += matches;
    });
    shell.classList.toggle('is-filtering', term !== '');
    emptySearch.hidden = term === '' || visible > 0;
  };
  if (search) search.addEventListener('input', filterModules);

  /* ---------- Eventos ---------- */
  const newSection = shell.querySelector('#menu-new-section');
  const newSectionToggle = shell.querySelector('[data-new-section-toggle]');
  const showNewSection = (show) => {
    newSection.hidden = !show;
    newSectionToggle.setAttribute('aria-expanded', String(show));
    if (show) newSection.querySelector('input[name="etiqueta"]').focus();
  };

  shell.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (button && button.matches('[data-move]')) { moveWithArrow(button); return; }
    if (button && button.matches('[data-add-subsection]')) { addSubsection(cardOf(button)); return; }
    if (button && button.matches('[data-sub-delete]')) { setSubsectionDeleted(button.closest('[data-subsection]'), true); return; }
    if (button && button.matches('[data-sub-restore]')) { setSubsectionDeleted(button.closest('[data-subsection]'), false); return; }
    if (button && button.matches('[data-section-discard]')) { discardSection(cardOf(button)); return; }
    if (button && button.matches('[data-new-section-toggle]')) { showNewSection(newSection.hidden); return; }
    if (button && button.matches('[data-new-section-cancel]')) { showNewSection(false); return; }
    if (button && button.matches('[data-toggle-all]')) {
      const open = !cards().every(isOpen);
      cards().forEach((card) => setOpen(card, open, false));
      rememberOpen();
      return;
    }
    // Todo el encabezado abre o contrae la seccion, salvo sus demas botones.
    const head = event.target.closest('[data-section-head]');
    if (head && (!button || button.matches('[data-section-toggle]'))) {
      const card = cardOf(head);
      setOpen(card, !isOpen(card));
    }
  });

  const onFieldChange = (event) => {
    const form = event.target.closest('[data-section-form]');
    if (!form) return;
    const card = cardOf(form);
    const field = event.target;
    if (field.matches('[data-field-label]')) card.querySelector('[data-section-label]').textContent = field.value;
    if (field.matches('[data-field-name]')) card.querySelector('[data-section-name]').textContent = field.value;
    if (field.matches('[data-field-icon]')) {
      card.querySelector('[data-section-symbol]').innerHTML = `<i data-feather="${field.value}"></i>`;
      refreshIcons();
    }
    if (field.matches('[data-field-active]')) {
      const state = card.querySelector('[data-section-state]');
      state.textContent = field.checked ? 'Visible' : 'Oculta';
      state.classList.toggle('on', field.checked);
      state.classList.toggle('off', !field.checked);
    }
    if (field.matches('[data-module-visible]')) field.closest('[data-module-row]').classList.toggle('is-off', !field.checked);
    if (field.matches('[data-module-destination]')) syncLeaving(field.closest('[data-module-row]'));
    markDirty(card);
  };
  shell.addEventListener('input', onFieldChange);
  shell.addEventListener('change', onFieldChange);

  shell.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-section-form]')) {
      event.preventDefault();
      saveSection(form);
      return;
    }
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    if (hasUnsaved() && !window.confirm('Hay secciones con cambios sin guardar y se perderan. Continuar de todos modos?')) {
      event.preventDefault();
      return;
    }
    leaving = true;
  });

  window.addEventListener('beforeunload', (event) => {
    if (leaving || !hasUnsaved()) return;
    event.preventDefault();
    event.returnValue = '';
  });

  /* ---------- Estado inicial ---------- */
  let opened = [];
  try { opened = JSON.parse(sessionStorage.getItem(OPEN_KEY) || '[]'); } catch (error) { opened = []; }
  const fromHash = document.getElementById(window.location.hash.slice(1));
  cards().forEach((card) => {
    syncSection(card);
    setOpen(card, opened.includes(card.dataset.sectionId) || card === fromHash, false);
  });
  rememberOpen();
  if (fromHash && fromHash.matches('[data-section-card]')) fromHash.scrollIntoView({ block: 'start' });
});
