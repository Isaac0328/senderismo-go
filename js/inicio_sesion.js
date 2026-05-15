document.addEventListener('DOMContentLoaded', function () {
    // Feather icons
    if (typeof feather !== 'undefined') feather.replace();

    // Pequeño "reveal" suave al cargar (moderno)
    const layout = document.querySelector('.login-layout');
    if (layout) {
        layout.classList.add('login-animate');
    }

    // Toggle password
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            const icon = this.querySelector('i');
            if (icon && typeof feather !== 'undefined') {
                icon.setAttribute('data-feather', type === 'password' ? 'eye' : 'eye-off');
                feather.replace();
            }

            passwordInput.focus();
        });
    }

    // Form submit (loading)
    const loginForm = document.querySelector('.login-form');
    const loginButton = document.getElementById('loginButton');
    const btnText = document.querySelector('.btn-text');
    const btnLoading = document.querySelector('.btn-loading');

    if (loginForm && loginButton) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const userInput = document.getElementById('user');
            const passInput = document.getElementById('password');

            let msg = '';
            if (!userInput.value.trim()) msg = 'Por favor, ingresa tu usuario o email';
            else if (!passInput.value) msg = 'Por favor, ingresa tu contraseña';

            if (msg) {
                showAlert(msg, 'error');
                return;
            }

            loginButton.disabled = true;
            if (btnText && btnLoading) {
                btnText.classList.add('hidden');
                btnLoading.classList.remove('hidden');
            }

            setTimeout(() => loginForm.submit(), 250);
        });
    }

    // Autofocus
    const userInput = document.getElementById('user');
    if (userInput) userInput.focus();

    function showAlert(message, type = 'error') {
        const existing = document.querySelector('.alert');
        if (existing) existing.remove();

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;

        const form = document.querySelector('.login-form');
        if (form) form.parentNode.insertBefore(alertDiv, form.nextSibling);

        setTimeout(() => {
            if (alertDiv.parentNode) alertDiv.remove();
        }, 5000);
    }
});
