<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in']) || (int) ($_SESSION['usuario_rol_id'] ?? 0) !== 1) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['puntos_error'] = "Metodo no permitido.";
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_puntos_encuentro.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

function redirect_puntos(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_puntos_encuentro.php");
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

try {
    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $direccion = trim((string) ($_POST['direccion_referencia'] ?? ''));
        $urlMapa = trim((string) ($_POST['url_mapa'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $_SESSION['puntos_error'] = "El nombre del punto es obligatorio.";
            redirect_puntos($conn);
        }

        if ($urlMapa !== '' && !filter_var($urlMapa, FILTER_VALIDATE_URL)) {
            $_SESSION['puntos_error'] = "La URL del mapa no tiene un formato valido.";
            redirect_puntos($conn);
        }

        if ($id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE puntos_encuentro
                 SET nombre = ?, direccion_referencia = ?, url_mapa = ?, activo = ?
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, "sssii", $nombre, $direccion, $urlMapa, $activo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['puntos_success'] = "Punto actualizado correctamente.";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO puntos_encuentro (nombre, direccion_referencia, url_mapa, activo)
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "sssi", $nombre, $direccion, $urlMapa, $activo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['puntos_success'] = "Punto creado correctamente.";
        }

        redirect_puntos($conn);
    }

    if ($action === 'toggle') {
        $activo = (int) ($_POST['activo'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE puntos_encuentro SET activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['puntos_success'] = $activo === 1 ? "Punto activado." : "Punto inactivado.";
        redirect_puntos($conn);
    }

    if ($action === 'delete') {
        $stmt = mysqli_prepare($conn, "SELECT activo FROM puntos_encuentro WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $punto = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$punto) {
            $_SESSION['puntos_error'] = "El punto seleccionado no existe.";
            redirect_puntos($conn);
        }

        if ((int) $punto['activo'] === 1) {
            $_SESSION['puntos_error'] = "Primero debes inactivar el punto antes de eliminarlo.";
            redirect_puntos($conn);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM puntos_encuentro WHERE id = ? AND activo = 0");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['puntos_success'] = "Punto eliminado correctamente.";
        redirect_puntos($conn);
    }

    $_SESSION['puntos_error'] = "Accion no valida.";
    redirect_puntos($conn);
} catch (mysqli_sql_exception $e) {
    if ((int) $e->getCode() === 1062) {
        $_SESSION['puntos_error'] = "Ya existe un punto con ese nombre.";
    } else {
        $_SESSION['puntos_error'] = "Error: " . $e->getMessage();
    }
    redirect_puntos($conn);
} catch (Throwable $e) {
    $_SESSION['puntos_error'] = "Error: " . $e->getMessage();
    redirect_puntos($conn);
}
