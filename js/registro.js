document.addEventListener('DOMContentLoaded', function () {

    /* ================= Feather Icons ================= */
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    /* ================= Toggle Password ================= */
    function togglePassword(btnId, inputId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);

        if (!btn || !input) return;

        btn.addEventListener('click', function () {
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');

            const icon = btn.querySelector('i');

            if (icon && typeof feather !== 'undefined') {
                icon.setAttribute('data-feather', isPassword ? 'eye-off' : 'eye');
                feather.replace();
            }

            input.focus();
        });
    }

    togglePassword('togglePassword', 'password');
    togglePassword('toggleConfirmPassword', 'confirm_password');

    /* ================= Form Submit ================= */
    const form = document.querySelector('.register-form');
    const btn = document.getElementById('registerButton');
    const btnText = btn?.querySelector('.btn-text');
    const btnLoading = btn?.querySelector('.btn-loading');

    if (form && btn) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const nombre = document.getElementById('nombre');
            const apellido = document.getElementById('apellido');
            const user = document.getElementById('user');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            const terms = document.getElementById('terms');

            // ================= Validaciones =================
            let error = '';

            if (!nombre.value.trim()) error = 'Debes ingresar tu nombre';
            else if (!apellido.value.trim()) error = 'Debes ingresar tu apellido';
            else if (!user.value.trim()) error = 'Debes ingresar un usuario';
            else if (/\s/.test(user.value)) error = 'El usuario no puede contener espacios';
            else if (!email.value.trim()) error = 'Debes ingresar tu correo';
            else if (!validateEmail(email.value)) error = 'Correo inválido';
            else if (!password.value) error = 'Debes ingresar una contraseña';
            else if (password.value.length < 6) error = 'La contraseña debe tener al menos 6 caracteres';
            else if (password.value !== confirm.value) error = 'Las contraseñas no coinciden';
            else if (!terms.checked) error = 'Debes aceptar los términos y condiciones';

            if (error) {
                showAlert(error, 'error');
                return;
            }

            // ================= Loading =================
            btn.disabled = true;

            if (btnText && btnLoading) {
                btnText.classList.add('hidden');
                btnLoading.classList.remove('hidden');
            }

            setTimeout(() => {
                form.submit();
            }

                , 300);
        });
    }

    /* ================= Helpers ================= */

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.toLowerCase());
    }

    function showAlert(message, type = 'error') {
        const old = document.querySelector('.alert');
        if (old) old.remove();

        const alert = document.createElement('div');

        alert.className = `alert alert-${type}`;
        alert.textContent = message;

        const card = document.querySelector('.register-card');

        if (card) {
            card.appendChild(alert);
        }

        setTimeout(() => {
            if (alert.parentNode) alert.remove();
        }

            , 5000);
    }

    /* ================= Animación suave al cargar ================= */
    const layout = document.querySelector('.register-layout');

    if (layout) {
        layout.classList.add('register-animate');
    }

});