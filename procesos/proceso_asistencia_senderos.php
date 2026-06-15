<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

function asistencia_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    $url = BASE_URL . "mantenimientos/mantenimiento_asistencia_senderos.php";
    if ($senderoId > 0) {
        $url .= "?sendero_id=" . $senderoId;
    }
    header("Location: " . $url);
    exit;
}

function asistencia_clean_note(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return substr($value, 0, 255);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_asistencia_senderos.php");
    exit;
}

$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$registroIds = $_POST['registro_ids'] ?? [];
$asistieron = $_POST['asistio'] ?? [];
$notas = $_POST['notas'] ?? [];
$adminId = (int) ($_SESSION['usuario_id'] ?? 0);

if ($senderoId <= 0 || !is_array($registroIds)) {
    $_SESSION['asistencia_error'] = "Selecciona un sendero valido.";
    asistencia_redirect($conn, $senderoId);
}

$registroIds = array_values(array_unique(array_filter(array_map('intval', $registroIds))));
$asistieron = is_array($asistieron) ? array_map('intval', $asistieron) : [];
$asistieronMap = array_flip($asistieron);

if (empty($registroIds)) {
    $_SESSION['asistencia_error'] = "No hay registros activos para actualizar.";
    asistencia_redirect($conn, $senderoId);
}

$idsSql = implode(',', $registroIds);
$res = mysqli_query(
    $conn,
    "SELECT id FROM registros_senderos
     WHERE sendero_id = " . (int) $senderoId . "
       AND estado = 'registrado'
       AND id IN ($idsSql)"
);
$validos = [];
while ($row = mysqli_fetch_assoc($res)) {
    $validos[] = (int) $row['id'];
}

if (empty($validos)) {
    $_SESSION['asistencia_error'] = "No se encontraron registros activos para este sendero.";
    asistencia_redirect($conn, $senderoId);
}

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE registros_senderos
         SET asistio = ?,
             fecha_asistencia = CASE WHEN ? = 1 THEN COALESCE(fecha_asistencia, NOW()) ELSE NULL END,
             asistencia_marcada_por = CASE WHEN ? = 1 THEN ? ELSE NULL END,
             asistencia_notas = ?
         WHERE id = ? AND sendero_id = ? AND estado = 'registrado'"
    );

    foreach ($validos as $registroId) {
        $asistio = isset($asistieronMap[$registroId]) ? 1 : 0;
        $nota = asistencia_clean_note((string) ($notas[$registroId] ?? ''));
        mysqli_stmt_bind_param($stmt, 'iiiisii', $asistio, $asistio, $asistio, $adminId, $nota, $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
    }

    mysqli_stmt_close($stmt);
    mysqli_commit($conn);

    $totalAsistieron = count(array_intersect($validos, $asistieron));
    $_SESSION['asistencia_success'] = "Asistencia actualizada. Marcados como presentes: {$totalAsistieron}.";
    asistencia_redirect($conn, $senderoId);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['asistencia_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo actualizar la asistencia.";
    asistencia_redirect($conn, $senderoId);
}
