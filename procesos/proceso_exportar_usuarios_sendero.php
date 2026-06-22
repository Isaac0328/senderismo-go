<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

function export_hrs($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function export_fecha(?string $fecha, bool $conHora = false): string
{
    if (!$fecha) {
        return 'Sin fecha';
    }

    $timestamp = strtotime($fecha);
    if (!$timestamp) {
        return 'Sin fecha';
    }

    return date($conHora ? 'd/m/Y h:i A' : 'd/m/Y', $timestamp);
}

function export_dinero($monto): string
{
    if ($monto === null || $monto === '') {
        return 'Sin monto';
    }

    return 'RD$ ' . number_format((float) $monto, 2);
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$formato = preg_replace('/[^a-z]/', '', strtolower(trim((string) ($_GET['formato'] ?? 'excel'))));

if ($senderoId <= 0 || !in_array($formato, ['excel', 'pdf'], true)) {
    http_response_code(400);
    die('Solicitud invalida.');
}

$stmtSendero = mysqli_prepare($conn, "
    SELECT id, nombre, fecha_sendero, estado
    FROM senderos
    WHERE id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmtSendero, 'i', $senderoId);
mysqli_stmt_execute($stmtSendero);
$sendero = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSendero));
mysqli_stmt_close($stmtSendero);

if (!$sendero) {
    http_response_code(404);
    die('Sendero no encontrado.');
}

$sql = "
    SELECT
        rs.id AS registro_id,
        rs.estado AS estado_registro,
        rs.fecha_registro,
        si.nombre AS inversion_nombre,
        si.monto AS inversion_monto,
        COALESCE(u.id, 0) AS usuario_id,
        COALESCE(u.nombre, rs.manual_nombre, 'Asistente') AS nombre,
        COALESCE(u.apellido, rs.manual_apellido, 'manual') AS apellido,
        COALESCE(u.user, CONCAT('manual-', rs.id)) AS user,
        COALESCE(u.email, rs.manual_email, '') AS email,
        COALESCE(u.estado, 1) AS usuario_estado,
        COALESCE(du.telefono, rs.manual_telefono, '') AS telefono,
        COALESCE(du.rango_edad, '') AS rango_edad,
        COALESCE(du.identificacion, '') AS identificacion,
        COALESCE(du.es_alergico, 0) AS es_alergico,
        COALESCE(du.alergias_detalle, '') AS alergias_detalle,
        COALESCE(du.grupo_sanguineo, '') AS grupo_sanguineo,
        COALESCE(du.enfermedad, '') AS enfermedad,
        COALESCE(du.seguro_medico, '') AS seguro_medico,
        COALESCE(du.experiencia_senderismo, '') AS experiencia_senderismo,
        COALESCE(du.via_entero, '') AS via_entero,
        COALESCE(du.referido_nombre, '') AS referido_nombre,
        COALESCE(du.emergencia_nombre, '') AS emergencia_nombre,
        COALESCE(du.emergencia_parentesco, '') AS emergencia_parentesco,
        COALESCE(du.emergencia_telefono, '') AS emergencia_telefono
    FROM registros_senderos rs
    LEFT JOIN usuarios u ON u.id = rs.usuario_id
    LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
    LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
    WHERE rs.sendero_id = ? AND rs.estado = 'registrado'
    ORDER BY rs.fecha_registro DESC, COALESCE(u.nombre, rs.manual_nombre) ASC, COALESCE(u.apellido, rs.manual_apellido) ASC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $senderoId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$participantes = [];
while ($row = mysqli_fetch_assoc($res)) {
    $participantes[] = $row;
}
mysqli_stmt_close($stmt);

$menoresPorRegistro = [];
$menoresExport = [];
$registroIds = array_map(static function ($row) {
    return (int) $row['registro_id'];
}, $participantes);
if (!empty($registroIds)) {
    $idsSql = implode(',', array_unique(array_filter($registroIds)));
    if ($idsSql !== '') {
        $resMenores = mysqli_query($conn, "
            SELECT
                rm.*,
                si.nombre AS inversion_nombre,
                si.monto AS inversion_monto
            FROM registro_sendero_menores rm
            LEFT JOIN sendero_inversiones si ON si.id = rm.inversion_id
            WHERE rm.registro_id IN ($idsSql)
            ORDER BY rm.registro_id ASC, rm.id ASC
        ");

        if ($resMenores) {
            while ($menor = mysqli_fetch_assoc($resMenores)) {
                $registroId = (int) $menor['registro_id'];
                $menoresPorRegistro[$registroId][] = $menor;
                $menoresExport[] = $menor;
            }
        }
    }
}
mysqli_close($conn);

$filenameBase = 'usuarios_sendero_' . $senderoId . '_' . date('Ymd_His');
$columns = [
    'Registro',
    'Nombre',
    'Usuario',
    'Email',
    'Telefono',
    'Edad',
    'Identificacion',
    'Grupo sanguineo',
    'Alergias',
    'Enfermedad',
    'Seguro medico',
    'Inversion',
    'Monto',
    'Experiencia',
    'Via',
    'Referido',
    'Contacto emergencia',
    'Parentesco',
    'Telefono emergencia',
    'Fecha registro',
];

if ($formato === 'excel') {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        http_response_code(500);
        die('No esta instalada la dependencia para generar XLSX.');
    }

    require_once $autoloadPath;

    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        http_response_code(500);
        die('PhpSpreadsheet no esta disponible.');
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Participantes');

    $sheet->setCellValue('A1', 'Usuarios por Sendero');
    $sheet->mergeCells('A1:T1');
    $sheet->setCellValue('A2', 'Sendero');
    $sheet->setCellValue('B2', $sendero['nombre']);
    $sheet->mergeCells('B2:T2');
    $sheet->setCellValue('A3', 'Fecha del sendero');
    $sheet->setCellValue('B3', export_fecha($sendero['fecha_sendero']));
    $sheet->setCellValue('D3', 'Generado');
    $sheet->setCellValue('E3', date('d/m/Y h:i A'));
    $sheet->setCellValue('G3', 'Registros');
    $sheet->setCellValue('H3', count($participantes));

    $headerRow = 5;
    $sheet->fromArray($columns, null, 'A' . $headerRow);

    $rowNumber = $headerRow + 1;
    foreach ($participantes as $row) {
        $sheet->fromArray([
            (int) $row['registro_id'],
            trim($row['nombre'] . ' ' . $row['apellido']),
            $row['user'],
            $row['email'],
            $row['telefono'],
            $row['rango_edad'],
            $row['identificacion'],
            $row['grupo_sanguineo'],
            (int) $row['es_alergico'] === 1 ? ($row['alergias_detalle'] ?: 'Si, no especificado') : 'No',
            $row['enfermedad'],
            $row['seguro_medico'],
            $row['inversion_nombre'] ?: 'Sin inversion',
            $row['inversion_monto'] !== null && $row['inversion_monto'] !== '' ? (float) $row['inversion_monto'] : null,
            $row['experiencia_senderismo'],
            $row['via_entero'],
            $row['referido_nombre'],
            $row['emergencia_nombre'],
            $row['emergencia_parentesco'],
            $row['emergencia_telefono'],
            export_fecha($row['fecha_registro'], true),
        ], null, 'A' . $rowNumber);
        $rowNumber++;
    }

    $lastRow = max($rowNumber - 1, $headerRow);
    $lastColumn = 'T';

    $sheet->getStyle('A1:T1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '111111']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('A2:T3')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4F4']],
    ]);
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B90000']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD'],
            ],
        ],
    ]);
    $sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastColumn . $lastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    $sheet->getStyle('A' . ($headerRow + 1) . ':' . $lastColumn . $lastRow)->getAlignment()->setWrapText(true);
    $sheet->getStyle('M' . ($headerRow + 1) . ':M' . $lastRow)->getNumberFormat()->setFormatCode('"RD$" #,##0.00');

    $widths = [
        'A' => 10, 'B' => 26, 'C' => 16, 'D' => 28, 'E' => 16,
        'F' => 14, 'G' => 18, 'H' => 16, 'I' => 28, 'J' => 28,
        'K' => 24, 'L' => 24, 'M' => 14, 'N' => 20, 'O' => 20,
        'P' => 22, 'Q' => 24, 'R' => 16, 'S' => 18, 'T' => 20,
    ];
    foreach ($widths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    $sheet->freezePane('A6');
    $sheet->setAutoFilter('A' . $headerRow . ':' . $lastColumn . $lastRow);
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);

    $minorColumns = [
        'Registro adulto',
        'Adulto responsable',
        'Nombre menor',
        'Telefono',
        'Rango edad',
        'Grupo sanguineo',
        'Alergias',
        'Enfermedad',
        'Seguro medico',
        'Experiencia',
        'Inversion',
        'Monto',
        'Contacto emergencia',
        'Parentesco',
        'Telefono emergencia',
    ];

    $minorSheet = $spreadsheet->createSheet();
    $minorSheet->setTitle('Menores');
    $minorSheet->setCellValue('A1', 'Menores por Sendero');
    $minorSheet->mergeCells('A1:O1');
    $minorSheet->setCellValue('A2', 'Sendero');
    $minorSheet->setCellValue('B2', $sendero['nombre']);
    $minorSheet->mergeCells('B2:O2');
    $minorSheet->setCellValue('A3', 'Total menores');
    $minorSheet->setCellValue('B3', count($menoresExport));

    $minorHeaderRow = 5;
    $minorSheet->fromArray($minorColumns, null, 'A' . $minorHeaderRow);
    $adultosPorRegistro = [];
    foreach ($participantes as $row) {
        $adultosPorRegistro[(int) $row['registro_id']] = trim($row['nombre'] . ' ' . $row['apellido']);
    }

    $minorRowNumber = $minorHeaderRow + 1;
    foreach ($menoresExport as $menor) {
        $minorSheet->fromArray([
            (int) $menor['registro_id'],
            $adultosPorRegistro[(int) $menor['registro_id']] ?? 'No identificado',
            trim($menor['nombre'] . ' ' . $menor['apellido']),
            $menor['telefono'],
            $menor['rango_edad'],
            $menor['grupo_sanguineo'],
            (int) $menor['es_alergico'] === 1 ? ($menor['alergias_detalle'] ?: 'Si, no especificado') : 'No',
            $menor['enfermedad'],
            $menor['seguro_medico'],
            $menor['experiencia_senderismo'],
            $menor['inversion_nombre'] ?: 'Sin inversion',
            $menor['inversion_monto'] !== null && $menor['inversion_monto'] !== '' ? (float) $menor['inversion_monto'] : null,
            $menor['emergencia_nombre'],
            $menor['emergencia_parentesco'],
            $menor['emergencia_telefono'],
        ], null, 'A' . $minorRowNumber);
        $minorRowNumber++;
    }

    $minorLastRow = max($minorRowNumber - 1, $minorHeaderRow);
    $minorLastColumn = 'O';
    $minorSheet->getStyle('A1:O1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '111111']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $minorSheet->getStyle('A2:O3')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4F4']],
    ]);
    $minorSheet->getStyle('A' . $minorHeaderRow . ':' . $minorLastColumn . $minorHeaderRow)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '255F38']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $minorSheet->getStyle('A1:' . $minorLastColumn . $minorLastRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'DDDDDD'],
            ],
        ],
    ]);
    $minorSheet->getStyle('A' . ($minorHeaderRow + 1) . ':' . $minorLastColumn . $minorLastRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    $minorSheet->getStyle('A' . ($minorHeaderRow + 1) . ':' . $minorLastColumn . $minorLastRow)->getAlignment()->setWrapText(true);
    $minorSheet->getStyle('L' . ($minorHeaderRow + 1) . ':L' . $minorLastRow)->getNumberFormat()->setFormatCode('"RD$" #,##0.00');

    $minorWidths = [
        'A' => 14, 'B' => 26, 'C' => 24, 'D' => 16, 'E' => 14,
        'F' => 16, 'G' => 28, 'H' => 28, 'I' => 24, 'J' => 20,
        'K' => 24, 'L' => 14, 'M' => 24, 'N' => 16, 'O' => 18,
    ];
    foreach ($minorWidths as $column => $width) {
        $minorSheet->getColumnDimension($column)->setWidth($width);
    }
    $minorSheet->freezePane('A6');
    $minorSheet->setAutoFilter('A' . $minorHeaderRow . ':' . $minorLastColumn . $minorLastRow);
    $minorSheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $minorSheet->getPageSetup()->setFitToWidth(1);
    $minorSheet->getPageSetup()->setFitToHeight(0);

    $spreadsheet->setActiveSheetIndex(0);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if ($formato === 'pdf') {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;

        if (class_exists('\\Dompdf\\Dompdf')) {
            $logoPath = realpath(__DIR__ . '/../imagenes/logo/logo_sg.png');
            $logoSrc = $logoPath ? 'file:///' . str_replace('\\', '/', $logoPath) : '';

            ob_start();
            ?>
            <!doctype html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <style>
                    @page {
                        margin: 24px;
                    }

                    * {
                        box-sizing: border-box;
                    }

                    body {
                        margin: 0;
                        color: #141414;
                        font-family: DejaVu Sans, Arial, sans-serif;
                        font-size: 11px;
                        line-height: 1.35;
                    }

                    .hero {
                        width: 100%;
                        padding: 22px 24px;
                        border-radius: 8px;
                        background: #111;
                        color: #fff;
                    }

                    .hero-table,
                    .meta-table,
                    .summary-table,
                    .participant-table {
                        width: 100%;
                        border-collapse: collapse;
                    }

                    .hero-logo {
                        width: 95px;
                        text-align: right;
                    }

                    .hero-logo img {
                        width: 82px;
                    }

                    .kicker {
                        display: inline-block;
                        padding: 5px 8px;
                        border-radius: 999px;
                        background: #e10600;
                        color: #fff;
                        font-size: 9px;
                        font-weight: bold;
                        text-transform: uppercase;
                    }

                    h1 {
                        margin: 10px 0 0;
                        font-size: 25px;
                        line-height: 1;
                        text-transform: uppercase;
                    }

                    .generated {
                        margin-top: 8px;
                        color: #e8e8e8;
                        font-size: 10px;
                    }

                    .meta-box {
                        margin-top: 14px;
                        padding: 13px 15px;
                        border: 1px solid #e3e3e3;
                        border-radius: 8px;
                        background: #fafafa;
                    }

                    .meta-label {
                        width: 120px;
                        color: #777;
                        font-size: 9px;
                        font-weight: bold;
                        text-transform: uppercase;
                    }

                    .meta-value {
                        color: #111;
                        font-weight: bold;
                    }

                    .summary-table {
                        margin-top: 12px;
                    }

                    .summary-table td {
                        width: 25%;
                        padding: 10px;
                        border: 1px solid #e2e2e2;
                        background: #fff;
                        vertical-align: top;
                    }

                    .summary-label {
                        display: block;
                        color: #777;
                        font-size: 9px;
                        font-weight: bold;
                        text-transform: uppercase;
                    }

                    .summary-value {
                        display: block;
                        margin-top: 5px;
                        color: #111;
                        font-size: 17px;
                        font-weight: bold;
                    }

                    .section-title {
                        margin: 18px 0 8px;
                        padding-bottom: 7px;
                        border-bottom: 2px solid #111;
                        font-size: 14px;
                        font-weight: bold;
                        text-transform: uppercase;
                    }

                    .participant {
                        margin-bottom: 10px;
                        border: 1px solid #dedede;
                        border-radius: 8px;
                        page-break-inside: avoid;
                        overflow: hidden;
                    }

                    .participant-head {
                        padding: 10px 12px;
                        background: #111;
                        color: #fff;
                    }

                    .participant-name {
                        font-size: 13px;
                        font-weight: bold;
                    }

                    .participant-sub {
                        margin-top: 3px;
                        color: #e5e5e5;
                        font-size: 9px;
                    }

                    .participant-table td {
                        width: 33.33%;
                        padding: 10px 12px;
                        border-top: 1px solid #ededed;
                        border-right: 1px solid #ededed;
                        vertical-align: top;
                    }

                    .participant-table td:last-child {
                        border-right: 0;
                    }

                    .minor-box {
                        padding: 9px 12px 10px;
                        border-top: 1px solid #ededed;
                        background: #fafafa;
                    }

                    .minor-line {
                        margin-top: 5px;
                        padding: 6px 8px;
                        border-left: 3px solid #b90000;
                        background: #fff;
                    }

                    .block-title {
                        display: block;
                        margin-bottom: 5px;
                        color: #b90000;
                        font-size: 9px;
                        font-weight: bold;
                        text-transform: uppercase;
                    }

                    .muted {
                        color: #666;
                    }

                    .empty {
                        padding: 24px;
                        border: 1px dashed #bbb;
                        border-radius: 8px;
                        color: #666;
                        text-align: center;
                    }
                </style>
            </head>
            <body>
                <header class="hero">
                    <table class="hero-table">
                        <tr>
                            <td>
                                <span class="kicker">Reporte administrativo</span>
                                <h1>Usuarios por Sendero</h1>
                                <div class="generated">Generado: <?= export_hrs(date('d/m/Y h:i A')) ?></div>
                            </td>
                            <td class="hero-logo">
                                <?php if ($logoSrc): ?>
                                    <img src="<?= export_hrs($logoSrc) ?>" alt="Senderismo Go">
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </header>

                <section class="meta-box">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Sendero</td>
                            <td class="meta-value"><?= export_hrs($sendero['nombre']) ?></td>
                        </tr>
                        <tr>
                            <td class="meta-label">Fecha</td>
                            <td><?= export_hrs(export_fecha($sendero['fecha_sendero'])) ?></td>
                        </tr>
                        <tr>
                            <td class="meta-label">Estado</td>
                            <td><?= export_hrs(ucfirst((string) $sendero['estado'])) ?></td>
                        </tr>
                    </table>
                </section>

                <table class="summary-table">
                    <tr>
                        <td>
                            <span class="summary-label">Adultos</span>
                            <span class="summary-value"><?= count($participantes) ?></span>
                        </td>
                        <td>
                            <span class="summary-label">Menores</span>
                            <span class="summary-value"><?= count($menoresExport) ?></span>
                        </td>
                        <td>
                            <span class="summary-label">Total</span>
                            <span class="summary-value"><?= count($participantes) + count($menoresExport) ?></span>
                        </td>
                        <td>
                            <span class="summary-label">Formato</span>
                            <span class="summary-value">PDF</span>
                        </td>
                    </tr>
                </table>

                <div class="section-title">Listado de participantes</div>

                <?php if (empty($participantes)): ?>
                    <div class="empty">Este sendero todavia no tiene participantes registrados.</div>
                <?php else: ?>
                    <?php foreach ($participantes as $row): ?>
                        <?php $menores = $menoresPorRegistro[(int) $row['registro_id']] ?? []; ?>
                        <article class="participant">
                            <div class="participant-head">
                                <div class="participant-name"><?= export_hrs(trim($row['nombre'] . ' ' . $row['apellido'])) ?></div>
                                <div class="participant-sub">
                                    @<?= export_hrs($row['user']) ?> | Registro #<?= (int) $row['registro_id'] ?> | <?= export_hrs(export_fecha($row['fecha_registro'], true)) ?>
                                </div>
                            </div>
                            <table class="participant-table">
                                <tr>
                                    <td>
                                        <span class="block-title">Contacto</span>
                                        <strong><?= export_hrs($row['telefono'] ?: 'Sin telefono') ?></strong><br>
                                        <span class="muted"><?= export_hrs($row['email'] ?: 'Sin correo') ?></span><br>
                                        Edad: <?= export_hrs($row['rango_edad'] ?: 'N/A') ?><br>
                                        ID: <?= export_hrs($row['identificacion'] ?: 'N/A') ?>
                                    </td>
                                    <td>
                                        <span class="block-title">Salud</span>
                                        Grupo: <strong><?= export_hrs($row['grupo_sanguineo'] ?: 'N/A') ?></strong><br>
                                        Alergias: <?= (int) $row['es_alergico'] === 1 ? export_hrs($row['alergias_detalle'] ?: 'Si, no especificado') : 'No' ?><br>
                                        Enfermedad: <?= export_hrs($row['enfermedad'] ?: 'No indicada') ?><br>
                                        Seguro: <?= export_hrs($row['seguro_medico'] ?: 'No indicado') ?>
                                    </td>
                                    <td>
                                        <span class="block-title">Inversion y emergencia</span>
                                        <?= export_hrs($row['inversion_nombre'] ?: 'Sin inversion') ?><br>
                                        <strong><?= export_hrs(export_dinero($row['inversion_monto'])) ?></strong><br>
                                        Emergencia: <?= export_hrs($row['emergencia_nombre'] ?: 'Sin contacto') ?><br>
                                        <?= export_hrs($row['emergencia_parentesco'] ?: 'N/A') ?> | <?= export_hrs($row['emergencia_telefono'] ?: 'Sin telefono') ?>
                                    </td>
                                </tr>
                            </table>
                            <?php if (!empty($menores)): ?>
                                <div class="minor-box">
                                    <span class="block-title">Menores acompanantes</span>
                                    <?php foreach ($menores as $menor): ?>
                                        <div class="minor-line">
                                            <strong><?= export_hrs(trim($menor['nombre'] . ' ' . $menor['apellido'])) ?></strong>
                                            | <?= export_hrs($menor['rango_edad']) ?>
                                            | <?= export_hrs($menor['grupo_sanguineo']) ?>
                                            | <?= export_hrs($menor['inversion_nombre'] ?: 'Sin inversion') ?>
                                            | Alergias: <?= (int) $menor['es_alergico'] === 1 ? export_hrs($menor['alergias_detalle'] ?: 'Si') : 'No' ?>
                                            | Emergencia: <?= export_hrs($menor['emergencia_nombre']) ?> / <?= export_hrs($menor['emergencia_telefono']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </body>
            </html>
            <?php
            $html = ob_get_clean();

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->setChroot(realpath(__DIR__ . '/..'));

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('letter', 'landscape');
            $dompdf->render();
            $dompdf->stream($filenameBase . '.pdf', ['Attachment' => true]);
            exit;
        }
    }

    $pdfEscape = static function ($text): string {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    };

    $wrapText = static function ($text, int $maxChars): array {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') {
            return [''];
        }
        return explode("\n", wordwrap($text, $maxChars, "\n", true));
    };

    $pages = [];
    $content = '';
    $pageW = 842;
    $pageH = 595;
    $margin = 34;
    $y = 0;
    $pageNo = 0;

    $cmd = static function ($line) use (&$content): void {
        $content .= $line . "\n";
    };

    $setFill = static function ($r, $g, $b) use ($cmd): void {
        $cmd(sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255));
    };

    $setStroke = static function ($r, $g, $b) use ($cmd): void {
        $cmd(sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255));
    };

    $rect = static function ($x, $yPos, $w, $h, $mode = 'f') use ($cmd): void {
        $cmd(sprintf('%.2F %.2F %.2F %.2F re %s', $x, $yPos, $w, $h, $mode));
    };

    $text = static function ($x, $yPos, $size, $value, $font = 'F1') use ($cmd, $pdfEscape): void {
        $cmd(sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET', $font, $size, $x, $yPos, $pdfEscape($value)));
    };

    $savePage = static function () use (&$pages, &$content): void {
        if ($content !== '') {
            $pages[] = $content;
            $content = '';
        }
    };

    $startPage = static function () use (
        &$content,
        &$y,
        &$pageNo,
        $pageW,
        $pageH,
        $margin,
        $cmd,
        $setFill,
        $setStroke,
        $rect,
        $text,
        $sendero,
        $participantes,
        $menoresExport
    ): void {
        $content = '';
        $pageNo++;
        $y = $pageH - 36;

        $setFill(17, 17, 17);
        $rect(0, $pageH - 104, $pageW, 104, 'f');
        $setFill(225, 6, 0);
        $rect(0, $pageH - 104, 10, 104, 'f');
        $setFill(255, 255, 255);
        $text($margin, $pageH - 48, 9, 'REPORTE ADMINISTRATIVO', 'F2');
        $text($margin, $pageH - 72, 22, 'USUARIOS POR SENDERO', 'F2');
        $text($margin, $pageH - 92, 10, 'Senderismo Go | Generado: ' . date('d/m/Y h:i A'), 'F1');

        $setFill(245, 245, 245);
        $rect($pageW - 178, $pageH - 82, 136, 38, 'f');
        $setFill(185, 0, 0);
        $text($pageW - 164, $pageH - 60, 18, (string) (count($participantes) + count($menoresExport)), 'F2');
        $setFill(70, 70, 70);
        $text($pageW - 112, $pageH - 59, 9, 'PERSONAS', 'F2');

        $y = $pageH - 132;
        $setFill(35, 35, 35);
        $text($margin, $y, 11, 'Sendero: ' . $sendero['nombre'], 'F2');
        $y -= 16;
        $setFill(80, 80, 80);
        $text($margin, $y, 9, 'Fecha: ' . export_fecha($sendero['fecha_sendero']) . '   Estado: ' . ucfirst((string) $sendero['estado']), 'F1');
        $y -= 22;
    };

    $drawCard = static function ($row) use (
        &$y,
        $margin,
        $pageW,
        $setFill,
        $setStroke,
        $rect,
        $text,
        $wrapText,
        $menoresPorRegistro
    ): void {
        $menores = $menoresPorRegistro[(int) $row['registro_id']] ?? [];
        $x = $margin;
        $w = $pageW - ($margin * 2);
        $h = empty($menores) ? 96 : 122 + (count($menores) * 12);
        $cardY = $y - $h;

        $setFill(255, 255, 255);
        $setStroke(220, 220, 220);
        $rect($x, $cardY, $w, $h, 'B');
        $setFill(225, 6, 0);
        $rect($x, $cardY + $h - 6, $w, 6, 'f');

        $nombre = trim($row['nombre'] . ' ' . $row['apellido']);
        $setFill(17, 17, 17);
        $text($x + 12, $cardY + $h - 24, 12, $nombre, 'F2');
        $setFill(80, 80, 80);
        $text($x + 12, $cardY + $h - 40, 8, '@' . $row['user'] . ' | Registro #' . (int) $row['registro_id'] . ' | ' . export_fecha($row['fecha_registro'], true), 'F1');

        $col1 = $x + 12;
        $col2 = $x + 260;
        $col3 = $x + 520;
        $base = $cardY + 42;

        $setFill(185, 0, 0);
        $text($col1, $base + 22, 8, 'CONTACTO', 'F2');
        $setFill(45, 45, 45);
        $text($col1, $base + 9, 8, $row['telefono'] ?: 'Sin telefono', 'F1');
        $text($col1, $base - 3, 8, $row['email'] ?: 'Sin correo', 'F1');

        $setFill(185, 0, 0);
        $text($col2, $base + 22, 8, 'SALUD', 'F2');
        $setFill(45, 45, 45);
        $salud = 'Grupo: ' . ($row['grupo_sanguineo'] ?: 'N/A') . ' | Alergias: ' . ((int) $row['es_alergico'] === 1 ? ($row['alergias_detalle'] ?: 'Si') : 'No');
        foreach (array_slice($wrapText($salud, 42), 0, 2) as $idx => $line) {
            $text($col2, $base + 9 - ($idx * 12), 8, $line, 'F1');
        }

        $setFill(185, 0, 0);
        $text($col3, $base + 22, 8, 'INVERSION Y EMERGENCIA', 'F2');
        $setFill(45, 45, 45);
        $text($col3, $base + 9, 8, ($row['inversion_nombre'] ?: 'Sin inversion') . ' | ' . export_dinero($row['inversion_monto']), 'F1');
        $text($col3, $base - 3, 8, ($row['emergencia_nombre'] ?: 'Sin contacto') . ' | ' . ($row['emergencia_telefono'] ?: 'Sin telefono'), 'F1');

        if (!empty($menores)) {
            $minorY = $cardY + 24 + (count($menores) * 12);
            $setFill(185, 0, 0);
            $text($x + 12, $minorY, 8, 'MENORES ACOMPANANTES', 'F2');
            $minorY -= 13;
            $setFill(45, 45, 45);
            foreach ($menores as $menor) {
                $line = trim($menor['nombre'] . ' ' . $menor['apellido']) . ' | ' . $menor['rango_edad'] . ' | ' . $menor['grupo_sanguineo'] . ' | ' . ($menor['inversion_nombre'] ?: 'Sin inversion');
                $text($x + 12, $minorY, 8, $line, 'F1');
                $minorY -= 12;
            }
        }

        $y = $cardY - 12;
    };

    $startPage();
    if (empty($participantes)) {
        $setFill(80, 80, 80);
        $text($margin, $y, 12, 'Este sendero todavia no tiene participantes registrados.', 'F1');
    } else {
        foreach ($participantes as $row) {
            $cardMenores = $menoresPorRegistro[(int) $row['registro_id']] ?? [];
            $neededHeight = empty($cardMenores) ? 130 : 158 + (count($cardMenores) * 12);
            if ($y < $neededHeight) {
                $savePage();
                $startPage();
            }
            $drawCard($row);
        }
    }
    $savePage();

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = [];
    foreach ($pages as $idx => $_) {
        $kids[] = (3 + ($idx * 2)) . ' 0 R';
    }
    $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';

    foreach ($pages as $idx => $pageContent) {
        $pageObj = 3 + ($idx * 2);
        $streamObj = $pageObj + 1;
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageW . ' ' . $pageH . '] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> /F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> >> >> /Contents ' . $streamObj . ' 0 R >>';
        $objects[] = "<< /Length " . strlen($pageContent) . " >>\nstream\n" . $pageContent . "endstream";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $idx => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($idx + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios por Sendero</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e9e9e9;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }

        .sheet {
            max-width: 1220px;
            margin: 22px auto;
            background: #fff;
            padding: 0;
            border-radius: 10px;
            box-shadow: 0 18px 46px rgba(0, 0, 0, .13);
            overflow: hidden;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            background: #111;
        }

        .topbar a,
        .topbar button {
            min-height: 38px;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 8px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, .08);
            color: #fff;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .topbar button {
            background: #e10600;
            border-color: #e10600;
        }

        .report-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 20px;
            align-items: center;
            padding: 24px 26px 18px;
            background: linear-gradient(135deg, #111 0%, #262626 72%, #b90000 150%);
            color: #fff;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand img {
            width: 82px;
            height: auto;
        }

        .kicker {
            display: inline-flex;
            border-radius: 999px;
            padding: 5px 9px;
            background: rgba(225, 6, 0, .18);
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        h1 {
            margin: 8px 0 0;
            font-size: 28px;
            line-height: 1;
            text-transform: uppercase;
        }

        .meta {
            padding: 18px 26px 0;
            color: #444;
            line-height: 1.45;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            padding: 16px 26px;
        }

        .summary div {
            min-height: 74px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 12px;
            background: #fafafa;
        }

        .summary span {
            display: block;
            color: #666;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .summary strong {
            display: block;
            margin-top: 7px;
            color: #111;
            font-size: 20px;
            line-height: 1.05;
        }

        .table-section {
            padding: 0 26px 26px;
        }

        .table-title {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 10px;
            border-top: 1px solid #eee;
            padding-top: 16px;
        }

        .table-title h2 {
            margin: 0;
            font-size: 17px;
            text-transform: uppercase;
        }

        .table-title p {
            margin: 0;
            color: #666;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            overflow-wrap: anywhere;
        }

        th,
        td {
            border: 1px solid #e1e1e1;
            padding: 6px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #111;
            color: #fff;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: .02em;
        }

        tr:nth-child(even) td {
            background: #f7f7f7;
        }

        td {
            color: #222;
            font-size: 10px;
            line-height: 1.3;
        }

        .empty {
            padding: 20px;
            border: 1px dashed #bbb;
            border-radius: 8px;
            text-align: center;
            color: #666;
        }

        @media print {
            body {
                background: #fff;
            }

            .sheet {
                max-width: none;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .topbar {
                display: none;
            }

            .report-hero {
                padding: 14px 0 12px;
                background: #111 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .meta,
            .summary,
            .table-section {
                padding-left: 0;
                padding-right: 0;
            }

            th {
                background: #111 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @page {
                size: landscape;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>
    <main class="sheet">
        <div class="topbar">
            <a href="<?= export_hrs(BASE_URL . 'pantallas/reporte_usuarios_sendero.php?sendero_id=' . $senderoId) ?>">Volver al reporte</a>
            <button type="button" onclick="window.print()">Guardar como PDF</button>
        </div>

        <header class="report-hero">
            <div>
                <span class="kicker">Reporte administrativo</span>
                <h1>Usuarios por Sendero</h1>
            </div>
            <div class="brand">
                <img src="<?= export_hrs(BASE_URL . 'imagenes/logo/logo_sg.png') ?>" alt="Senderismo Go">
            </div>
        </header>

        <div class="meta">
            <strong>Sendero:</strong> <?= export_hrs($sendero['nombre']) ?><br>
            <strong>Fecha del sendero:</strong> <?= export_hrs(export_fecha($sendero['fecha_sendero'])) ?><br>
            <strong>Estado:</strong> <?= export_hrs(ucfirst((string) $sendero['estado'])) ?><br>
            <strong>Generado:</strong> <?= export_hrs(date('d/m/Y h:i A')) ?>
        </div>

        <div class="summary">
            <div>
                <span>Adultos</span>
                <strong><?= count($participantes) ?></strong>
            </div>
            <div>
                <span>Menores</span>
                <strong><?= count($menoresExport) ?></strong>
            </div>
            <div>
                <span>Total personas</span>
                <strong><?= count($participantes) + count($menoresExport) ?></strong>
            </div>
            <div>
                <span>Datos incluidos</span>
                <strong>Contacto, salud y menores</strong>
            </div>
        </div>

        <section class="table-section">
            <div class="table-title">
                <h2>Listado de participantes</h2>
                <p><?= count($participantes) ?> registros</p>
            </div>

            <?php if (empty($participantes)): ?>
                <div class="empty">Este sendero todavia no tiene participantes registrados.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?= export_hrs($column) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participantes as $row): ?>
                            <tr>
                                <td><?= (int) $row['registro_id'] ?></td>
                                <td><?= export_hrs(trim($row['nombre'] . ' ' . $row['apellido'])) ?></td>
                                <td><?= export_hrs($row['user']) ?></td>
                                <td><?= export_hrs($row['email']) ?></td>
                                <td><?= export_hrs($row['telefono']) ?></td>
                                <td><?= export_hrs($row['rango_edad']) ?></td>
                                <td><?= export_hrs($row['identificacion']) ?></td>
                                <td><?= export_hrs($row['grupo_sanguineo']) ?></td>
                                <td><?= (int) $row['es_alergico'] === 1 ? export_hrs($row['alergias_detalle'] ?: 'Si, no especificado') : 'No' ?></td>
                                <td><?= export_hrs($row['enfermedad']) ?></td>
                                <td><?= export_hrs($row['seguro_medico']) ?></td>
                                <td><?= export_hrs($row['inversion_nombre'] ?: 'Sin inversion') ?></td>
                                <td><?= export_hrs(export_dinero($row['inversion_monto'])) ?></td>
                                <td><?= export_hrs($row['experiencia_senderismo']) ?></td>
                                <td><?= export_hrs($row['via_entero']) ?></td>
                                <td><?= export_hrs($row['referido_nombre']) ?></td>
                                <td><?= export_hrs($row['emergencia_nombre']) ?></td>
                                <td><?= export_hrs($row['emergencia_parentesco']) ?></td>
                                <td><?= export_hrs($row['emergencia_telefono']) ?></td>
                                <td><?= export_hrs(export_fecha($row['fecha_registro'], true)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="table-section">
            <div class="table-title">
                <h2>Menores acompanantes</h2>
                <p><?= count($menoresExport) ?> registros</p>
            </div>

            <?php if (empty($menoresExport)): ?>
                <div class="empty">Este sendero no tiene menores registrados.</div>
            <?php else: ?>
                <?php
                    $adultosPorRegistro = [];
                    foreach ($participantes as $adulto) {
                        $adultosPorRegistro[(int) $adulto['registro_id']] = trim($adulto['nombre'] . ' ' . $adulto['apellido']);
                    }
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Registro adulto</th>
                            <th>Adulto responsable</th>
                            <th>Menor</th>
                            <th>Edad</th>
                            <th>Telefono</th>
                            <th>Grupo</th>
                            <th>Salud</th>
                            <th>Inversion</th>
                            <th>Emergencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menoresExport as $menor): ?>
                            <tr>
                                <td><?= (int) $menor['registro_id'] ?></td>
                                <td><?= export_hrs($adultosPorRegistro[(int) $menor['registro_id']] ?? 'No identificado') ?></td>
                                <td><?= export_hrs(trim($menor['nombre'] . ' ' . $menor['apellido'])) ?></td>
                                <td><?= export_hrs($menor['rango_edad']) ?></td>
                                <td><?= export_hrs($menor['telefono']) ?></td>
                                <td><?= export_hrs($menor['grupo_sanguineo']) ?></td>
                                <td>
                                    <?= (int) $menor['es_alergico'] === 1 ? 'Alergico: ' . export_hrs($menor['alergias_detalle'] ?: 'Si, no especificado') : 'No alergico' ?><br>
                                    <?= export_hrs($menor['enfermedad']) ?><br>
                                    Seguro: <?= export_hrs($menor['seguro_medico']) ?>
                                </td>
                                <td><?= export_hrs($menor['inversion_nombre'] ?: 'Sin inversion') ?><br><?= export_hrs(export_dinero($menor['inversion_monto'])) ?></td>
                                <td><?= export_hrs($menor['emergencia_nombre']) ?><br><?= export_hrs($menor['emergencia_parentesco']) ?> / <?= export_hrs($menor['emergencia_telefono']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
