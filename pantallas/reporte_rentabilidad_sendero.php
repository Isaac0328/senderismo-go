<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';
require_once __DIR__ . '/../componentes/filtro_senderos.php';

contabilidad_bootstrap($conn);

$pageTitle = "Rentabilidad por Sendero | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function rrs_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rrs_fecha(?string $fecha): string
{
    $time = $fecha ? strtotime($fecha) : false;
    return $time ? date('d/m/Y', $time) : 'Sin fecha';
}

function rrs_money($value): string
{
    return 'RD$ ' . number_format((float) $value, 2);
}

function rrs_pct(float $value): string
{
    return number_format($value, 2) . '%';
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$senderoFiltros = sgf_params();
$nivelesDificultad = sgf_niveles_dificultad($conn);
[$senderoWhere, $senderoTypes, $senderoValues] = sgf_where($senderoFiltros, 's');

$senderos = [];
$resSenderos = sgf_execute_query($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        s.distancia_km,
        nd.nombre AS dificultad_nombre,
        COALESCE(i.ingresos, 0) AS ingresos,
        COALESCE(g.gastos, 0) AS gastos
    FROM senderos s
    LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    LEFT JOIN (
        SELECT sendero_id, SUM(monto_pagado) AS ingresos
        FROM contabilidad_registro_pagos
        WHERE pagado = 1
        GROUP BY sendero_id
    ) i ON i.sendero_id = s.id
    LEFT JOIN (
        SELECT sendero_id, SUM(total) AS gastos
        FROM contabilidad_sendero_gastos
        GROUP BY sendero_id
    ) g ON g.sendero_id = s.id
    {$senderoWhere}
    ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
", $senderoTypes, $senderoValues);
while ($resSenderos && $row = mysqli_fetch_assoc($resSenderos)) {
    $senderos[] = $row;
}

$sendero = null;
foreach ($senderos as $item) {
    if ((int) $item['id'] === $senderoId) {
        $sendero = $item;
        break;
    }
}

$resumen = [
    'inscritos' => 0,
    'pagados' => 0,
    'asistieron' => 0,
    'ingresos' => 0.0,
    'gastos' => 0.0,
    'utilidad' => 0.0,
    'margen' => 0.0,
    'retorno' => 0.0,
];
$gastosDetalle = [];
$ingresosDetalle = [];

if ($sendero) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            COUNT(rs.id) AS inscritos,
            SUM(CASE WHEN rs.asistio = 1 THEN 1 ELSE 0 END) AS asistieron,
            SUM(CASE WHEN crp.pagado = 1 THEN 1 ELSE 0 END) AS pagados,
            COALESCE(SUM(CASE WHEN crp.pagado = 1 THEN crp.monto_pagado ELSE 0 END), 0) AS ingresos
        FROM registros_senderos rs
        LEFT JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
        WHERE rs.sendero_id = ? AND rs.estado = 'registrado'"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);

    $resumen['inscritos'] = (int) ($row['inscritos'] ?? 0);
    $resumen['pagados'] = (int) ($row['pagados'] ?? 0);
    $resumen['asistieron'] = (int) ($row['asistieron'] ?? 0);
    $resumen['ingresos'] = (float) ($row['ingresos'] ?? 0);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            csg.*,
            cg.nombre AS gasto_nombre,
            cg.unidad,
            ccg.nombre AS categoria_nombre
        FROM contabilidad_sendero_gastos csg
        INNER JOIN contabilidad_gastos_catalogo cg ON cg.id = csg.gasto_id
        LEFT JOIN contabilidad_categoria_gasto ccg ON ccg.id = cg.categoria_gasto_id
        WHERE csg.sendero_id = ?
        ORDER BY ccg.nombre ASC, cg.nombre ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $resumen['gastos'] += (float) $row['total'];
        $gastosDetalle[] = $row;
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            crp.*,
            cmp.nombre AS metodo_nombre,
            u.nombre,
            u.apellido,
            u.user,
            rs.asistio,
            si.nombre AS inversion_nombre
        FROM contabilidad_registro_pagos crp
        INNER JOIN registros_senderos rs ON rs.id = crp.registro_id
        INNER JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        LEFT JOIN contabilidad_metodo_pago cmp ON cmp.id = crp.metodo_pago_id
        WHERE crp.sendero_id = ? AND rs.estado = 'registrado'
        ORDER BY crp.pagado DESC, u.nombre ASC, u.apellido ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $ingresosDetalle[] = $row;
    }
    mysqli_stmt_close($stmt);

    $resumen['utilidad'] = $resumen['ingresos'] - $resumen['gastos'];
    $resumen['margen'] = $resumen['ingresos'] > 0 ? ($resumen['utilidad'] / $resumen['ingresos']) * 100 : 0;
    $resumen['retorno'] = $resumen['gastos'] > 0 ? ($resumen['utilidad'] / $resumen['gastos']) * 100 : 0;
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Reporte financiero</span>
                <h1>Rentabilidad por sendero</h1>
                <p>Consulta un sendero para ver ingresos cobrados, gastos detallados, utilidad y rentabilidad.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php sgf_render([
            'params' => $senderoFiltros,
            'niveles' => $nivelesDificultad,
            'senderos' => $senderos,
            'selected_id' => $senderoId,
            'clear_url' => BASE_URL . 'pantallas/reporte_rentabilidad_sendero.php',
            'card_class' => 'fin-card fin-filter-card',
            'head_class' => 'fin-card-head',
            'form_class' => 'fin-filter-form',
            'icon' => 'search',
            'option_label' => static function (array $item): string {
                $utilidad = (float) $item['ingresos'] - (float) $item['gastos'];
                $km = $item['distancia_km'] !== null ? ' - ' . number_format((float) $item['distancia_km'], 1) . ' km' : '';
                $dificultad = !empty($item['dificultad_nombre']) ? ' - ' . $item['dificultad_nombre'] : '';
                return $item['nombre'] . ' - ' . rrs_fecha($item['fecha_sendero']) . $dificultad . $km . ' (' . rrs_money($utilidad) . ')';
            },
        ]); ?>

        <?php if (!$sendero): ?>
            <section class="fin-empty">
                <i data-feather="bar-chart-2"></i>
                <h2>Selecciona un sendero</h2>
                <p>Veras el detalle financiero completo de la ruta seleccionada.</p>
            </section>
        <?php else: ?>
            <section class="fin-route-banner fin-income-banner">
                <div>
                    <span><?= rrs_h(ucfirst((string) $sendero['estado'])) ?></span>
                    <h2><?= rrs_h($sendero['nombre']) ?></h2>
                    <p>Fecha: <?= rrs_h(rrs_fecha($sendero['fecha_sendero'])) ?></p>
                </div>
                <div class="fin-stat-grid">
                    <article class="money"><span>Ingresos</span><strong><?= rrs_h(rrs_money($resumen['ingresos'])) ?></strong></article>
                    <article class="warn"><span>Gastos</span><strong><?= rrs_h(rrs_money($resumen['gastos'])) ?></strong></article>
                    <article class="<?= $resumen['utilidad'] >= 0 ? 'ok' : 'warn' ?>"><span>Utilidad</span><strong><?= rrs_h(rrs_money($resumen['utilidad'])) ?></strong></article>
                    <article><span>Margen</span><strong><?= rrs_h(rrs_pct($resumen['margen'])) ?></strong></article>
                    <article><span>Retorno gasto</span><strong><?= rrs_h(rrs_pct($resumen['retorno'])) ?></strong></article>
                </div>
            </section>

            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span>Resumen</span>
                        <h2>Participacion y cobros</h2>
                    </div>
                    <i data-feather="users"></i>
                </div>
                <div class="fin-report-summary">
                    <article><span>Inscritos</span><strong><?= (int) $resumen['inscritos'] ?></strong></article>
                    <article><span>Pagados</span><strong><?= (int) $resumen['pagados'] ?></strong></article>
                    <article><span>Asistieron</span><strong><?= (int) $resumen['asistieron'] ?></strong></article>
                </div>
            </section>

            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span>Detalle</span>
                        <h2>Gastos del sendero</h2>
                    </div>
                    <i data-feather="shopping-bag"></i>
                </div>
                <?php if (empty($gastosDetalle)): ?>
                    <div class="fin-empty compact"><h2>Sin gastos registrados</h2><p>Este sendero aun no tiene gastos asignados.</p></div>
                <?php else: ?>
                    <div class="fin-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Gasto</th>
                                    <th>Cantidad</th>
                                    <th>Costo unitario</th>
                                    <th>Total</th>
                                    <th>Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gastosDetalle as $gasto): ?>
                                    <tr>
                                        <td><?= rrs_h($gasto['categoria_nombre'] ?: 'General') ?></td>
                                        <td><strong><?= rrs_h($gasto['gasto_nombre']) ?></strong><span><?= rrs_h($gasto['unidad']) ?></span></td>
                                        <td><?= rrs_h(number_format((float) $gasto['cantidad'], 2)) ?></td>
                                        <td><?= rrs_h(rrs_money($gasto['costo_unitario'])) ?></td>
                                        <td><strong><?= rrs_h(rrs_money($gasto['total'])) ?></strong></td>
                                        <td><?= rrs_h($gasto['nota'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span>Detalle</span>
                        <h2>Ingresos registrados</h2>
                    </div>
                    <i data-feather="credit-card"></i>
                </div>
                <?php if (empty($ingresosDetalle)): ?>
                    <div class="fin-empty compact"><h2>Sin pagos registrados</h2><p>Este sendero aun no tiene ingresos marcados.</p></div>
                <?php else: ?>
                    <div class="fin-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th>Participante</th>
                                    <th>Inversion</th>
                                    <th>Monto</th>
                                    <th>Fecha pago</th>
                                    <th>Metodo</th>
                                    <th>Asistio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ingresosDetalle as $ingreso): ?>
                                    <tr class="<?= (int) $ingreso['pagado'] === 1 ? 'is-paid' : '' ?>">
                                        <td><span class="fin-pill <?= (int) $ingreso['pagado'] === 1 ? 'ok' : 'off' ?>"><?= (int) $ingreso['pagado'] === 1 ? 'Pagado' : 'Pendiente' ?></span></td>
                                        <td><strong><?= rrs_h(trim($ingreso['nombre'] . ' ' . $ingreso['apellido'])) ?></strong><span>@<?= rrs_h($ingreso['user']) ?></span></td>
                                        <td><?= rrs_h($ingreso['inversion_nombre'] ?: 'Sin inversion') ?></td>
                                        <td><strong><?= rrs_h(rrs_money($ingreso['monto_pagado'])) ?></strong></td>
                                        <td><?= rrs_h(rrs_fecha($ingreso['fecha_pago'])) ?></td>
                                        <td><?= rrs_h($ingreso['metodo_nombre'] ?: $ingreso['metodo_pago'] ?: '-') ?></td>
                                        <td><?= (int) $ingreso['asistio'] === 1 ? 'Si' : 'No' ?></td>
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
