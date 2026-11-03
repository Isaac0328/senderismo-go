document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const inputId = button.dataset.togglePassword;
            const input = inputId ? document.getElementById(inputId) : null;
            if (!input) return;

            const nextType = input.type === 'password' ? 'text' : 'password';
            input.type = nextType;

            const icon = button.querySelector('i');
            if (icon && typeof feather !== 'undefined') {
                icon.setAttribute('data-feather', nextType === 'password' ? 'eye' : 'eye-off');
                feather.replace();
            }

            input.focus();
        });
    });
});
