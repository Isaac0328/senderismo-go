<?php
// Guarda cobros y asistencia de "Ingresos por sendero". Responde JSON: cada accion
// toca solo las inscripciones indicadas y devuelve sus filas ya calculadas.
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/ingresos_cobro.php';

function ingresos_responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ingresos_responder(405, ['ok' => false, 'mensaje' => 'Metodo no permitido.']);
}
$tokenSesion = (string) ($_SESSION['csrf_token'] ?? '');
$tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
if ($tokenSesion === '' || $tokenEnviado === '' || !hash_equals($tokenSesion, $tokenEnviado)) {
    ingresos_responder(419, ['ok' => false, 'mensaje' => 'La sesion expiro. Recarga la pagina e intenta de nuevo.']);
}
if (!ic_esquema_listo($conn)) {
    ingresos_responder(409, ['ok' => false, 'mensaje' => 'Falta aplicar la actualizacion de cobros en Actualizaciones del sistema.']);
}

$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');
$adminId = (int) ($_SESSION['usuario_id'] ?? 0);
$ids = $_POST['registro_ids'] ?? [($_POST['registro_id'] ?? 0)];
$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : [$ids]))));
if ($senderoId <= 0 || !$ids || !in_array($accion, ['guardar', 'asistencia', 'cobro_completo', 'deshacer_cobro'], true)) {
    ingresos_responder(400, ['ok' => false, 'mensaje' => 'Solicitud incompleta.']);
}
if ($accion === 'guardar' && count($ids) !== 1) {
    ingresos_responder(400, ['ok' => false, 'mensaje' => 'Se guarda un cobro a la vez.']);
}

// Lo que el panel envia, en pesos, a partir del cobro guardado de la fila.
$datosDesdeFila = static function (array $fila): array {
    $e = $fila['entrada'];
    return [
        'version' => (string) $fila['version'],
        'tipo' => $e['tipo'],
        'asistio' => $e['asistio'],
        'descuento' => ic_decimal($e['descuento']),
        'credito' => ic_decimal($e['credito']),
        'recibido' => ic_decimal($e['recibido']),
        'a_credito' => ic_decimal($e['a_credito']),
        'devuelto' => ic_decimal($e['devuelto']),
        'cargado' => ic_decimal($e['cargado']),
        'cargado_a' => $e['cargado_a'],
        'metodo_id' => $e['metodo_id'],
        'fecha' => $e['fecha'],
        'nota' => $fila['nota'],
    ];
};

$guardados = [];
$omitidos = [];
$errores = [];
$mensajeError = '';

foreach ($ids as $registroId) {
    try {
        if ($accion === 'guardar') {
            $datos = [];
            foreach (['version', 'tipo', 'descuento', 'credito', 'recibido', 'a_credito', 'devuelto', 'cargado', 'cargado_a', 'metodo_id', 'fecha', 'nota'] as $campo) {
                $datos[$campo] = (string) ($_POST[$campo] ?? '');
            }
            $datos['asistio'] = ($_POST['asistio'] ?? '0') === '1';
        } else {
            $actual = ic_cargar($conn, $senderoId, [$registroId]);
            if (!$actual) {
                throw new IngresoValidacionException('Inscripcion #' . $registroId . ' no encontrada.');
            }
            $datos = $datosDesdeFila($actual[0]);
            if ($accion === 'asistencia') {
                $datos['asistio'] = ($_POST['asistio'] ?? '0') === '1';
            } elseif ($accion === 'deshacer_cobro') {
                // Deja la inscripcion sin cobro: devuelve el credito usado y anula el credito
                // que se habia generado (si ya se uso en otro sendero, se rechaza esa fila).
                $c = $actual[0]['calculo'];
                if ($c['tipo'] !== 'automatico' || ($c['recibido'] + $c['credito'] + $c['descuento'] + $c['a_credito'] + $c['devuelto'] + $c['cargado']) === 0) {
                    $omitidos[] = $actual[0]['nombre'] . ': no tenia cobro registrado.';
                    continue;
                }
                foreach (['descuento', 'credito', 'recibido', 'a_credito', 'devuelto', 'cargado', 'cargado_a'] as $campo) {
                    $datos[$campo] = '0';
                }
                $datos['metodo_id'] = 0;
                $datos['fecha'] = '';
            } else {
                $c = $actual[0]['calculo'];
                // Solo a quien no tiene nada registrado y debe algo.
                if ($c['tipo'] !== 'automatico' || $c['recibido'] > 0 || $c['credito'] > 0 || $c['cargado'] > 0 || $c['debe_pagar'] <= 0) {
                    $omitidos[] = $actual[0]['nombre'] . ': ya tenia un cobro registrado.';
                    continue;
                }
                $datos['recibido'] = ic_decimal($c['debe_pagar']);
                $datos['metodo_id'] = (int) ($_POST['metodo_id'] ?? 0);
                $datos['fecha'] = (string) ($_POST['fecha'] ?? '');
            }
        }

        mysqli_begin_transaction($conn);
        $resultado = ic_guardar($conn, $senderoId, $registroId, $datos, $adminId);
        mysqli_commit($conn);
        $guardados[] = $resultado['nombre'];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $mensaje = $e instanceof IngresoValidacionException || APP_DEBUG ? $e->getMessage() : 'No se pudo guardar el cobro.';
        if (!($e instanceof IngresoValidacionException)) {
            error_log('Ingresos por sendero: ' . $e->getMessage());
        }
        if ($accion === 'guardar') {
            $mensajeError = $mensaje;
            $errores = $e instanceof IngresoValidacionException ? $e->errores : [];
        } else {
            $omitidos[] = $mensaje;
        }
    }
}

// Filas actualizadas y resumen del sendero completo.
$catalogo = sg_financial_status_catalog($conn);
$todas = ic_cargar($conn, $senderoId);
$filasHtml = [];
foreach ($todas as $fila) {
    if (in_array((int) $fila['registro_id'], $ids, true)) {
        $filasHtml[(int) $fila['registro_id']] = ic_render_fila($fila, $catalogo);
    }
}
$totales = ic_totales($todas);
mysqli_close($conn);

if ($accion === 'guardar' && $mensajeError !== '') {
    ingresos_responder(422, ['ok' => false, 'mensaje' => $mensajeError, 'errores' => $errores, 'filas' => $filasHtml]);
}

$mensajes = [
    'guardar' => 'Cobro de ' . ($guardados[0] ?? '') . ' guardado.',
    'asistencia' => count($guardados) === 1 ? 'Asistencia de ' . $guardados[0] . ' guardada.' : 'Asistencia guardada para ' . count($guardados) . ' personas.',
    'cobro_completo' => 'Cobro completo registrado para ' . count($guardados) . ' persona(s).',
    'deshacer_cobro' => 'Cobro deshecho para ' . count($guardados) . ' persona(s).',
];
ingresos_responder(200, [
    'ok' => true,
    'mensaje' => $mensajes[$accion],
    'omitidos' => $omitidos,
    'filas' => $filasHtml,
    'resumen' => ic_render_resumen($totales),
    'grupos' => $totales['grupos'],
    'revisar' => $totales['revisar'],
]);
