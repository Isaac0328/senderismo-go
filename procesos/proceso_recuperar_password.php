<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/smtp_mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_recovery(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "pantallas/recuperar_password.php");
    exit;
}

function absolute_url(string $path): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . ltrim($path, '/');
}

function recovery_is_local(): bool
{
    $server = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return in_array($server, ['localhost', '127.0.0.1'], true)
        || str_starts_with($host, 'localhost')
        || str_starts_with($host, '127.0.0.1');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['recovery_error'] = "Metodo no permitido.";
    redirect_recovery($conn);
}
csrf_validate_post(BASE_URL . "pantallas/recuperar_password.php", 'recovery_error');

$email = trim((string) ($_POST['email'] ?? ''));
$_SESSION['recovery_email'] = $email;

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['recovery_error'] = "Escribe un correo electronico valido.";
    redirect_recovery($conn);
}

$genericMessage = "Si el correo existe y esta activo, recibiras un enlace para restablecer tu contrasena.";

$stmt = mysqli_prepare($conn, "SELECT id, nombre, email FROM usuarios WHERE email = ? AND estado = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$usuario = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$usuario) {
    $_SESSION['recovery_success'] = $genericMessage;
    redirect_recovery($conn);
}

$usuarioId = (int) $usuario['id'];
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', time() + 3600);
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE password_resets
         SET used_at = NOW()
         WHERE usuario_id = ? AND used_at IS NULL"
    );
    mysqli_stmt_bind_param($stmt, "i", $usuarioId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO password_resets (usuario_id, token_hash, expires_at, ip_address)
         VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "isss", $usuarioId, $tokenHash, $expiresAt, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['recovery_error'] = "No se pudo generar el enlace. Intenta nuevamente.";
    redirect_recovery($conn);
}

$link = absolute_url("pantallas/restablecer_password.php?token=" . $token);
$subject = "Restablecer contrasena - Senderismo Go";
$message = "Hola " . $usuario['nombre'] . ",\n\n"
    . "Recibimos una solicitud para restablecer tu contrasena.\n"
    . "Usa este enlace durante la proxima hora:\n\n"
    . $link . "\n\n"
    . "Si no solicitaste este cambio, puedes ignorar este mensaje.";
if (!recovery_is_local()) {
    $mailError = null;
    $sent = smtp_mailer_send($usuario['email'], $usuario['nombre'], $subject, $message, $mailError);
    if (!$sent) {
        $_SESSION['recovery_error'] = APP_DEBUG && $mailError
            ? "No se pudo enviar el correo: {$mailError}"
            : "No se pudo enviar el correo en este momento. Intenta nuevamente mas tarde.";
        redirect_recovery($conn);
    }
}

$_SESSION['recovery_success'] = $genericMessage;
if (recovery_is_local()) {
    $_SESSION['recovery_debug_link'] = $link;
}

redirect_recovery($conn);
