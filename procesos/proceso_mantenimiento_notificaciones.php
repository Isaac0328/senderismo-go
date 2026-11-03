<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$PERMISO_REQUERIDO = 'panel.notificaciones';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
$volver = BASE_URL . 'mantenimientos/mantenimiento_notificaciones.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $volver);
    exit;
}
csrf_validate_post($volver, 'notificaciones_error');
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/notificaciones.php';
require_once __DIR__ . '/../componentes/permisos.php';
if (!sg_has_permission_action($conn, 'panel.notificaciones', 'editar') || !sg_notificaciones_tabla($conn, 'notificacion_tipos')) {
    $_SESSION['notificaciones_error'] = 'No tienes permiso para modificar estos avisos o falta actualizar la base de datos.';
    header('Location: ' . $volver);
    exit;
}
try {
    $activos = $_POST['activo'] ?? [];
    $importantes = $_POST['importante'] ?? [];
    if (!is_array($activos) || !is_array($importantes)) throw new RuntimeException('Los datos recibidos no son validos.');
    $conn->begin_transaction();
    $stmt = $conn->prepare('UPDATE notificacion_tipos SET activo = ?, importante = ? WHERE codigo = ?');
    foreach (sg_notificaciones_tipos() as $codigo => $_tipo) {
        $activo = isset($activos[$codigo]) ? 1 : 0;
        $importante = isset($importantes[$codigo]) ? 1 : 0;
        $stmt->bind_param('iis', $activo, $importante, $codigo);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();
    $_SESSION['notificaciones_mensaje'] = 'Configuracion de avisos guardada.';
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Configuracion de avisos: ' . $e->getMessage());
    $_SESSION['notificaciones_error'] = 'No se pudo guardar la configuracion. Intenta nuevamente.';
}
header('Location: ' . $volver);
exit;
