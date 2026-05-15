<?php
session_start();

/* ================= CERRAR SESIÓN ================= */

// Eliminar variables de sesión
$_SESSION = [];

// Destruir la sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

/* ================= LIMPIAR COOKIE REMEMBER ME ================= */
if (isset($_COOKIE['remember_token'])) {
    setcookie(
        'remember_token',
        '',
        time() - 3600,
        '/',
        '',
        true,
        true
    );
}

/* ================= REDIRECCIÓN ================= */
header("Location: ../pantallas/inicio_sesion.php");
exit;
