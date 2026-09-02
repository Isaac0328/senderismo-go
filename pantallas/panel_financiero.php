<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'finanzas.panel';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';
require_once __DIR__ . '/../componentes/finanzas_metricas.php';

contabilidad_bootstrap($conn);

function pf_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pf_money($value): string
{
    return 'RD$ ' . number_format((float) $value, 2);
}

function pf_pct($value): string
{
    return number_format((float) $value, 1) . '%';
}

function pf_date(string $value): string
{
    $time = strtotime($value);
    return $time ? date('d/m/Y', $time) : $value;
}

function pf_month(string $month): string
{
    $names = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
    [$year, $number] = array_pad(explode('-', $month, 2), 2, '');
    return ($names[$number] ?? $month) . ' ' . $year;
}

$today = date('Y-m-d');
$defaultFrom = date('Y-01-01');
$from = trim((string) ($_GET['desde'] ?? $defaultFrom));
$to = trim((string) ($_GET['hasta'] ?? $today));

if (!sg_finanzas_fecha_valida($from)) {
    $from = $defaultFrom;
}
if (!sg_finanzas_fecha_valida($to)) {
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$data = sg_finanzas_resumen_periodo($conn, $from, $to);
$totals = $data['totales'];
$methods = sg_finanzas_metodos_periodo($conn, $from, $to);
$activeCredits = sg_finanzas_creditos_activos($conn);
$maxChart = 1.0;
foreach ($data['meses'] as $month) {
    $maxChart = max($maxChart, $month['ingresos'], $month['gastos']);
}
$maxMethod = !empty($methods) ? max(array_column($methods, 'total')) : 1.0;

$alerts = [];
foreach ($data['senderos'] as $trail) {
    if ($trail['por_cobrar'] > 0) {
        $alerts[] = ['warning', 'Cobro pendiente', $trail['nombre'], pf_money($trail['por_cobrar']), $trail['id']];
    }
    if ($trail['gastos'] <= 0 && $trail['ingreso_reconocido'] > 0) {
        $alerts[] = ['neutral', 'Faltan gastos', $trail['nombre'], 'Tiene ingresos sin costos registrados', $trail['id']];
    }
    if ($trail['utilidad'] < 0) {
        $alerts[] = ['danger', 'Resultado negativo', $trail['nombre'], pf_money($trail['utilidad']), $trail['id']];
    }
}
$alerts = array_slice($alerts, 0, 6);

$pageTitle = 'Centro Financiero | Senderismo Go!';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/panel_financiero.css'];
$jsFiles = ['js/barra_navegacion.js'];

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="finance-dashboard">
    <div class="finance-dashboard-container">
        <header class="finance-dashboard-header">
            <div>
                <span class="finance-eyebrow">Centro financiero</span>
                <h1>Panel financiero</h1>
                <p>Lectura consolidada de cobros, creditos, gastos y rentabilidad.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="finance-back"><i data-feather="arrow-left"></i>Volver al panel</a>
        </header>

        <section class="finance-toolbar">
            <form method="get" class="finance-period-form">
                <label>Desde<input type="date" name="desde" value="<?= pf_h($from) ?>" required></label>
                <label>Hasta<input type="date" name="hasta" value="<?= pf_h($to) ?>" required></label>
                <button type="submit"><i data-feather="filter"></i>Aplicar</button>
            </form>
            <div class="finance-presets">
                <a href="?desde=<?= date('Y-m-01') ?>&hasta=<?= $today ?>">Este mes</a>
                <a href="?desde=<?= date('Y-m-d', strtotime('-89 days')) ?>&hasta=<?= $today ?>">90 dias</a>
                <a href="?desde=<?= date('Y-01-01') ?>&hasta=<?= $today ?>">Este ano</a>
            </div>
        </section>

        <section class="finance-kpi-grid">
            <article class="finance-kpi is-primary"><span><i data-feather="trending-up"></i>Ingreso reconocido</span><strong><?= pf_h(pf_money($totals['ingreso_reconocido'])) ?></strong><small>Cobrado + credito aplicado - credito generado</small></article>
            <article class="finance-kpi"><span><i data-feather="credit-card"></i>Cobrado bruto</span><strong><?= pf_h(pf_money($totals['cobrado_bruto'])) ?></strong><small><?= (int) $totals['pagos_registrados'] ?> operaciones con valor</small></article>
            <article class="finance-kpi is-warning"><span><i data-feather="shopping-bag"></i>Gastos</span><strong><?= pf_h(pf_money($totals['gastos'])) ?></strong><small>Costos registrados en el periodo</small></article>
            <article class="finance-kpi <?= $totals['utilidad'] >= 0 ? 'is-success' : 'is-danger' ?>"><span><i data-feather="activity"></i>Utilidad</span><strong><?= pf_h(pf_money($totals['utilidad'])) ?></strong><small>Margen <?= pf_h(pf_pct($totals['margen'])) ?></small></article>
            <article class="finance-kpi"><span><i data-feather="refresh-cw"></i>Credito aplicado</span><strong><?= pf_h(pf_money($totals['credito_aplicado'])) ?></strong><small>Fondos anteriores usados</small></article>
            <article class="finance-kpi"><span><i data-feather="percent"></i>Descuento autorizado</span><strong><?= pf_h(pf_money($totals['descuento_autorizado'] ?? 0)) ?></strong><small>Ajustes que cierran saldos sin cobrar</small></article>
            <article class="finance-kpi"><span><i data-feather="plus-circle"></i>Credito generado</span><strong><?= pf_h(pf_money($totals['credito_generado'])) ?></strong><small>Nuevos compromisos a favor</small></article>
            <article class="finance-kpi is-warning"><span><i data-feather="clock"></i>Por cobrar</span><strong><?= pf_h(pf_money($totals['por_cobrar'])) ?></strong><small><?= (int) $totals['cuentas_pendientes'] ?> cuentas pendientes</small></article>
            <article class="finance-kpi"><span><i data-feather="shield"></i>Creditos activos</span><strong><?= pf_h(pf_money($activeCredits['saldo'])) ?></strong><small><?= (int) $activeCredits['cuentas'] ?> saldos disponibles actualmente</small></article>
        </section>

        <section class="finance-main-grid">
            <article class="finance-panel finance-trend-panel">
                <header><div><span>Tendencia</span><h2>Ingresos frente a gastos</h2></div><small><?= pf_h(pf_date($from)) ?> - <?= pf_h(pf_date($to)) ?></small></header>
                <?php if (empty($data['meses'])): ?>
                    <div class="finance-empty">No hay movimientos para este periodo.</div>
                <?php else: ?>
                    <div class="finance-chart">
                        <?php foreach ($data['meses'] as $month): ?>
                            <div class="finance-chart-column">
                                <div class="finance-chart-bars">
                                    <span class="income" style="height: <?= max(3, ($month['ingresos'] / $maxChart) * 100) ?>%" title="Ingresos: <?= pf_h(pf_money($month['ingresos'])) ?>"></span>
                                    <span class="expense" style="height: <?= max(3, ($month['gastos'] / $maxChart) * 100) ?>%" title="Gastos: <?= pf_h(pf_money($month['gastos'])) ?>"></span>
                                </div>
                                <strong><?= pf_h(pf_month($month['mes'])) ?></strong>
                                <small><?= (int) $month['senderos'] ?> rutas</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="finance-chart-legend"><span class="income"></span>Ingresos <span class="expense"></span>Gastos</div>
                <?php endif; ?>
            </article>

            <article class="finance-panel finance-health-panel">
                <header><div><span>Salud financiera</span><h2>Indicadores del periodo</h2></div><i data-feather="pie-chart"></i></header>
                <div class="finance-health-list">
                    <div><span>Margen neto</span><strong><?= pf_h(pf_pct($totals['margen'])) ?></strong></div>
                    <div><span>Retorno sobre gasto</span><strong><?= pf_h(pf_pct($totals['retorno'])) ?></strong></div>
                    <div><span>Monto esperado</span><strong><?= pf_h(pf_money($totals['esperado'])) ?></strong></div>
                    <div><span>Descuento autorizado</span><strong><?= pf_h(pf_money($totals['descuento_autorizado'] ?? 0)) ?></strong></div>
                    <div><span>Monto retenido</span><strong><?= pf_h(pf_money($totals['monto_retenido'])) ?></strong></div>
                    <div><span>Senderos analizados</span><strong><?= (int) $totals['senderos'] ?></strong></div>
                </div>
            </article>
        </section>

        <section class="finance-lower-grid">
            <article class="finance-panel">
                <header><div><span>Comparativo</span><h2>Rentabilidad por sendero</h2></div><a href="<?= BASE_URL ?>pantallas/reporte_rentabilidad_fechas.php?desde=<?= pf_h($from) ?>&hasta=<?= pf_h($to) ?>">Ver reporte</a></header>
                <div class="finance-ranking">
                    <?php if (empty($data['ranking'])): ?><div class="finance-empty">Sin senderos para comparar.</div><?php endif; ?>
                    <?php foreach ($data['ranking'] as $index => $trail): ?>
                        <a href="<?= BASE_URL ?>pantallas/reporte_rentabilidad_sendero.php?sendero_id=<?= (int) $trail['id'] ?>">
                            <span class="rank-number"><?= $index + 1 ?></span>
                            <span class="rank-copy"><strong><?= pf_h($trail['nombre']) ?></strong><small><?= pf_h(pf_date($trail['fecha_sendero'])) ?> · Margen <?= pf_h(pf_pct($trail['margen'])) ?></small></span>
                            <strong class="rank-value <?= $trail['utilidad'] >= 0 ? 'positive' : 'negative' ?>"><?= pf_h(pf_money($trail['utilidad'])) ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="finance-panel">
                <header><div><span>Tesoreria</span><h2>Metodos de cobro</h2></div><i data-feather="briefcase"></i></header>
                <div class="finance-methods">
                    <?php if (empty($methods)): ?><div class="finance-empty">No hay cobros clasificados.</div><?php endif; ?>
                    <?php foreach ($methods as $method): ?>
                        <div><span><strong><?= pf_h($method['metodo']) ?></strong><small><?= (int) $method['operaciones'] ?> operaciones</small></span><b><?= pf_h(pf_money($method['total'])) ?></b><i style="width: <?= max(4, ($method['total'] / max($maxMethod, 1)) * 100) ?>%"></i></div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="finance-panel finance-alerts">
            <header><div><span>Control</span><h2>Alertas que requieren revision</h2></div><i data-feather="alert-circle"></i></header>
            <?php if (empty($alerts)): ?>
                <div class="finance-empty is-good"><i data-feather="check-circle"></i>No hay alertas financieras en el periodo.</div>
            <?php else: ?>
                <div class="finance-alert-grid">
                    <?php foreach ($alerts as $alert): ?>
                        <a class="<?= pf_h($alert[0]) ?>" href="<?= BASE_URL ?>pantallas/reporte_rentabilidad_sendero.php?sendero_id=<?= (int) $alert[4] ?>">
                            <i data-feather="<?= $alert[0] === 'danger' ? 'trending-down' : ($alert[0] === 'warning' ? 'clock' : 'file-text') ?>"></i>
                            <span><small><?= pf_h($alert[1]) ?></small><strong><?= pf_h($alert[2]) ?></strong></span><b><?= pf_h($alert[3]) ?></b>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
