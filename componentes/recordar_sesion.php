<?php
require_once __DIR__ . '/../configuracion.php';

if (!function_exists('sg_cookie_segura')) {
    function sg_cookie_segura(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }
}

if (!function_exists('sg_limpiar_cookie_recordar')) {
    function sg_limpiar_cookie_recordar(): void
    {
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => sg_cookie_segura(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['remember_token']);
    }
}

if (!function_exists('sg_restaurar_sesion_recordada')) {
    function sg_restaurar_sesion_recordada(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['logged_in'])) {
            return true;
        }

        $token = trim((string) ($_COOKIE['remember_token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return false;
        }

        $connRecordar = $GLOBALS['conn'] ?? null;

        if (!$connRecordar instanceof mysqli) {
            require __DIR__ . '/../bd/conexion.php';
            $connRecordar = $GLOBALS['conn'] ?? null;
        }

        if (!$connRecordar instanceof mysqli) {
            return false;
        }

        $ahora = time();
        mysqli_query($connRecordar, "DELETE FROM sesiones_usuario WHERE expires_at <= {$ahora}");

        $sql = "SELECT su.id AS sesion_id, u.id, u.nombre, u.apellido, u.rol_id, r.nombre AS rol_nombre
                FROM sesiones_usuario su
                INNER JOIN usuarios u ON u.id = su.user_id
                LEFT JOIN roles r ON r.id = u.rol_id
                WHERE su.token = ? AND su.expires_at > ? AND u.estado = 1
                LIMIT 1";
        $stmt = mysqli_prepare($connRecordar, $sql);

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'si', $token, $ahora);
        mysqli_stmt_execute($stmt);
        $usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
        mysqli_stmt_close($stmt);

        if (empty($usuario['id'])) {
            sg_limpiar_cookie_recordar();
            return false;
        }

        session_regenerate_id(true);

        $nombreCompleto = trim((string) (($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')));
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nombre'] = $nombreCompleto;
        $_SESSION['usuario_rol_id'] = (int) ($usuario['rol_id'] ?? 0);
        $_SESSION['usuario_rol'] = (string) ($usuario['rol_nombre'] ?? '');
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = $ahora;
        $_SESSION['last_activity'] = $ahora;
        $_SESSION['remember_restored'] = true;

        $stmtLogin = mysqli_prepare($connRecordar, "UPDATE usuarios SET last_login = NOW() WHERE id = ?");
        if ($stmtLogin) {
            $uid = (int) $usuario['id'];
            mysqli_stmt_bind_param($stmtLogin, 'i', $uid);
            mysqli_stmt_execute($stmtLogin);
            mysqli_stmt_close($stmtLogin);
        }

        return true;
    }
}
