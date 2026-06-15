<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/recordar_sesion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= CERRAR SESION ================= */

if (isset($_COOKIE['remember_token'])) {
    $token = trim((string) $_COOKIE['remember_token']);

    if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
        require_once __DIR__ . '/../bd/conexion.php';

        $stmt = mysqli_prepare($conn, "DELETE FROM sesiones_usuario WHERE token = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $token);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        mysqli_close($conn);
    }

    sg_limpiar_cookie_recordar();
}

$_SESSION = [];

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

header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
exit;
