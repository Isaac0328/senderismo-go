<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';

contabilidad_bootstrap($conn);

function gastos_redirect(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_gastos.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['gastos_error'] = "Metodo no permitido.";
    gastos_redirect($conn);
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_gastos.php", 'gastos_error');

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

try {
    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $categoriaGastoId = (int) ($_POST['categoria_gasto_id'] ?? 0);
        $categoriaGastoId = $categoriaGastoId > 0 ? $categoriaGastoId : null;
        $unidad = trim((string) ($_POST['unidad'] ?? 'unidad'));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $costo = max(0, (float) ($_POST['costo_unitario'] ?? 0));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $_SESSION['gastos_error'] = "El nombre del gasto es obligatorio.";
            gastos_redirect($conn);
        }
        if ($unidad === '') {
            $unidad = 'unidad';
        }

        if ($id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE contabilidad_gastos_catalogo
                 SET nombre = ?, descripcion = ?, categoria_gasto_id = ?, unidad = ?, costo_unitario = ?, activo = ?
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'ssisdii', $nombre, $descripcion, $categoriaGastoId, $unidad, $costo, $activo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['gastos_success'] = "Gasto actualizado correctamente.";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO contabilidad_gastos_catalogo (nombre, descripcion, categoria_gasto_id, unidad, costo_unitario, activo)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssisdi', $nombre, $descripcion, $categoriaGastoId, $unidad, $costo, $activo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['gastos_success'] = "Gasto creado correctamente.";
        }

        gastos_redirect($conn);
    }

    if ($action === 'toggle' && $id > 0) {
        $activo = (int) ($_POST['activo'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE contabilidad_gastos_catalogo SET activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['gastos_success'] = $activo === 1 ? "Gasto activado." : "Gasto inactivado.";
        gastos_redirect($conn);
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT activo FROM contabilidad_gastos_catalogo WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $gasto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$gasto) {
            $_SESSION['gastos_error'] = "El gasto no existe.";
            gastos_redirect($conn);
        }

        if ((int) $gasto['activo'] === 1) {
            $_SESSION['gastos_error'] = "Solo puedes eliminar gastos inactivos.";
            gastos_redirect($conn);
        }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM contabilidad_sendero_gastos WHERE gasto_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $uso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int) ($uso['total'] ?? 0) > 0) {
            $_SESSION['gastos_error'] = "No se puede eliminar este gasto porque ya fue usado en senderos.";
            gastos_redirect($conn);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM contabilidad_gastos_catalogo WHERE id = ? AND activo = 0");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['gastos_success'] = "Gasto eliminado correctamente.";
        gastos_redirect($conn);
    }

    $_SESSION['gastos_error'] = "Accion no valida.";
    gastos_redirect($conn);
} catch (Throwable $e) {
    $_SESSION['gastos_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo guardar el gasto.";
    gastos_redirect($conn);
}
