<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$PERMISO_REQUERIDO = 'usuarios.sin_cuenta';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/personas_sin_cuenta.php';
$redirect = sg_clean_url('mantenimientos/mantenimiento_usuarios_sin_cuenta.php');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metodo no permitido.');
}
csrf_validate_post($redirect, 'sin_cuenta_error');
$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$permisoAccion = match ($action) {
    'save' => $id === 0 ? 'agregar' : 'editar',
    'eliminar' => 'eliminar',
    default => 'editar',
};
sg_require_permission_action($conn, $PERMISO_REQUERIDO, $permisoAccion, $redirect);
$actor = (int) ($_SESSION['usuario_id'] ?? 0);

try {
    if (!psc_listo($conn)) {
        throw new InvalidArgumentException('Aplica primero la actualizacion pendiente del sistema.');
    }
    if ($action !== 'save' && $id <= 0) {
        throw new InvalidArgumentException('Accion no valida.');
    }
    switch ($action) {
        case 'save':
            psc_guardar($conn, $id, $_POST, $actor);
            $mensaje = $id > 0 ? 'Datos actualizados. Sus inscripciones muestran los datos nuevos.' : 'Persona sin cuenta creada.';
            break;
        case 'inactivar':
        case 'activar':
            psc_cambiar_estado($conn, $id, $action === 'activar');
            $mensaje = $action === 'activar' ? 'Persona activada.' : 'Persona inactivada.';
            break;
        case 'eliminar':
            psc_eliminar($conn, $id);
            $mensaje = 'Persona eliminada.';
            break;
        case 'unir':
            // Por defecto se conserva la ficha elegida; con "Invertir" se conserva esta.
            $otraId = (int) ($_POST['destino_id'] ?? 0);
            $conservarEsta = ($_POST['conservar'] ?? '') === 'esta';
            $movidas = $conservarEsta ? psc_unir($conn, $otraId, $id) : psc_unir($conn, $id, $otraId);
            $mensaje = 'Fichas unidas. ' . $movidas . ' inscripciones pasaron a la ficha que se conserva.';
            break;
        case 'reconciliar':
            $movidas = psc_reconciliar($conn, $id, (int) ($_POST['usuario_id'] ?? 0), $actor);
            $mensaje = 'Reconciliado. ' . $movidas . ' inscripciones ahora aparecen con la cuenta del usuario.';
            break;
        default:
            throw new InvalidArgumentException('Accion no valida.');
    }
    $_SESSION['sin_cuenta_success'] = $mensaje;
} catch (Throwable $error) {
    error_log('Usuarios sin cuenta: ' . $error->getMessage());
    $_SESSION['sin_cuenta_error'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo completar la operacion.';
    if ($action === 'save' || $action === 'reconciliar' || $action === 'unir') {
        $modo = ['save' => 'edit', 'reconciliar' => 'reconciliar', 'unir' => 'unir'][$action];
        $redirect = sg_clean_url('mantenimientos/mantenimiento_usuarios_sin_cuenta.php' . ($id > 0 ? '?' . $modo . '=' . $id : '?nueva=1'));
    }
}
mysqli_close($conn);
header('Location: ' . $redirect);
exit;
