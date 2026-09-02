<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Registro | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/registro.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/registro.js"
];

// Si ya está logueado, enviarlo a Home
if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

$old = $_SESSION['reg_old'] ?? [];
$rangosEdad = ['0-18', '19-30', '31-40', '41-50', '51-60', '61+'];
$gruposSanguineos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
$experiencias = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
$vias = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'Google', 'Amigos', 'Otro'];

function reg_old(array $old, string $key): string
{
    return htmlspecialchars((string) ($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

function reg_selected(array $old, string $key, string $value): string
{
    return (string) ($old[$key] ?? '') === $value ? 'selected' : '';
}

function reg_checked(array $old, string $key, string $value): string
{
    return (string) ($old[$key] ?? '') === $value ? 'checked' : '';
}

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="register-page">
    <div class="register-layout register-animate">

        <!-- LADO IZQUIERDO (IMAGEN + TEXTO) -->
        <div class="register-left register-animate-left">
            <!-- Cambia esta ruta por tu imagen personalizada -->
            <img class="register-left-image" src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Imagen Registro">
            <div class="register-left-overlay"></div>

            <div class="register-left-content">
                <h2 class="register-left-title">ÚNETE A LA AVENTURA</h2>
                <p class="register-left-subtitle">
                    Crea tu cuenta y empieza a vivir la experiencia Senderismo Go.
                </p>
            </div>
        </div>

        <!-- LADO DERECHO (FORMULARIO) -->
        <div class="register-right register-animate-right">
            <div class="register-card">

                <div class="register-logo">
                    <img src="<?= BASE_URL ?>imagenes/logo/logo_sg.png" alt="Senderismo Go">
                </div>

                <h1 class="register-title">Crear cuenta</h1>
                <p class="register-desc">Completa tus datos para registrarte</p>

                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_registro.php" class="register-form"
                    novalidate>
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" name="nombre" id="nombre" required placeholder="Tu nombre"
                                value="<?= reg_old($old, 'nombre') ?>"
                                autocomplete="given-name" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                        </div>

                        <div class="form-group">
                            <label for="apellido">Apellido</label>
                            <input type="text" name="apellido" id="apellido" required placeholder="Tu apellido"
                                value="<?= reg_old($old, 'apellido') ?>"
                                autocomplete="family-name" pattern="[^0-9]*" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="user">Usuario</label>
                        <input type="text" name="user" id="user" required placeholder="nombre.usuario"
                            value="<?= reg_old($old, 'user') ?>"
                            autocomplete="username">
                        <small class="hint">Sin espacios. Puedes usar puntos o guiones bajos.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Correo</label>
                        <input type="email" name="email" id="email" required placeholder="tu@email.com"
                            value="<?= reg_old($old, 'email') ?>"
                            autocomplete="email">
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" required placeholder="••••••••"
                                    autocomplete="new-password">
                                <button type="button" class="toggle-password" id="togglePassword"
                                    aria-label="Mostrar/Ocultar contraseña">
                                    <i data-feather="eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirmar</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" required
                                    placeholder="••••••••" autocomplete="new-password">
                                <button type="button" class="toggle-password" id="toggleConfirmPassword"
                                    aria-label="Mostrar/Ocultar confirmación">
                                    <i data-feather="eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>


                    <div class="register-section-title">
                        <span>Datos del senderista</span>
                        <small>Se usaran al reservar tus senderos.</small>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="telefono">Telefono</label>
                            <input type="tel" name="telefono" id="telefono" required inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" placeholder="8090000000" value="<?= reg_old($old, 'telefono') ?>" oninput="this.value=this.value.replace(/\D/g,'')">
                        </div>
                        <div class="form-group">
                            <label for="rango_edad">Edad</label>
                            <select name="rango_edad" id="rango_edad" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($rangosEdad as $rango): ?>
                                    <option value="<?= htmlspecialchars($rango) ?>" <?= reg_selected($old, 'rango_edad', $rango) ?>><?= htmlspecialchars($rango) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="identificacion">Identificacion</label>
                            <input type="text" name="identificacion" id="identificacion" required maxlength="50" value="<?= reg_old($old, 'identificacion') ?>">
                        </div>
                        <div class="form-group">
                            <label for="grupo_sanguineo">Grupo sanguineo</label>
                            <select name="grupo_sanguineo" id="grupo_sanguineo" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($gruposSanguineos as $grupo): ?>
                                    <option value="<?= htmlspecialchars($grupo) ?>" <?= reg_selected($old, 'grupo_sanguineo', $grupo) ?>><?= htmlspecialchars($grupo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Es alergico?</label>
                            <div class="radio-inline">
                                <label><input type="radio" name="es_alergico" value="1" <?= reg_checked($old, 'es_alergico', '1') ?> required> Si</label>
                                <label><input type="radio" name="es_alergico" value="0" <?= $old ? reg_checked($old, 'es_alergico', '0') : 'checked' ?> required> No</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="alergias_detalle">Detalle de alergia</label>
                            <input type="text" name="alergias_detalle" id="alergias_detalle" maxlength="255" value="<?= reg_old($old, 'alergias_detalle') ?>" placeholder="Si no aplica, dejar vacio">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="enfermedad">Padece alguna enfermedad?</label>
                        <input type="text" name="enfermedad" id="enfermedad" required maxlength="255" value="<?= reg_old($old, 'enfermedad') ?>" placeholder="Si no aplica, escribe No">
                    </div>

                    <div class="form-group">
                        <label for="seguro_medico">Tiene seguro medico?</label>
                        <input type="text" name="seguro_medico" id="seguro_medico" required maxlength="255" value="<?= reg_old($old, 'seguro_medico') ?>" placeholder="Si no aplica, escribe No">
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="experiencia_senderismo">Experiencia</label>
                            <select name="experiencia_senderismo" id="experiencia_senderismo" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($experiencias as $experiencia): ?>
                                    <option value="<?= htmlspecialchars($experiencia) ?>" <?= reg_selected($old, 'experiencia_senderismo', $experiencia) ?>><?= htmlspecialchars($experiencia) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="via_entero">Por cual via te enteraste?</label>
                            <select name="via_entero" id="via_entero" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($vias as $via): ?>
                                    <option value="<?= htmlspecialchars($via) ?>" <?= reg_selected($old, 'via_entero', $via) ?>><?= htmlspecialchars($via) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="referido_nombre">Si fue por amigos, escribe su nombre</label>
                        <input type="text" name="referido_nombre" id="referido_nombre" maxlength="150" pattern="[^0-9]*" value="<?= reg_old($old, 'referido_nombre') ?>" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                    </div>

                    <div class="register-section-title">
                        <span>Contacto de emergencia</span>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="emergencia_nombre">Nombre</label>
                            <input type="text" name="emergencia_nombre" id="emergencia_nombre" required maxlength="150" pattern="[^0-9]*" value="<?= reg_old($old, 'emergencia_nombre') ?>" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                        </div>
                        <div class="form-group">
                            <label for="emergencia_parentesco">Parentesco</label>
                            <input type="text" name="emergencia_parentesco" id="emergencia_parentesco" required maxlength="80" value="<?= reg_old($old, 'emergencia_parentesco') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="emergencia_telefono">Telefono de emergencia</label>
                        <input type="tel" name="emergencia_telefono" id="emergencia_telefono" required inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" placeholder="8090000000" value="<?= reg_old($old, 'emergencia_telefono') ?>" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>
                    <label class="terms">
                        <input type="checkbox" id="terms" name="terms" value="1" required>
                        <span>Acepto los términos y condiciones</span>
                    </label>

                    <button type="submit" class="btn-register" id="registerButton">
                        <span class="btn-text">Crear cuenta</span>
                        <span class="btn-loading hidden">Creando...</span>
                    </button>
                </form>

                <!-- Mensajes -->
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php unset($_SESSION['reg_old']); ?>

                <div class="register-footer">
                    <p class="register-login">
                        ¿Ya tienes cuenta?
                        <a href="<?= BASE_URL ?>pantallas/inicio_sesion.php">Inicia sesión</a>
                    </p>

                    <p class="mt-2">
                        <a href="<?= BASE_URL ?>index.php">← Volver al inicio</a>
                    </p>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
