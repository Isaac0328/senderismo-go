<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'usuarios.pasaporte';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['pasaporte_error'] = "Metodo no permitido.";
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_pasaporte.php");
    exit;
}

csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_pasaporte.php", 'pasaporte_error');

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/pasaporte_bootstrap.php';

pasaporte_bootstrap($conn);

function pasaporte_redirect(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_pasaporte.php");
    exit;
}

function pasaporte_icono_valido(string $icono): string
{
    $permitidos = ['compass', 'map', 'trending-up', 'activity', 'award', 'star', 'flag', 'navigation'];
    return in_array($icono, $permitidos, true) ? $icono : 'map';
}

function pasaporte_color_valido(string $color): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#0f7a3f';
}

try {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $nombre = sg_clean_text((string) ($_POST['nombre'] ?? ''), 120);
        $descripcion = sg_clean_text((string) ($_POST['descripcion'] ?? ''), 255);
        $icono = pasaporte_icono_valido((string) ($_POST['icono'] ?? 'map'));
        $color = pasaporte_color_valido((string) ($_POST['color'] ?? '#0f7a3f'));
        $minSenderos = max(0, (int) ($_POST['min_senderos'] ?? 0));
        $minKm = max(0, (float) ($_POST['min_km'] ?? 0));
        $orden = (int) ($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $_SESSION['pasaporte_error'] = "El nombre del nivel es obligatorio.";
            pasaporte_redirect($conn);
        }

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "
                UPDATE pasaporte_niveles
                SET nombre = ?, descripcion = ?, icono = ?, color = ?, min_senderos = ?, min_km = ?, orden = ?, activo = ?
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, "ssssidiii", $nombre, $descripcion, $icono, $color, $minSenderos, $minKm, $orden, $activo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['pasaporte_success'] = "Nivel actualizado correctamente.";
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO pasaporte_niveles (nombre, descripcion, icono, color, min_senderos, min_km, orden, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "ssssidii", $nombre, $descripcion, $icono, $color, $minSenderos, $minKm, $orden, $activo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['pasaporte_success'] = "Nivel creado correctamente.";
        }

        pasaporte_redirect($conn);
    }

    if ($action === 'toggle') {
        $activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;

        if ($id <= 0) {
            $_SESSION['pasaporte_error'] = "Nivel no valido.";
            pasaporte_redirect($conn);
        }

        if ($activo === 0) {
            $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM pasaporte_niveles WHERE activo = 1 AND id <> ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ((int) ($row['total'] ?? 0) === 0) {
                $_SESSION['pasaporte_error'] = "Debe quedar al menos un nivel activo.";
                pasaporte_redirect($conn);
            }
        }

        $stmt = mysqli_prepare($conn, "UPDATE pasaporte_niveles SET activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['pasaporte_success'] = $activo === 1 ? "Nivel activado." : "Nivel inactivado.";
        pasaporte_redirect($conn);
    }

    $_SESSION['pasaporte_error'] = "Accion no valida.";
    pasaporte_redirect($conn);
} catch (Throwable $e) {
    $_SESSION['pasaporte_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo procesar el nivel.";
    pasaporte_redirect($conn);
}
