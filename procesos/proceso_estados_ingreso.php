<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';

function estados_ingreso_redirect(mysqli $conn): void
{
    mysqli_close($conn);
    header('Location: ' . BASE_URL . 'mantenimientos/mantenimiento_estados_ingreso.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['estado_ingreso_error'] = 'Metodo no permitido.';
    estados_ingreso_redirect($conn);
}
csrf_validate_post(BASE_URL . 'mantenimientos/mantenimiento_estados_ingreso.php', 'estado_ingreso_error');

$action = (string) ($_POST['action'] ?? '');
$codigo = trim((string) ($_POST['codigo'] ?? ''));
$defaults = sg_financial_status_defaults();

try {
    if (!isset($defaults[$codigo])) {
        throw new RuntimeException('El estado seleccionado no es valido.');
    }

    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $color = strtoupper(trim((string) ($_POST['color'] ?? '')));
        $orden = max(0, min(9999, (int) ($_POST['orden'] ?? 0)));
        $activo = isset($_POST['activo']) ? 1 : 0;
        if ($nombre === '' || !preg_match('/^#[0-9A-F]{6}$/', $color)) {
            throw new RuntimeException('Completa un nombre y un color hexadecimal valido.');
        }
        $stmt = mysqli_prepare($conn, 'UPDATE contabilidad_estado_financiero SET nombre = ?, descripcion = ?, color = ?, orden = ?, activo = ? WHERE codigo = ?');
        mysqli_stmt_bind_param($stmt, 'sssiis', $nombre, $descripcion, $color, $orden, $activo, $codigo);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['estado_ingreso_success'] = 'Estado actualizado correctamente.';
        estados_ingreso_redirect($conn);
    }

    if ($action === 'toggle') {
        $activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;
        $stmt = mysqli_prepare($conn, 'UPDATE contabilidad_estado_financiero SET activo = ? WHERE codigo = ?');
        mysqli_stmt_bind_param($stmt, 'is', $activo, $codigo);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['estado_ingreso_success'] = $activo === 1 ? 'Estado visible.' : 'Estado oculto del catalogo.';
        estados_ingreso_redirect($conn);
    }

    throw new RuntimeException('Accion no valida.');
} catch (Throwable $e) {
    $_SESSION['estado_ingreso_error'] = APP_DEBUG ? $e->getMessage() : 'No se pudo actualizar el estado.';
    estados_ingreso_redirect($conn);
}
