<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';

contabilidad_bootstrap($conn);

function ingresos_sendero_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    $url = BASE_URL . "mantenimientos/mantenimiento_ingresos_sendero.php";
    if ($senderoId > 0) {
        $url .= "?sendero_id=" . $senderoId;
    }
    header("Location: " . $url);
    exit;
}

function ingresos_clean_text(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return substr($value, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['ingresos_sendero_error'] = "Metodo no permitido.";
    ingresos_sendero_redirect($conn, 0);
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_ingresos_sendero.php", 'ingresos_sendero_error');

$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$registroIds = $_POST['registro_ids'] ?? [];
$pagados = $_POST['pagado'] ?? [];
$asistieron = $_POST['asistio'] ?? [];
$montos = $_POST['monto_pagado'] ?? [];
$fechas = $_POST['fecha_pago'] ?? [];
$metodos = $_POST['metodo_pago_id'] ?? [];
$notas = $_POST['nota'] ?? [];
$adminId = (int) ($_SESSION['usuario_id'] ?? 0);

if ($senderoId <= 0 || !is_array($registroIds)) {
    $_SESSION['ingresos_sendero_error'] = "Selecciona un sendero valido.";
    ingresos_sendero_redirect($conn, $senderoId);
}

$registroIds = array_values(array_unique(array_filter(array_map('intval', $registroIds))));
$pagadosMap = array_flip(is_array($pagados) ? array_map('intval', $pagados) : []);
$asistieronMap = array_flip(is_array($asistieron) ? array_map('intval', $asistieron) : []);
$montos = is_array($montos) ? $montos : [];
$fechas = is_array($fechas) ? $fechas : [];
$metodos = is_array($metodos) ? $metodos : [];
$notas = is_array($notas) ? $notas : [];

if (empty($registroIds)) {
    $_SESSION['ingresos_sendero_error'] = "No hay inscritos activos para actualizar.";
    ingresos_sendero_redirect($conn, $senderoId);
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
while ($res && $row = mysqli_fetch_assoc($res)) {
    $validos[] = (int) $row['id'];
}

if (empty($validos)) {
    $_SESSION['ingresos_sendero_error'] = "No se encontraron inscritos activos para este sendero.";
    ingresos_sendero_redirect($conn, $senderoId);
}

mysqli_begin_transaction($conn);

try {
    $stmtPago = mysqli_prepare(
        $conn,
        "INSERT INTO contabilidad_registro_pagos
            (registro_id, sendero_id, pagado, monto_pagado, fecha_pago, metodo_pago, metodo_pago_id, nota)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            sendero_id = VALUES(sendero_id),
            pagado = VALUES(pagado),
            monto_pagado = VALUES(monto_pagado),
            fecha_pago = VALUES(fecha_pago),
            metodo_pago = VALUES(metodo_pago),
            metodo_pago_id = VALUES(metodo_pago_id),
            nota = VALUES(nota)"
    );

    $stmtAsistencia = mysqli_prepare(
        $conn,
        "UPDATE registros_senderos
         SET asistio = ?,
             fecha_asistencia = CASE WHEN ? = 1 THEN COALESCE(fecha_asistencia, NOW()) ELSE NULL END,
             asistencia_marcada_por = CASE WHEN ? = 1 THEN ? ELSE NULL END
         WHERE id = ? AND sendero_id = ? AND estado = 'registrado'"
    );

    $totalCobrado = 0;
    $totalPagados = 0;
    $totalAsistieron = 0;

    foreach ($validos as $registroId) {
        $pagado = isset($pagadosMap[$registroId]) ? 1 : 0;
        $asistio = isset($asistieronMap[$registroId]) ? 1 : 0;
        $monto = max(0, (float) ($montos[$registroId] ?? 0));
        $fecha = trim((string) ($fechas[$registroId] ?? ''));
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null;
        if ($pagado === 1 && $fecha === null) {
            $fecha = date('Y-m-d');
        }
        if ($pagado === 0) {
            $monto = 0;
            $fecha = null;
        }
        $metodoPagoId = (int) ($metodos[$registroId] ?? 0);
        $metodoPagoId = $metodoPagoId > 0 ? $metodoPagoId : null;
        $metodo = '';
        if ($metodoPagoId !== null) {
            $stmtMetodo = mysqli_prepare($conn, "SELECT nombre FROM contabilidad_metodo_pago WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmtMetodo, 'i', $metodoPagoId);
            mysqli_stmt_execute($stmtMetodo);
            $rowMetodo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMetodo));
            mysqli_stmt_close($stmtMetodo);
            $metodo = ingresos_clean_text((string) ($rowMetodo['nombre'] ?? ''), 60);
            if ($metodo === '') {
                $metodoPagoId = null;
            }
        }
        if ($pagado === 0) {
            $metodoPagoId = null;
            $metodo = '';
        }
        $nota = ingresos_clean_text((string) ($notas[$registroId] ?? ''), 255);

        mysqli_stmt_bind_param($stmtPago, 'iiidssis', $registroId, $senderoId, $pagado, $monto, $fecha, $metodo, $metodoPagoId, $nota);
        mysqli_stmt_execute($stmtPago);

        mysqli_stmt_bind_param($stmtAsistencia, 'iiiiii', $asistio, $asistio, $asistio, $adminId, $registroId, $senderoId);
        mysqli_stmt_execute($stmtAsistencia);

        $totalPagados += $pagado;
        $totalAsistieron += $asistio;
        $totalCobrado += $pagado === 1 ? $monto : 0;
    }

    mysqli_stmt_close($stmtPago);
    mysqli_stmt_close($stmtAsistencia);
    mysqli_commit($conn);

    $_SESSION['ingresos_sendero_success'] = "Ingresos guardados. Pagados: {$totalPagados}. Asistieron: {$totalAsistieron}. Cobrado: RD$ " . number_format($totalCobrado, 2) . ".";
    ingresos_sendero_redirect($conn, $senderoId);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['ingresos_sendero_error'] = APP_DEBUG ? $e->getMessage() : "No se pudieron guardar los ingresos.";
    ingresos_sendero_redirect($conn, $senderoId);
}
