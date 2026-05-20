/* Contacto.js - Senderismo Go! */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = submitBtn?.querySelector('.submit-text');
    const submitLoad = submitBtn?.querySelector('.submit-loading');

    if (!form) return;

    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) return;

        const honeypot = form.querySelector('input[name="website"]');
        if (honeypot && honeypot.value.trim() !== '') {
            event.preventDefault();
            return;
        }

        if (submitText) submitText.classList.add('hidden');
        if (submitLoad) submitLoad.classList.remove('hidden');
        if (submitBtn) submitBtn.disabled = true;
    });

    const textarea = document.getElementById('mensaje');
    const hint = document.querySelector('[data-character-counter="mensaje"]');

    if (textarea && hint) {
        const max = parseInt(textarea.getAttribute('maxlength'), 10) || 1000;

        const updateCounter = () => {
            const left = max - textarea.value.length;
            hint.textContent = `${textarea.value.length} / ${max} caracteres`;
            hint.style.color = left < 50 ? '#b65322' : '';
        };

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    }

    document.querySelectorAll('.smooth-scroll').forEach((link) => {
        link.addEventListener('click', function (event) {
            const target = document.querySelector(this.getAttribute('href'));
            if (!target) return;

            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
});
