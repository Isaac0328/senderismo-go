document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-image-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const preview = document.querySelector(`[data-preview-for="${input.dataset.imageInput}"]`);
            if (!file || !preview) return;
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                preview.src = String(reader.result || '');
            });
            reader.readAsDataURL(file);
        });
    });

    // Contador de caracteres en los textos largos: usados / limite.
    document.querySelectorAll('.site-config-section textarea[maxlength]').forEach((textarea) => {
        const label = textarea.closest('label');
        const max = Number(textarea.getAttribute('maxlength'));
        if (!label || !max) return;

        const foot = document.createElement('div');
        foot.className = 'site-config-field-foot';
        const help = label.querySelector('small');
        if (help) foot.appendChild(help);
        const counter = document.createElement('span');
        counter.className = 'site-config-counter';
        counter.setAttribute('aria-live', 'polite');
        foot.appendChild(counter);
        textarea.insertAdjacentElement('afterend', foot);

        const update = () => {
            const used = textarea.value.length;
            counter.textContent = `${used.toLocaleString('es')} / ${max.toLocaleString('es')}`;
            counter.classList.toggle('is-near', used >= max * 0.9 && used < max);
            counter.classList.toggle('is-full', used >= max);
        };

        textarea.addEventListener('input', update);
        update();
    });
});
