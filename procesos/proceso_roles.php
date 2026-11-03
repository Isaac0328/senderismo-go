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
require_once __DIR__ . '/../componentes/cargos.php';

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

// Tipo del rol: acceso o cargo. El Administrador siempre es de acceso y no se cambia de tipo un rol que ya se usa como el otro.
function guardar_tipo_rol(mysqli $conn, string $nombre, string $tipo): string
{
    if (!sg_cargos_listo($conn) || !in_array($tipo, ['acceso', 'cargo'], true)) {
        return '';
    }
    $stmt = mysqli_prepare($conn, 'SELECT id, tipo FROM roles WHERE nombre = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $nombre);
    mysqli_stmt_execute($stmt);
    $rol = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$rol || $rol['tipo'] === $tipo) {
        return '';
    }
    $id = (int) $rol['id'];
    if ($id === 1) {
        return 'El Administrador siempre es un rol de acceso.';
    }
    $enUso = $tipo === 'cargo'
        ? mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM usuarios WHERE rol_id = ' . $id))[0]
        : mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM usuario_cargos WHERE cargo_id = ' . $id))[0];
    if ((int) $enUso > 0) {
        return 'Guardado, pero no se cambio el tipo: hay usuarios que usan "' . $nombre . '" como ' . ($tipo === 'cargo' ? 'rol de acceso' : 'cargo') . '. Cambialos primero.';
    }
    $upd = mysqli_prepare($conn, 'UPDATE roles SET tipo = ? WHERE id = ?');
    mysqli_stmt_bind_param($upd, 'si', $tipo, $id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
    return '';
}

try {
    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, 'SET @p_codigo = 0');

        $stmt = mysqli_prepare($conn, 'CALL sp_roles_guardar(?, ?, ?, @p_mensaje, @p_codigo)');
        if (!$stmt) {
            $_SESSION['roles_error'] = APP_DEBUG
                ? 'Error preparando consulta: ' . mysqli_error($conn)
                : 'No se pudo guardar el rol.';
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
            $errorTipo = guardar_tipo_rol($conn, $nombre, (string) ($_POST['tipo'] ?? ''));
            if ($errorTipo !== '') {
                $_SESSION['roles_error'] = $errorTipo;
            }
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
            $_SESSION['roles_error'] = APP_DEBUG
                ? 'Error preparando consulta: ' . mysqli_error($conn)
                : 'No se pudo eliminar el rol.';
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
    error_log('Mantenimiento de roles: ' . $e->getMessage());
    $_SESSION['roles_error'] = APP_DEBUG
        ? 'Error: ' . $e->getMessage()
        : 'No se pudo procesar el rol.';
    redirect_roles($conn);
}
