<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function volver_contacto(): void
{
    header("Location: " . BASE_URL . "pantallas/contacto.php#contacto-form");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "pantallas/contacto.php");
    exit;
}
csrf_validate_post(BASE_URL . "pantallas/contacto.php#contacto-form", 'contact_error');

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$apellido = trim((string) ($_POST['apellido'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));
$asunto = trim((string) ($_POST['asunto'] ?? ''));
$mensaje = trim((string) ($_POST['mensaje'] ?? ''));
$website = trim((string) ($_POST['website'] ?? ''));

$_SESSION['contact_old'] = [
    'nombre' => $nombre,
    'apellido' => $apellido,
    'email' => $email,
    'telefono' => $telefono,
    'asunto' => $asunto,
    'mensaje' => $mensaje
];

if ($website !== '') {
    $_SESSION['contact_error'] = "No se pudo procesar el mensaje.";
    volver_contacto();
}

$asuntosPermitidos = [
    'informacion_ruta',
    'servicio_privado',
    'proximo_sendero',
    'dificultad_equipo',
    'alianza',
    'otro'
];

if ($nombre === '' || $email === '' || $asunto === '' || $mensaje === '') {
    $_SESSION['contact_error'] = "Completa los campos obligatorios para poder responderte.";
    volver_contacto();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['contact_error'] = "Escribe un correo electronico valido.";
    volver_contacto();
}

if (!in_array($asunto, $asuntosPermitidos, true)) {
    $_SESSION['contact_error'] = "Selecciona un asunto valido.";
    volver_contacto();
}

if (strlen($nombre) > 100 || strlen($apellido) > 100 || strlen($email) > 150 || strlen($telefono) > 30 || strlen($mensaje) > 1000) {
    $_SESSION['contact_error'] = "Revisa la longitud de los campos enviados.";
    volver_contacto();
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    $_SESSION['contact_error'] = "No se pudo conectar con el buzon de contacto. Intenta mas tarde.";
    volver_contacto();
}

mysqli_set_charset($conn, "utf8mb4");

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO mensajes_contacto (nombre, apellido, email, telefono, asunto, mensaje, ip, user_agent)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    $_SESSION['contact_error'] = "No se pudo registrar el mensaje. Intenta mas tarde.";
    volver_contacto();
}

$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

mysqli_stmt_bind_param($stmt, 'ssssssss', $nombre, $apellido, $email, $telefono, $asunto, $mensaje, $ip, $userAgent);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $_SESSION['contact_error'] = "No se pudo enviar el mensaje. Intenta mas tarde.";
    volver_contacto();
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

unset($_SESSION['contact_old']);
$_SESSION['contact_success'] = "Mensaje recibido. Te responderemos lo antes posible.";

volver_contacto();
