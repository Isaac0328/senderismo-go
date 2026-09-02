<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

$pageTitle = "Login | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/inicio_sesion.css",
    "css/barra_navegacion.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/inicio_sesion.js"
];

if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

$showRegisterPrompt = !empty($_SESSION['show_register_prompt']);
unset($_SESSION['show_register_prompt']);

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="login-page">
    <div class="login-layout login-animate">

        <!-- LADO IZQUIERDO (IMAGEN + TEXTO) -->
        <div class="login-left login-animate-left">
            <!-- Cambia esta ruta por tu imagen personalizada -->
            <img class="login-left-image" src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Imagen Login">

            <div class="login-left-overlay"></div>

            <div class="login-left-content">
                <h2 class="login-left-title">TE ESTAMOS ESPERANDO</h2>
                <p class="login-left-subtitle">Accede para continuar tu experiencia con Senderismo Go</p>
            </div>
        </div>

        <!-- LADO DERECHO (FORMULARIO) -->
        <div class="login-right login-animate-right">
            <div class="login-card">

                <div class="login-logo">
                    <img src="<?= BASE_URL ?>imagenes/logo/logo_sg.png" alt="Senderismo Go">
                </div>

                <h1 class="login-title">Iniciar Sesión</h1>
                <p class="login-desc">Ingresa tus datos para acceder</p>

                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_inicio_sesion.php" class="login-form" novalidate>
                    <div class="form-group">
                        <label for="user">Usuario o Email</label>
                        <input type="text" name="user" id="user" required placeholder="nombre.usuario o tu@email.com"
                            value="<?= isset($_SESSION['login_attempt_user']) ? htmlspecialchars($_SESSION['login_attempt_user']) : '' ?>"
                            autocomplete="username">
                        <?php unset($_SESSION['login_attempt_user']); ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" required placeholder="••••••••"
                                autocomplete="current-password">
                            <button type="button" class="toggle-password" id="togglePassword"
                                aria-label="Mostrar/Ocultar contraseña">
                                <i data-feather="eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Recordar sesión</span>
                        </label>

                        <a class="forgot-password" href="<?= BASE_URL ?>pantallas/recuperar_password.php">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    <button type="submit" class="btn-login" id="loginButton">
                        <span class="btn-text">Entrar</span>
                        <span class="btn-loading hidden">Cargando...</span>
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

                <!-- Registro + volver -->
                <div class="login-footer">
                    <p class="login-register">
                        ¿Aún no tienes cuenta?
                        <a href="<?= BASE_URL ?>pantallas/registro.php">Regístrate</a>
                    </p>

                    <p class="mt-2">
                        <a href="<?= BASE_URL ?>index.php">← Volver al inicio</a>
                    </p>
                </div>

            </div>
        </div>

    </div>
</div>

<?php if ($showRegisterPrompt): ?>
    <dialog class="login-register-dialog" data-register-prompt aria-labelledby="register-prompt-title" aria-describedby="register-prompt-description">
        <button class="login-register-dialog-close" type="button" aria-label="Cerrar mensaje" title="Cerrar" data-close-register-prompt>
            <i data-feather="x"></i>
        </button>
        <span class="login-register-dialog-icon" aria-hidden="true">
            <i data-feather="user-plus"></i>
        </span>
        <span class="login-register-dialog-kicker">Crea tu acceso</span>
        <h2 id="register-prompt-title">¿Aún no tienes usuario?</h2>
        <p id="register-prompt-description">Si no tienes un usuario, haz clic aquí para crear tu cuenta.</p>
        <div class="login-register-dialog-actions">
            <button class="login-register-dialog-secondary" type="button" data-close-register-prompt>Seguir intentando</button>
            <a class="login-register-dialog-primary btn" href="<?= BASE_URL ?>pantallas/registro.php">
                <i data-feather="user-plus"></i>
                Crear usuario
            </a>
        </div>
    </dialog>
<?php endif; ?>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
