<?php
require_once __DIR__ . '/cuentas_usuarios.php';
require_once __DIR__ . '/../vendor/autoload.php';

function sgcu_workbook(array $data, array $filters, array $labels): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $book->removeSheetByIndex(0);
    $periodo = ($filters['desde'] ?: 'Inicio') . ' / ' . ($filters['hasta'] ?: 'Sin limite');
    $addSheet = static function (string $name, array $headers, array $rows, array $moneyColumns = [], string $scope = '') use ($book, $periodo): void {
        $sheet = $book->createSheet();
        $sheet->setTitle($name);
        // Todo texto se escribe como texto literal para evitar formulas provenientes de nombres o notas.
        $binder = static function ($worksheet, int $col, int $row, $value): void {
            $worksheet->setCellValueExplicit([$col, $row], $value,
                is_int($value) || is_float($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        };
        $sheet->setCellValue('A1', 'Estado de cuenta por usuario');
        $sheet->setCellValue('A2', $scope ?: $periodo);
        foreach ($headers as $index => $header) { $binder($sheet, $index + 1, 4, $header); }
        foreach ($rows as $index => $row) {
            foreach ($row as $col => $value) { $binder($sheet, $col + 1, $index + 5, $value ?? ''); }
        }
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = max(5, count($rows) + 4);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A4:' . $last . '4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A4:' . $last . '4')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF17643E');
        $sheet->freezePane('A5'); $sheet->setAutoFilter('A4:' . $last . $lastRow);
        foreach (range(1, count($headers)) as $col) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($letter)->setWidth(in_array($col, $moneyColumns, true) ? 22 : 28);
        }
        foreach ($moneyColumns as $col) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($letter . '5:' . $letter . $lastRow)->getNumberFormat()->setFormatCode('"RD$ "#,##0.00;[Red]-"RD$ "#,##0.00');
        }
    };
    $summary = [];
    foreach ($data['accounts'] as $a) {
        $summary[] = [$a['nombre'], $a['tipo'] === 'temporal' ? ($a['key'][0] === 'p' ? 'Sin cuenta #' : 'Temporal #') . $a['id'] : 'Registrado #' . $a['id'], $a['usuario'], $a['email'], $a['telefono'], $a['inscripciones'],
            $a['totals']['pagado'] / 100, $a['totals']['descuento'] / 100, $a['totals']['aplicado'] / 100, $a['totals']['generado'] / 100,
            $a['totals']['retenido'] / 100, $a['totals']['ingreso'] / 100, $a['pendiente_actual'] / 100, $a['credito_actual'] / 100, $a['tuvo_credito'] ? 'Si' : 'No'];
    }
    $addSheet('Cuentas', ['Participante', 'Cuenta', 'Usuario', 'Email', 'Telefono', 'Senderos', 'Dinero pagado', 'Descuentos', 'Credito aplicado', 'Credito generado', 'Efectivo retenido', 'Total ingreso', 'Pendiente actual', 'Credito actual', 'Tiene o tuvo credito'], $summary, range(7, 14), $periodo . ' | Importes por fecha de ' . $filters['criterio'] . '; saldos actuales de todas las fechas.');
    $rows = [];
    foreach ($data['history'] as $r) {
        $a = $data['accounts'][$r['key']];
        $row = [$a['nombre'], $r['key'], (int) $r['registro_id'], $r['sendero'], $r['fecha_sendero'] ?? '', $labels[$r['estado']] ?? $r['estado'], $r['registro_estado']];
        foreach (['inversion', 'descuento', 'esperado', 'pagado', 'aplicado', 'balance', 'generado', 'retenido', 'ingreso'] as $field) { $row[] = $r['values'][$field] / 100; }
        array_push($row, $r['fecha_pago'] ?? '', $r['metodo'], $r['nota']); $rows[] = $row;
    }
    $addSheet('Senderos y pagos', ['Participante', 'Cuenta', 'Registro', 'Sendero', 'Fecha sendero', 'Estado financiero', 'Inscripcion', 'Valor inversion', 'Descuento', 'Valor esperado', 'Monto pagado', 'Credito aplicado', 'Balance', 'Credito generado', 'Efectivo retenido', 'Total ingreso', 'Fecha pago', 'Metodo', 'Nota'], $rows, range(8, 16), $periodo . ' | Fecha de ' . $filters['criterio'] . ' | Pagos acumulados vigentes, no abonos individuales.');
    $rows = [];
    foreach ($data['credits'] as $r) {
        $rows[] = [$data['accounts']['u' . $r['usuario_id']]['nombre'], (int) $r['id'], $r['created_at'], $r['origen'] ?? '',
            sgcu_cents($r['monto']) / 100, $r['estado'] === 'activo' ? sgcu_cents($r['saldo_disponible']) / 100 : 0, $r['estado'], $r['motivo'] ?? ''];
    }
    $addSheet('Creditos', ['Participante', 'Credito', 'Creacion', 'Origen', 'Monto registrado', 'Disponible actual', 'Estado', 'Motivo'], $rows, [5, 6], 'Todas las fechas; saldos actuales.');
    if ($data['selected']) {
        $rows = [];
        foreach ($data['applications'] as $r) {
            $rows[] = [(int) $r['credito_id'], $r['origen'] ?? '', $r['destino'], $r['fecha_sendero'] ?? '', sgcu_cents($r['credito_aplicado']) / 100, (int) $r['registro_id']];
        }
        $addSheet('Aplicaciones vigentes', ['Credito', 'Origen', 'Aplicado en', 'Fecha sendero', 'Credito aplicado', 'Registro'], $rows, [5], 'Todas las fechas; aplicaciones vigentes en pagos.');
        $rows = [];
        foreach ($data['movements'] as $r) {
            $rows[] = [$r['created_at'], $r['tipo'], (int) $r['credito_id'], $r['origen'] ?? '', $r['destino'] ?? '', sgcu_cents($r['monto']) / 100, $r['responsable'] ?? '', $r['nota'] ?? ''];
        }
        $addSheet('Movimientos de credito', ['Fecha', 'Movimiento', 'Credito', 'Origen', 'Sendero del movimiento', 'Monto', 'Responsable', 'Nota'], $rows, [6], $periodo . ' | Fecha del movimiento. Bitacora; puede incluir guardados repetidos. No se suma para los totales.');
    }
    $totalsRows = [];
    foreach (['pagado' => 'Dinero pagado', 'descuento' => 'Descuentos', 'aplicado' => 'Credito aplicado', 'generado' => 'Credito generado', 'retenido' => 'Efectivo retenido', 'ingreso' => 'Total ingreso'] as $key => $label) { $totalsRows[] = [$label, $data['totals'][$key] / 100, 'Periodo']; }
    $totalsRows[] = ['Balance pendiente actual', $data['current']['pendiente'] / 100, 'Todas las fechas'];
    $totalsRows[] = ['Pendiente de senderos que aun no se realizan', $data['current']['proximo'] / 100, 'Incluido en el balance pendiente'];
    $totalsRows[] = ['Credito disponible actual', $data['current']['credito'] / 100, 'Todas las fechas'];
    $addSheet('Totales', ['Concepto', 'Monto', 'Alcance'], $totalsRows, [2], $periodo);
    $book->setActiveSheetIndex(0);
    return $book;
}
