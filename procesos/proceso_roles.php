<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = ['usuarios.roles', 'usuarios.permisos_roles'];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/permisos.php';

$action = (string) ($_POST['action'] ?? '');
$csrfRedirect = $action === 'save_permissions'
    ? BASE_URL . 'mantenimientos/mantenimiento_permisos_roles.php'
    : BASE_URL . 'mantenimientos/mantenimiento_roles.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['roles_error'] = 'Metodo no permitido.';
    header('Location: ' . $csrfRedirect);
    exit;
}

csrf_validate_post($csrfRedirect, 'roles_error');

require_once __DIR__ . '/../bd/conexion.php';
sg_seed_permission_catalog($conn);

$id = (int) ($_POST['id'] ?? 0);

if (in_array($action, ['save', 'delete'], true)) {
    sg_require_permission($conn, 'usuarios.roles');
}
if ($action === 'save_permissions') {
    sg_require_permission($conn, 'usuarios.permisos_roles');
}

function redirect_roles(mysqli $conn, string $target = 'roles'): void
{
    mysqli_close($conn);
    $page = $target === 'permissions' ? 'mantenimiento_permisos_roles.php' : 'mantenimiento_roles.php';
    header('Location: ' . BASE_URL . 'mantenimientos/' . $page);
    exit;
}

try {
    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, 'SET @p_codigo = 0');

        $stmt = mysqli_prepare($conn, 'CALL sp_roles_guardar(?, ?, ?, @p_mensaje, @p_codigo)');
        if (!$stmt) {
            $_SESSION['roles_error'] = 'Error preparando consulta: ' . mysqli_error($conn);
            redirect_roles($conn);
        }

        mysqli_stmt_bind_param($stmt, 'iss', $id, $nombre, $descripcion);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }

        $res = mysqli_query($conn, 'SELECT @p_mensaje AS mensaje, @p_codigo AS codigo');
        $data = $res ? mysqli_fetch_assoc($res) : null;
        $codigo = (int) ($data['codigo'] ?? 99);
        $mensaje = (string) ($data['mensaje'] ?? 'Error desconocido');

        if ($codigo === 0) {
            $_SESSION['roles_success'] = $mensaje;
        } else {
            $_SESSION['roles_error'] = $mensaje;
        }

        redirect_roles($conn);
    }

    if ($action === 'delete') {
        if ($id <= 0) {
            $_SESSION['roles_error'] = 'ID invalido para eliminar.';
            redirect_roles($conn);
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, 'SET @p_codigo = 0');

        $stmt = mysqli_prepare($conn, 'CALL sp_roles_eliminar(?, @p_mensaje, @p_codigo)');
        if (!$stmt) {
            $_SESSION['roles_error'] = 'Error preparando consulta: ' . mysqli_error($conn);
            redirect_roles($conn);
        }

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }

        $res = mysqli_query($conn, 'SELECT @p_mensaje AS mensaje, @p_codigo AS codigo');
        $data = $res ? mysqli_fetch_assoc($res) : null;
        $codigo = (int) ($data['codigo'] ?? 99);
        $mensaje = (string) ($data['mensaje'] ?? 'Error desconocido');

        if ($codigo === 0) {
            $_SESSION['roles_success'] = $mensaje;
        } else {
            $_SESSION['roles_error'] = $mensaje;
        }

        redirect_roles($conn);
    }

    if ($action === 'save_permissions') {
        if ($id <= 0) {
            $_SESSION['roles_error'] = 'Selecciona un rol valido para asignar permisos.';
            redirect_roles($conn, 'permissions');
        }

        $catalog = sg_permission_flat_catalog();
        $selected = array_values(array_intersect(
            array_map('strval', $_POST['permisos'] ?? []),
            array_keys($catalog)
        ));

        if ($id === 1) {
            $selected = array_keys($catalog);
        }

        mysqli_begin_transaction($conn);

        $escapedCodes = array_map(
            static fn ($code) => "'" . mysqli_real_escape_string($conn, $code) . "'",
            array_keys($catalog)
        );
        mysqli_query($conn, "
            DELETE rp
            FROM rol_permiso rp
            INNER JOIN permisos p ON p.id = rp.permiso_id
            WHERE rp.rol_id = {$id}
              AND p.nombre IN (" . implode(',', $escapedCodes) . ")
        ");

        if (!empty($selected)) {
            $stmt = mysqli_prepare($conn, "
                INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
                SELECT ?, id
                FROM permisos
                WHERE nombre = ?
            ");
            if (!$stmt) {
                throw new RuntimeException('Error preparando permisos: ' . mysqli_error($conn));
            }

            foreach ($selected as $code) {
                mysqli_stmt_bind_param($stmt, 'is', $id, $code);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);
        $_SESSION['roles_success'] = 'Permisos actualizados correctamente.';
        redirect_roles($conn, 'permissions');
    }

    $_SESSION['roles_error'] = 'Accion no valida.';
    redirect_roles($conn);
} catch (Throwable $e) {
    if (mysqli_errno($conn)) {
        mysqli_rollback($conn);
    }
    $_SESSION['roles_error'] = 'Error: ' . $e->getMessage();
    redirect_roles($conn);
}
