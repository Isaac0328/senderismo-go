/* Contacto.js — Senderismo Go! */

document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = submitBtn?.querySelector('.submit-text');
    const submitLoad = submitBtn?.querySelector('.submit-loading');

    if (!form) return;

    /* ── Spinner al enviar ── */
    form.addEventListener('submit', function (e) {
        // Validación nativa HTML5
        if (!form.checkValidity()) return;

        // Honeypot check
        const honeypot = form.querySelector('input[name="website"]');
        if (honeypot && honeypot.value.trim() !== '') {
            e.preventDefault();
            return;
        }

        // Mostrar loading
        if (submitText) submitText.classList.add('hidden');
        if (submitLoad) submitLoad.classList.remove('hidden');
        if (submitBtn) submitBtn.disabled = true;
    });

    /* ── Contador de caracteres en textarea ── */
    const textarea = document.getElementById('mensaje');
    const hint = textarea?.closest('.contact-field')?.querySelector('.field-hint');

    if (textarea && hint) {
        const max = parseInt(textarea.getAttribute('maxlength')) || 1000;
        textarea.addEventListener('input', () => {
            const left = max - textarea.value.length;
            hint.textContent = `${textarea.value.length} / ${max} caracteres`;
            hint.style.color = left < 50 ? '#d97706' : '';
        });
    }

    /* ── Smooth scroll para el botón del hero ── */
    document.querySelectorAll('.smooth-scroll').forEach(link => {
        link.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

});