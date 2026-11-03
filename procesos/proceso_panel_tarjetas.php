<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$PERMISO_REQUERIDO = 'panel.tarjetas';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/panel_tarjetas.php';

$redirect = sg_clean_url('mantenimientos/mantenimiento_panel_tarjetas.php');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metodo no permitido.');
}
csrf_validate_post($redirect, 'panel_tarjetas_error');
sg_require_permission_action($conn, $PERMISO_REQUERIDO, 'editar', $redirect);

$accion = (string) ($_POST['action'] ?? '');
try {
    if ($accion === 'guardar') {
        sg_panel_tarjetas_guardar($conn, is_array($_POST['tarjetas'] ?? null) ? $_POST['tarjetas'] : []);
        $_SESSION['panel_tarjetas_success'] = 'Listo. El panel ya se muestra con tu configuracion.';
    } elseif ($accion === 'restablecer') {
        sg_panel_tarjetas_restablecer($conn);
        $_SESSION['panel_tarjetas_success'] = 'El panel volvio a su configuracion original: todo visible, en su orden y para todos los roles.';
    } else {
        throw new InvalidArgumentException('Accion no valida.');
    }
} catch (Throwable $error) {
    error_log('Tarjetas del panel: ' . $error->getMessage());
    $_SESSION['panel_tarjetas_error'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo guardar la configuracion del panel.';
}
mysqli_close($conn);
header('Location: ' . $redirect);
exit;
