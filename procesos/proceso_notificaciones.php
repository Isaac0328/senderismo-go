<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
csrf_validate_post();

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/notificaciones.php';

if (!sg_notificaciones_tabla($conn, 'usuario_notificacion_lecturas')) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Notificaciones pendientes de instalacion.']);
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$resumen = sg_notificaciones_resumen($conn, $usuarioId, sg_notificaciones_permisos($conn));
$claves = array_column($resumen['items'], 'clave');
$accion = (string) ($_POST['action'] ?? '');
if ($accion === 'read') {
    $clave = (string) ($_POST['key'] ?? '');
    if (!in_array($clave, $claves, true)) {
        http_response_code(404);
        echo json_encode(['ok' => false]);
        exit;
    }
    $claves = [$clave];
} elseif ($accion !== 'read_all') {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $conn->prepare('INSERT IGNORE INTO usuario_notificacion_lecturas (usuario_id,clave) VALUES (?,?)');
foreach ($claves as $clave) {
    $stmt->bind_param('is', $usuarioId, $clave);
    $stmt->execute();
}
$stmt->close();
echo json_encode(['ok' => true, 'count' => count($claves)]);
