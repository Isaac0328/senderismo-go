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
});
