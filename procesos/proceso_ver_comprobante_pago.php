<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    http_response_code(403);
    exit('Debes iniciar sesion para ver este comprobante.');
}

require_once __DIR__ . '/../bd/conexion.php';

$registroId = (int) ($_GET['registro_id'] ?? 0);
if ($registroId <= 0) {
    http_response_code(404);
    exit('Comprobante no encontrado.');
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT usuario_id, comprobante_pago_ruta, comprobante_pago_nombre, comprobante_pago_mime
     FROM registros_senderos
     WHERE id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $registroId);
mysqli_stmt_execute($stmt);
$registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
mysqli_close($conn);

if (!$registro) {
    http_response_code(404);
    exit('Comprobante no encontrado.');
}

$esPropietario = (int) $registro['usuario_id'] === (int) $_SESSION['usuario_id'];
$esAdministrador = (int) ($_SESSION['usuario_rol_id'] ?? 0) === 1;
if (!$esPropietario && !$esAdministrador) {
    http_response_code(403);
    exit('No tienes permiso para ver este comprobante.');
}

$rutaRelativa = str_replace('\\', '/', trim((string) $registro['comprobante_pago_ruta']));
$mime = trim((string) $registro['comprobante_pago_mime']);
$mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if ($rutaRelativa === '' || !str_starts_with($rutaRelativa, 'archivos/comprobantes_pago/') || !in_array($mime, $mimesPermitidos, true)) {
    http_response_code(404);
    exit('Comprobante no encontrado.');
}

$raiz = realpath(dirname(__DIR__) . '/archivos/comprobantes_pago');
$archivo = realpath(dirname(__DIR__) . '/' . $rutaRelativa);
if ($raiz === false || $archivo === false || !is_file($archivo) || !str_starts_with($archivo, $raiz . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Comprobante no encontrado.');
}

$nombre = basename(trim((string) $registro['comprobante_pago_nombre']));
if ($nombre === '') {
    $nombre = basename($archivo);
}
$nombre = preg_replace('/[^A-Za-z0-9._ -]/', '_', $nombre) ?: 'comprobante';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($archivo));
header('Content-Disposition: inline; filename="' . addcslashes($nombre, '\\"') . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($archivo);
exit;
