<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Recuperar contrasena | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/inicio_sesion.css",
    "css/barra_navegacion.css"
];

$jsFiles = [
    "js/barra_navegacion.js"
];

if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

$server = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isRecoveryLocal = in_array($server, ['localhost', '127.0.0.1'], true)
    || str_starts_with($host, 'localhost')
    || str_starts_with($host, '127.0.0.1');

if (!$isRecoveryLocal) {
    unset($_SESSION['recovery_debug_link']);
}

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="login-page">
    <div class="login-layout login-animate">
        <div class="login-left login-animate-left">
            <img class="login-left-image" src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Senderismo Go">
            <div class="login-left-overlay"></div>
            <div class="login-left-content">
                <h2 class="login-left-title">RECUPERA TU ACCESO</h2>
                <p class="login-left-subtitle">Te enviaremos un enlace temporal para crear una nueva contrasena.</p>
            </div>
        </div>

        <div class="login-right login-animate-right">
            <div class="login-card">
                <div class="login-logo">
                    <img src="<?= BASE_URL ?>imagenes/logo/logo_sg.png" alt="Senderismo Go">
                </div>

                <h1 class="login-title">Olvidaste tu contrasena</h1>
                <p class="login-desc">Escribe el correo asociado a tu cuenta.</p>

                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_recuperar_password.php" class="login-form recovery-form" novalidate>
                    <div class="form-group">
                        <label for="email">Correo electronico</label>
                        <input type="email" name="email" id="email" required placeholder="tu@email.com" autocomplete="email"
                            value="<?= isset($_SESSION['recovery_email']) ? htmlspecialchars($_SESSION['recovery_email']) : '' ?>">
                        <?php unset($_SESSION['recovery_email']); ?>
                    </div>

                    <button type="submit" class="btn-login">Enviar enlace</button>
                </form>

                <?php if (isset($_SESSION['recovery_error'])): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($_SESSION['recovery_error']) ?>
                        <?php unset($_SESSION['recovery_error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['recovery_success'])): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($_SESSION['recovery_success']) ?>
                        <?php unset($_SESSION['recovery_success']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($isRecoveryLocal && isset($_SESSION['recovery_debug_link'])): ?>
                    <div class="alert alert-dev">
                        <strong>Enlace local de prueba:</strong>
                        <a href="<?= htmlspecialchars($_SESSION['recovery_debug_link']) ?>"><?= htmlspecialchars($_SESSION['recovery_debug_link']) ?></a>
                        <?php unset($_SESSION['recovery_debug_link']); ?>
                    </div>
                <?php endif; ?>

                <div class="login-footer">
                    <p class="login-register">
                        <a href="<?= BASE_URL ?>pantallas/inicio_sesion.php">Volver a iniciar sesion</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
