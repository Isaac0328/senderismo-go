<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/filtro_senderos.php';
require_once __DIR__ . '/../componentes/gastos_sendero.php';
require_once __DIR__ . '/../componentes/ingresos_cobro.php';
require_once __DIR__ . '/../componentes/cuadre_sendero.php';

sg_actualizar_senderos_vencidos($conn);

$pageTitle = "Gastos por Sendero";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css",
    "css/gastos_sendero.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/gastos_sendero.js"
];

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$senderoFiltros = sgf_params();
$nivelesDificultad = sgf_niveles_dificultad($conn);
[$senderoWhere, $senderoTypes, $senderoValues] = sgf_where($senderoFiltros, 's');

$senderos = [];
$resSenderos = sgf_execute_query($conn, "
    SELECT s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, nd.nombre AS dificultad_nombre, COALESCE(SUM(csg.total), 0) AS total_gastos
    FROM senderos s
    LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    LEFT JOIN contabilidad_sendero_gastos csg ON csg.sendero_id = s.id
    {$senderoWhere}
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, nd.nombre
    ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
", $senderoTypes, $senderoValues);
while ($resSenderos && $row = mysqli_fetch_assoc($resSenderos)) {
    $senderos[] = $row;
}
$senderoSeleccionado = null;
foreach ($senderos as $sendero) {
    if ((int) $sendero['id'] === $senderoId) {
        $senderoSeleccionado = $sendero;
        break;
    }
}

$esquemaListo = gs_esquema_listo($conn);
$datos = null;
if ($senderoSeleccionado && $esquemaListo) {
    $lineas = gs_lineas($conn, $senderoId);
    $ingreso = null;
    $asistieron = 0;
    if (ic_esquema_listo($conn)) {
        $totalesIngreso = ic_totales(ic_cargar($conn, $senderoId));
        $ingreso = $totalesIngreso['ingreso'] / 100;
        $asistieron = (int) $totalesIngreso['asistieron'];
    }
    $datos = gs_catalogo($conn) + [
        'lineas' => $lineas,
        'version' => gs_version($lineas),
        'ingreso' => $ingreso,
        'asistieron' => $asistieron,
    ];
}

$mensajeOk = $_SESSION['gastos_sendero_success'] ?? '';
unset($_SESSION['gastos_sendero_success']);

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page gs-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Gastos por sendero</h1>
                <p>Registra lo que costo cada ruta, separado por tipo de gasto, y los otros gastos con su detalle.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if ($mensajeOk !== ''): ?>
            <div class="fin-alert success"><?= sg_h($mensajeOk) ?></div>
        <?php endif; ?>

        <?php sgf_render([
            'conn' => $conn,
            'cuadre_area' => 'gastos',
            'params' => $senderoFiltros,
            'niveles' => $nivelesDificultad,
            'senderos' => $senderos,
            'selected_id' => $senderoId,
            'clear_url' => BASE_URL . 'mantenimientos/mantenimiento_gastos_sendero.php',
            'card_class' => 'fin-card fin-filter-card',
            'head_class' => 'fin-card-head',
            'form_class' => 'fin-filter-form',
            'icon' => 'map',
            'option_label' => static function (array $sendero): string {
                $km = $sendero['distancia_km'] !== null ? ' - ' . number_format((float) $sendero['distancia_km'], 1) . ' km' : '';
                $dificultad = !empty($sendero['dificultad_nombre']) ? ' - ' . $sendero['dificultad_nombre'] : '';
                return $sendero['nombre'] . ' - ' . sg_fecha($sendero['fecha_sendero']) . $dificultad . $km . ' (' . sg_money($sendero['total_gastos']) . ')';
            },
        ]); ?>

        <?php if (!$senderoSeleccionado): ?>
            <section class="fin-empty">
                <i data-feather="dollar-sign"></i>
                <h2>Selecciona un sendero</h2>
                <p>Luego podras registrar sus gastos por tipo y los otros gastos con su detalle.</p>
            </section>
        <?php elseif (!$esquemaListo): ?>
            <div class="fin-alert error">
                Esta pantalla necesita una actualizacion de la base de datos.
                Aplicala en <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_actualizaciones.php">Actualizaciones del sistema</a> y vuelve aqui.
            </div>
        <?php else: ?>
            <div class="gs-app" data-gastos
                 data-endpoint="<?= sg_h(BASE_URL . 'procesos/proceso_gastos_sendero.php') ?>"
                 data-csrf="<?= sg_h(csrf_token()) ?>"
                 data-sendero="<?= (int) $senderoId ?>">
                <section class="fin-card gs-cabecera">
                    <div class="gs-sendero">
                        <div>
                            <span class="gs-sendero-estado"><?= sg_h(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                            <h2><?= sg_h($senderoSeleccionado['nombre']) ?></h2>
                            <p><?= sg_h(sg_fecha($senderoSeleccionado['fecha_sendero'])) ?></p>
                        </div>
                        <?php sg_cuadre_sendero_render($conn, (int) $senderoId, 'gastos'); ?>
                        <a class="fin-head-link" href="<?= BASE_URL ?>mantenimientos/mantenimiento_gastos.php">
                            <i data-feather="book-open"></i> Catalogo de gastos
                        </a>
                    </div>
                    <div class="gs-kpis" data-kpis></div>
                    <div class="gs-reparto" data-reparto></div>
                </section>

                <div class="gs-secciones" data-secciones></div>

                <section class="fin-card gs-otros">
                    <header class="gs-seccion-head">
                        <div>
                            <h3><i data-feather="layers"></i> Otros gastos</h3>
                            <p>Gastos que no estan en el catalogo, agrupados con un nombre. Ej.: "Exploracion" con cada gasto que se hizo.</p>
                        </div>
                        <strong data-otros-total></strong>
                    </header>
                    <div data-grupos></div>
                    <button type="button" class="gs-btn" data-nuevo-grupo><i data-feather="folder-plus"></i>Nuevo grupo de otros gastos</button>
                </section>

                <ul class="gs-errores" data-errores hidden></ul>

                <div class="gs-pie">
                    <div>
                        <span>Total de gastos</span>
                        <strong data-total-general></strong>
                    </div>
                    <span class="gs-estado" data-estado>Sin cambios</span>
                    <button type="button" class="gs-btn is-primary" data-guardar><i data-feather="save"></i>Guardar gastos</button>
                </div>
            </div>
            <script type="application/json" id="gs-datos"><?= json_encode($datos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
        <?php endif; ?>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
