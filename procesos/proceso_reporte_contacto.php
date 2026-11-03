<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'reportes.contactos';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "pantallas/reporte_contacto.php");
    exit;
}
csrf_validate_post(BASE_URL . "pantallas/reporte_contacto.php", 'reporte_contacto_error');

$id = (int) ($_POST['id'] ?? 0);
$estado = trim((string) ($_POST['estado'] ?? ''));
$permitidos = ['nuevo', 'leido', 'respondido', 'archivado'];

if ($id <= 0 || !in_array($estado, $permitidos, true)) {
    $_SESSION['reporte_contacto_error'] = "Solicitud no valida.";
    header("Location: " . BASE_URL . "pantallas/reporte_contacto.php");
    exit;
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    $_SESSION['reporte_contacto_error'] = "No se pudo conectar con la base de datos.";
    header("Location: " . BASE_URL . "pantallas/reporte_contacto.php");
    exit;
}
mysqli_set_charset($conn, "utf8mb4");

$stmt = mysqli_prepare($conn, "UPDATE mensajes_contacto SET estado = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $estado, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

$_SESSION['reporte_contacto_success'] = "Estado del mensaje actualizado.";
header("Location: " . BASE_URL . "pantallas/reporte_contacto.php");
exit;
