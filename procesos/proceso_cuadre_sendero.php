<?php
// Marca o reabre el cuadre de los cobros o los gastos de un sendero y vuelve a su pantalla.
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/cuadre_sendero.php';

$area = (string) ($_POST['area'] ?? '');
$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$areas = sg_cuadre_sendero_areas();
$volver = BASE_URL . ($areas[$area][1] ?? 'admin') . ($senderoId > 0 ? '?sendero_id=' . $senderoId : '');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $volver);
    exit;
}
csrf_validate_post($volver, 'sg_cuadre_mensaje');

try {
    if ($senderoId <= 0 || !isset($areas[$area])) {
        throw new InvalidArgumentException('Solicitud incompleta.');
    }
    if (!sg_cuadre_sendero_listo($conn)) {
        throw new InvalidArgumentException('Falta aplicar la actualizacion 20261021_013 en Actualizaciones del sistema.');
    }
    $stmt = mysqli_prepare($conn, 'SELECT id FROM senderos WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) !== null;
    mysqli_stmt_close($stmt);
    if (!$existe) {
        throw new InvalidArgumentException('El sendero no existe.');
    }
    sg_cuadre_sendero_cambiar($conn, $senderoId, $area, ($_POST['accion'] ?? '') === 'marcar', (int) ($_SESSION['usuario_id'] ?? 0));
} catch (Throwable $e) {
    $_SESSION['sg_cuadre_mensaje'] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo guardar el cuadre del sendero.';
    if (!$e instanceof InvalidArgumentException) {
        error_log('Cuadre de sendero: ' . $e->getMessage());
    }
}
header('Location: ' . $volver);
exit;
