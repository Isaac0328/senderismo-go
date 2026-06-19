<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguridad: solo Admin logueado
if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}
if (($_SESSION['usuario_rol_id'] ?? 0) != 1) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['roles_error'] = "Método no permitido.";
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_roles.php");
    exit;
}

csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_roles.php", 'roles_error');

require_once __DIR__ . '/../bd/conexion.php';

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

function redirect_roles(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_roles.php");
    exit;
}

try {

    if ($action === 'save') {

        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        // Preparar OUT params
        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        // Llamar SP
        $stmt = mysqli_prepare($conn, "CALL sp_roles_guardar(?, ?, ?, @p_mensaje, @p_codigo)");
        if (!$stmt) {
            $_SESSION['roles_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_roles($conn);
        }

        mysqli_stmt_bind_param($stmt, "iss", $id, $nombre, $descripcion);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Limpiar resultados del CALL
        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }

        // Obtener OUT params
        $res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
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
            $_SESSION['roles_error'] = "ID inválido para eliminar.";
            redirect_roles($conn);
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        $stmt = mysqli_prepare($conn, "CALL sp_roles_eliminar(?, @p_mensaje, @p_codigo)");
        if (!$stmt) {
            $_SESSION['roles_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_roles($conn);
        }

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }

        $res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
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

    // Acción desconocida
    $_SESSION['roles_error'] = "Acción no válida.";
    redirect_roles($conn);

} catch (Throwable $e) {
    $_SESSION['roles_error'] = "Error: " . $e->getMessage();
    redirect_roles($conn);
}
