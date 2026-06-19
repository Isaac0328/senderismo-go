<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_reset(string $token = ''): void
{
    $url = BASE_URL . "pantallas/restablecer_password.php";
    if ($token !== '') {
        $url .= "?token=" . urlencode($token);
    }
    header("Location: " . $url);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['reset_error'] = "Metodo no permitido.";
    redirect_reset();
}
csrf_validate_post(BASE_URL . "pantallas/restablecer_password.php", 'reset_error');

$token = trim((string) ($_POST['token'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $_SESSION['reset_error'] = "El enlace no es valido.";
    redirect_reset();
}

if (strlen($password) < 6 || strlen($password) > 120) {
    $_SESSION['reset_error'] = "La contrasena debe tener entre 6 y 120 caracteres.";
    redirect_reset($token);
}

if ($password !== $confirmPassword) {
    $_SESSION['reset_error'] = "Las contrasenas no coinciden.";
    redirect_reset($token);
}

$tokenHash = hash('sha256', $token);

$stmt = mysqli_prepare(
    $conn,
    "SELECT pr.id, pr.usuario_id
     FROM password_resets pr
     INNER JOIN usuarios u ON u.id = pr.usuario_id
     WHERE pr.token_hash = ?
       AND pr.used_at IS NULL
       AND pr.expires_at >= NOW()
       AND u.estado = 1
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "s", $tokenHash);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$reset = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$reset) {
    $_SESSION['reset_error'] = "Este enlace no es valido, ya fue usado o expiro.";
    redirect_reset();
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    $_SESSION['reset_error'] = "No se pudo proteger la nueva contrasena.";
    redirect_reset($token);
}

$resetId = (int) $reset['id'];
$usuarioId = (int) $reset['usuario_id'];

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare($conn, "UPDATE usuarios SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $passwordHash, $usuarioId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $resetId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM sesiones_usuario WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $usuarioId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['reset_error'] = "No se pudo cambiar la contrasena. Intenta nuevamente.";
    redirect_reset($token);
}

mysqli_close($conn);

$_SESSION['success_message'] = "Contrasena actualizada correctamente. Ya puedes iniciar sesion.";
header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
exit;
