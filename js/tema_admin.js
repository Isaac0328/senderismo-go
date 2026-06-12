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

    colorInputs.forEach((input) => {
        input.addEventListener('input', () => {
            if (customRadio) customRadio.checked = true;
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
            syncPreview();
        });
    });

    syncPreview();
});
