<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'operaciones.encuestas';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/encuestas_bootstrap.php';
require_once __DIR__ . '/../componentes/encuestas_resultados.php';

encuestas_bootstrap($conn);
sg_seed_permission_catalog($conn);
sg_require_permission_action($conn, 'operaciones.encuestas', 'ver', BASE_URL . 'mantenimientos/mantenimiento_encuestas.php?vista=consultar');

$encuestaId = (int) ($_GET['encuesta_id'] ?? $_GET['id'] ?? 0);
$formato = preg_replace('/[^a-z]/', '', strtolower(trim((string) ($_GET['formato'] ?? 'excel'))));

if ($encuestaId <= 0 || !in_array($formato, ['excel', 'pdf'], true)) {
    http_response_code(400);
    die('Solicitud invalida.');
}

$resultados = sg_encuesta_resultados_cargar($conn, $encuestaId);
mysqli_close($conn);

if (!$resultados) {
    http_response_code(404);
    die('Encuesta no encontrada.');
}

function srex_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function srex_numero($value, int $decimales = 1): string
{
    return $value === null || $value === '' ? 'N/A' : number_format((float) $value, $decimales, '.', ',');
}

function srex_estado(string $estado): string
{
    return [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'cancelada' => 'Cancelada',
        'cerrada' => 'Cerrada',
    ][$estado] ?? ucfirst($estado);
}

function srex_tipo(string $tipo): string
{
    return [
        'texto' => 'Respuesta corta',
        'textarea' => 'Texto abierto',
        'radio' => 'Una opcion',
        'checkbox' => 'Varias opciones',
        'select' => 'Lista desplegable',
        'escala' => 'Rango / escala',
        'numero' => 'Numero',
    ][$tipo] ?? 'Respuesta';
}

function srex_nombre_archivo(string $titulo): string
{
    $titulo = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titulo) ?: 'encuesta';
    $titulo = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $titulo));
    return trim($titulo, '_') ?: 'encuesta';
}

$encuesta = $resultados['encuesta'];
$preguntas = $resultados['preguntas'];
$envios = $resultados['envios'];
$analisis = $resultados['analisis'];
$metricas = $resultados['metricas'];
$esAnonima = (int) ($encuesta['anonima'] ?? 0) === 1;
$filenameBase = 'resultados_' . srex_nombre_archivo((string) $encuesta['titulo']) . '_' . date('Ymd_His');
$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);
    die('No estan instaladas las dependencias de exportacion.');
}

require_once $autoloadPath;

if ($formato === 'excel') {
    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        http_response_code(500);
        die('PhpSpreadsheet no esta disponible.');
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('Senderismo Go!')
        ->setTitle('Resultados - ' . $encuesta['titulo']);

    $darkHeader = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '111111']],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ];
    $redTitle = [
        'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B90000']],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ];
    $softFill = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDF7F1']],
    ];

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Resumen');
    $sheet->setCellValue('A1', 'RESULTADOS DE ENCUESTA');
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1:H1')->applyFromArray($redTitle);
    $sheet->getRowDimension(1)->setRowHeight(28);

    $resumenGeneral = [
        ['Encuesta', $encuesta['titulo'], 'Estado', srex_estado((string) $encuesta['estado'])],
        ['Sendero', $encuesta['sendero_nombre'] ?: 'Sin sendero especifico', 'Privacidad', $esAnonima ? 'Anonima' : 'Identificada'],
        ['Envios', (int) $encuesta['total_envios'], 'Respuestas', (int) $encuesta['total_respuestas']],
        ['Pendientes', (int) $encuesta['total_pendientes'], 'Cancelados', (int) $encuesta['total_cancelados']],
        ['Participacion', srex_numero($metricas['tasa_respuesta']) . '%', 'Satisfaccion', $metricas['satisfaccion'] === null ? 'N/A' : srex_numero($metricas['satisfaccion']) . '%'],
        ['Generado', date('d/m/Y h:i A'), '', ''],
    ];
    $sheet->fromArray($resumenGeneral, null, 'A3');
    $sheet->getStyle('A3:A8')->applyFromArray($softFill);
    $sheet->getStyle('C3:C8')->applyFromArray($softFill);

    $questionHeaderRow = 10;
    $sheet->fromArray(['#', 'Pregunta', 'Tipo', 'Respondidas', 'Omitidas', 'Promedio', 'Puntaje max.', 'Detalle'], null, 'A' . $questionHeaderRow);
    $sheet->getStyle('A' . $questionHeaderRow . ':H' . $questionHeaderRow)->applyFromArray($darkHeader);
    $row = $questionHeaderRow + 1;
    foreach ($preguntas as $index => $pregunta) {
        $pid = (int) $pregunta['id'];
        $resumen = $analisis[$pid] ?? [];
        $tipo = (string) $pregunta['tipo'];
        $detalle = '';
        if (!empty($resumen['distribucion'])) {
            $partes = [];
            foreach ($resumen['distribucion'] as $item) {
                $partes[] = $item['etiqueta'] . ': ' . (int) $item['cantidad'] . ' (' . srex_numero($item['porcentaje']) . '%)';
            }
            $detalle = implode('; ', $partes);
        } elseif ($tipo === 'numero') {
            $detalle = 'Min: ' . srex_numero($resumen['minimo_numero'] ?? null, 2)
                . ' | Max: ' . srex_numero($resumen['maximo_numero'] ?? null, 2);
        } elseif (!empty($resumen['textos'])) {
            $detalle = count($resumen['textos']) . ' respuestas de texto';
        }

        $sheet->fromArray([
            $index + 1,
            $pregunta['pregunta'],
            srex_tipo($tipo),
            (int) ($resumen['respondidas'] ?? 0),
            (int) ($resumen['omitidas'] ?? 0),
            ($resumen['promedio_puntaje'] ?? null) === null ? '' : (float) $resumen['promedio_puntaje'],
            (float) ($pregunta['puntaje_max'] ?? 0),
            $detalle,
        ], null, 'A' . $row);
        $row++;
    }
    $sheet->freezePane('A' . ($questionHeaderRow + 1));
    $sheet->getStyle('A' . ($questionHeaderRow + 1) . ':H' . max($questionHeaderRow + 1, $row - 1))->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    foreach (['A' => 7, 'B' => 42, 'C' => 20, 'D' => 13, 'E' => 11, 'F' => 12, 'G' => 13, 'H' => 55] as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    $distributionSheet = $spreadsheet->createSheet();
    $distributionSheet->setTitle('Distribucion');
    $distributionSheet->setCellValue('A1', 'DISTRIBUCION POR PREGUNTA');
    $distributionSheet->mergeCells('A1:E1');
    $distributionSheet->getStyle('A1:E1')->applyFromArray($redTitle);
    $distributionSheet->fromArray(['#', 'Pregunta', 'Opcion / valor', 'Cantidad', 'Porcentaje'], null, 'A3');
    $distributionSheet->getStyle('A3:E3')->applyFromArray($darkHeader);
    $row = 4;
    foreach ($preguntas as $index => $pregunta) {
        $items = $analisis[(int) $pregunta['id']]['distribucion'] ?? [];
        foreach ($items as $item) {
            $distributionSheet->fromArray([
                $index + 1,
                $pregunta['pregunta'],
                $item['etiqueta'],
                (int) $item['cantidad'],
                (float) $item['porcentaje'] / 100,
            ], null, 'A' . $row);
            $distributionSheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('0.0%');
            $row++;
        }
    }
    if ($row === 4) {
        $distributionSheet->setCellValue('A4', 'Esta encuesta no tiene respuestas de seleccion o escala.');
        $distributionSheet->mergeCells('A4:E4');
    }
    $distributionSheet->getStyle('A4:E' . max(4, $row - 1))->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    foreach (['A' => 7, 'B' => 48, 'C' => 34, 'D' => 12, 'E' => 14] as $column => $width) {
        $distributionSheet->getColumnDimension($column)->setWidth($width);
    }

    $responseSheet = $spreadsheet->createSheet();
    $responseSheet->setTitle('Respuestas');
    $responseSheet->setCellValue('A1', $esAnonima ? 'RESPUESTAS ANONIMAS' : 'RESPUESTAS INDIVIDUALES');
    $baseColumns = $esAnonima
        ? ['Respuesta', 'Fecha']
        : ['Respuesta', 'Participante', 'Usuario', 'Email', 'Fecha'];
    $headers = $baseColumns;
    foreach ($preguntas as $index => $pregunta) {
        $headers[] = ($index + 1) . '. ' . $pregunta['pregunta'];
    }
    $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($headers)));
    $responseSheet->mergeCells('A1:' . $lastColumn . '1');
    $responseSheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($redTitle);
    $responseSheet->fromArray($headers, null, 'A3');
    $responseSheet->getStyle('A3:' . $lastColumn . '3')->applyFromArray($darkHeader);
    $row = 4;
    foreach ($envios as $envio) {
        if ($esAnonima) {
            $values = ['Respuesta anonima #' . (int) $envio['numero_respuesta'], sg_fecha($envio['respondido_at'], true)];
        } else {
            $nombre = trim((string) ($envio['nombre'] . ' ' . $envio['apellido']));
            $values = [
                '#' . (int) $envio['numero_respuesta'],
                $nombre ?: 'Usuario #' . (int) $envio['usuario_id'],
                $envio['user'] ?: '',
                $envio['email'] ?: '',
                sg_fecha($envio['respondido_at'], true),
            ];
        }
        foreach ($preguntas as $pregunta) {
            $values[] = sg_encuesta_resultado_valor($envio['respuestas'][(int) $pregunta['id']] ?? []);
        }
        $responseSheet->fromArray($values, null, 'A' . $row);
        $row++;
    }
    if ($row === 4) {
        $responseSheet->setCellValue('A4', 'Aun no hay respuestas recibidas.');
        $responseSheet->mergeCells('A4:' . $lastColumn . '4');
    }
    $responseSheet->freezePane('A4');
    $responseSheet->setAutoFilter('A3:' . $lastColumn . max(3, $row - 1));
    $responseSheet->getStyle('A4:' . $lastColumn . max(4, $row - 1))->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
    for ($columnIndex = 1; $columnIndex <= count($headers); $columnIndex++) {
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
        $responseSheet->getColumnDimension($column)->setWidth($columnIndex <= count($baseColumns) ? 22 : 36);
    }

    $spreadsheet->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (!class_exists('\\Dompdf\\Dompdf')) {
    http_response_code(500);
    die('Dompdf no esta disponible.');
}

ob_start();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px; }
        body { color: #151515; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1, h2, h3, p { margin: 0; }
        .hero { margin-bottom: 14px; padding: 16px; background: #111; color: #fff; }
        .hero small { color: #ff6b63; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .hero h1 { margin: 5px 0; font-size: 20px; }
        .hero p { color: #ddd; }
        .metrics { width: 100%; margin-bottom: 14px; border-collapse: separate; border-spacing: 5px; }
        .metrics td { width: 25%; padding: 9px; background: #edf7f1; border: 1px solid #d7e4dc; }
        .metrics span { display: block; color: #5c665f; font-size: 7px; text-transform: uppercase; }
        .metrics strong { display: block; margin-top: 3px; font-size: 16px; }
        .section-title { margin: 14px 0 7px; padding-bottom: 5px; border-bottom: 2px solid #b90000; font-size: 13px; }
        .question { margin-bottom: 8px; padding: 9px; border: 1px solid #dfe4e1; page-break-inside: avoid; }
        .question h3 { margin-bottom: 4px; font-size: 10px; }
        .question-meta { margin-bottom: 5px; color: #637069; font-size: 7px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { padding: 5px; background: #111; color: #fff; text-align: left; }
        table.data td { padding: 5px; border: 1px solid #e1e4e2; vertical-align: top; }
        .response { margin-bottom: 10px; padding: 9px; border: 1px solid #dfe4e1; page-break-inside: avoid; }
        .response h3 { margin-bottom: 3px; color: #0d5f35; }
        .response-meta { margin-bottom: 7px; color: #68716c; font-size: 7px; }
        .response-row { padding: 4px 0; border-top: 1px solid #eceeed; }
        .response-row strong { display: block; margin-bottom: 2px; }
        .muted { color: #6b746f; }
    </style>
</head>
<body>
    <div class="hero">
        <small>Resultados de encuesta</small>
        <h1><?= srex_h($encuesta['titulo']) ?></h1>
        <p><?= srex_h($encuesta['sendero_nombre'] ?: 'Sin sendero especifico') ?> | <?= srex_h(srex_estado((string) $encuesta['estado'])) ?> | <?= $esAnonima ? 'Anonima' : 'Identificada' ?></p>
    </div>

    <table class="metrics"><tr>
        <td><span>Envios</span><strong><?= (int) $encuesta['total_envios'] ?></strong></td>
        <td><span>Respuestas</span><strong><?= (int) $encuesta['total_respuestas'] ?></strong></td>
        <td><span>Participacion</span><strong><?= srex_h(srex_numero($metricas['tasa_respuesta'])) ?>%</strong></td>
        <td><span>Satisfaccion</span><strong><?= $metricas['satisfaccion'] === null ? 'N/A' : srex_h(srex_numero($metricas['satisfaccion'])) . '%' ?></strong></td>
    </tr></table>

    <h2 class="section-title">Analisis por pregunta</h2>
    <?php foreach ($preguntas as $index => $pregunta): ?>
        <?php $resumen = $analisis[(int) $pregunta['id']] ?? []; ?>
        <div class="question">
            <h3><?= $index + 1 ?>. <?= srex_h($pregunta['pregunta']) ?></h3>
            <div class="question-meta"><?= srex_h(srex_tipo((string) $pregunta['tipo'])) ?> | <?= (int) ($resumen['respondidas'] ?? 0) ?> respondidas | <?= (int) ($resumen['omitidas'] ?? 0) ?> omitidas</div>
            <?php if (!empty($resumen['distribucion'])): ?>
                <table class="data"><thead><tr><th>Opcion / valor</th><th>Cantidad</th><th>Porcentaje</th></tr></thead><tbody>
                <?php foreach ($resumen['distribucion'] as $item): ?>
                    <tr><td><?= srex_h($item['etiqueta']) ?></td><td><?= (int) $item['cantidad'] ?></td><td><?= srex_h(srex_numero($item['porcentaje'])) ?>%</td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php elseif ((string) $pregunta['tipo'] === 'numero'): ?>
                <p>Promedio: <?= srex_h(srex_numero($resumen['promedio_numero'] ?? null, 2)) ?> | Minimo: <?= srex_h(srex_numero($resumen['minimo_numero'] ?? null, 2)) ?> | Maximo: <?= srex_h(srex_numero($resumen['maximo_numero'] ?? null, 2)) ?></p>
            <?php elseif (!empty($resumen['textos'])): ?>
                <?php foreach ($resumen['textos'] as $texto): ?><p class="response-row"><?= nl2br(srex_h($texto)) ?></p><?php endforeach; ?>
            <?php else: ?>
                <p class="muted">Sin respuestas para esta pregunta.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <h2 class="section-title">Respuestas individuales</h2>
    <?php if (empty($envios)): ?>
        <p class="muted">Aun no hay respuestas recibidas.</p>
    <?php else: ?>
        <?php foreach ($envios as $envio): ?>
            <?php
            $nombre = $esAnonima
                ? 'Respuesta anonima #' . (int) $envio['numero_respuesta']
                : trim((string) ($envio['nombre'] . ' ' . $envio['apellido']));
            $nombre = $nombre ?: 'Usuario #' . (int) $envio['usuario_id'];
            ?>
            <div class="response">
                <h3><?= srex_h($nombre) ?></h3>
                <div class="response-meta">
                    <?php if (!$esAnonima): ?>@<?= srex_h($envio['user'] ?: 'sin.usuario') ?> | <?= srex_h($envio['email'] ?: 'Sin correo') ?> | <?php endif; ?>
                    <?= srex_h(sg_fecha($envio['respondido_at'], true)) ?>
                </div>
                <?php foreach ($preguntas as $pregunta): ?>
                    <div class="response-row">
                        <strong><?= srex_h($pregunta['pregunta']) ?></strong>
                        <?= nl2br(srex_h(sg_encuesta_resultado_valor($envio['respuestas'][(int) $pregunta['id']] ?? []))) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();
$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream($filenameBase . '.pdf', ['Attachment' => true]);
exit;
