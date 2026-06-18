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

function categoria_gasto_redirect(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_categoria_gasto.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['categoria_gasto_error'] = "Metodo no permitido.";
    categoria_gasto_redirect($conn);
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_categoria_gasto.php", 'categoria_gasto_error');

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

try {
    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $_SESSION['categoria_gasto_error'] = "El nombre de la categoria es obligatorio.";
            categoria_gasto_redirect($conn);
        }

        if ($id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE contabilidad_categoria_gasto
                 SET nombre = ?, descripcion = ?, activo = ?
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'ssii', $nombre, $descripcion, $activo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['categoria_gasto_success'] = "Categoria de gasto actualizada correctamente.";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO contabilidad_categoria_gasto (nombre, descripcion, activo)
                 VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssi', $nombre, $descripcion, $activo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['categoria_gasto_success'] = "Categoria de gasto creada correctamente.";
        }

        categoria_gasto_redirect($conn);
    }

    if ($action === 'toggle' && $id > 0) {
        $activo = (int) ($_POST['activo'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE contabilidad_categoria_gasto SET activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['categoria_gasto_success'] = $activo === 1 ? "Categoria activada." : "Categoria inactivada.";
        categoria_gasto_redirect($conn);
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT activo FROM contabilidad_categoria_gasto WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $categoria = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$categoria) {
            $_SESSION['categoria_gasto_error'] = "La categoria no existe.";
            categoria_gasto_redirect($conn);
        }

        if ((int) $categoria['activo'] === 1) {
            $_SESSION['categoria_gasto_error'] = "Solo puedes eliminar categorias inactivas.";
            categoria_gasto_redirect($conn);
        }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM contabilidad_gastos_catalogo WHERE categoria_gasto_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $uso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int) ($uso['total'] ?? 0) > 0) {
            $_SESSION['categoria_gasto_error'] = "No se puede eliminar esta categoria porque tiene gastos asociados.";
            categoria_gasto_redirect($conn);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM contabilidad_categoria_gasto WHERE id = ? AND activo = 0");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['categoria_gasto_success'] = "Categoria eliminada correctamente.";
        categoria_gasto_redirect($conn);
    }

    $_SESSION['categoria_gasto_error'] = "Accion no valida.";
    categoria_gasto_redirect($conn);
} catch (Throwable $e) {
    $_SESSION['categoria_gasto_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo guardar la categoria.";
    categoria_gasto_redirect($conn);
}
