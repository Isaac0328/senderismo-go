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

    function setupConditionalDetail(radioName, inputId) {
        const radios = document.querySelectorAll(`input[name="${radioName}"]`);
        const input = document.getElementById(inputId);
        const container = document.querySelector(`[data-conditional-detail="${inputId}"]`);

        if (!radios.length || !input || !container) return;

        const sync = function () {
            const selected = document.querySelector(`input[name="${radioName}"]:checked`);
            const visible = selected?.value === '1';
            container.hidden = !visible;
            input.required = visible;
            if (!visible) input.value = '';
        };

        radios.forEach((radio) => radio.addEventListener('change', sync));
        sync();
    }

    setupConditionalDetail('es_alergico', 'alergias_detalle');
    setupConditionalDetail('tiene_enfermedad', 'enfermedad');
    setupConditionalDetail('tiene_seguro_medico', 'seguro_medico');

    const viaEntero = document.getElementById('via_entero');
    const referidoNombreInput = document.getElementById('referido_nombre');
    const referidoContainer = document.querySelector('[data-select-conditional="referido_nombre"]');

    function syncReferidoNombre() {
        if (!viaEntero || !referidoNombreInput || !referidoContainer) return;
        const visible = viaEntero.value === 'Amigos';
        referidoContainer.hidden = !visible;
        referidoNombreInput.required = visible;
        if (!visible) referidoNombreInput.value = '';
    }

    viaEntero?.addEventListener('change', syncReferidoNombre);
    syncReferidoNombre();

    /* ================= Form Submit ================= */
    const form = document.querySelector('.register-form');
    const btn = document.getElementById('registerButton');
    const btnText = btn?.querySelector('.btn-text');
    const btnLoading = btn?.querySelector('.btn-loading');
    const validationSummary = document.querySelector('[data-registration-validation-summary]');
    let validationAttempted = false;

    const getField = (id) => document.getElementById(id);
    const getAnchor = (id) => {
        const input = getField(id);
        return id === 'terms' ? input?.closest('.terms-field') : input?.closest('.form-group');
    };

    const validationRules = [
        { id: 'nombre', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar tu nombre.' },
        { id: 'nombre', test: (el) => !/\d/.test(el.value), message: 'El nombre no puede contener numeros.' },
        { id: 'apellido', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar tu apellido.' },
        { id: 'apellido', test: (el) => !/\d/.test(el.value), message: 'El apellido no puede contener numeros.' },
        { id: 'user', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar un usuario.' },
        { id: 'user', test: (el) => !/\s/.test(el.value), message: 'El usuario no puede contener espacios.' },
        { id: 'email', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar tu correo.' },
        { id: 'email', test: (el) => !el.value.trim() || validateEmail(el.value), message: 'Ingresa un correo valido.' },
        { id: 'password', test: (el) => Boolean(el.value), message: 'Debes ingresar una contrasena.' },
        { id: 'password', test: (el) => !el.value || el.value.length >= 6, message: 'La contrasena debe tener al menos 6 caracteres.' },
        { id: 'confirm_password', test: (el) => Boolean(el.value), message: 'Debes confirmar la contrasena.' },
        { id: 'confirm_password', test: (el) => !el.value || el.value === getField('password')?.value, message: 'Las contrasenas no coinciden.' },
        { id: 'telefono', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar tu telefono.' },
        { id: 'telefono', test: (el) => !el.value.trim() || isNumericPhone(el.value), message: 'El telefono debe tener entre 10 y 15 numeros.' },
        { id: 'rango_edad', test: (el) => Boolean(el.value), message: 'Debes seleccionar tu rango de edad.' },
        { id: 'identificacion', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar tu identificacion.' },
        { id: 'grupo_sanguineo', test: (el) => Boolean(el.value), message: 'Debes seleccionar tu grupo sanguineo.' },
        { id: 'alergias_detalle', active: (el) => el.required, test: (el) => Boolean(el.value.trim()), message: 'Debes especificar tu alergia.' },
        { id: 'enfermedad', active: (el) => el.required, test: (el) => Boolean(el.value.trim()), message: 'Debes especificar la enfermedad.' },
        { id: 'seguro_medico', active: (el) => el.required, test: (el) => Boolean(el.value.trim()), message: 'Debes especificar el seguro medico.' },
        { id: 'experiencia_senderismo', test: (el) => Boolean(el.value), message: 'Debes seleccionar tu experiencia.' },
        { id: 'via_entero', test: (el) => Boolean(el.value), message: 'Debes indicar como te enteraste.' },
        { id: 'referido_nombre', active: (el) => el.required, test: (el) => Boolean(el.value.trim()), message: 'Debes escribir el nombre de tu amigo.' },
        { id: 'referido_nombre', active: (el) => el.required, test: (el) => !/\d/.test(el.value), message: 'El nombre del amigo no puede contener numeros.' },
        { id: 'emergencia_nombre', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar el nombre del contacto de emergencia.' },
        { id: 'emergencia_nombre', test: (el) => !/\d/.test(el.value), message: 'El nombre de emergencia no puede contener numeros.' },
        { id: 'emergencia_parentesco', test: (el) => Boolean(el.value.trim()), message: 'Debes indicar el parentesco.' },
        { id: 'emergencia_telefono', test: (el) => Boolean(el.value.trim()), message: 'Debes ingresar el telefono de emergencia.' },
        { id: 'emergencia_telefono', test: (el) => !el.value.trim() || isNumericPhone(el.value), message: 'El telefono de emergencia debe tener entre 10 y 15 numeros.' },
        { id: 'terms', test: (el) => el.checked, message: 'Debes aceptar los terminos y condiciones.' }
    ];

    function setRuleState(id, message) {
        const anchor = getAnchor(id);
        if (!anchor) return;

        let error = anchor.querySelector(`[data-field-validation="${id}"]`);
        if (!error) {
            error = document.createElement('small');
            error.className = 'registration-field-error';
            error.dataset.fieldValidation = id;
            error.setAttribute('role', 'alert');
            anchor.appendChild(error);
        }

        anchor.classList.toggle('has-validation-error', Boolean(message));
        error.textContent = message || '';
        error.hidden = !message;
    }

    function validateForm() {
        const failures = [];
        const firstFailureByField = new Map();

        validationRules.forEach((rule) => {
            const input = getField(rule.id);
            if (!input || (rule.active && !rule.active(input))) return;
            if (!rule.test(input) && !firstFailureByField.has(rule.id)) {
                const failure = { input, message: rule.message };
                firstFailureByField.set(rule.id, failure);
                failures.push(failure);
            }
        });

        [...new Set(validationRules.map((rule) => rule.id))].forEach((id) => {
            setRuleState(id, firstFailureByField.get(id)?.message || '');
        });

        return failures;
    }

    function updateValidationSummary(failures) {
        if (!validationSummary) return;
        validationSummary.textContent = failures.map((failure) => failure.message).join(' ');
        validationSummary.hidden = failures.length === 0;
    }

    if (form && btn) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            validationAttempted = true;
            const failures = validateForm();
            updateValidationSummary(failures);

            if (failures.length) {
                const firstInvalid = failures[0].input;
                getAnchor(firstInvalid.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => firstInvalid.focus({ preventScroll: true }), 350);
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

        ['input', 'change'].forEach((eventName) => {
            form.addEventListener(eventName, function () {
                if (!validationAttempted) return;
                updateValidationSummary(validateForm());
            });
        });
    }

    /* ================= Helpers ================= */

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.toLowerCase());
    }

    function isNumericPhone(phone) {
        return /^\d{10,15}$/.test(String(phone || '').trim());
    }

    /* ================= Animación suave al cargar ================= */
    const layout = document.querySelector('.register-layout');

    if (layout) {
        layout.classList.add('register-animate');
    }

});
