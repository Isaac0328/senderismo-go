<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';

sg_actualizar_senderos_vencidos($conn);
contabilidad_bootstrap($conn);

$pageTitle = "Rentabilidad por Fechas | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function rrf_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rrf_fecha(?string $fecha): string
{
    $time = $fecha ? strtotime($fecha) : false;
    return $time ? date('d/m/Y', $time) : 'Sin fecha';
}

function rrf_money($value): string
{
    return 'RD$ ' . number_format((float) $value, 2);
}

function rrf_pct(float $value): string
{
    return number_format($value, 2) . '%';
}

$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));
$desdeValida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde);
$hastaValida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta);

$rows = [];
$totales = [
    'senderos' => 0,
    'inscritos' => 0,
    'pagados' => 0,
    'asistieron' => 0,
    'ingresos' => 0.0,
    'gastos' => 0.0,
    'utilidad' => 0.0,
    'margen' => 0.0,
    'retorno' => 0.0,
];

if ($desdeValida && $hastaValida) {
    if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            s.id,
            s.nombre,
            s.fecha_sendero,
            s.estado,
            COALESCE(r.inscritos, 0) AS inscritos,
            COALESCE(r.asistieron, 0) AS asistieron,
            COALESCE(r.pagados, 0) AS pagados,
            COALESCE(r.ingresos, 0) AS ingresos,
            COALESCE(g.gastos, 0) AS gastos
        FROM senderos s
        LEFT JOIN (
            SELECT
                rs.sendero_id,
                COUNT(rs.id) AS inscritos,
                SUM(CASE WHEN rs.asistio = 1 THEN 1 ELSE 0 END) AS asistieron,
                SUM(CASE WHEN crp.pagado = 1 THEN 1 ELSE 0 END) AS pagados,
                SUM(CASE WHEN crp.pagado = 1 THEN crp.monto_pagado ELSE 0 END) AS ingresos
            FROM registros_senderos rs
            LEFT JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
            WHERE rs.estado = 'registrado'
            GROUP BY rs.sendero_id
        ) r ON r.sendero_id = s.id
        LEFT JOIN (
            SELECT sendero_id, SUM(total) AS gastos
            FROM contabilidad_sendero_gastos
            GROUP BY sendero_id
        ) g ON g.sendero_id = s.id
        WHERE s.fecha_sendero BETWEEN ? AND ?
        ORDER BY s.fecha_sendero ASC, s.nombre ASC"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $row['ingresos'] = (float) $row['ingresos'];
        $row['gastos'] = (float) $row['gastos'];
        $row['utilidad'] = $row['ingresos'] - $row['gastos'];
        $row['margen'] = $row['ingresos'] > 0 ? ($row['utilidad'] / $row['ingresos']) * 100 : 0;
        $row['retorno'] = $row['gastos'] > 0 ? ($row['utilidad'] / $row['gastos']) * 100 : 0;
        $rows[] = $row;

        $totales['senderos']++;
        $totales['inscritos'] += (int) $row['inscritos'];
        $totales['pagados'] += (int) $row['pagados'];
        $totales['asistieron'] += (int) $row['asistieron'];
        $totales['ingresos'] += $row['ingresos'];
        $totales['gastos'] += $row['gastos'];
        $totales['utilidad'] += $row['utilidad'];
    }
    mysqli_stmt_close($stmt);

    $totales['margen'] = $totales['ingresos'] > 0 ? ($totales['utilidad'] / $totales['ingresos']) * 100 : 0;
    $totales['retorno'] = $totales['gastos'] > 0 ? ($totales['utilidad'] / $totales['gastos']) * 100 : 0;
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Reporte financiero</span>
                <h1>Rentabilidad por fechas</h1>
                <p>Selecciona un rango de fechas para resumir ingresos, gastos, utilidad y rentabilidad de los senderos realizados o programados en ese periodo.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <section class="fin-card fin-filter-card">
            <div class="fin-card-head">
                <div>
                    <span>Filtro</span>
                    <h2>Rango de fechas</h2>
                </div>
                <i data-feather="calendar"></i>
            </div>
            <form method="GET" class="fin-filter-form fin-date-filter">
                <input type="date" name="desde" value="<?= rrf_h($desde) ?>" required>
                <input type="date" name="hasta" value="<?= rrf_h($hasta) ?>" required>
                <button type="submit"><i data-feather="search"></i> Consultar</button>
                <a href="<?= BASE_URL ?>pantallas/reporte_rentabilidad_fechas.php">Limpiar</a>
            </form>
        </section>

        <?php if (!$desdeValida || !$hastaValida): ?>
            <section class="fin-empty">
                <i data-feather="calendar"></i>
                <h2>Selecciona un rango</h2>
                <p>El reporte resumira todos los senderos cuya fecha cae dentro del periodo.</p>
            </section>
        <?php else: ?>
            <section class="fin-route-banner fin-income-banner">
                <div>
                    <span>Periodo</span>
                    <h2><?= rrf_h(rrf_fecha($desde)) ?> - <?= rrf_h(rrf_fecha($hasta)) ?></h2>
                    <p><?= (int) $totales['senderos'] ?> senderos encontrados</p>
                </div>
                <div class="fin-stat-grid">
                    <article class="money"><span>Ingresos</span><strong><?= rrf_h(rrf_money($totales['ingresos'])) ?></strong></article>
                    <article class="warn"><span>Gastos</span><strong><?= rrf_h(rrf_money($totales['gastos'])) ?></strong></article>
                    <article class="<?= $totales['utilidad'] >= 0 ? 'ok' : 'warn' ?>"><span>Utilidad</span><strong><?= rrf_h(rrf_money($totales['utilidad'])) ?></strong></article>
                    <article><span>Margen</span><strong><?= rrf_h(rrf_pct($totales['margen'])) ?></strong></article>
                    <article><span>Retorno gasto</span><strong><?= rrf_h(rrf_pct($totales['retorno'])) ?></strong></article>
                </div>
            </section>

            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span>Resumen</span>
                        <h2>Senderos del periodo</h2>
                    </div>
                    <i data-feather="bar-chart-2"></i>
                </div>
                <?php if (empty($rows)): ?>
                    <div class="fin-empty compact">
                        <h2>Sin senderos</h2>
                        <p>No hay senderos registrados dentro de este rango de fechas.</p>
                    </div>
                <?php else: ?>
                    <div class="fin-report-summary">
                        <article><span>Inscritos</span><strong><?= (int) $totales['inscritos'] ?></strong></article>
                        <article><span>Pagados</span><strong><?= (int) $totales['pagados'] ?></strong></article>
                        <article><span>Asistieron</span><strong><?= (int) $totales['asistieron'] ?></strong></article>
                    </div>
                    <div class="fin-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Sendero</th>
                                    <th>Fecha</th>
                                    <th>Inscritos</th>
                                    <th>Pagados</th>
                                    <th>Asistieron</th>
                                    <th>Ingresos</th>
                                    <th>Gastos</th>
                                    <th>Utilidad</th>
                                    <th>Margen</th>
                                    <th>Retorno</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr class="<?= (float) $row['utilidad'] >= 0 ? 'is-paid' : '' ?>">
                                        <td><strong><?= rrf_h($row['nombre']) ?></strong><span><?= rrf_h(ucfirst((string) $row['estado'])) ?></span></td>
                                        <td><?= rrf_h(rrf_fecha($row['fecha_sendero'])) ?></td>
                                        <td><?= (int) $row['inscritos'] ?></td>
                                        <td><?= (int) $row['pagados'] ?></td>
                                        <td><?= (int) $row['asistieron'] ?></td>
                                        <td><strong><?= rrf_h(rrf_money($row['ingresos'])) ?></strong></td>
                                        <td><?= rrf_h(rrf_money($row['gastos'])) ?></td>
                                        <td><strong><?= rrf_h(rrf_money($row['utilidad'])) ?></strong></td>
                                        <td><?= rrf_h(rrf_pct((float) $row['margen'])) ?></td>
                                        <td><?= rrf_h(rrf_pct((float) $row['retorno'])) ?></td>
                                        <td><a class="fin-table-link" href="<?= BASE_URL ?>pantallas/reporte_rentabilidad_sendero.php?sendero_id=<?= (int) $row['id'] ?>">Ver detalle</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
