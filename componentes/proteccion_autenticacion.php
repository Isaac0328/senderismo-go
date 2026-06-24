<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $_SESSION['redirect_after_login'] = $returnUrl;
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

function sg_auth_return_url(): string
{
    $basePath = (string) (parse_url(BASE_URL, PHP_URL_PATH) ?: '/');
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $source = $requestUri;

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && $referer !== '') {
        $refererParts = parse_url($referer);
        $currentHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $refererHost = (string) ($refererParts['host'] ?? '');

        if ($refererHost === '' || strcasecmp($refererHost, $currentHost) === 0) {
            $source = (string) ($refererParts['path'] ?? '');
            if (!empty($refererParts['query'])) {
                $source .= '?' . $refererParts['query'];
            }
        }
    }

    $parts = parse_url($source);
    $path = (string) ($parts['path'] ?? '');
    $query = !empty($parts['query']) ? '?' . $parts['query'] : '';

    if ($path === '') {
        return BASE_URL . 'pantallas/inicio.php';
    }

    if ($basePath !== '/' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }

    return BASE_URL . ltrim($path, '/') . $query;
}

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
    $_SESSION['redirect_after_login'] = sg_auth_return_url();
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

// 2) Timeout por inactividad
$now = time();
$last = (int)($_SESSION['last_activity'] ?? 0);

if ($last > 0 && ($now - $last) > $INACTIVITY_LIMIT) {
    $returnUrl = sg_auth_return_url();

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
