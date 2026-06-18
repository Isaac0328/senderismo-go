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

function metodo_pago_redirect(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_metodo_pago.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['metodo_pago_error'] = "Metodo no permitido.";
    metodo_pago_redirect($conn);
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_metodo_pago.php", 'metodo_pago_error');

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

try {
    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $_SESSION['metodo_pago_error'] = "El nombre del metodo de pago es obligatorio.";
            metodo_pago_redirect($conn);
        }

        if ($id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE contabilidad_metodo_pago
                 SET nombre = ?, descripcion = ?, activo = ?
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'ssii', $nombre, $descripcion, $activo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['metodo_pago_success'] = "Metodo de pago actualizado correctamente.";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO contabilidad_metodo_pago (nombre, descripcion, activo)
                 VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssi', $nombre, $descripcion, $activo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['metodo_pago_success'] = "Metodo de pago creado correctamente.";
        }

        metodo_pago_redirect($conn);
    }

    if ($action === 'toggle' && $id > 0) {
        $activo = (int) ($_POST['activo'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE contabilidad_metodo_pago SET activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['metodo_pago_success'] = $activo === 1 ? "Metodo activado." : "Metodo inactivado.";
        metodo_pago_redirect($conn);
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT activo FROM contabilidad_metodo_pago WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $metodo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$metodo) {
            $_SESSION['metodo_pago_error'] = "El metodo no existe.";
            metodo_pago_redirect($conn);
        }

        if ((int) $metodo['activo'] === 1) {
            $_SESSION['metodo_pago_error'] = "Solo puedes eliminar metodos inactivos.";
            metodo_pago_redirect($conn);
        }

        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM contabilidad_registro_pagos WHERE metodo_pago_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $uso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int) ($uso['total'] ?? 0) > 0) {
            $_SESSION['metodo_pago_error'] = "No se puede eliminar este metodo porque ya fue usado en pagos.";
            metodo_pago_redirect($conn);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM contabilidad_metodo_pago WHERE id = ? AND activo = 0");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['metodo_pago_success'] = "Metodo eliminado correctamente.";
        metodo_pago_redirect($conn);
    }

    $_SESSION['metodo_pago_error'] = "Accion no valida.";
    metodo_pago_redirect($conn);
} catch (Throwable $e) {
    $_SESSION['metodo_pago_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo guardar el metodo.";
    metodo_pago_redirect($conn);
}

