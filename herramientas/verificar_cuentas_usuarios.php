<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/cuentas_usuarios_excel.php';

function cu_check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

try {
    $base = ['usuario_id' => 1, 'registro_id' => 1, 'inversion_base' => '1400.00', 'monto_esperado' => '1400.00',
        'pagado' => 1, 'monto_pagado' => '1000.00', 'descuento_autorizado' => '700.00', 'credito_aplicado' => '0.00',
        'credito_generado' => '200.00', 'estado_financiero' => 'pagado', 'asistio' => 1,
        'fecha_sendero' => '2020-01-01', 'registro_estado' => 'registrado'];
    $r = sgcu_registration($base);
    cu_check($r['values']['esperado'] === 70000 && $r['values']['balance'] === -30000, 'El descuento debe conservar un balance negativo.');
    cu_check($r['values']['retenido'] === 80000 && $r['values']['ingreso'] === 80000, 'El credito generado se resta solo una vez.');
    $r = sgcu_registration(array_replace($base, ['descuento_autorizado' => '0', 'credito_aplicado' => '400', 'credito_generado' => '0']));
    cu_check($r['estado'] === 'pagado_con_credito' && $r['values']['balance'] === 0 && $r['values']['ingreso'] === 140000, 'Pago mixto incorrecto.');
    $r = sgcu_registration(array_replace($base, ['estado_financiero' => 'exento', 'monto_esperado' => '0', 'monto_pagado' => '0', 'credito_generado' => '0', 'descuento_autorizado' => '0']));
    cu_check($r['values']['esperado'] === 0 && $r['values']['balance'] === 0 && $r['values']['inversion'] === 140000, 'Exento no debe generar cobro.');
    $r = sgcu_registration(array_replace($base, ['registro_estado' => 'cancelado', 'descuento_autorizado' => '0']));
    cu_check($r['values']['balance'] === 0 && $r['values']['pagado'] === 100000, 'Cancelacion debe preservar pagos sin generar deuda.');
    $r = sgcu_registration(array_replace($base, ['usuario_id' => 0, 'pagado' => 0, 'monto_pagado' => '0', 'asistio' => 0,
        'credito_generado' => '0', 'descuento_autorizado' => '0', 'monto_esperado' => null]));
    cu_check($r['key'] === 'm1' && $r['estado'] === 'no_asistio_sin_pago' && $r['values']['balance'] === 0, 'Temporal o no asistente incorrecto.');
    try { sgcu_filters(['desde' => '2026-02-30']); throw new RuntimeException('Fecha invalida aceptada.'); } catch (InvalidArgumentException $e) {}
    try { sgcu_filters(['desde' => '2026-10-10', 'hasta' => '2026-01-01']); throw new RuntimeException('Rango invertido aceptado.'); } catch (InvalidArgumentException $e) {}
    cu_check(!sgcu_in_period(null, sgcu_filters(['desde' => '2026-01-01'])), 'Una fecha vacia no pertenece al periodo.');
    cu_check(sgcu_fold('Sánchez') === sgcu_fold('sanchez'), 'La busqueda debe ignorar tildes.');

    $data = sgcu_load($conn, sgcu_filters([]));
    $expected = (int) round((float) $conn->query("SELECT COALESCE(SUM(saldo_disponible), 0) AS total FROM usuario_creditos WHERE estado = 'activo' AND saldo_disponible > 0")->fetch_assoc()['total'] * 100);
    cu_check($data['current']['credito'] === $expected, 'Credito disponible no concilia con el catalogo.');
    cu_check($data['totals']['ingreso'] === $data['totals']['retenido'] + $data['totals']['aplicado'], 'Ingreso no concilia con efectivo mas credito.');
    $keys = array_keys($data['accounts']);
    cu_check(count($keys) === count(array_unique($keys)), 'Hay cuentas duplicadas.');
    $filtered = sgcu_load($conn, sgcu_filters(['desde' => '2026-01-01', 'hasta' => '2026-01-31', 'criterio' => 'pago']));
    cu_check($filtered['current'] === $data['current'], 'El periodo altera los saldos actuales.');
    foreach ($filtered['history'] as $row) { cu_check(substr($row['fecha_pago'], 0, 7) === '2026-01', 'Pago fuera del periodo.'); }
    $book = sgcu_workbook($data, sgcu_filters([]), sg_financial_statuses($conn));
    cu_check($book->getSheetByName('Cuentas')->getHighestRow() === count($data['accounts']) + 4, 'Excel incompleto.');
    cu_check($book->getSheetByName('Senderos y pagos')->getHighestRow() === count($data['history']) + 4, 'Excel de senderos incompleto.');
    $book->disconnectWorksheets();
    $creditKey = null;
    foreach ($data['accounts'] as $a) { if ($a['tuvo_credito']) { $creditKey = $a['key']; break; } }
    if ($creditKey) {
        $f = sgcu_filters(['cuenta' => $creditKey]); $detail = sgcu_load($conn, $f);
        cu_check(count($detail['accounts']) === 1 && $detail['selected']['key'] === $creditKey, 'La cuenta individual mezcla usuarios.');
        foreach ($detail['credits'] as $credit) { cu_check('u' . $credit['usuario_id'] === $creditKey, 'Credito de otro usuario.'); }
        foreach ($detail['movements'] as $movement) { cu_check('u' . $movement['usuario_id'] === $creditKey, 'Movimiento de otro usuario.'); }
        $book = sgcu_workbook($detail, $f, sg_financial_statuses($conn));
        cu_check($book->getSheetByName('Movimientos de credito') !== null && $book->getSheetByName('Aplicaciones vigentes') !== null, 'Faltan hojas de trazabilidad.');
        $file = tmpfile();
        try {
            $path = stream_get_meta_data($file)['uri'];
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            cu_check($loaded->getSheetCount() === 6, 'Archivo XLSX invalido.');
            $loaded->disconnectWorksheets();
        } finally { fclose($file); $book->disconnectWorksheets(); }
    }
    echo "OK: reglas financieras, fechas, cuentas separadas, saldos actuales, trazabilidad y Excel.\n";
} finally { mysqli_close($conn); }
