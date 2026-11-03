document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.nosotros-form');
    if (!form) return;

    form.addEventListener('invalid', (event) => {
        const section = event.target.closest('.nosotros-config-section');
        if (section) section.open = true;
    }, true);
});
