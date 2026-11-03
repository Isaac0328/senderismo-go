<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'reportes.encuestas';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/permisos.php';
sg_require_permission_action($conn, 'reportes.encuestas', 'ver', BASE_URL . 'pantallas/panel_administrativo.php');

$pageTitle = 'Reporte de Encuestas';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/reportes.css', 'css/reporte_encuestas.css'];
$jsFiles = ['js/barra_navegacion.js'];

function re_h($value): string
{
    return sg_h($value);
}

function re_fecha(string $value, string $empty = 'Sin fecha'): string
{
    return trim($value) === '' ? $empty : sg_fecha($value, true, $empty);
}

function re_fecha_valida(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function re_fetch_all(mysqli $conn, string $sql, string $types = '', array $values = []): array
{
    $rows = [];
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $rows;
    }

    if ($types !== '') {
        $params = [$types];
        foreach ($values as $key => $value) {
            $params[] = &$values[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $params);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

$senderoId = max(0, (int) ($_GET['sendero_id'] ?? 0));
$usuarioId = max(0, (int) ($_GET['usuario_id'] ?? 0));
$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));
$fechaError = ($desde !== '' && !re_fecha_valida($desde)) || ($hasta !== '' && !re_fecha_valida($hasta));
if ($fechaError || ($desde !== '' && $hasta !== '' && $desde > $hasta)) {
    $desde = '';
    $hasta = '';
    $fechaError = true;
}

$senderos = re_fetch_all($conn, "SELECT id, nombre, fecha_sendero FROM senderos WHERE activo = 1 ORDER BY fecha_sendero DESC, nombre ASC");
$participantes = re_fetch_all($conn, "
    SELECT DISTINCT u.id, u.nombre, u.apellido, u.user
    FROM encuesta_envios ee
    INNER JOIN usuarios u ON u.id = ee.usuario_id
    ORDER BY u.nombre ASC, u.apellido ASC, u.user ASC
");

$where = ['e.activo = 1'];
$types = '';
$values = [];
if ($senderoId > 0) {
    $where[] = 'e.sendero_id = ?';
    $types .= 'i';
    $values[] = $senderoId;
}
if ($usuarioId > 0) {
    $where[] = 'ee.usuario_id = ?';
    $types .= 'i';
    $values[] = $usuarioId;
}
if ($desde !== '') {
    $where[] = 'DATE(COALESCE(e.fecha_envio, e.created_at)) >= ?';
    $types .= 's';
    $values[] = $desde;
}
if ($hasta !== '') {
    $where[] = 'DATE(COALESCE(e.fecha_envio, e.created_at)) <= ?';
    $types .= 's';
    $values[] = $hasta;
}
$whereSql = implode(' AND ', $where);

$encuestas = re_fetch_all($conn, "
    SELECT
        e.id, e.titulo, e.anonima, e.estado, e.fecha_envio, e.created_at,
        s.nombre AS sendero_nombre,
        COUNT(DISTINCT ee.id) AS envios,
        COUNT(DISTINCT CASE WHEN ee.estado = 'respondida' THEN ee.id END) AS respondidas,
        COUNT(DISTINCT CASE WHEN ee.estado = 'pendiente' THEN ee.id END) AS pendientes,
        COUNT(DISTINCT CASE WHEN ee.estado = 'cancelada' THEN ee.id END) AS canceladas,
        MAX(ee.respondido_at) AS ultima_respuesta
    FROM encuestas e
    LEFT JOIN senderos s ON s.id = e.sendero_id
    LEFT JOIN encuesta_envios ee ON ee.encuesta_id = e.id
    WHERE {$whereSql}
    GROUP BY e.id
    ORDER BY COALESCE(e.fecha_envio, e.created_at) DESC, e.id DESC
", $types, $values);

$envios = re_fetch_all($conn, "
    SELECT
        ee.id, ee.estado, ee.enviado_at, ee.respondido_at,
        e.id AS encuesta_id, e.titulo, e.anonima,
        s.nombre AS sendero_nombre,
        u.nombre, u.apellido, u.user, u.email
    FROM encuesta_envios ee
    INNER JOIN encuestas e ON e.id = ee.encuesta_id
    LEFT JOIN senderos s ON s.id = COALESCE(ee.sendero_id, e.sendero_id)
    LEFT JOIN usuarios u ON u.id = ee.usuario_id
    WHERE {$whereSql}
    ORDER BY COALESCE(ee.respondido_at, ee.enviado_at) DESC, ee.id DESC
    LIMIT 250
", $types, $values);

$totales = ['encuestas' => count($encuestas), 'envios' => 0, 'respondidas' => 0, 'pendientes' => 0, 'canceladas' => 0];
foreach ($encuestas as $encuesta) {
    $totales['envios'] += (int) $encuesta['envios'];
    $totales['respondidas'] += (int) $encuesta['respondidas'];
    $totales['pendientes'] += (int) $encuesta['pendientes'];
    $totales['canceladas'] += (int) $encuesta['canceladas'];
}
$tasaRespuesta = $totales['envios'] > 0 ? ($totales['respondidas'] / $totales['envios']) * 100 : 0;

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="survey-report-page">
    <section class="survey-report-container">
        <header class="survey-report-hero">
            <div>
                <span>Analitica de experiencia</span>
                <h1>Reporte de encuestas</h1>
                <p>Consulta participacion, respuestas y seguimiento por sendero, periodo y participante.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php"><i data-feather="arrow-left"></i> Volver al panel</a>
        </header>

        <section class="survey-report-filter">
            <div class="survey-report-section-head">
                <div><span>Filtros</span><h2>Consulta consolidada</h2></div>
                <i data-feather="sliders"></i>
            </div>
            <form method="GET">
                <label>Sendero
                    <select name="sendero_id"><option value="0">Todos los senderos</option><?php foreach ($senderos as $sendero): ?><option value="<?= (int) $sendero['id'] ?>" <?= $senderoId === (int) $sendero['id'] ? 'selected' : '' ?>><?= re_h($sendero['nombre']) ?> - <?= re_h(sg_fecha($sendero['fecha_sendero'])) ?></option><?php endforeach; ?></select>
                </label>
                <label>Candidato / participante
                    <select name="usuario_id"><option value="0">Todos los participantes</option><?php foreach ($participantes as $participante): ?><option value="<?= (int) $participante['id'] ?>" <?= $usuarioId === (int) $participante['id'] ? 'selected' : '' ?>><?= re_h(trim($participante['nombre'] . ' ' . $participante['apellido']) ?: $participante['user']) ?></option><?php endforeach; ?></select>
                </label>
                <label>Desde<input type="date" name="desde" value="<?= re_h($desde) ?>"></label>
                <label>Hasta<input type="date" name="hasta" value="<?= re_h($hasta) ?>"></label>
                <button type="submit"><i data-feather="search"></i> Consultar</button>
                <a href="<?= BASE_URL ?>pantallas/reporte_encuestas.php">Limpiar</a>
            </form>
            <?php if ($fechaError): ?><p class="survey-report-error">El rango de fechas no es valido. Revisa las fechas indicadas.</p><?php endif; ?>
        </section>

        <section class="survey-report-metrics" aria-label="Resumen de encuestas filtradas">
            <article><span>Encuestas</span><strong><?= $totales['encuestas'] ?></strong><small>Dentro de los filtros</small></article>
            <article><span>Envios</span><strong><?= $totales['envios'] ?></strong><small>Invitaciones generadas</small></article>
            <article class="ok"><span>Respondidas</span><strong><?= $totales['respondidas'] ?></strong><small><?= number_format($tasaRespuesta, 1) ?>% de participacion</small></article>
            <article class="warn"><span>Pendientes</span><strong><?= $totales['pendientes'] ?></strong><small><?= $totales['canceladas'] ?> canceladas</small></article>
        </section>

        <section class="survey-report-card">
            <div class="survey-report-section-head"><div><span>Resumen</span><h2>Encuestas del periodo</h2></div><i data-feather="bar-chart-2"></i></div>
            <?php if (empty($encuestas)): ?>
                <div class="survey-report-empty"><i data-feather="inbox"></i><h2>Sin resultados</h2><p>No hay encuestas que coincidan con los filtros seleccionados.</p></div>
            <?php else: ?>
                <div class="survey-report-table-wrap"><table><thead><tr><th>Encuesta</th><th>Sendero</th><th>Enviada</th><th>Envios</th><th>Respondidas</th><th>Pendientes</th><th>Participacion</th><th>Detalle</th></tr></thead><tbody>
                    <?php foreach ($encuestas as $encuesta): $tasa = (int) $encuesta['envios'] > 0 ? ((int) $encuesta['respondidas'] / (int) $encuesta['envios']) * 100 : 0; ?>
                        <tr><td><strong><?= re_h($encuesta['titulo']) ?></strong><small><?= re_h(ucfirst($encuesta['estado'])) ?><?= (int) $encuesta['anonima'] === 1 ? ' · Anonima' : '' ?></small></td><td><?= re_h($encuesta['sendero_nombre'] ?: 'Sin sendero') ?></td><td><?= re_h(re_fecha((string) ($encuesta['fecha_envio'] ?: $encuesta['created_at']))) ?></td><td><?= (int) $encuesta['envios'] ?></td><td><?= (int) $encuesta['respondidas'] ?></td><td><?= (int) $encuesta['pendientes'] ?></td><td><span class="survey-report-rate"><?= number_format($tasa, 1) ?>%</span></td><td><a href="<?= BASE_URL ?>pantallas/resultados_encuesta.php?encuesta_id=<?= (int) $encuesta['id'] ?>">Ver resultados</a></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>

        <section class="survey-report-card">
            <div class="survey-report-section-head"><div><span>Seguimiento</span><h2>Participantes e invitaciones</h2></div><i data-feather="users"></i></div>
            <?php if (empty($envios)): ?>
                <div class="survey-report-empty compact"><p>No hay invitaciones para mostrar con estos filtros.</p></div>
            <?php else: ?>
                <div class="survey-report-table-wrap"><table><thead><tr><th>Participante</th><th>Encuesta</th><th>Sendero</th><th>Estado</th><th>Enviada</th><th>Respondida</th></tr></thead><tbody>
                    <?php foreach ($envios as $envio): $anonima = (int) $envio['anonima'] === 1; $nombre = trim((string) $envio['nombre'] . ' ' . (string) $envio['apellido']); ?>
                        <tr><td><strong><?= re_h($anonima ? 'Respuesta anonima' : ($nombre ?: $envio['user'])) ?></strong><?php if (!$anonima): ?><small><?= re_h($envio['email']) ?></small><?php endif; ?></td><td><?= re_h($envio['titulo']) ?></td><td><?= re_h($envio['sendero_nombre'] ?: 'Sin sendero') ?></td><td><span class="survey-report-status <?= re_h($envio['estado']) ?>"><?= re_h(ucfirst($envio['estado'])) ?></span></td><td><?= re_h(re_fecha((string) $envio['enviado_at'])) ?></td><td><?= re_h(re_fecha((string) ($envio['respondido_at'] ?? ''), 'Pendiente')) ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php mysqli_close($conn); include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
