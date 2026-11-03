<?php
require_once __DIR__ . '/../configuracion.php';
$PERMISO_REQUERIDO = 'finanzas.cuentas_usuarios';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
sg_require_permission_action($conn, $PERMISO_REQUERIDO, 'ver');
require_once __DIR__ . '/../componentes/cuentas_usuarios_excel.php';
try {
    $filters = sgcu_filters($_GET);
    $data = sgcu_load($conn, $filters);
    $book = sgcu_workbook($data, $filters, sg_financial_statuses($conn));
    $file = tmpfile();
    if ($file === false) { throw new RuntimeException('No se pudo preparar el archivo Excel.'); }
    try {
        $path = stream_get_meta_data($file)['uri'];
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="estado-cuenta-usuarios-' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    } finally { fclose($file); $book->disconnectWorksheets(); }
} catch (Throwable $error) {
    error_log('Excel estado de cuenta: ' . $error->getMessage());
    http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    echo $error instanceof InvalidArgumentException ? sgcu_h($error->getMessage()) : 'No se pudo generar el Excel.';
} finally { mysqli_close($conn); }
