<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../bd/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ================= CONFIGURACIÓN ================= */
$MAX_ATTEMPTS = 5;
$LOCKOUT_TIME = 300; // 5 minutos

function redirect_to_login()
{
  header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
  exit;
}

/* ================= VALIDACIONES ================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  $_SESSION['error_message'] = "Método no permitido";
  redirect_to_login();
}

$user = trim($_POST['user'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

if ($user === '' || $password === '') {
  $_SESSION['error_message'] = "Por favor, completa todos los campos";
  redirect_to_login();
}

$_SESSION['login_attempt_user'] = $user;

/* ================= CONTROL DE INTENTOS ================= */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now = time();
$since = $now - $LOCKOUT_TIME;

$stmt = mysqli_prepare(
  $conn,
  "SELECT attempts, last_attempt
   FROM intentos_inicio_sesion
   WHERE ip_address = ? AND last_attempt > ?"
);
mysqli_stmt_bind_param($stmt, "si", $ip, $since);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($res)) {
  if ((int) $row['attempts'] >= $MAX_ATTEMPTS) {
    $min = (int) ceil(((int) $row['last_attempt'] + $LOCKOUT_TIME - $now) / 60);
    $_SESSION['error_message'] = "Demasiados intentos fallidos. Espera $min minutos.";
    mysqli_stmt_close($stmt);
    redirect_to_login();
  }
}
mysqli_stmt_close($stmt);

/* ================= LLAMAR PROCEDURE ================= */
mysqli_query($conn, "SET @p_mensaje = ''");
mysqli_query($conn, "SET @p_codigo = 0");

$stmt = mysqli_prepare($conn, "CALL sp_iniciar_sesion(?, @p_mensaje, @p_codigo)");
mysqli_stmt_bind_param($stmt, "s", $user);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

/* Limpiar resultados del procedure (por si devuelve result sets) */
while (mysqli_more_results($conn)) {
  mysqli_next_result($conn);
  $r = mysqli_store_result($conn);
  if ($r) {
    mysqli_free_result($r);
  }
}

/* Obtener OUT params */
$res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
$data = mysqli_fetch_assoc($res);

$codigo = (int) ($data['codigo'] ?? 0);
$mensaje = (string) ($data['mensaje'] ?? '');

if ($codigo !== 0) {
  registrar_intento_fallido($conn, $ip, $now);
  $_SESSION['error_message'] = $mensaje !== '' ? $mensaje : "Credenciales inválidas";
  redirect_to_login();
}

/* ================= PARSEAR RESULTADO ================= */
$parts = explode('|', $mensaje);
if (count($parts) < 5) {
  registrar_intento_fallido($conn, $ip, $now);
  $_SESSION['error_message'] = "Respuesta inválida del servidor. Intente nuevamente.";
  redirect_to_login();
}

[$hash, $user_id, $nombre, $rol_id, $rol_nombre] = $parts;

$user_id = (int) $user_id;
$rol_id = (int) $rol_id;

if (!password_verify($password, $hash)) {
  registrar_intento_fallido($conn, $ip, $now);

  $intentos = get_attempts_count($conn, $ip, $since);
  $restantes = max(0, $MAX_ATTEMPTS - $intentos);

  $_SESSION['error_message'] = $restantes > 0
    ? "Contraseña incorrecta. Te quedan $restantes intentos."
    : "Demasiados intentos fallidos. Espera 5 minutos.";

  redirect_to_login();
}

/* ================= LOGIN OK ================= */
limpiar_intentos($conn, $ip);

// Seguridad: regenerar sesión al iniciar login
session_regenerate_id(true);

/* Sesión */
$_SESSION['usuario_id'] = $user_id;
$_SESSION['usuario_nombre'] = $nombre;
$_SESSION['usuario_rol_id'] = $rol_id;
$_SESSION['usuario_rol'] = $rol_nombre;
$_SESSION['logged_in'] = true;
$_SESSION['login_time'] = $now;
$_SESSION['last_activity'] = $now;

/* Remember me */
if ($remember) {
  $token = bin2hex(random_bytes(32));
  $exp = time() + (30 * 86400);

  $stmt = mysqli_prepare(
    $conn,
    "INSERT INTO sesiones_usuario (user_id, token, expires_at)
     VALUES (?, ?, ?)"
  );
  mysqli_stmt_bind_param($stmt, "isi", $user_id, $token, $exp);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

  // setcookie moderno (PHP 7.3+)
  setcookie('remember_token', $token, [
    'expires' => $exp,
    'path' => '/',
    'secure' => $isHttps,   // en local HTTP será false; en Hostinger HTTPS será true
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
}

/* Último login */
$stmt = mysqli_prepare(
  $conn,
  "UPDATE usuarios SET last_login = NOW() WHERE id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

unset($_SESSION['login_attempt_user']);

mysqli_close($conn);

$redirectAfterLogin = $_SESSION['redirect_after_login'] ?? '';
unset($_SESSION['redirect_after_login']);

if (is_string($redirectAfterLogin) && substr($redirectAfterLogin, 0, strlen(BASE_URL)) === BASE_URL) {
  header("Location: " . $redirectAfterLogin);
  exit;
}

// Redirect final (ruta sólida)
header("Location: " . BASE_URL . "pantallas/inicio.php");
exit;

/* ================= FUNCIONES ================= */
function registrar_intento_fallido($conn, $ip, $time)
{
  $sql = "INSERT INTO intentos_inicio_sesion (ip_address, attempts, last_attempt)
          VALUES (?, 1, ?)
          ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = ?";
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, "sii", $ip, $time, $time);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
}

function get_attempts_count($conn, $ip, $since)
{
  $stmt = mysqli_prepare(
    $conn,
    "SELECT attempts FROM intentos_inicio_sesion
     WHERE ip_address = ? AND last_attempt > ?"
  );
  mysqli_stmt_bind_param($stmt, "si", $ip, $since);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $attempts = ($row = mysqli_fetch_assoc($res)) ? (int) $row['attempts'] : 0;
  mysqli_stmt_close($stmt);
  return $attempts;
}

function limpiar_intentos($conn, $ip)
{
  $stmt = mysqli_prepare(
    $conn,
    "DELETE FROM intentos_inicio_sesion WHERE ip_address = ?"
  );
  mysqli_stmt_bind_param($stmt, "s", $ip);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
}
