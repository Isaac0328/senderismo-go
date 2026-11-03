<?php
// Factura en PDF de un cobro de sendero: se previsualiza en el navegador o se descarga.
require_once __DIR__ . '/../configuracion.php';
$PERMISO_REQUERIDO = 'finanzas.balances';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/factura_sendero.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit('Metodo no permitido.');
}
if (!sg_has_permission_action($conn, 'finanzas.balances', 'agregar')) {
    http_response_code(403);
    exit('No tienes permiso para crear facturas.');
}

$fecha = static function (string $clave, string $porDefecto): string {
    $valor = (string) ($_GET[$clave] ?? '');
    $analizada = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $analizada && $analizada->format('Y-m-d') === $valor ? $valor : $porDefecto;
};

$registroId = (int) ($_GET['registro_id'] ?? 0);
$datos = $registroId > 0 ? factura_sendero_datos($conn, $registroId) : null;
if (!$datos) {
    http_response_code(404);
    exit('No se encontro la inscripcion.');
}
$opciones = [
    'fecha_emision' => $fecha('fecha_emision', date('Y-m-d')),
    'vencimiento' => $fecha('vencimiento', ''),
    'nota' => mb_substr(trim((string) ($_GET['nota'] ?? '')), 0, 300),
];

try {
    $dompdf = factura_sendero_pdf(factura_sendero_html($datos, $opciones));
} catch (Throwable $error) {
    error_log('Factura de sendero: ' . $error->getMessage());
    http_response_code(500);
    exit($error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo generar la factura.');
}

$nombre = 'Factura-' . $datos['numero'] . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $datos['cliente']) ?: 'cliente') . '.pdf';
mysqli_close($conn);
$dompdf->stream(trim($nombre, '-'), ['Attachment' => !empty($_GET['descargar'])]);
exit;
