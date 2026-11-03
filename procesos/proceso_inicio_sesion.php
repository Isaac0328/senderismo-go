<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* ================= CONFIGURACIÓN ================= */
$MAX_ATTEMPTS = 5;        // intentos fallidos permitidos por usuario
$MAX_ATTEMPTS_ORIGEN = 40; // intentos fallidos permitidos desde un mismo origen (todas las cuentas)
$LOCKOUT_TIME = 300;      // 5 minutos

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
csrf_validate_post(BASE_URL . "pantallas/inicio_sesion.php", 'error_message');

$user = trim($_POST['user'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

if ($user === '' || $password === '') {
  unset($_SESSION['login_unknown_identifier'], $_SESSION['login_unknown_attempts'], $_SESSION['show_register_prompt']);
  $_SESSION['error_message'] = "Por favor, completa todos los campos";
  redirect_to_login();
}

$_SESSION['login_attempt_user'] = $user;

/* ================= CONTROL DE INTENTOS ================= */
// El bloqueo se lleva por usuario, no por IP: detras del CDN del hosting todos los
// visitantes llegan con la misma direccion y un solo fallo ajeno bloqueaba a todos.
$now = time();
$since = $now - $LOCKOUT_TIME;
$claveUsuario = clave_intentos_usuario($user);
$claveOrigen = clave_intentos_origen();

$bloqueoUsuario = estado_intentos($conn, $claveUsuario, $since);
if ($bloqueoUsuario['attempts'] >= $MAX_ATTEMPTS) {
  $min = max(1, (int) ceil(($bloqueoUsuario['last_attempt'] + $LOCKOUT_TIME - $now) / 60));
  $_SESSION['error_message'] = "Demasiados intentos fallidos con este usuario. Espera $min minutos o recupera tu contraseña.";
  redirect_to_login();
}

$bloqueoOrigen = estado_intentos($conn, $claveOrigen, $since);
if ($bloqueoOrigen['attempts'] >= $MAX_ATTEMPTS_ORIGEN) {
  $min = max(1, (int) ceil(($bloqueoOrigen['last_attempt'] + $LOCKOUT_TIME - $now) / 60));
  $_SESSION['error_message'] = "Demasiados intentos fallidos desde esta conexión. Espera $min minutos.";
  redirect_to_login();
}

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
  registrar_intento_fallido($conn, [$claveUsuario, $claveOrigen], $now, $since);

  if ($codigo === 1) {
    $identifierKey = function_exists('mb_strtolower')
      ? mb_strtolower($user, 'UTF-8')
      : strtolower($user);
    $previousIdentifier = (string) ($_SESSION['login_unknown_identifier'] ?? '');
    $unknownAttempts = $previousIdentifier === $identifierKey
      ? ((int) ($_SESSION['login_unknown_attempts'] ?? 0) + 1)
      : 1;

    $_SESSION['login_unknown_identifier'] = $identifierKey;
    $_SESSION['login_unknown_attempts'] = $unknownAttempts;

    if ($unknownAttempts >= 2) {
      $_SESSION['show_register_prompt'] = true;
    }
  } else {
    unset($_SESSION['login_unknown_identifier'], $_SESSION['login_unknown_attempts'], $_SESSION['show_register_prompt']);
  }

  $_SESSION['error_message'] = $mensaje !== '' ? $mensaje : "Credenciales inválidas";
  redirect_to_login();
}

unset($_SESSION['login_unknown_identifier'], $_SESSION['login_unknown_attempts'], $_SESSION['show_register_prompt']);

/* ================= PARSEAR RESULTADO ================= */
$parts = explode('|', $mensaje);
if (count($parts) < 5) {
  $_SESSION['error_message'] = "Respuesta inválida del servidor. Intente nuevamente.";
  redirect_to_login();
}

[$hash, $user_id, $nombre, $rol_id, $rol_nombre] = $parts;

$user_id = (int) $user_id;
$rol_id = (int) $rol_id;

if (!password_verify($password, $hash)) {
  registrar_intento_fallido($conn, [$claveUsuario, $claveOrigen], $now, $since);

  $intentos = estado_intentos($conn, $claveUsuario, $since)['attempts'];
  $restantes = max(0, $MAX_ATTEMPTS - $intentos);

  $_SESSION['error_message'] = $restantes > 0
    ? "Contraseña incorrecta. Te quedan $restantes intentos."
    : "Demasiados intentos fallidos. Espera 5 minutos.";

  redirect_to_login();
}

/* ================= LOGIN OK ================= */
limpiar_intentos($conn, [$claveUsuario, $claveOrigen], $now);

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

// Las claves se guardan en la columna ip_address (45 caracteres): prefijo + huella.
function clave_intentos_usuario(string $user): string
{
  $normalizado = function_exists('mb_strtolower') ? mb_strtolower(trim($user), 'UTF-8') : strtolower(trim($user));
  return 'u:' . substr(hash('sha256', $normalizado), 0, 40);
}

// Origen de la peticion: la direccion que ve el servidor mas la que informa el CDN.
// Solo sirve como tope general; el control real es el bloqueo por usuario.
function clave_intentos_origen(): string
{
  $remota = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
  $reenviada = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
  return 'o:' . substr(hash('sha256', $remota . '|' . $reenviada), 0, 40);
}

// Intentos fallidos vigentes: los anteriores a la ventana de bloqueo ya no cuentan.
function estado_intentos($conn, string $clave, int $since): array
{
  $stmt = mysqli_prepare(
    $conn,
    "SELECT attempts, last_attempt FROM intentos_inicio_sesion
     WHERE ip_address = ? AND last_attempt > ?"
  );
  if (!$stmt) {
    return ['attempts' => 0, 'last_attempt' => 0];
  }
  mysqli_stmt_bind_param($stmt, "si", $clave, $since);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $row = $res ? mysqli_fetch_assoc($res) : null;
  mysqli_stmt_close($stmt);

  return [
    'attempts' => (int) ($row['attempts'] ?? 0),
    'last_attempt' => (int) ($row['last_attempt'] ?? 0),
  ];
}

function registrar_intento_fallido($conn, array $claves, int $time, int $since)
{
  // Si el ultimo fallo quedo fuera de la ventana, la cuenta vuelve a empezar en 1.
  $sql = "INSERT INTO intentos_inicio_sesion (ip_address, attempts, last_attempt)
          VALUES (?, 1, ?)
          ON DUPLICATE KEY UPDATE
            attempts = IF(last_attempt > ?, attempts + 1, 1),
            last_attempt = VALUES(last_attempt)";
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) {
    return;
  }
  foreach ($claves as $clave) {
    mysqli_stmt_bind_param($stmt, "sii", $clave, $time, $since);
    mysqli_stmt_execute($stmt);
  }
  mysqli_stmt_close($stmt);
}

function limpiar_intentos($conn, array $claves, int $now)
{
  $stmt = mysqli_prepare($conn, "DELETE FROM intentos_inicio_sesion WHERE ip_address = ?");
  if ($stmt) {
    foreach ($claves as $clave) {
      mysqli_stmt_bind_param($stmt, "s", $clave);
      mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
  }

  // Limpieza de registros viejos (incluye los del control anterior por IP).
  $limite = $now - 86400;
  mysqli_query($conn, "DELETE FROM intentos_inicio_sesion WHERE last_attempt < " . (int) $limite);
}
