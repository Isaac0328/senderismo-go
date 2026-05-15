<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguridad: solo Admin
if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}
if (($_SESSION['usuario_rol_id'] ?? 0) != 1) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['usuarios_error'] = "Método no permitido.";
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_usuarios.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

function redirect_users(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_usuarios.php");
    exit;
}

try {

    // =========================
    // GUARDAR / ACTUALIZAR
    // =========================
    if ($action === 'save') {

        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol_id = (int) ($_POST['rol_id'] ?? 0);

        $passwordPlain = (string) ($_POST['password'] ?? '');
        $passwordPlain = trim($passwordPlain);

        // Si es creación (id=0) password obligatoria
        if ($id === 0 && $passwordPlain === '') {
            $_SESSION['usuarios_error'] = "La contraseña es obligatoria para crear el usuario.";
            redirect_users($conn);
        }

        // Hash: solo si viene password
        $passwordHash = '';
        if ($passwordPlain !== '') {
            $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                $_SESSION['usuarios_error'] = "No se pudo generar el hash de la contraseña.";
                redirect_users($conn);
            }
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        $stmt = mysqli_prepare(
            $conn,
            "CALL sp_usuarios_guardar(?, ?, ?, ?, ?, ?, ?, @p_mensaje, @p_codigo)"
        );

        if (!$stmt) {
            $_SESSION['usuarios_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_users($conn);
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssssi",
            $id,
            $nombre,
            $apellido,
            $user,
            $email,
            $passwordHash,
            $rol_id
        );

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
            $_SESSION['usuarios_success'] = $mensaje;
        } else {
            $_SESSION['usuarios_error'] = $mensaje;
        }

        redirect_users($conn);
    }

    // =========================
    // ACTIVAR / INACTIVAR
    // =========================
    if ($action === 'toggle_estado') {

        $estado = (int) ($_POST['estado'] ?? -1);

        // Evitar que el admin se inhabilite a sí mismo (opcional pero recomendado)
        if ($id === (int) ($_SESSION['usuario_id'] ?? 0) && $estado === 0) {
            $_SESSION['usuarios_error'] = "No puedes inactivarte a ti mismo.";
            redirect_users($conn);
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        $stmt = mysqli_prepare($conn, "CALL sp_usuarios_cambiar_estado(?, ?, @p_mensaje, @p_codigo)");
        if (!$stmt) {
            $_SESSION['usuarios_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_users($conn);
        }

        mysqli_stmt_bind_param($stmt, "ii", $id, $estado);
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
            $_SESSION['usuarios_success'] = $mensaje;
        } else {
            $_SESSION['usuarios_error'] = $mensaje;
        }

        redirect_users($conn);
    }

    // =========================
    // ELIMINAR
    // =========================
    if ($action === 'delete') {

        // Evitar borrar tu propio usuario (recomendado)
        if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
            $_SESSION['usuarios_error'] = "No puedes eliminar tu propio usuario.";
            redirect_users($conn);
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        $stmt = mysqli_prepare($conn, "CALL sp_usuarios_eliminar(?, @p_mensaje, @p_codigo)");
        if (!$stmt) {
            $_SESSION['usuarios_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_users($conn);
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
            $_SESSION['usuarios_success'] = $mensaje;
        } else {
            $_SESSION['usuarios_error'] = $mensaje;
        }

        redirect_users($conn);
    }

    // Acción desconocida
    $_SESSION['usuarios_error'] = "Acción no válida.";
    redirect_users($conn);

} catch (Throwable $e) {
    $_SESSION['usuarios_error'] = "Error: " . $e->getMessage();
    redirect_users($conn);
}
