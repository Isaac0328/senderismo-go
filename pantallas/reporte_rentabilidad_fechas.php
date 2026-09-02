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
    'esperado' => 0.0,
    'cobrado_bruto' => 0.0,
    'credito_aplicado' => 0.0,
    'descuento_autorizado' => 0.0,
    'credito_generado' => 0.0,
    'monto_retenido' => 0.0,
    'por_cobrar' => 0.0,
    'ingreso_neto' => 0.0,
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
            COALESCE(r.esperado, 0) AS esperado,
            COALESCE(r.cobrado_bruto, 0) AS cobrado_bruto,
            COALESCE(r.credito_aplicado, 0) AS credito_aplicado,
            COALESCE(r.descuento_autorizado, 0) AS descuento_autorizado,
            COALESCE(r.credito_generado, 0) AS credito_generado,
            COALESCE(r.monto_retenido, 0) AS monto_retenido,
            COALESCE(r.por_cobrar, 0) AS por_cobrar,
            COALESCE(r.ingreso_neto, 0) AS ingreso_neto,
            COALESCE(g.gastos, 0) AS gastos
        FROM senderos s
        LEFT JOIN (
            SELECT
                rs.sendero_id,
                COALESCE(SUM(1 + COALESCE(m.menores, 0)), 0) AS inscritos,
                COALESCE(SUM(CASE WHEN rs.asistio = 1 THEN 1 + COALESCE(m.menores, 0) ELSE 0 END), 0) AS asistieron,
                COALESCE(SUM(CASE WHEN crp.pagado = 1 THEN 1 + COALESCE(m.menores, 0) ELSE 0 END), 0) AS pagados,
                SUM(CASE WHEN COALESCE(crp.estado_financiero, '') NOT IN ('cortesia', 'exento') THEN COALESCE(crp.monto_esperado, 0) ELSE 0 END) AS esperado,
                SUM(CASE WHEN crp.pagado = 1 THEN COALESCE(crp.monto_pagado, 0) ELSE 0 END) AS cobrado_bruto,
                SUM(COALESCE(crp.credito_aplicado, 0)) AS credito_aplicado,
                SUM(COALESCE(crp.descuento_autorizado, 0)) AS descuento_autorizado,
                SUM(COALESCE(crp.credito_generado, 0)) AS credito_generado,
                SUM(COALESCE(crp.monto_retenido, 0)) AS monto_retenido,
                SUM(
                    CASE
                        WHEN COALESCE(crp.estado_financiero, '') IN ('cortesia', 'exento', 'no_asistio_sin_pago') THEN 0
                        ELSE COALESCE(crp.saldo_pendiente, 0)
                    END
                ) AS por_cobrar,
                SUM(
                    CASE
                        WHEN COALESCE(crp.estado_financiero, '') IN ('cortesia', 'exento') THEN 0
                        ELSE GREATEST(COALESCE(crp.monto_pagado, 0) + COALESCE(crp.credito_aplicado, 0) - COALESCE(crp.descuento_autorizado, 0) - COALESCE(crp.credito_generado, 0), 0)
                    END
                ) AS ingreso_neto
            FROM registros_senderos rs
            LEFT JOIN (
                SELECT registro_id, COUNT(*) AS menores
                FROM registro_sendero_menores
                GROUP BY registro_id
            ) m ON m.registro_id = rs.id
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
        $row['esperado'] = (float) $row['esperado'];
        $row['cobrado_bruto'] = (float) $row['cobrado_bruto'];
        $row['credito_aplicado'] = (float) $row['credito_aplicado'];
        $row['descuento_autorizado'] = (float) $row['descuento_autorizado'];
        $row['credito_generado'] = (float) $row['credito_generado'];
        $row['monto_retenido'] = (float) $row['monto_retenido'];
        $row['por_cobrar'] = (float) $row['por_cobrar'];
        $row['ingreso_neto'] = (float) $row['ingreso_neto'];
        $row['gastos'] = (float) $row['gastos'];
        $row['utilidad'] = $row['ingreso_neto'] - $row['gastos'];
        $row['margen'] = $row['ingreso_neto'] > 0 ? ($row['utilidad'] / $row['ingreso_neto']) * 100 : 0;
        $row['retorno'] = $row['gastos'] > 0 ? ($row['utilidad'] / $row['gastos']) * 100 : 0;
        $rows[] = $row;

        $totales['senderos']++;
        $totales['inscritos'] += (int) $row['inscritos'];
        $totales['pagados'] += (int) $row['pagados'];
        $totales['asistieron'] += (int) $row['asistieron'];
        $totales['esperado'] += $row['esperado'];
        $totales['cobrado_bruto'] += $row['cobrado_bruto'];
        $totales['credito_aplicado'] += $row['credito_aplicado'];
        $totales['descuento_autorizado'] += $row['descuento_autorizado'];
        $totales['credito_generado'] += $row['credito_generado'];
        $totales['monto_retenido'] += $row['monto_retenido'];
        $totales['por_cobrar'] += $row['por_cobrar'];
        $totales['ingreso_neto'] += $row['ingreso_neto'];
        $totales['gastos'] += $row['gastos'];
        $totales['utilidad'] += $row['utilidad'];
    }
    mysqli_stmt_close($stmt);

    $totales['margen'] = $totales['ingreso_neto'] > 0 ? ($totales['utilidad'] / $totales['ingreso_neto']) * 100 : 0;
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
                <p>Selecciona un rango para resumir efectivo cobrado, creditos, gastos, utilidad neta y rentabilidad de los senderos del periodo.</p>
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
                    <article class="money"><span>Ingreso neto</span><strong><?= rrf_h(rrf_money($totales['ingreso_neto'])) ?></strong></article>
                    <article><span>Cobrado bruto</span><strong><?= rrf_h(rrf_money($totales['cobrado_bruto'])) ?></strong></article>
                    <article><span>Credito aplicado</span><strong><?= rrf_h(rrf_money($totales['credito_aplicado'])) ?></strong></article>
                    <article><span>Descuento</span><strong><?= rrf_h(rrf_money($totales['descuento_autorizado'])) ?></strong></article>
                    <article class="warn"><span>Credito abonado</span><strong><?= rrf_h(rrf_money($totales['credito_generado'])) ?></strong></article>
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
                        <article><span>Esperado</span><strong><?= rrf_h(rrf_money($totales['esperado'])) ?></strong></article>
                        <article><span>Ajuste autorizado</span><strong><?= rrf_h(rrf_money($totales['descuento_autorizado'])) ?></strong></article>
                        <article><span>Retenido</span><strong><?= rrf_h(rrf_money($totales['monto_retenido'])) ?></strong></article>
                        <article><span>Por cobrar</span><strong><?= rrf_h(rrf_money($totales['por_cobrar'])) ?></strong></article>
                    </div>
                    <div class="fin-table-wrap fin-date-profit-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Sendero</th>
                                    <th>Fecha</th>
                                    <th>Inscritos</th>
                                    <th>Pagados</th>
                                    <th>Asistieron</th>
                                    <th>Esperado</th>
                                    <th>Ingreso neto</th>
                                    <th>Cobrado bruto</th>
                                    <th>Credito aplicado</th>
                                    <th>Descuento</th>
                                    <th>Credito abonado</th>
                                    <th>Por cobrar</th>
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
                                        <td><?= rrf_h(rrf_money($row['esperado'])) ?></td>
                                        <td><strong><?= rrf_h(rrf_money($row['ingreso_neto'])) ?></strong></td>
                                        <td><?= rrf_h(rrf_money($row['cobrado_bruto'])) ?></td>
                                        <td><?= rrf_h(rrf_money($row['credito_aplicado'])) ?></td>
                                        <td><?= rrf_h(rrf_money($row['descuento_autorizado'])) ?></td>
                                        <td><?= rrf_h(rrf_money($row['credito_generado'])) ?></td>
                                        <td><?= rrf_h(rrf_money($row['por_cobrar'])) ?></td>
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
