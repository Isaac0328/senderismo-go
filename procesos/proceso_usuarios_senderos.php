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
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_usuarios_senderos.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

$registroId = (int) ($_POST['registro_id'] ?? 0);
$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$accion = trim((string) ($_POST['accion'] ?? ''));

function usuarios_senderos_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    $url = BASE_URL . "mantenimientos/mantenimiento_usuarios_senderos.php";
    if ($senderoId > 0) {
        $url .= "?sendero_id=" . $senderoId;
    }
    header("Location: " . $url);
    exit;
}

if ($registroId <= 0 || $senderoId <= 0) {
    $_SESSION['usuarios_senderos_error'] = "Registro no valido.";
    usuarios_senderos_redirect($conn, $senderoId);
}

$stmt = mysqli_prepare($conn, "SELECT id, sendero_id, estado FROM registros_senderos WHERE id = ? AND sendero_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
mysqli_stmt_execute($stmt);
$registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$registro) {
    $_SESSION['usuarios_senderos_error'] = "No se encontro el registro seleccionado.";
    usuarios_senderos_redirect($conn, $senderoId);
}

try {
    if ($accion === 'cancelar') {
        if ($registro['estado'] === 'cancelado') {
            $_SESSION['usuarios_senderos_error'] = "Este registro ya esta inactivo.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $stmt = mysqli_prepare($conn, "UPDATE registros_senderos SET estado = 'cancelado' WHERE id = ? AND sendero_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['usuarios_senderos_success'] = "Usuario inactivado de este sendero.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    if ($accion === 'reactivar') {
        if ($registro['estado'] === 'registrado') {
            $_SESSION['usuarios_senderos_error'] = "Este registro ya esta activo.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $stmt = mysqli_prepare($conn, "UPDATE registros_senderos SET estado = 'registrado' WHERE id = ? AND sendero_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['usuarios_senderos_success'] = "Usuario reactivado en este sendero.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    if ($accion === 'eliminar') {
        if ($registro['estado'] !== 'cancelado') {
            $_SESSION['usuarios_senderos_error'] = "Primero debes inactivar el registro antes de eliminarlo.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM registros_senderos WHERE id = ? AND sendero_id = ? AND estado = 'cancelado'");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['usuarios_senderos_success'] = "Registro eliminado permanentemente.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    $_SESSION['usuarios_senderos_error'] = "Accion no valida.";
    usuarios_senderos_redirect($conn, $senderoId);
} catch (Throwable $e) {
    $_SESSION['usuarios_senderos_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo procesar la accion.";
    usuarios_senderos_redirect($conn, $senderoId);
}
