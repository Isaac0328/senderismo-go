<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'sistema.actualizaciones';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

function actualizaciones_redirigir(): void
{
    header('Location: ' . BASE_URL . 'mantenimientos/mantenimiento_actualizaciones.php');
    exit;
}

if (!sg_is_admin_role()) {
    http_response_code(403);
    exit('Solo el administrador principal puede aplicar actualizaciones.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['actualizaciones_error'] = 'Metodo no permitido.';
    actualizaciones_redirigir();
}

csrf_validate_post(BASE_URL . 'mantenimientos/mantenimiento_actualizaciones.php', 'actualizaciones_error');
if (trim((string) ($_POST['confirmacion'] ?? '')) !== 'ACTUALIZAR') {
    $_SESSION['actualizaciones_error'] = 'Escribe ACTUALIZAR para confirmar la accion.';
    actualizaciones_redirigir();
}

require_once __DIR__ . '/../componentes/migraciones.php';

try {
    $aplicadas = sg_migraciones_aplicar($conn);
    $_SESSION['actualizaciones_success'] = $aplicadas
        ? 'Se aplicaron ' . count($aplicadas) . ' actualizacion(es) correctamente.'
        : 'La plataforma ya estaba actualizada.';
} catch (Throwable $error) {
    $_SESSION['actualizaciones_error'] = APP_DEBUG ? $error->getMessage() : 'No se pudieron aplicar las actualizaciones.';
}

mysqli_close($conn);
actualizaciones_redirigir();
