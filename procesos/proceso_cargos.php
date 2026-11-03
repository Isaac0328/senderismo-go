<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'usuarios.cargos';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/permisos.php';

$redirect = BASE_URL . 'mantenimientos/mantenimiento_cargos.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['cargos_error'] = 'Metodo no permitido.';
    header('Location: ' . $redirect);
    exit;
}

csrf_validate_post($redirect, 'cargos_error');

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/cargos.php';

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

if (!sg_cargos_listo($conn)) {
    $_SESSION['cargos_error'] = 'Falta aplicar la actualizacion pendiente del sistema.';
    mysqli_close($conn);
    header('Location: ' . $redirect);
    exit;
}

function cargos_terminar(mysqli $conn, string $redirect): void
{
    mysqli_close($conn);
    header('Location: ' . $redirect);
    exit;
}

// Un cargo existente: solo se edita o elimina lo que ya es de tipo cargo.
function cargos_existente(mysqli $conn, int $id): ?array
{
    $stmt = mysqli_prepare($conn, "SELECT id, nombre FROM roles WHERE id = ? AND tipo = 'cargo'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: null;
}

try {
    if ($action === 'save') {
        sg_require_permission_action($conn, 'usuarios.cargos', $id > 0 ? 'editar' : 'agregar', $redirect);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        if ($id > 0 && !cargos_existente($conn, $id)) {
            $_SESSION['cargos_error'] = 'Ese cargo no existe.';
            cargos_terminar($conn, $redirect);
        }

        // Mismas validaciones que los roles (nombre obligatorio y sin repetir); luego se marca como cargo.
        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, 'SET @p_codigo = 0');
        $stmt = mysqli_prepare($conn, 'CALL sp_roles_guardar(?, ?, ?, @p_mensaje, @p_codigo)');
        mysqli_stmt_bind_param($stmt, 'iss', $id, $nombre, $descripcion);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }
        $res = mysqli_query($conn, 'SELECT @p_mensaje AS mensaje, @p_codigo AS codigo');
        $data = $res ? mysqli_fetch_assoc($res) : null;
        if ((int) ($data['codigo'] ?? 99) !== 0) {
            $_SESSION['cargos_error'] = (string) ($data['mensaje'] ?? 'No se pudo guardar el cargo.');
            cargos_terminar($conn, $redirect);
        }

        $upd = mysqli_prepare($conn, "UPDATE roles SET tipo = 'cargo' WHERE nombre = ? AND id <> 1");
        mysqli_stmt_bind_param($upd, 's', $nombre);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        $_SESSION['cargos_success'] = $id > 0 ? 'Cargo actualizado correctamente.' : 'Cargo creado correctamente. Asigna sus permisos en "Permisos".';
        cargos_terminar($conn, $redirect);
    }

    if ($action === 'delete') {
        sg_require_permission_action($conn, 'usuarios.cargos', 'eliminar', $redirect);
        $cargo = $id > 0 ? cargos_existente($conn, $id) : null;
        if (!$cargo) {
            $_SESSION['cargos_error'] = 'Ese cargo no existe.';
            cargos_terminar($conn, $redirect);
        }
        $asignados = (int) mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM usuario_cargos WHERE cargo_id = ' . $id))[0];
        if ($asignados > 0) {
            $_SESSION['cargos_error'] = 'No se puede eliminar: ' . $asignados . ' persona(s) tienen el cargo "' . $cargo['nombre'] . '". Quitaselo primero en Usuarios.';
            cargos_terminar($conn, $redirect);
        }
        mysqli_query($conn, 'DELETE FROM roles WHERE id = ' . $id . " AND tipo = 'cargo'");
        $_SESSION['cargos_success'] = 'Cargo eliminado correctamente.';
        cargos_terminar($conn, $redirect);
    }

    $_SESSION['cargos_error'] = 'Accion no valida.';
} catch (Throwable $e) {
    error_log('Mantenimiento de cargos: ' . $e->getMessage());
    $_SESSION['cargos_error'] = APP_DEBUG ? $e->getMessage() : 'No se pudo procesar el cargo.';
}
cargos_terminar($conn, $redirect);
