<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Restablecer contrasena | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/inicio_sesion.css",
    "css/barra_navegacion.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/restablecer_password.js"
];

if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

$token = trim((string) ($_GET['token'] ?? ''));
$tokenValido = false;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = mysqli_prepare(
        $conn,
        "SELECT pr.id
         FROM password_resets pr
         INNER JOIN usuarios u ON u.id = pr.usuario_id
         WHERE pr.token_hash = ?
           AND pr.used_at IS NULL
           AND pr.expires_at >= NOW()
           AND u.estado = 1
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $tokenHash);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tokenValido = (bool) mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="login-page">
    <div class="login-layout login-animate">
        <div class="login-left login-animate-left">
            <img class="login-left-image" src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Senderismo Go">
            <div class="login-left-overlay"></div>
            <div class="login-left-content">
                <h2 class="login-left-title">NUEVA CONTRASENA</h2>
                <p class="login-left-subtitle">Crea una contrasena segura para volver a entrar a tu cuenta.</p>
            </div>
        </div>

        <div class="login-right login-animate-right">
            <div class="login-card">
                <div class="login-logo">
                    <img src="<?= BASE_URL ?>imagenes/logo/logo_sg.png" alt="Senderismo Go">
                </div>

                <h1 class="login-title">Restablecer contrasena</h1>

                <?php if (!$tokenValido): ?>
                    <p class="login-desc">Este enlace no es valido, ya fue usado o expiro.</p>
                    <div class="alert alert-error">Solicita un nuevo enlace para continuar.</div>
                    <div class="login-footer">
                        <p class="login-register">
                            <a href="<?= BASE_URL ?>pantallas/recuperar_password.php">Solicitar nuevo enlace</a>
                        </p>
                    </div>
                <?php else: ?>
                    <p class="login-desc">El enlace es valido. Escribe tu nueva contrasena.</p>

                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_restablecer_password.php" class="login-form reset-form" novalidate>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="form-group">
                            <label for="password">Nueva contrasena</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" required minlength="6" maxlength="120" placeholder="Minimo 6 caracteres" autocomplete="new-password">
                                <button type="button" class="toggle-password" data-toggle-password="password" aria-label="Mostrar u ocultar contrasena">
                                    <i data-feather="eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirmar contrasena</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" required minlength="6" maxlength="120" placeholder="Repite la contrasena" autocomplete="new-password">
                                <button type="button" class="toggle-password" data-toggle-password="confirm_password" aria-label="Mostrar u ocultar contrasena">
                                    <i data-feather="eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-login">Cambiar contrasena</button>
                    </form>
                <?php endif; ?>

                <?php if (isset($_SESSION['reset_error'])): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($_SESSION['reset_error']) ?>
                        <?php unset($_SESSION['reset_error']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
