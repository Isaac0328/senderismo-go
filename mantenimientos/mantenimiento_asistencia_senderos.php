<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/cargos.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/filtro_senderos.php';
require_once __DIR__ . '/../componentes/senderos_admin_data.php';
require_once __DIR__ . '/../componentes/personas_sin_cuenta.php';
require_once __DIR__ . '/../componentes/ingresos_cobro.php';
require_once __DIR__ . '/../componentes/menores_asistencia.php';

sg_actualizar_senderos_vencidos($conn);

$pageTitle = "Asistencia por Sendero";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/asistencia_senderos.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/directiva_manual.js"
];

function asis_h($value): string
{
    return sg_h($value);
}

function asis_fecha(?string $fecha, bool $conHora = false): string
{
    return sg_fecha($fecha, $conHora);
}

function asis_money($monto): string
{
    return sg_money($monto, 'Sin monto');
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$senderoFiltros = sgf_params();
$nivelesDificultad = sgf_niveles_dificultad($conn);
[$senderoWhere, $senderoTypes, $senderoValues] = sgf_where($senderoFiltros, 's');

$senderos = sg_admin_senderos_para_asistencia($conn, $senderoWhere, $senderoTypes, $senderoValues);
$senderoSeleccionado = sg_admin_find_row_by_id($senderos, $senderoId);

$registros = [];
$inversionesSendero = [];
$inversionesMenoresSendero = [];
$usuariosDisponibles = [];
$personasSinCuenta = [];
$tallasChalecos = [];
$totalMenores = 0;
$menoresPorRegistro = [];
if ($senderoSeleccionado) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            rs.id AS registro_id,
            rs.fecha_registro,
            rs.asistio,
            rs.fecha_asistencia,
            rs.asistencia_notas,
            rs.registro_origen,
            rs.inversion_id,
            si.nombre AS inversion_nombre,
            si.monto AS inversion_monto,
            COALESCE(u.id, 0) AS usuario_id,
            COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(rs.manual_nombre), ''), 'Sin nombre') AS nombre,
            COALESCE(NULLIF(TRIM(u.apellido), ''), NULLIF(TRIM(rs.manual_apellido), ''), '') AS apellido,
            COALESCE(NULLIF(TRIM(rs.manual_nombre), ''), '') AS manual_nombre,
            COALESCE(NULLIF(TRIM(rs.manual_apellido), ''), '') AS manual_apellido,
            COALESCE(NULLIF(TRIM(rs.manual_telefono), ''), '') AS manual_telefono,
            COALESCE(NULLIF(TRIM(rs.manual_email), ''), '') AS manual_email,
            COALESCE(u.user, CONCAT('manual-', rs.id)) AS user,
            COALESCE(u.email, rs.manual_email, '') AS email,
            COALESCE(NULLIF(TRIM(du.telefono), ''), NULLIF(TRIM(rs.manual_telefono), ''), '') AS telefono,
            COALESCE(m.menores, 0) AS total_menores
        FROM registros_senderos rs
        LEFT JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        LEFT JOIN (
            SELECT registro_id, COUNT(*) AS menores
            FROM registro_sendero_menores
            GROUP BY registro_id
        ) m ON m.registro_id = rs.id
        WHERE rs.sendero_id = ? AND rs.estado = 'registrado'
        ORDER BY rs.asistio DESC, COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(rs.manual_nombre), ''), 'zzz') ASC, COALESCE(NULLIF(TRIM(u.apellido), ''), NULLIF(TRIM(rs.manual_apellido), ''), '') ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $totalMenores += (int) ($row['total_menores'] ?? 0);
        $registros[] = $row;
    }
    mysqli_stmt_close($stmt);
    // Menores de cada inscripcion, con su asistencia propia (se despliegan en la lista).
    $menoresPorRegistro = sg_menores_de_sendero($conn, $senderoId);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, nombre, monto, monto_menor, es_menor
        FROM sendero_inversiones
        WHERE sendero_id = ? AND activo = 1
        ORDER BY orden ASC, monto ASC, nombre ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        if ((int) $row['es_menor'] === 1) {
            $inversionesMenoresSendero[] = $row;
        } else {
            $inversionesSendero[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    if ((int) ($senderoSeleccionado['menores_misma_inversion'] ?? 1) === 1) {
        $inversionesMenoresSendero = $inversionesSendero;
    }

    if ((int) ($senderoSeleccionado['incluye_chaleco_salvavidas'] ?? 0) === 1) {
        $resTallas = mysqli_query($conn, 'SELECT id, nombre, descripcion FROM tallas_chalecos_salvavidas WHERE activo = 1 ORDER BY orden ASC, nombre ASC');
        while ($resTallas && $row = mysqli_fetch_assoc($resTallas)) {
            $tallasChalecos[] = $row;
        }
    }

    $sqlDirectiva = sg_directiva_sql($conn);
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            u.id,
            u.nombre,
            u.apellido,
            u.user,
            u.email,
            du.telefono,
            COALESCE(ro.nombre, '') AS rol_nombre,
            {$sqlDirectiva} AS es_directiva
        FROM usuarios u
        LEFT JOIN roles ro ON ro.id = u.rol_id
        LEFT JOIN detalles_usuarios du ON du.usuario_id = u.id
        LEFT JOIN registros_senderos rs ON rs.usuario_id = u.id AND rs.sendero_id = ? AND rs.estado = 'registrado'
        WHERE u.estado = 1 AND rs.id IS NULL
        ORDER BY u.nombre ASC, u.apellido ASC
        LIMIT 300"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $usuariosDisponibles[] = $row;
    }
    mysqli_stmt_close($stmt);

    // Personas sin cuenta que aun no estan en este sendero.
    if (psc_listo($conn)) {
        $stmt = mysqli_prepare($conn, "SELECT DISTINCT persona_sin_cuenta_id FROM registros_senderos WHERE sendero_id = ? AND persona_sin_cuenta_id IS NOT NULL");
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        mysqli_stmt_execute($stmt);
        $enSendero = array_flip(array_map('intval', array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'persona_sin_cuenta_id')));
        mysqli_stmt_close($stmt);
        $personasSinCuenta = array_values(array_filter(psc_disponibles($conn), static fn($persona) => !isset($enSendero[(int) $persona['id']])));
    }
}

// Estado de pago de cada inscripcion (mismo calculo que Ingresos por sendero), para que
// quien pasa lista sepa a quien cobrarle.
$cobros = [];
$catalogoEstados = [];
if ($senderoSeleccionado && ic_esquema_listo($conn)) {
    foreach (ic_cargar($conn, $senderoId) as $filaCobro) {
        $cobros[(int) $filaCobro['registro_id']] = $filaCobro['calculo'];
    }
    $catalogoEstados = sg_financial_status_catalog($conn);
}

function asis_pago_html(?array $c, array $catalogo): string
{
    if (!$c) {
        return '<span class="asis-pago is-muted">Sin datos</span>';
    }
    $nombre = $catalogo[$c['estado']]['nombre'] ?? sg_financial_status_label($c['estado']);
    $color = $catalogo[$c['estado']]['color'] ?? '#64748B';
    if ($c['tipo'] !== 'automatico') {
        $detalle = 'Sin cobro';
    } elseif ($c['saldo'] > 0) {
        $detalle = ($c['modo'] === 'asistio' ? 'Debe ' : 'Falta ') . ic_dinero($c['saldo']);
    } elseif ($c['modo'] === 'no_asistio') {
        $detalle = '';
    } else {
        $detalle = 'Al dia';
    }
    return '<span class="asis-pago" style="--estado: ' . asis_h($color) . '">' . asis_h($nombre) . '</span>'
        . ($detalle !== '' ? '<small class="asis-pago-detalle' . ($c['saldo'] > 0 ? ' is-debe' : '') . '">' . asis_h($detalle) . '</small>' : '');
}

$totalRegistros = count($registros) + $totalMenores;
$menoresAsistencia = sg_menores_asistencia_listo($conn);
$totalAsistieron = array_sum(array_map(static fn($r) => ((int) ($r['asistio'] ?? 0) === 1 ? 1 + ($menoresAsistencia ? 0 : (int) ($r['total_menores'] ?? 0)) : 0), $registros));
if ($menoresAsistencia) {
    foreach ($menoresPorRegistro as $listaMenores) {
        foreach ($listaMenores as $menorFila) {
            $totalAsistieron += $menorFila['asistio'] ? 1 : 0;
        }
    }
}

// Menores desplegables de una inscripcion; en el celular las casillas copian a las de la tabla.
function asis_menores_html(int $rid, array $menores, bool $movil, bool $listo): string
{
    if (!$menores) {
        return '<strong>0</strong>';
    }
    $marcados = count(array_filter($menores, static fn(array $m): bool => $m['asistio']));
    $html = '<details class="asis-menores"><summary><strong>' . count($menores) . '</strong> <span data-menores-contador="' . $rid . '">'
        . ($listo ? $marcados . '/' . count($menores) . ' asistieron' : 'ver') . '</span></summary><div class="asis-menores-lista">';
    foreach ($menores as $m) {
        $detalle = trim(implode(' - ', array_filter([$m['rango_edad'], $m['inversion'] . ' ' . asis_money($m['monto'])])));
        $comun = ' data-registro-menor="' . $rid . '"' . ($m['asistio'] ? ' checked' : '') . ($listo ? '' : ' disabled');
        $input = $movil
            ? '<input type="checkbox" data-mobile-menor="' . (int) $m['id'] . '"' . $comun . '>'
            : '<input type="checkbox" name="menor_asistio[]" value="' . (int) $m['id'] . '"' . $comun . '>';
        $html .= '<label class="asis-menor">' . $input . '<span><strong>' . asis_h($m['nombre']) . '</strong><small>' . asis_h($detalle) . '</small></span></label>';
    }
    return $html . '</div></details>';
}
$pendientes = max(0, $totalRegistros - $totalAsistieron);

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="asis-page">
    <section class="asis-container">
        <header class="asis-header">
            <div>
                <span class="asis-kicker">Control de asistencia</span>
                <h1>Asistencia por sendero</h1>
                <p>Marca quienes realmente asistieron. Estos datos alimentaran el historial del usuario y futuras metricas, premios e insignias.</p>
            </div>
            <a class="asis-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['asistencia_success'])): ?>
            <div class="asis-alert success"><?= asis_h($_SESSION['asistencia_success']) ?></div>
            <?php unset($_SESSION['asistencia_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['asistencia_error'])): ?>
            <div class="asis-alert error"><?= asis_h($_SESSION['asistencia_error']) ?></div>
            <?php unset($_SESSION['asistencia_error']); ?>
        <?php endif; ?>

        <?php sgf_render([
            'params' => $senderoFiltros,
            'niveles' => $nivelesDificultad,
            'senderos' => $senderos,
            'selected_id' => $senderoId,
            'clear_url' => BASE_URL . 'mantenimientos/mantenimiento_asistencia_senderos.php',
            'card_class' => 'asis-card asis-filter',
            'head_class' => 'asis-card-head',
            'form_class' => 'asis-filter-form',
            'icon' => 'map',
            'option_label' => static function (array $sendero): string {
                $registrados = (int) ($sendero['registrados'] ?? 0);
                $asistieron = (int) ($sendero['asistieron'] ?? 0);
                $km = $sendero['distancia_km'] !== null ? ' - ' . number_format((float) $sendero['distancia_km'], 1) . ' km' : '';
                $dificultad = !empty($sendero['dificultad_nombre']) ? ' - ' . $sendero['dificultad_nombre'] : '';
                return $sendero['nombre'] . ' - ' . asis_fecha($sendero['fecha_sendero']) . $dificultad . $km . " ({$asistieron}/{$registrados} asistieron)";
            },
        ]); ?>

        <?php if (!$senderoSeleccionado): ?>
            <section class="asis-empty">
                <i data-feather="check-circle"></i>
                <h2>Selecciona un sendero</h2>
                <p>Al elegir una ruta veras los inscritos activos para marcar asistencia real.</p>
            </section>
        <?php else: ?>
            <section class="asis-route-banner">
                <div>
                    <span><?= asis_h(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                    <h2><?= asis_h($senderoSeleccionado['nombre']) ?></h2>
                    <p>Fecha: <?= asis_h(asis_fecha($senderoSeleccionado['fecha_sendero'])) ?></p>
                </div>
                <div class="asis-route-side">
                    <?php if (sg_has_permission($conn, 'finanzas.ingresos_sendero')): ?>
                        <a class="asis-route-link" href="<?= asis_h(BASE_URL . 'admin/ingresos-sendero?sendero_id=' . (int) $senderoId) ?>" data-confirmar-salida>
                            <i data-feather="dollar-sign"></i>
                            Registrar ingresos
                        </a>
                    <?php endif; ?>
                    <div class="asis-route-stats">
                        <article>
                            <strong><?= $totalRegistros ?></strong>
                            <span>Inscritos</span>
                        </article>
                        <article class="ok">
                            <strong><?= $totalAsistieron ?></strong>
                            <span>Asistieron</span>
                        </article>
                        <article class="warn">
                            <strong><?= $pendientes ?></strong>
                            <span>Pendientes</span>
                        </article>
                        <article>
                            <strong><?= $totalMenores ?></strong>
                            <span>Menores</span>
                        </article>
                    </div>
                </div>
            </section>

            <section class="asis-card">
                <div class="asis-card-head">
                    <div>
                        <span>Listado</span>
                        <h2>Participantes inscritos</h2>
                    </div>
                    <div class="asis-card-tools">
                        <button type="button" class="asis-open-modal" data-open-participante>
                            <i data-feather="user-plus"></i>
                            Agregar asistente
                        </button>
                        <i data-feather="clipboard"></i>
                    </div>
                </div>

                <?php if (empty($registros)): ?>
                    <div class="asis-empty compact">
                        <i data-feather="user-x"></i>
                        <h2>Sin inscritos activos</h2>
                        <p>Este sendero no tiene reservas activas para marcar asistencia.</p>
                    </div>
                <?php else: ?>
                    <div class="asis-table-search">
                        <i data-feather="search" aria-hidden="true"></i>
                        <label class="asis-sr-only" for="asis-attendance-search">Buscar participante</label>
                        <input id="asis-attendance-search" type="search" data-attendance-search placeholder="Buscar por participante, usuario, contacto, inversion o nota..." autocomplete="off" aria-controls="asis-attendance-table asis-attendance-mobile">
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_asistencia_senderos.php" class="asis-attendance-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                        <div class="asis-tools">
                            <button type="button" data-check-all>Marcar todos</button>
                            <button type="button" data-uncheck-all>Limpiar marcas</button>
                            <button type="submit" class="primary">Guardar asistencia</button>
                        </div>

                        <div class="asis-table-wrap">
                            <table id="asis-attendance-table">
                                <thead>
                                    <tr>
                                        <th>Asistio</th>
                                        <th>Usuario</th>
                                        <th>Contacto</th>
                                        <th>Inversion</th>
                                        <th>Pago</th>
                                        <th>Menores</th>
                                        <th>Registro</th>
                                        <th>Nota</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registros as $registro): ?>
                                        <?php $rid = (int) $registro['registro_id']; ?>
                                        <?php $esManual = ($registro['registro_origen'] ?? '') === 'admin_manual'; ?>
                                        <tr class="<?= (int) $registro['asistio'] === 1 ? 'is-present' : '' ?>" data-attendance-row data-attendance-search="<?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido']) . ' ' . $registro['user'] . ' ' . $registro['telefono'] . ' ' . $registro['email'] . ' ' . $registro['inversion_nombre'] . ' ' . ($registro['asistencia_notas'] ?? '')) ?>">
                                            <td>
                                                <input type="hidden" name="registro_ids[]" value="<?= $rid ?>">
                                                <?php if ($menoresAsistencia): ?><input type="hidden" name="menores_enviados" value="1"><?php endif; ?>
                                                <input type="hidden" name="asistio_original[<?= $rid ?>]" value="<?= (int) $registro['asistio'] ?>">
                                                <input type="hidden" name="nota_original[<?= $rid ?>]" value="<?= asis_h($registro['asistencia_notas'] ?? '') ?>">
                                                <label class="asis-check">
                                                    <input type="checkbox" name="asistio[]" value="<?= $rid ?>" <?= (int) $registro['asistio'] === 1 ? 'checked' : '' ?>>
                                                    <span></span>
                                                </label>
                                            </td>
                                            <td>
                                                <strong><?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?></strong>
                                                <span>@<?= asis_h($registro['user']) ?> / <?= (int) $registro['usuario_id'] > 0 ? 'ID ' . (int) $registro['usuario_id'] : 'Temporal' ?></span>
                                            </td>
                                            <td>
                                                <strong><?= asis_h($registro['telefono'] ?: 'Sin telefono') ?></strong>
                                                <span><?= asis_h($registro['email']) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= asis_h($registro['inversion_nombre'] ?: 'Sin inversion') ?></strong>
                                                <span><?= asis_h(asis_money($registro['inversion_monto'])) ?></span>
                                            </td>
                                            <td class="asis-col-pago"><?= asis_pago_html($cobros[$rid] ?? null, $catalogoEstados) ?></td>
                                            <td class="asis-col-menores"><?= asis_menores_html($rid, $menoresPorRegistro[$rid] ?? [], false, $menoresAsistencia) ?></td>
                                            <td>
                                                <strong><?= asis_h(asis_fecha($registro['fecha_registro'], true)) ?></strong>
                                                <span><?= (int) $registro['asistio'] === 1 ? 'Marcado: ' . asis_h(asis_fecha($registro['fecha_asistencia'], true)) : 'Sin asistencia marcada' ?></span>
                                            </td>
                                            <td>
                                                <input class="asis-note" type="text" name="notas[<?= $rid ?>]" maxlength="255" placeholder="Nota opcional" value="<?= asis_h($registro['asistencia_notas'] ?? '') ?>">
                                            </td>
                                            <td>
                                                <?php if ($esManual): ?>
                                                    <div class="asis-row-actions">
                                                        <button
                                                            type="button"
                                                            class="asis-mini-action"
                                                            data-edit-manual
                                                            data-registro-id="<?= $rid ?>"
                                                            data-nombre="<?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>"
                                                            data-manual-nombre="<?= asis_h($registro['manual_nombre'] ?? '') ?>"
                                                            data-manual-apellido="<?= asis_h($registro['manual_apellido'] ?? '') ?>"
                                                            data-manual-telefono="<?= asis_h($registro['manual_telefono'] ?? '') ?>"
                                                            data-manual-email="<?= asis_h($registro['manual_email'] ?? '') ?>"
                                                            data-inversion-id="<?= (int) ($registro['inversion_id'] ?? 0) ?>"
                                                            data-asistio="<?= (int) ($registro['asistio'] ?? 0) ?>"
                                                            data-nota="<?= asis_h($registro['asistencia_notas'] ?? '') ?>"
                                                        >
                                                            Editar
                                                        </button>
                                                        <button type="submit" form="asis-delete-manual-<?= $rid ?>" class="asis-mini-action danger">
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="asis-origin-pill">Registro publico</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="asis-search-empty" data-attendance-empty hidden>No encontramos participantes con esa busqueda.</div>

                        <div class="asis-mobile-list" id="asis-attendance-mobile">
                            <?php foreach ($registros as $registro): ?>
                                <?php $rid = (int) $registro['registro_id']; ?>
                                <?php $esManual = ($registro['registro_origen'] ?? '') === 'admin_manual'; ?>
                                <article class="asis-user-card <?= (int) $registro['asistio'] === 1 ? 'is-present' : '' ?>" data-attendance-card data-attendance-search="<?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido']) . ' ' . $registro['user'] . ' ' . $registro['telefono'] . ' ' . $registro['email'] . ' ' . $registro['inversion_nombre'] . ' ' . ($registro['asistencia_notas'] ?? '')) ?>">
                                    <div class="asis-user-head">
                                        <div>
                                            <strong><?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?></strong>
                                            <span>@<?= asis_h($registro['user']) ?></span>
                                        </div>
                                        <label class="asis-check mobile">
                                            <input type="checkbox" data-mobile-check="<?= $rid ?>" value="<?= $rid ?>" <?= (int) $registro['asistio'] === 1 ? 'checked' : '' ?>>
                                            <span></span>
                                        </label>
                                    </div>
                                    <p><?= asis_h($registro['telefono'] ?: 'Sin telefono') ?> / <?= asis_h($registro['email']) ?></p>
                                    <p><?= asis_h($registro['inversion_nombre'] ?: 'Sin inversion') ?> - <?= asis_h(asis_money($registro['inversion_monto'])) ?></p>
                                    <div class="asis-pago-movil"><?= asis_pago_html($cobros[$rid] ?? null, $catalogoEstados) ?></div>
                                    <?php if ((int) $registro['total_menores'] > 0): ?><div class="asis-menores-movil"><span>Menores:</span> <?= asis_menores_html($rid, $menoresPorRegistro[$rid] ?? [], true, $menoresAsistencia) ?></div><?php endif; ?>
                                    <small>Menores: <?= (int) $registro['total_menores'] ?> | Registro: <?= asis_h(asis_fecha($registro['fecha_registro'], true)) ?></small>
                                    <input class="asis-note" type="text" data-mobile-note="<?= $rid ?>" maxlength="255" placeholder="Nota opcional" value="<?= asis_h($registro['asistencia_notas'] ?? '') ?>">
                                    <?php if ($esManual): ?>
                                        <div class="asis-row-actions mobile">
                                            <button
                                                type="button"
                                                class="asis-mini-action"
                                                data-edit-manual
                                                data-registro-id="<?= $rid ?>"
                                                data-nombre="<?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>"
                                                data-manual-nombre="<?= asis_h($registro['manual_nombre'] ?? '') ?>"
                                                data-manual-apellido="<?= asis_h($registro['manual_apellido'] ?? '') ?>"
                                                data-manual-telefono="<?= asis_h($registro['manual_telefono'] ?? '') ?>"
                                                data-manual-email="<?= asis_h($registro['manual_email'] ?? '') ?>"
                                                data-inversion-id="<?= (int) ($registro['inversion_id'] ?? 0) ?>"
                                                data-asistio="<?= (int) ($registro['asistio'] ?? 0) ?>"
                                                data-nota="<?= asis_h($registro['asistencia_notas'] ?? '') ?>"
                                            >
                                                Editar
                                            </button>
                                            <button type="submit" form="asis-delete-manual-<?= $rid ?>" class="asis-mini-action danger">
                                                Eliminar
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="asis-origin-pill mobile">Registro publico</span>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="asis-sticky-save">
                            <button type="submit">Guardar asistencia</button>
                        </div>
                    </form>

                    <?php foreach ($registros as $registro): ?>
                        <?php if (($registro['registro_origen'] ?? '') !== 'admin_manual') { continue; } ?>
                        <?php $rid = (int) $registro['registro_id']; ?>
                        <form
                            id="asis-delete-manual-<?= $rid ?>"
                            method="POST"
                            action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php"
                            onsubmit="return confirm('Eliminar este asistente manual del sendero?');"
                            class="asis-hidden-form"
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="eliminar_manual">
                            <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                            <input type="hidden" name="registro_id" value="<?= $rid ?>">
                            <input type="hidden" name="volver_a" value="asistencia">
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <dialog class="asis-modal" data-participante-modal>
                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" class="asis-modal-box" data-participante-form novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="agregar_participante">
                    <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                    <input type="hidden" name="volver_a" value="asistencia">
                    <input type="hidden" name="marcar_asistio" value="1">
                    <input type="hidden" name="registro_id" value="" data-registered-id>

                    <div class="asis-modal-head">
                        <div>
                            <span>Asistencia manual</span>
                            <h2 data-participante-title>Agregar asistente no inscrito</h2>
                        </div>
                        <button type="button" class="asis-modal-close" data-close-participante aria-label="Cerrar">
                            <i data-feather="x"></i>
                        </button>
                    </div>

                    <div class="asis-mode-switch">
                        <label>
                            <input type="radio" name="tipo_participante" value="existente" checked>
                            Usuario existente
                        </label>
                        <label>
                            <input type="radio" name="tipo_participante" value="nuevo">
                            Persona sin cuenta
                        </label>
                        <label>
                            <input type="radio" name="tipo_participante" value="registrado">
                            Agregar menor
                        </label>
                    </div>

                    <div class="asis-form-grid">
                        <label class="asis-field asis-registered-field" hidden>
                            <span>Usuario registrado en este sendero</span>
                            <select data-registered-select>
                                <option value="">Selecciona un usuario</option>
                                <?php foreach ($registros as $registro): ?>
                                    <?php if ((int) $registro['usuario_id'] > 0): ?>
                                        <option value="<?= (int) $registro['registro_id'] ?>">
                                            <?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?> - @<?= asis_h($registro['user']) ?> (<?= (int) $registro['total_menores'] ?> menores)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <small class="asis-field-error" data-field-error="registro_id" role="alert" hidden></small>
                        </label>
                        <div class="asis-field asis-existing-field" data-user-search-root>
                            <span>Elegir usuario <b aria-hidden="true">*</b></span>
                            <input type="hidden" name="usuario_id" value="" data-user-id-input>
                            <input type="search" placeholder="Escribe nombre, usuario, correo o telefono" autocomplete="off" data-user-search-input aria-describedby="asis-user-error">
                            <div class="asis-user-results" data-user-results>
                                <?php foreach ($usuariosDisponibles as $usuario): ?>
                                    <?php
                                    $nombreUsuario = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
                                    $detalleUsuario = '@' . $usuario['user'] . ($usuario['telefono'] ? ' / ' . $usuario['telefono'] : '') . ($usuario['email'] ? ' / ' . $usuario['email'] : '');
                                    $busquedaUsuario = strtolower($nombreUsuario . ' ' . $usuario['user'] . ' ' . $usuario['email'] . ' ' . $usuario['telefono']);
                                    ?>
                                    <button type="button" data-user-option data-directiva="<?= (int) ($usuario['es_directiva'] ?? 0) === 1 ? '1' : '0' ?>" data-user-id="<?= (int) $usuario['id'] ?>" data-user-label="<?= asis_h($nombreUsuario . ' - @' . $usuario['user']) ?>" data-user-search="<?= asis_h($busquedaUsuario) ?>">
                                        <strong><?= asis_h($nombreUsuario) ?></strong>
                                        <span><?= asis_h($detalleUsuario) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <small class="asis-search-note" data-user-empty style="display:none;">No hay coincidencias con esa busqueda.</small>
                            <small class="asis-field-error" id="asis-user-error" data-field-error="usuario_id" role="alert" hidden></small>
                        </div>

                        <div class="asis-new-fields">
                            <?php if ($personasSinCuenta): ?>
                            <label class="asis-field asis-person-field">
                                <span>Persona sin cuenta</span>
                                <select name="persona_sin_cuenta_id" data-person-select>
                                    <option value="">Nueva persona: escribir sus datos</option>
                                    <?php foreach ($personasSinCuenta as $persona): ?>
                                        <option value="<?= (int) $persona['id'] ?>"><?= asis_h(trim($persona['nombre'] . ' ' . $persona['apellido']) . ($persona['telefono'] ? ' - ' . $persona['telefono'] : '') . ' (' . (int) $persona['inscripciones'] . ' senderos)') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Si ya vino antes, eligela para que su historial quede junto.</small>
                            </label>
                            <?php endif; ?>
                            <label class="asis-field">
                                <span>Nombre <b aria-hidden="true">*</b></span>
                                <input type="text" name="nuevo_nombre" maxlength="100" placeholder="Nombre" data-required-new aria-describedby="asis-nombre-error">
                                <small class="asis-field-error" id="asis-nombre-error" data-field-error="nuevo_nombre" role="alert" hidden></small>
                            </label>
                            <label class="asis-field">
                                <span>Apellido <b aria-hidden="true">*</b></span>
                                <input type="text" name="nuevo_apellido" maxlength="100" placeholder="Apellido" data-required-new aria-describedby="asis-apellido-error">
                                <small class="asis-field-error" id="asis-apellido-error" data-field-error="nuevo_apellido" role="alert" hidden></small>
                            </label>
                            <label class="asis-field">
                                <span>Email</span>
                                <input type="email" name="nuevo_email" maxlength="100" placeholder="Opcional">
                            </label>
                            <label class="asis-field">
                                <span>Telefono</span>
                                <input type="text" name="nuevo_telefono" maxlength="20" placeholder="8090000000">
                            </label>
                        </div>

                        <label class="asis-field" data-adult-investment>
                            <span>Inversion <b aria-hidden="true">*</b></span>
                            <select name="inversion_id" required>
                                <option value="">Selecciona una inversion</option>
                                <?php foreach ($inversionesSendero as $inversion): ?>
                                    <option value="<?= (int) $inversion['id'] ?>">
                                        <?= asis_h($inversion['nombre']) ?> - RD$ <?= number_format((float) $inversion['monto'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="asis-field-error" id="asis-inversion-error" data-field-error="inversion_id" role="alert" hidden></small>
                        </label>

                        <?php if ((int) ($senderoSeleccionado['incluye_chaleco_salvavidas'] ?? 0) === 1): ?>
                            <label class="asis-field" data-adult-vest>
                                <span>Talla de chaleco salvavidas *</span>
                                <select name="chaleco_talla_id" required>
                                    <option value="">Selecciona una talla</option>
                                    <?php foreach ($tallasChalecos as $talla): ?>
                                        <option value="<?= (int) $talla['id'] ?>"><?= asis_h($talla['nombre']) ?><?= $talla['descripcion'] ? ' - ' . asis_h($talla['descripcion']) : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>

                        <section class="asis-minors-box" data-minors-root>
                            <div class="asis-minors-head">
                                <div>
                                    <span>Menores asociados</span>
                                    <strong>Acompañantes del participante</strong>
                                </div>
                                <button type="button" class="asis-add-minor" data-add-minor>
                                    <i data-feather="plus"></i> Agregar menor
                                </button>
                            </div>
                            <?php if (!$inversionesMenoresSendero): ?>
                                <p class="asis-search-note">Este sendero necesita una inversion activa para menores antes de agregarlos.</p>
                            <?php endif; ?>
                            <div class="asis-minors-list" data-minors-list></div>
                        </section>

                        <p class="asis-required-note" data-adult-note><b aria-hidden="true">*</b> Campos obligatorios</p>
                        <p class="asis-modal-note" data-temporary-note>Se guardara como persona sin cuenta. Podras reconciliarla con su cuenta cuando la cree, en Usuarios sin cuenta.</p>
                    </div>

                    <div class="asis-modal-actions">
                        <button type="button" class="secondary" data-close-participante>Cancelar</button>
                        <button type="submit" data-participante-submit>Guardar asistente</button>
                    </div>
                </form>
            </dialog>

            <?php include __DIR__ . '/../componentes/menor_sendero_template.php'; ?>

            <dialog class="asis-modal" data-manual-edit-modal>
                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" class="asis-modal-box">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="editar_manual">
                    <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                    <input type="hidden" name="registro_id" value="" data-edit-registro-id>
                    <input type="hidden" name="volver_a" value="asistencia">

                    <div class="asis-modal-head">
                        <div>
                            <span>Asistente manual</span>
                            <h2>Editar asistencia</h2>
                            <p class="asis-modal-subtitle" data-edit-nombre></p>
                        </div>
                        <button type="button" class="asis-modal-close" data-close-manual-edit aria-label="Cerrar">
                            <i data-feather="x"></i>
                        </button>
                    </div>

                    <div class="asis-form-grid">
                        <label class="asis-field">
                            <span>Nombre</span>
                            <input type="text" name="manual_nombre" maxlength="100" placeholder="Nombre del asistente" data-edit-manual-nombre>
                        </label>

                        <label class="asis-field">
                            <span>Apellido</span>
                            <input type="text" name="manual_apellido" maxlength="100" placeholder="Apellido del asistente" data-edit-manual-apellido>
                        </label>

                        <label class="asis-field">
                            <span>Telefono</span>
                            <input type="text" name="manual_telefono" maxlength="20" placeholder="8090000000" data-edit-manual-telefono>
                        </label>

                        <label class="asis-field">
                            <span>Email</span>
                            <input type="email" name="manual_email" maxlength="100" placeholder="Opcional" data-edit-manual-email>
                        </label>

                        <label class="asis-field">
                            <span>Inversion</span>
                            <select name="inversion_id" required data-edit-inversion>
                                <option value="">Selecciona una inversion</option>
                                <?php foreach ($inversionesSendero as $inversion): ?>
                                    <option value="<?= (int) $inversion['id'] ?>">
                                        <?= asis_h($inversion['nombre']) ?> - RD$ <?= number_format((float) $inversion['monto'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="asis-checkline">
                            <input type="checkbox" name="marcar_asistio" value="1" data-edit-asistio>
                            Marcar como asistio a este sendero
                        </label>

                        <label class="asis-field">
                            <span>Nota</span>
                            <input type="text" name="asistencia_notas" maxlength="255" placeholder="Nota opcional" data-edit-nota>
                        </label>
                    </div>

                    <div class="asis-modal-actions">
                        <button type="button" class="secondary" data-close-manual-edit>Cancelar</button>
                        <button type="submit">Guardar cambios</button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-participante-modal]');
    const openButton = document.querySelector('[data-open-participante]');
    const closeButtons = document.querySelectorAll('[data-close-participante]');
    const radios = document.querySelectorAll('input[name="tipo_participante"]');
    const existingField = document.querySelector('.asis-existing-field');
    const newFields = document.querySelector('.asis-new-fields');
    const participanteForm = document.querySelector('[data-participante-form]');
    const registeredField = participanteForm?.querySelector('.asis-registered-field');
    const registeredSelect = participanteForm?.querySelector('[data-registered-select]');
    const registeredId = participanteForm?.querySelector('[data-registered-id]');
    const minorsList = participanteForm?.querySelector('[data-minors-list]');
    const minorTemplate = document.getElementById('asisMinorTemplate');

    // Con una persona sin cuenta elegida no hace falta escribir sus datos.
    const personSelect = document.querySelector('[data-person-select]');
    function syncPerson() {
        const chosen = Boolean(personSelect && personSelect.value);
        newFields?.querySelectorAll('.asis-field:not(.asis-person-field)').forEach(function (field) {
            field.style.display = chosen ? 'none' : '';
            field.querySelectorAll('input').forEach(function (input) { input.disabled = chosen; });
        });
    }
    personSelect?.addEventListener('change', syncPerson);

    function syncMode() {
        const selected = document.querySelector('input[name="tipo_participante"]:checked');
        const isNew = selected && selected.value === 'nuevo';
        const isRegistered = selected && selected.value === 'registrado';
        if (existingField) {
            existingField.style.display = isNew || isRegistered ? 'none' : 'grid';
        }
        if (newFields) {
            newFields.style.display = isNew ? 'grid' : 'none';
        }
        if (registeredField) registeredField.style.display = isRegistered ? 'grid' : 'none';
        if (registeredSelect) registeredSelect.required = Boolean(isRegistered);
        if (registeredId) registeredId.value = isRegistered ? registeredSelect?.value || '' : '';
        if (participanteForm) participanteForm.elements.accion.value = isRegistered ? 'agregar_menores' : 'agregar_participante';
        participanteForm?.querySelectorAll('[data-adult-investment], [data-adult-vest], [data-adult-note]').forEach(function (field) {
            field.style.display = isRegistered ? 'none' : '';
            field.querySelectorAll('input, select').forEach(function (input) { input.disabled = Boolean(isRegistered); });
        });
        syncPerson();
        const temporaryNote = participanteForm?.querySelector('[data-temporary-note]');
        if (temporaryNote) temporaryNote.style.display = isNew ? '' : 'none';
        const title = participanteForm?.querySelector('[data-participante-title]');
        if (title) title.textContent = isRegistered ? 'Agregar menor a usuario inscrito' : 'Agregar persona al sendero';
        const submitButton = participanteForm?.querySelector('[data-participante-submit]');
        if (submitButton) submitButton.textContent = isRegistered ? 'Guardar menor' : 'Guardar asistente';
        participanteForm?.querySelectorAll('[data-field-error]').forEach(function (message) {
            message.hidden = true;
            message.textContent = '';
        });
        participanteForm?.querySelectorAll('.has-field-error').forEach(function (field) {
            field.classList.remove('has-field-error');
        });
        participanteForm?.querySelectorAll('[aria-invalid="true"]').forEach(function (field) {
            field.removeAttribute('aria-invalid');
        });
    }

    function showFieldError(field, fieldName, message) {
        const error = participanteForm?.querySelector('[data-field-error="' + fieldName + '"]');
        field?.setAttribute('aria-invalid', 'true');
        field?.closest('.asis-field, .asis-existing-field')?.classList.add('has-field-error');
        if (error) {
            error.textContent = message;
            error.hidden = false;
        }
    }

    function clearFieldError(field) {
        const name = field?.name || (field?.matches('[data-user-search-input]') ? 'usuario_id' : field?.matches('[data-registered-select]') ? 'registro_id' : '');
        const error = participanteForm?.querySelector('[data-field-error="' + name + '"]');
        field?.removeAttribute('aria-invalid');
        field?.closest('.asis-field, .asis-existing-field')?.classList.remove('has-field-error');
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }
    }

    function initUserSearch() {
        const rootSearch = document.querySelector('[data-user-search-root]');
        if (!rootSearch) return;
        const input = rootSearch.querySelector('[data-user-search-input]');
        const hidden = rootSearch.querySelector('[data-user-id-input]');
        const results = rootSearch.querySelector('[data-user-results]');
        const empty = rootSearch.querySelector('[data-user-empty]');
        const options = Array.from(rootSearch.querySelectorAll('[data-user-option]'));
        if (!input || !hidden || !results) return;

        const render = (clearSelection = false) => {
            const term = input.value.trim().toLowerCase();
            let visible = 0;
            if (clearSelection) {
                hidden.value = '';
            }
            options.forEach((option) => {
                const matches = term === '' || (option.dataset.userSearch || '').includes(term);
                option.hidden = !matches;
                if (matches) visible++;
            });
            if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
        };

        options.forEach((option) => {
            option.addEventListener('click', () => {
                hidden.value = option.dataset.userId || '';
                input.value = option.dataset.userLabel || option.textContent.trim();
                options.forEach((item) => item.hidden = item !== option);
                if (empty) empty.style.display = 'none';
                clearFieldError(input);
            });
        });

        input.addEventListener('input', () => {
            render(true);
            clearFieldError(input);
        });
        input.addEventListener('focus', () => render(false));
        render();
    }

    if (openButton && modal) {
        openButton.addEventListener('click', function () {
            if (typeof modal.showModal === 'function') {
                modal.showModal();
            } else {
                modal.setAttribute('open', 'open');
            }
        });
    }

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            modal.close();
        });
    });

    radios.forEach(function (radio) {
        radio.addEventListener('change', syncMode);
    });
    registeredSelect?.addEventListener('change', function () {
        if (registeredId) registeredId.value = registeredSelect.value;
        clearFieldError(registeredSelect);
    });
    syncMode();
    initUserSearch();

    function refreshMinorNames() {
        minorsList?.querySelectorAll('[data-minor-card]').forEach(function (card, index) {
            card.querySelector('[data-minor-title]').textContent = `Menor ${index + 1}`;
            card.querySelectorAll('[data-minor-name]').forEach(function (field) {
                field.name = `menores[${index}][${field.dataset.minorName}]`;
            });
        });
    }

    participanteForm?.querySelector('[data-add-minor]')?.addEventListener('click', function () {
        if (!minorTemplate || !minorsList) return;
        const card = minorTemplate.content.firstElementChild.cloneNode(true);
        minorsList.appendChild(card);
        card.querySelector('[data-remove-minor]')?.addEventListener('click', function () {
            card.remove();
            refreshMinorNames();
        });
        const allergy = card.querySelector('[data-minor-allergy]');
        const allergyDetail = card.querySelector('[data-minor-allergy-detail]');
        allergy?.addEventListener('change', function () {
            allergyDetail.required = allergy.value === '1';
        });
        refreshMinorNames();
        if (window.feather) window.feather.replace();
        card.querySelector('[data-minor-name="nombre"]')?.focus();
    });

    participanteForm?.addEventListener('reset', function () {
        minorsList?.replaceChildren();
        window.setTimeout(syncMode, 0);
    });

    function initAttendanceSearch() {
        const input = document.querySelector('[data-attendance-search]');
        if (!input) return;
        const tableRows = Array.from(document.querySelectorAll('[data-attendance-row]'));
        const mobileCards = Array.from(document.querySelectorAll('[data-attendance-card]'));
        const emptyMessages = Array.from(document.querySelectorAll('[data-attendance-empty]'));
        const normalize = function (value) {
            return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
        };
        const filter = function () {
            const term = normalize(input.value);
            let matches = 0;
            tableRows.forEach(function (row) {
                const visible = !term || normalize(row.dataset.attendanceSearch || '').includes(term);
                row.hidden = !visible;
                if (visible) matches += 1;
            });
            mobileCards.forEach(function (card) {
                card.hidden = Boolean(term) && !normalize(card.dataset.attendanceSearch || '').includes(term);
            });
            emptyMessages.forEach(function (message) {
                message.hidden = matches !== 0;
            });
        };
        input.addEventListener('input', filter);
    }
    initAttendanceSearch();

    participanteForm?.addEventListener('submit', function (event) {
        const mode = document.querySelector('input[name="tipo_participante"]:checked')?.value;
        const isNew = mode === 'nuevo';
        const isRegistered = mode === 'registrado';
        const required = isRegistered
            ? [[registeredSelect, 'registro_id', 'Selecciona un usuario registrado en este sendero.']]
            : isNew && personSelect && personSelect.value
            ? []
            : isNew
            ? [
                [participanteForm.elements.nuevo_nombre, 'nuevo_nombre', 'Escribe el nombre del asistente.'],
                [participanteForm.elements.nuevo_apellido, 'nuevo_apellido', 'Escribe el apellido del asistente.']
            ]
            : [[participanteForm.querySelector('[data-user-search-input]'), 'usuario_id', 'Selecciona un usuario existente de la lista.']];
        // La directiva no elige inversion (el campo queda deshabilitado).
        if (!isRegistered && !participanteForm.elements.inversion_id.disabled) required.push([participanteForm.elements.inversion_id, 'inversion_id', 'Selecciona una inversion.']);
        if (!isRegistered && participanteForm.elements.chaleco_talla_id) {
            required.push([participanteForm.elements.chaleco_talla_id, 'chaleco_talla_id', 'Selecciona una talla de chaleco.']);
        }

        let firstInvalid = null;
        required.forEach(function ([field, name, message]) {
            const value = name === 'usuario_id'
                ? participanteForm.querySelector('[data-user-id-input]')?.value.trim()
                : field?.value.trim();
            if (!value) {
                showFieldError(field, name, message);
                firstInvalid ??= field;
            } else {
                clearFieldError(field);
            }
        });
        if (isRegistered && !minorsList?.querySelector('[data-minor-card]')) {
            event.preventDefault();
            alert('Agrega al menos un menor.');
            return;
        }
        minorsList?.querySelectorAll('[data-minor-card] input[required], [data-minor-card] select[required]').forEach(function (field) {
            if (!field.checkValidity()) firstInvalid ??= field;
        });
        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.reportValidity();
        }
    });
    participanteForm?.querySelectorAll('input, select').forEach(function (field) {
        field.addEventListener('input', function () { clearFieldError(field); });
        field.addEventListener('change', function () { clearFieldError(field); });
    });

    const editModal = document.querySelector('[data-manual-edit-modal]');
    const editRegistroId = document.querySelector('[data-edit-registro-id]');
    const editNombre = document.querySelector('[data-edit-nombre]');
    const editManualNombre = document.querySelector('[data-edit-manual-nombre]');
    const editManualApellido = document.querySelector('[data-edit-manual-apellido]');
    const editManualTelefono = document.querySelector('[data-edit-manual-telefono]');
    const editManualEmail = document.querySelector('[data-edit-manual-email]');
    const editInversion = document.querySelector('[data-edit-inversion]');
    const editAsistio = document.querySelector('[data-edit-asistio]');
    const editNota = document.querySelector('[data-edit-nota]');

    document.querySelectorAll('[data-close-manual-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            editModal?.close();
        });
    });

    document.querySelectorAll('[data-edit-manual]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!editModal || !editRegistroId || !editInversion || !editAsistio || !editNota) {
                return;
            }

            editRegistroId.value = button.dataset.registroId || '';
            editInversion.value = button.dataset.inversionId || '';
            editAsistio.checked = button.dataset.asistio === '1';
            editNota.value = button.dataset.nota || '';
            if (editManualNombre) editManualNombre.value = button.dataset.manualNombre || '';
            if (editManualApellido) editManualApellido.value = button.dataset.manualApellido || '';
            if (editManualTelefono) editManualTelefono.value = button.dataset.manualTelefono || '';
            if (editManualEmail) editManualEmail.value = button.dataset.manualEmail || '';
            if (editNombre) {
                editNombre.textContent = button.dataset.nombre || 'Completa los datos de la persona sin cuenta';
            }

            if (typeof editModal.showModal === 'function') {
                editModal.showModal();
            } else {
                editModal.setAttribute('open', 'open');
            }
        });
    });

    const root = document.querySelector('.asis-attendance-form');
    if (!root) {
        return;
    }
    const huellaAsistencia = function () {
        return JSON.stringify(Array.from(new FormData(root).entries()));
    };
    const asistenciaInicial = huellaAsistencia();
    let enviandoAsistencia = false;
    const hayCambiosAsistencia = function () {
        return !enviandoAsistencia && huellaAsistencia() !== asistenciaInicial;
    };
    document.querySelectorAll('[data-confirmar-salida]').forEach(function (enlace) {
        enlace.addEventListener('click', function (evento) {
            if (!hayCambiosAsistencia()) return;
            if (!window.confirm('Tienes cambios de asistencia sin guardar. ¿Deseas salir y descartarlos?')) {
                evento.preventDefault();
            }
        });
    });
    window.addEventListener('beforeunload', function (evento) {
        if (!hayCambiosAsistencia()) return;
        evento.preventDefault();
        evento.returnValue = '';
    });
    root.addEventListener('submit', function () {
        enviandoAsistencia = true;
    });
    const checks = () => root.querySelectorAll('input[type="checkbox"][name="asistio[]"]');
    const mobileChecks = () => root.querySelectorAll('input[type="checkbox"][data-mobile-check]');
    const syncRows = () => {
        checks().forEach(function (check) {
            const row = check.closest('tr, .asis-user-card');
            if (row) {
                row.classList.toggle('is-present', check.checked);
            }
            const mobileCheck = root.querySelector('input[data-mobile-check="' + check.value + '"]');
            if (mobileCheck && mobileCheck.checked !== check.checked) {
                mobileCheck.checked = check.checked;
            }
            const mobileCard = mobileCheck ? mobileCheck.closest('.asis-user-card') : null;
            if (mobileCard) {
                mobileCard.classList.toggle('is-present', check.checked);
            }
        });
    };
    root.querySelector('[data-check-all]')?.addEventListener('click', function () {
        checks().forEach(check => check.checked = true);
        syncRows();
    });
    root.querySelector('[data-uncheck-all]')?.addEventListener('click', function () {
        checks().forEach(check => check.checked = false);
        syncRows();
    });
    checks().forEach(check => check.addEventListener('change', syncRows));

    // Menores: la casilla del adulto marca o desmarca a todos sus menores; cada menor se puede cambiar aparte.
    const casillasMenor = (rid) => root.querySelectorAll('input[name="menor_asistio[]"][data-registro-menor="' + rid + '"]');
    const actualizarMenores = (rid) => {
        const reales = Array.from(casillasMenor(rid));
        const marcados = reales.filter(c => c.checked).length;
        root.querySelectorAll('[data-menores-contador="' + rid + '"]').forEach(el => {
            el.textContent = marcados + '/' + reales.length + ' asistieron';
        });
        reales.forEach(real => {
            const movil = root.querySelector('input[data-mobile-menor="' + real.value + '"]');
            if (movil && movil.checked !== real.checked) movil.checked = real.checked;
        });
    };
    checks().forEach(check => check.addEventListener('change', function () {
        casillasMenor(check.value).forEach(c => { c.checked = check.checked; });
        actualizarMenores(check.value);
    }));
    root.querySelectorAll('input[name="menor_asistio[]"]').forEach(real => {
        real.addEventListener('change', () => actualizarMenores(real.dataset.registroMenor));
    });
    root.querySelectorAll('input[data-mobile-menor]').forEach(movil => {
        movil.addEventListener('change', () => {
            const real = root.querySelector('input[name="menor_asistio[]"][value="' + movil.dataset.mobileMenor + '"]');
            if (real) {
                real.checked = movil.checked;
                actualizarMenores(real.dataset.registroMenor);
            }
        });
    });
    ['[data-check-all]', '[data-uncheck-all]'].forEach(selector => {
        root.querySelector(selector)?.addEventListener('click', () => {
            checks().forEach(check => {
                casillasMenor(check.value).forEach(c => { c.checked = check.checked; });
                actualizarMenores(check.value);
            });
        });
    });
    mobileChecks().forEach(function (mobileCheck) {
        mobileCheck.addEventListener('change', function () {
            const realCheck = root.querySelector('input[type="checkbox"][name="asistio[]"][value="' + mobileCheck.value + '"]');
            if (realCheck) {
                realCheck.checked = mobileCheck.checked;
            }
            syncRows();
        });
    });
    root.querySelectorAll('[data-mobile-note]').forEach(function (mobileNote) {
        mobileNote.addEventListener('input', function () {
            const realNote = root.querySelector('input[name="notas[' + mobileNote.dataset.mobileNote + ']"]');
            if (realNote) {
                realNote.value = mobileNote.value;
            }
        });
    });
    syncRows();
});
</script>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
