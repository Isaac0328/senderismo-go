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
$creditosAplicados = $_POST['credito_aplicado'] ?? [];
$estadosFinancieros = $_POST['estado_financiero'] ?? [];
$generarCreditos = $_POST['generar_credito'] ?? [];
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
$creditosAplicados = is_array($creditosAplicados) ? $creditosAplicados : [];
$estadosFinancieros = is_array($estadosFinancieros) ? $estadosFinancieros : [];
$generarCreditosMap = array_flip(is_array($generarCreditos) ? array_map('intval', $generarCreditos) : []);
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
    "SELECT
        rs.id,
        rs.usuario_id,
        COALESCE(si.monto, 0) + COALESCE(m.total_menores_monto, 0) AS monto_esperado,
        crp.credito_id AS credito_anterior_id,
        COALESCE(crp.credito_aplicado, 0) AS credito_anterior_monto
     FROM registros_senderos rs
     LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
     LEFT JOIN (
        SELECT rsm.registro_id, COALESCE(SUM(si2.monto), 0) AS total_menores_monto
        FROM registro_sendero_menores rsm
        LEFT JOIN sendero_inversiones si2 ON si2.id = rsm.inversion_id
        GROUP BY rsm.registro_id
     ) m ON m.registro_id = rs.id
     LEFT JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
     WHERE rs.sendero_id = " . (int) $senderoId . "
       AND rs.estado = 'registrado'
       AND rs.id IN ($idsSql)"
);
$validos = [];
$registrosInfo = [];
while ($res && $row = mysqli_fetch_assoc($res)) {
    $registroId = (int) $row['id'];
    $validos[] = $registroId;
    $registrosInfo[$registroId] = [
        'usuario_id' => (int) $row['usuario_id'],
        'monto_esperado' => max(0, (float) $row['monto_esperado']),
        'credito_anterior_id' => (int) ($row['credito_anterior_id'] ?? 0),
        'credito_anterior_monto' => max(0, (float) ($row['credito_anterior_monto'] ?? 0)),
    ];
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
            (registro_id, sendero_id, pagado, estado_financiero, monto_esperado, monto_pagado, credito_aplicado, saldo_pendiente, credito_id, fecha_pago, metodo_pago, metodo_pago_id, nota)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            sendero_id = VALUES(sendero_id),
            pagado = VALUES(pagado),
            estado_financiero = VALUES(estado_financiero),
            monto_esperado = VALUES(monto_esperado),
            monto_pagado = VALUES(monto_pagado),
            credito_aplicado = VALUES(credito_aplicado),
            saldo_pendiente = VALUES(saldo_pendiente),
            credito_id = VALUES(credito_id),
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
    $totalCreditoAplicado = 0;
    $totalDeuda = 0;

    foreach ($validos as $registroId) {
        $info = $registrosInfo[$registroId];
        $montoEsperado = $info['monto_esperado'];
        $usuarioId = $info['usuario_id'];

        if ($info['credito_anterior_id'] > 0 && $info['credito_anterior_monto'] > 0) {
            $creditoAnteriorId = $info['credito_anterior_id'];
            $creditoAnteriorMonto = $info['credito_anterior_monto'];
            $stmtRestore = mysqli_prepare(
                $conn,
                "UPDATE usuario_creditos
                 SET saldo_disponible = saldo_disponible + ?,
                     estado = CASE WHEN estado = 'usado' THEN 'activo' ELSE estado END
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmtRestore, 'di', $creditoAnteriorMonto, $creditoAnteriorId);
            mysqli_stmt_execute($stmtRestore);
            mysqli_stmt_close($stmtRestore);
        }

        $pagado = isset($pagadosMap[$registroId]) ? 1 : 0;
        $asistio = isset($asistieronMap[$registroId]) ? 1 : 0;
        $monto = max(0, (float) ($montos[$registroId] ?? 0));
        $creditoAplicado = max(0, (float) ($creditosAplicados[$registroId] ?? 0));
        $fecha = trim((string) ($fechas[$registroId] ?? ''));
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null;
        if ($pagado === 1 && $fecha === null) {
            $fecha = date('Y-m-d');
        }
        if ($pagado === 0) {
            $monto = 0;
            $fecha = null;
        }
        if ($creditoAplicado > 0 && $usuarioId <= 0) {
            throw new RuntimeException('No se pudo identificar el usuario del registro #' . $registroId . ' para aplicar credito.');
        }

        $creditoIdAplicado = null;
        if ($creditoAplicado > 0) {
            $resCreditos = mysqli_query(
                $conn,
                "SELECT id, saldo_disponible
                 FROM usuario_creditos
                 WHERE usuario_id = " . (int) $usuarioId . "
                   AND estado = 'activo'
                   AND saldo_disponible >= " . (float) $creditoAplicado . "
                   AND (registro_origen_id IS NULL OR registro_origen_id <> " . (int) $registroId . ")
                 ORDER BY created_at ASC, id ASC
                 LIMIT 1
                 FOR UPDATE"
            );
            $credito = $resCreditos ? mysqli_fetch_assoc($resCreditos) : null;
            if (!$credito) {
                throw new RuntimeException('El usuario del registro #' . $registroId . ' no tiene credito suficiente.');
            }

            $creditoIdAplicado = (int) $credito['id'];
            $saldoDisponible = (float) $credito['saldo_disponible'];
            $nuevoSaldo = max(0, $saldoDisponible - $creditoAplicado);
            $nuevoEstado = $nuevoSaldo <= 0.00001 ? 'usado' : 'activo';

            $stmtCredito = mysqli_prepare($conn, "UPDATE usuario_creditos SET saldo_disponible = ?, estado = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmtCredito, 'dsi', $nuevoSaldo, $nuevoEstado, $creditoIdAplicado);
            mysqli_stmt_execute($stmtCredito);
            mysqli_stmt_close($stmtCredito);

            $stmtMov = mysqli_prepare(
                $conn,
                "INSERT INTO usuario_credito_movimientos (credito_id, registro_id, sendero_id, tipo, monto, nota, creado_por)
                 VALUES (?, ?, ?, 'aplicacion', ?, ?, ?)"
            );
            $notaCredito = "Credito aplicado al registro #{$registroId}";
            mysqli_stmt_bind_param($stmtMov, 'iiidsi', $creditoIdAplicado, $registroId, $senderoId, $creditoAplicado, $notaCredito, $adminId);
            mysqli_stmt_execute($stmtMov);
            mysqli_stmt_close($stmtMov);
        }

        $totalCubierto = $monto + $creditoAplicado;
        $saldoPendiente = max(0, $montoEsperado - $totalCubierto);
        $estadoManual = ingresos_clean_text((string) ($estadosFinancieros[$registroId] ?? ''), 30);
        $estadosPermitidos = ['pendiente', 'pagado', 'parcial', 'credito_aplicado', 'deuda', 'cortesia', 'no_asistio_sin_pago'];
        if (!in_array($estadoManual, $estadosPermitidos, true)) {
            if ($montoEsperado <= 0) {
                $estadoManual = 'cortesia';
                $saldoPendiente = 0;
            } elseif ($totalCubierto >= $montoEsperado) {
                $estadoManual = $creditoAplicado > 0 && $monto <= 0 ? 'credito_aplicado' : 'pagado';
            } elseif ($totalCubierto > 0) {
                $estadoManual = 'parcial';
            } elseif ($asistio === 1) {
                $estadoManual = 'deuda';
                $saldoPendiente = $montoEsperado;
            } else {
                $estadoManual = 'no_asistio_sin_pago';
                $saldoPendiente = 0;
            }
        }
        if ($estadoManual === 'no_asistio_sin_pago') {
            $pagado = 0;
            $monto = 0;
            $creditoAplicado = 0;
            $saldoPendiente = 0;
            $creditoIdAplicado = null;
        }
        if ($estadoManual === 'cortesia') {
            $pagado = 0;
            $monto = 0;
            $creditoAplicado = 0;
            $saldoPendiente = 0;
            $creditoIdAplicado = null;
        }
        if (in_array($estadoManual, ['pagado', 'credito_aplicado'], true) && $saldoPendiente <= 0.00001) {
            $pagado = 1;
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
        if ($pagado === 0 && $monto <= 0) {
            $metodoPagoId = null;
            $metodo = '';
        }
        $nota = ingresos_clean_text((string) ($notas[$registroId] ?? ''), 255);

        mysqli_stmt_bind_param(
            $stmtPago,
            'iiisddddissis',
            $registroId,
            $senderoId,
            $pagado,
            $estadoManual,
            $montoEsperado,
            $monto,
            $creditoAplicado,
            $saldoPendiente,
            $creditoIdAplicado,
            $fecha,
            $metodo,
            $metodoPagoId,
            $nota
        );
        mysqli_stmt_execute($stmtPago);

        mysqli_stmt_bind_param($stmtAsistencia, 'iiiiii', $asistio, $asistio, $asistio, $adminId, $registroId, $senderoId);
        mysqli_stmt_execute($stmtAsistencia);

        $totalPagados += $pagado;
        $totalAsistieron += $asistio;
        $totalCobrado += $pagado === 1 ? $monto : 0;
        $totalCreditoAplicado += $creditoAplicado;
        $totalDeuda += $saldoPendiente;

        if (isset($generarCreditosMap[$registroId]) && $monto > 0 && $asistio === 0 && $usuarioId > 0) {
            $motivo = "Credito generado por pago sin asistencia en sendero #{$senderoId}";
            $stmtFindCredito = mysqli_prepare($conn, "SELECT id FROM usuario_creditos WHERE registro_origen_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmtFindCredito, 'i', $registroId);
            mysqli_stmt_execute($stmtFindCredito);
            $creditoExistente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFindCredito));
            mysqli_stmt_close($stmtFindCredito);

            if (!empty($creditoExistente['id'])) {
                $creditoId = (int) $creditoExistente['id'];
                $stmtUpdateCredito = mysqli_prepare(
                    $conn,
                    "UPDATE usuario_creditos
                     SET monto = ?, saldo_disponible = ?, estado = 'activo', motivo = ?, creado_por = ?
                     WHERE id = ?"
                );
                mysqli_stmt_bind_param($stmtUpdateCredito, 'ddsii', $monto, $monto, $motivo, $adminId, $creditoId);
                mysqli_stmt_execute($stmtUpdateCredito);
                mysqli_stmt_close($stmtUpdateCredito);
            } else {
                $stmtNewCredito = mysqli_prepare(
                    $conn,
                    "INSERT INTO usuario_creditos (usuario_id, registro_origen_id, sendero_origen_id, monto, saldo_disponible, estado, motivo, creado_por)
                     VALUES (?, ?, ?, ?, ?, 'activo', ?, ?)"
                );
                mysqli_stmt_bind_param($stmtNewCredito, 'iiiddsi', $usuarioId, $registroId, $senderoId, $monto, $monto, $motivo, $adminId);
                mysqli_stmt_execute($stmtNewCredito);
                $creditoId = (int) mysqli_insert_id($conn);
                mysqli_stmt_close($stmtNewCredito);

                $stmtMov = mysqli_prepare(
                    $conn,
                    "INSERT INTO usuario_credito_movimientos (credito_id, registro_id, sendero_id, tipo, monto, nota, creado_por)
                     VALUES (?, ?, ?, 'creacion', ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmtMov, 'iiidsi', $creditoId, $registroId, $senderoId, $monto, $motivo, $adminId);
                mysqli_stmt_execute($stmtMov);
                mysqli_stmt_close($stmtMov);
            }
        }
    }

    mysqli_stmt_close($stmtPago);
    mysqli_stmt_close($stmtAsistencia);
    mysqli_commit($conn);

    $_SESSION['ingresos_sendero_success'] = "Ingresos guardados. Pagados: {$totalPagados}. Asistieron: {$totalAsistieron}. Cobrado: RD$ " . number_format($totalCobrado, 2) . ". Credito aplicado: RD$ " . number_format($totalCreditoAplicado, 2) . ". Por cobrar: RD$ " . number_format($totalDeuda, 2) . ".";
    ingresos_sendero_redirect($conn, $senderoId);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['ingresos_sendero_error'] = APP_DEBUG ? $e->getMessage() : "No se pudieron guardar los ingresos.";
    ingresos_sendero_redirect($conn, $senderoId);
}
