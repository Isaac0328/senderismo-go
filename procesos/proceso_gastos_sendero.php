<?php
// Guarda las lineas de gasto de un sendero. Responde JSON; la pantalla recarga al terminar bien.
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/gastos_sendero.php';

function gastos_responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    gastos_responder(405, ['ok' => false, 'mensaje' => 'Metodo no permitido.']);
}
$tokenSesion = (string) ($_SESSION['csrf_token'] ?? '');
$tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
if ($tokenSesion === '' || $tokenEnviado === '' || !hash_equals($tokenSesion, $tokenEnviado)) {
    gastos_responder(419, ['ok' => false, 'mensaje' => 'La sesion expiro. Recarga la pagina e intenta de nuevo.']);
}
if (!gs_esquema_listo($conn)) {
    gastos_responder(409, ['ok' => false, 'mensaje' => 'Falta aplicar la actualizacion de gastos en Actualizaciones del sistema.']);
}

$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$lineas = json_decode((string) ($_POST['lineas'] ?? ''), true);
$version = (string) ($_POST['version'] ?? '');
if ($senderoId <= 0 || !is_array($lineas)) {
    gastos_responder(400, ['ok' => false, 'mensaje' => 'Solicitud incompleta.']);
}
if (count($lineas) > 500) {
    gastos_responder(400, ['ok' => false, 'mensaje' => 'Demasiadas lineas en un solo guardado.']);
}

$stmt = mysqli_prepare($conn, "SELECT id FROM senderos WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $senderoId);
mysqli_stmt_execute($stmt);
$existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$existe) {
    gastos_responder(404, ['ok' => false, 'mensaje' => 'El sendero no existe.']);
}

try {
    $resultado = gs_guardar($conn, $senderoId, $lineas, $version);
} catch (Throwable $e) {
    error_log('Gastos por sendero: ' . $e->getMessage());
    gastos_responder(500, ['ok' => false, 'mensaje' => APP_DEBUG ? $e->getMessage() : 'No se pudieron guardar los gastos.']);
}
mysqli_close($conn);

if ($resultado['ok']) {
    $_SESSION['gastos_sendero_success'] = $resultado['mensaje'];
}
gastos_responder($resultado['ok'] ? 200 : 422, $resultado);
