<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['usuarios_error'] = "Este proceso fue reemplazado por UsuariosProcess.php.";
header("Location: " . BASE_URL . "mantenimientos/mantenimiento_usuarios.php");
exit;
