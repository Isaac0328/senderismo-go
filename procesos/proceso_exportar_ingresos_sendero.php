<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/ingresos_cobro.php';

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
if ($senderoId <= 0) {
    http_response_code(400);
    exit('Sendero invalido.');
}

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoloadPath)) {
    http_response_code(500);
    exit('No esta instalada la dependencia para generar XLSX.');
}
require_once $autoloadPath;

if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    http_response_code(500);
    exit('PhpSpreadsheet no esta disponible.');
}
if (!ic_esquema_listo($conn)) {
    http_response_code(409);
    exit('Falta aplicar la actualizacion de cobros en Actualizaciones del sistema.');
}

$stmtSendero = mysqli_prepare($conn, 'SELECT id, nombre, fecha_sendero FROM senderos WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmtSendero, 'i', $senderoId);
mysqli_stmt_execute($stmtSendero);
$sendero = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSendero));
mysqli_stmt_close($stmtSendero);

if (!$sendero) {
    http_response_code(404);
    exit('Sendero no encontrado.');
}

// Mismo calculo que la pantalla de ingresos.
$registros = ic_cargar($conn, $senderoId);
$totales = ic_totales($registros);
$financialStatuses = sg_financial_statuses($conn);
mysqli_close($conn);

$pesos = static fn(int $centavos): float => round($centavos / 100, 2);
$tipos = ['automatico' => 'Normal', 'cortesia' => 'Cortesia', 'exento' => 'Exento'];
$modos = ['asistio' => 'Si', 'no_asistio' => 'No', 'pendiente' => 'Aun no'];

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Ingresos');
$lastColumn = 'V';
$sheet->setCellValue('A1', 'Ingresos por sendero');
$sheet->mergeCells('A1:' . $lastColumn . '1');
$sheet->setCellValue('A2', 'Sendero');
$sheet->setCellValue('B2', $sendero['nombre']);
$sheet->mergeCells('B2:' . $lastColumn . '2');
$sheet->setCellValue('A3', 'Fecha del sendero');
$sheet->setCellValue('B3', $sendero['fecha_sendero']);
$sheet->setCellValue('D3', 'Generado');
$sheet->setCellValue('E3', date('d/m/Y H:i'));

$headers = [
    'Registro', 'Asistio', 'Participante', 'Usuario', 'Email', 'Telefono', 'Inversion', 'Menores',
    'Tipo', 'Estado', 'Valor', 'Descuento', 'A pagar', 'Credito usado', 'Recibido', 'Devuelto',
    'Paso a credito', 'Retenido', 'Ingreso del sendero', 'Balance', 'Fecha de pago', 'Metodo / nota',
];
$headerRow = 5;
$sheet->fromArray($headers, null, 'A' . $headerRow);
$rowNumber = $headerRow + 1;

foreach ($registros as $row) {
    $c = $row['calculo'];
    $metodoNota = trim($row['metodo_nombre'] . ($row['nota'] !== '' ? ' · ' . $row['nota'] : ''), ' ·');
    $sheet->fromArray([
        (int) $row['registro_id'],
        $modos[$c['modo']] ?? '',
        $row['nombre'],
        $row['usuario'] !== '' ? $row['usuario'] : 'Manual',
        $row['email'],
        $row['telefono'],
        $row['inversion_nombre'],
        (int) $row['total_menores'],
        $tipos[$c['tipo']] ?? $c['tipo'],
        $financialStatuses[$c['estado']] ?? $c['estado'],
        $pesos($c['valor']),
        $pesos($c['descuento']),
        $pesos($c['debe_pagar']),
        $pesos($c['credito']),
        $pesos($c['recibido']),
        $pesos($c['devuelto']),
        $pesos($c['a_credito']),
        $pesos($c['retenido']),
        $pesos($c['ingreso']),
        $pesos($c['saldo']),
        $c['fecha'],
        $metodoNota,
    ], null, 'A' . $rowNumber);
    $rowNumber++;
}

$lastRow = max($headerRow, $rowNumber - 1);
$summary = $spreadsheet->createSheet();
$summary->setTitle('Resumen');
$filasResumen = [
    ['Esperado', $pesos($totales['esperado'])],
    ['Recibido', $pesos($totales['recibido'])],
    ['Devuelto', $pesos($totales['devuelto'])],
    ['Credito usado', $pesos($totales['credito_aplicado'])],
    ['Credito generado', $pesos($totales['credito_generado'])],
    ['Descuentos', $pesos($totales['descuento'])],
    ['Efectivo retenido', $pesos($totales['retenido'])],
    ['Ingreso del sendero', $pesos($totales['ingreso'])],
    ['Por cobrar (asistieron)', $pesos($totales['por_cobrar'])],
    ['Pendiente (aun no ocurre)', $pesos($totales['pendiente'])],
];
$summary->fromArray(array_merge([
    ['Resumen de ingresos'],
    ['Sendero', $sendero['nombre']],
    ['Fecha', $sendero['fecha_sendero']],
    [],
    ['Concepto', 'Monto'],
], $filasResumen), null, 'A1');
$ultimaResumen = 5 + count($filasResumen);

$titleStyle = [
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '111111']],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
];
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F6B3B']],
];
$borderStyle = ['borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2DC']]]];
$sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($titleStyle);
$sheet->getStyle('A5:' . $lastColumn . '5')->applyFromArray($headerStyle);
$sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray($borderStyle);
$sheet->getStyle('A5:' . $lastColumn . $lastRow)->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
$sheet->getStyle('K6:T' . $lastRow)->getNumberFormat()->setFormatCode('"RD$" #,##0.00');
$sheet->freezePane('A6');
$sheet->setAutoFilter('A5:' . $lastColumn . $lastRow);
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);

$widths = ['A' => 10, 'B' => 9, 'C' => 28, 'D' => 18, 'E' => 26, 'F' => 15, 'G' => 18, 'H' => 9, 'I' => 11, 'J' => 20,
    'K' => 14, 'L' => 14, 'M' => 14, 'N' => 14, 'O' => 14, 'P' => 14, 'Q' => 15, 'R' => 14, 'S' => 17, 'T' => 14, 'U' => 13, 'V' => 30];
foreach ($widths as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}
$summary->getStyle('A1:B1')->applyFromArray($titleStyle);
$summary->mergeCells('A1:B1');
$summary->getStyle('A5:B5')->applyFromArray($headerStyle);
$summary->getStyle('A1:B' . $ultimaResumen)->applyFromArray($borderStyle);
$summary->getStyle('B6:B' . $ultimaResumen)->getNumberFormat()->setFormatCode('"RD$" #,##0.00');
$summary->getColumnDimension('A')->setWidth(28);
$summary->getColumnDimension('B')->setWidth(28);

$filename = 'ingresos_sendero_' . $senderoId . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
