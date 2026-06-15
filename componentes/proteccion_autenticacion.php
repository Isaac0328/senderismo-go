<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/recordar_sesion.php';
sg_restaurar_sesion_recordada();

/**
 * Timeout por inactividad (segundos)
 * - Admin: 10 min
 * - Otros: 20 min
 */
$roleId = (int)($_SESSION['usuario_rol_id'] ?? 0);
$INACTIVITY_LIMIT = ($roleId === 1) ? (10 * 60) : (20 * 60);

function redirect_login_msg(string $msg)
{
    $_SESSION['error_message'] = $msg;
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

// 1) Validar sesión
if (
    empty($_SESSION['usuario_id']) ||
    empty($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true
) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

// 2) Timeout por inactividad
$now = time();
$last = (int)($_SESSION['last_activity'] ?? 0);

if ($last > 0 && ($now - $last) > $INACTIVITY_LIMIT) {

    // Limpiar sesión
    $_SESSION = [];

    // Borrar cookie de sesión PHP
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    session_destroy();

    // Arrancar sesión nueva solo para mensaje
    session_start();
    redirect_login_msg("Tu sesión expiró por inactividad. Inicia sesión nuevamente.");
}

// 3) Actualizar última actividad
$_SESSION['last_activity'] = $now;

// 4) Roles permitidos (si el archivo que lo incluye define $ROLES_PERMITIDOS)
if (isset($ROLES_PERMITIDOS)) {
    $rol = (int)($_SESSION['usuario_rol_id'] ?? 0);

    if ($rol <= 0 || !in_array($rol, $ROLES_PERMITIDOS, true)) {
        header("Location: " . BASE_URL . "pantallas/inicio.php");
        exit;
    }
}
