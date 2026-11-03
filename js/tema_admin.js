document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.tema-form');
    if (!form) return;

    const customRadio = form.querySelector('input[name="tema"][value="personalizado"]');
    const colorInputs = Array.from(form.querySelectorAll('input[type="color"]'));
    const preview = document.querySelector('.theme-preview');

    const syncPreview = () => {
        if (!preview) return;
        const values = Object.fromEntries(colorInputs.map((input) => [input.name, input.value]));
        preview.style.setProperty('--app-primary', values.primary_color || '#255f38');
        preview.style.setProperty('--app-primary-dark', values.primary_dark_color || '#102617');
        preview.style.setProperty('--app-accent', values.accent_color || '#e10600');
        preview.style.setProperty('--app-accent-dark', values.accent_dark_color || '#b90000');
        preview.style.setProperty('--app-bg', values.background_color || '#f3f6ef');
        preview.style.setProperty('--app-surface', values.surface_color || '#ffffff');
        preview.style.setProperty('--app-text', values.text_color || '#111111');
        preview.style.setProperty('--app-muted', values.muted_color || '#5f6d64');
    };

    // Acepta "#44719d", "44719D", "#abc" y texto pegado con espacios o comillas.
    const normalizeHex = (text) => {
        const clean = String(text || '').trim().replace(/[^0-9a-fA-F]/g, '');
        if (clean.length === 3) return '#' + clean.split('').map((c) => c + c).join('').toLowerCase();
        return clean.length === 6 ? '#' + clean.toLowerCase() : '';
    };

    // Junto a cada muestra se agrega un campo de texto para escribir o pegar el codigo.
    const hexFields = new Map();
    colorInputs.forEach((input) => {
        const field = document.createElement('input');
        field.type = 'text';
        field.className = 'color-hex';
        field.maxLength = 9;
        field.autocomplete = 'off';
        field.spellcheck = false;
        field.placeholder = '#000000';
        field.value = input.value;
        field.setAttribute('aria-label', 'Codigo de color de ' + (input.closest('label')?.querySelector('span')?.textContent || input.name));
        input.insertAdjacentElement('afterend', field);
        hexFields.set(input, field);

        const applyTyped = () => {
            const hex = normalizeHex(field.value);
            field.classList.toggle('is-invalid', hex === '' && field.value.trim() !== '');
            if (hex === '') return;
            input.value = hex;
            if (customRadio) customRadio.checked = true;
            syncPreview();
        };

        field.addEventListener('input', applyTyped);
        field.addEventListener('blur', () => {
            field.value = input.value;
            field.classList.remove('is-invalid');
        });
    });

    const syncHexFields = () => {
        hexFields.forEach((field, input) => {
            if (document.activeElement !== field) field.value = input.value;
        });
    };

    colorInputs.forEach((input) => {
        input.addEventListener('input', () => {
            if (customRadio) customRadio.checked = true;
            syncHexFields();
            syncPreview();
        });
    });

    form.querySelectorAll('input[name="tema"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (radio.value !== 'personalizado') {
                const map = {
                    primary_color: radio.dataset.primary,
                    primary_dark_color: radio.dataset.primaryDark,
                    accent_color: radio.dataset.accent,
                    accent_dark_color: radio.dataset.accentDark,
                    background_color: radio.dataset.background,
                    surface_color: radio.dataset.surface,
                    text_color: radio.dataset.text,
                    muted_color: radio.dataset.muted,
                };

                colorInputs.forEach((input) => {
                    if (map[input.name]) input.value = map[input.name];
                });
            }
            syncHexFields();
            syncPreview();
        });
    });

    syncPreview();
});
