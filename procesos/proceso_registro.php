<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bd/conexion.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Método no permitido";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

// Capturar y limpiar
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$user = trim($_POST['user'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

// Para repoblar el form si falla
$_SESSION['reg_old'] = [
    'nombre' => $nombre,
    'apellido' => $apellido,
    'user' => $user,
    'email' => $email,
];

// Validaciones server-side
if ($nombre === '' || $apellido === '' || $user === '' || $email === '' || $password === '' || $confirm === '') {
    $_SESSION['error_message'] = "Por favor completa todos los campos.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if (preg_match('/\s/', $user)) {
    $_SESSION['error_message'] = "El usuario no puede contener espacios.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "Correo inválido.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['error_message'] = "La contraseña debe tener al menos 6 caracteres.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if ($password !== $confirm) {
    $_SESSION['error_message'] = "Las contraseñas no coinciden.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

// Hash seguro (compatible con password_verify en Login)
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Llamar SP
mysqli_query($conn, "SET @p_mensaje = ''");
mysqli_query($conn, "SET @p_codigo = 0");

$stmt = mysqli_prepare($conn, "CALL sp_registrar_usuario(?, ?, ?, ?, ?, @p_mensaje, @p_codigo)");
if (!$stmt) {
    $_SESSION['error_message'] = "Error preparando el registro.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "sssss", $nombre, $apellido, $user, $email, $passwordHash);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Limpiar resultados restantes (por seguridad)
while (mysqli_more_results($conn)) {
    mysqli_next_result($conn);
}

// Obtener OUT params
$res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
$data = $res ? mysqli_fetch_assoc($res) : null;

$codigo = (int) ($data['codigo'] ?? 99);
$mensaje = $data['mensaje'] ?? 'No se pudo completar el registro.';

mysqli_close($conn);

// Respuesta
if ($codigo !== 0) {
    $_SESSION['error_message'] = $mensaje;
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

// OK
unset($_SESSION['reg_old']);
$_SESSION['success_message'] = "✅ Cuenta creada. Ahora inicia sesión.";
header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
exit;
