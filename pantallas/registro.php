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
                                value="<?= isset($_SESSION['reg_old']['nombre']) ? htmlspecialchars($_SESSION['reg_old']['nombre']) : '' ?>"
                                autocomplete="given-name">
                        </div>

                        <div class="form-group">
                            <label for="apellido">Apellido</label>
                            <input type="text" name="apellido" id="apellido" required placeholder="Tu apellido"
                                value="<?= isset($_SESSION['reg_old']['apellido']) ? htmlspecialchars($_SESSION['reg_old']['apellido']) : '' ?>"
                                autocomplete="family-name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="user">Usuario</label>
                        <input type="text" name="user" id="user" required placeholder="nombre.usuario"
                            value="<?= isset($_SESSION['reg_old']['user']) ? htmlspecialchars($_SESSION['reg_old']['user']) : '' ?>"
                            autocomplete="username">
                        <small class="hint">Sin espacios. Puedes usar puntos o guiones bajos.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Correo</label>
                        <input type="email" name="email" id="email" required placeholder="tu@email.com"
                            value="<?= isset($_SESSION['reg_old']['email']) ? htmlspecialchars($_SESSION['reg_old']['email']) : '' ?>"
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

                    <label class="terms">
                        <input type="checkbox" id="terms" required>
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