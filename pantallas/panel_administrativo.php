<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/menu_panel.php';

sg_actualizar_senderos_vencidos($conn);
$adminSiteIdentity = sg_site_identity($conn);

$pageTitle = "Panel Administrativo";

$cssFiles = [
    "css/global.css",
    "css/panel_administrativo.css"
];

$jsFiles = [
    "js/panel_administrativo.js"
];

$stats = require __DIR__ . '/../componentes/estadisticas_admin.php';
require_once __DIR__ . '/../componentes/panel_tarjetas.php';

function admin_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_money($value): string
{
    return 'RD$ ' . number_format((float) $value, 2);
}

function admin_date(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return 'Sin fecha';
    }
    $time = strtotime($date);
    return $time ? date('d/m/Y', $time) : 'Sin fecha';
}

function admin_days_left(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return 'Sin fecha';
    }
    $today = new DateTime('today');
    $event = new DateTime($date);
    $days = (int) $today->diff($event)->format('%r%a');
    if ($days === 0) {
        return 'Hoy';
    }
    if ($days === 1) {
        return 'Manana';
    }
    return $days > 1 ? "En {$days} dias" : 'Vencido';
}

function admin_item_permission(array $item): string
{
    return (string) ($item['permission'] ?? '');
}

function admin_can_view(mysqli $conn, array|string $permission): bool
{
    return sg_has_permission($conn, $permission);
}

function admin_filter_items(mysqli $conn, array $items): array
{
    return array_values(array_filter($items, static function (array $item) use ($conn): bool {
        $permission = admin_item_permission($item);
        return $permission === '' || admin_can_view($conn, $permission);
    }));
}

function admin_filter_groups(mysqli $conn, array $groups): array
{
    $filtered = [];
    foreach ($groups as $group) {
        if (isset($group['sections'])) {
            $sections = [];
            foreach ($group['sections'] as $section) {
                $items = admin_filter_items($conn, $section['items'] ?? []);
                if (!empty($items)) {
                    $section['items'] = $items;
                    $sections[] = $section;
                }
            }
            if (!empty($sections)) {
                $group['sections'] = $sections;
                $filtered[] = $group;
            }
            continue;
        }

        $items = admin_filter_items($conn, $group['items'] ?? []);
        if (!empty($items)) {
            $group['items'] = $items;
            $filtered[] = $group;
        }
    }

    return $filtered;
}

$adminName = $_SESSION['usuario_nombre'] ?? 'Administrador';
$adminRole = $_SESSION['usuario_rol'] ?? $_SESSION['rol_nombre'] ?? 'Administrador';
$firstAdminName = trim(explode(' ', trim($adminName))[0] ?? '') ?: 'Admin';

$moduleGroups = admin_filter_groups($conn, sg_admin_menu_groups($conn, $stats));

// Devuelve la URL solo si el rol puede abrir ese modulo.
$adminUrl = static function (string $permission, string $path) use ($conn): string {
    return admin_can_view($conn, $permission) ? BASE_URL . $path : '';
};
$puedeVerFinanzas = admin_can_view($conn, 'finanzas.panel');

// Tarjetas del panel: cuales se muestran y en que orden segun la configuracion (y el rol actual).
$tarjetasPanel = sg_panel_tarjetas_config($conn);
$rolPanel = (int) ($_SESSION['usuario_rol_id'] ?? 0);
$ver = static fn(string $codigo): bool => sg_panel_tarjeta_visible($tarjetasPanel, $codigo, $rolPanel);

$quickActions = admin_filter_items($conn, [
    ['Nuevo sendero', 'Crear una salida o ruta de catalogo', 'plus-circle', BASE_URL . 'mantenimientos/mantenimiento_senderos.php?nuevo=1', 'permission' => 'operaciones.senderos'],
    ['Participantes', 'Reservas y participantes por sendero', 'users', BASE_URL . 'mantenimientos/mantenimiento_usuarios_senderos.php', 'permission' => 'operaciones.usuarios_senderos'],
    ['Asistencia', 'Confirmar quienes fueron al sendero', 'check-square', BASE_URL . 'mantenimientos/mantenimiento_asistencia_senderos.php', 'permission' => 'operaciones.asistencia'],
    ['Ingresos', 'Pagos, creditos, cortesias y exentos', 'credit-card', BASE_URL . 'mantenimientos/mantenimiento_ingresos_sendero.php', 'permission' => 'finanzas.ingresos_sendero'],
    ['Gastos', 'Costos reales de cada ruta', 'shopping-bag', BASE_URL . 'mantenimientos/mantenimiento_gastos_sendero.php', 'permission' => 'finanzas.gastos_sendero'],
    ['Usuarios', 'Agregar o completar un participante', 'user-plus', BASE_URL . 'mantenimientos/mantenimiento_usuarios.php', 'permission' => 'usuarios.usuarios'],
    ['Mensajes', 'Solicitudes enviadas desde la web', 'mail', BASE_URL . 'pantallas/reporte_contacto.php', 'permission' => 'reportes.contactos'],
    ['Finanzas', 'Indicadores y rentabilidad', 'bar-chart-2', BASE_URL . 'pantallas/panel_financiero.php', 'permission' => 'finanzas.panel'],
]);

// Accesos rapidos: la barra completa y cada boton se pueden ocultar y reordenar.
$codigoAcceso = [
    'Nuevo sendero' => 'acceso_nuevo_sendero', 'Participantes' => 'acceso_participantes', 'Asistencia' => 'acceso_asistencia', 'Ingresos' => 'acceso_ingresos',
    'Gastos' => 'acceso_gastos', 'Usuarios' => 'acceso_usuarios', 'Mensajes' => 'acceso_mensajes', 'Finanzas' => 'acceso_finanzas',
];
$quickActions = $ver('accesos_rapidos')
    ? sg_panel_tarjetas_ordenar(
        array_values(array_filter($quickActions, static fn(array $accion): bool => $ver($codigoAcceso[$accion[0]] ?? ''))),
        $tarjetasPanel,
        static fn(array $accion): string => $codigoAcceso[$accion[0]] ?? ''
    )
    : [];

$balanceTotal = (float) $stats['ingresosTotales'] - (float) $stats['gastosTotales'];

// Indicadores: cada uno abre su modulo y compara con los 30 dias anteriores.
$indicadores = [
    [
        'titulo' => 'Proximas rutas', 'icono' => 'calendar', 'principal' => true,
        'valor' => $stats['senderosProximos'], 'tendencia' => null,
        'detalle' => (int) $stats['registrosActivos'] . ' registros activos en la plataforma',
        'url' => $adminUrl('operaciones.senderos', 'mantenimientos/mantenimiento_senderos.php'),
    ],
    [
        'titulo' => 'Usuarios', 'icono' => 'users', 'principal' => false,
        'valor' => $stats['totalUsuarios'], 'tendencia' => (int) $stats['nuevos30d'] - (int) $stats['nuevosPrev30d'],
        'detalle' => (int) $stats['nuevos30d'] . ' nuevos en 30 dias / ' . (int) $stats['usuariosActivos'] . ' activos',
        'url' => $adminUrl('usuarios.usuarios', 'mantenimientos/mantenimiento_usuarios.php'),
    ],
    [
        'titulo' => 'Registros', 'icono' => 'clipboard', 'principal' => false,
        'valor' => $stats['registros30d'], 'tendencia' => (int) $stats['registros30d'] - (int) $stats['registrosPrev30d'],
        'detalle' => 'Inscripciones en 30 dias / ' . (int) $stats['asistencias'] . ' asistencias confirmadas',
        'url' => $adminUrl('operaciones.usuarios_senderos', 'mantenimientos/mantenimiento_usuarios_senderos.php'),
    ],
    [
        'titulo' => 'Contacto', 'icono' => 'mail', 'principal' => false,
        'valor' => $stats['mensajesNuevos'], 'tendencia' => null,
        'detalle' => 'Mensajes sin leer / ' . (int) $stats['mensajes7d'] . ' recibidos en 7 dias',
        'url' => $adminUrl('reportes.contactos', 'pantallas/reporte_contacto.php'),
    ],
];

// Indicadores: cada uno se puede ocultar y reordenar (el primero que quede se muestra como principal solo si ya lo era).
$codigoIndicador = ['Proximas rutas' => 'kpi_rutas', 'Usuarios' => 'kpi_usuarios', 'Registros' => 'kpi_registros', 'Contacto' => 'kpi_contacto'];
$indicadores = sg_panel_tarjetas_ordenar(
    array_values(array_filter($indicadores, static fn(array $indicador): bool => $ver($codigoIndicador[$indicador['titulo']] ?? ''))),
    $tarjetasPanel,
    static fn(array $indicador): string => $codigoIndicador[$indicador['titulo']] ?? ''
);

// Proximo sendero con accesos directos a sus modulos.
$proximo = $stats['proximoSendero'];
$accionesProximo = [];
if ($proximo) {
    $filtroSendero = '?sendero_id=' . (int) $proximo['id'];
    foreach ([
        ['Participantes', 'users', $adminUrl('operaciones.usuarios_senderos', 'mantenimientos/mantenimiento_usuarios_senderos.php' . $filtroSendero)],
        ['Asistencia', 'check-square', $adminUrl('operaciones.asistencia', 'mantenimientos/mantenimiento_asistencia_senderos.php' . $filtroSendero)],
        ['Ingresos', 'credit-card', $adminUrl('finanzas.ingresos_sendero', 'mantenimientos/mantenimiento_ingresos_sendero.php' . $filtroSendero)],
        ['Editar', 'edit-2', $adminUrl('operaciones.senderos', 'mantenimientos/mantenimiento_senderos.php?edit=' . (int) $proximo['id'])],
    ] as $accion) {
        if ($accion[2] !== '') {
            $accionesProximo[] = $accion;
        }
    }
}

// Pendientes: solo se listan los que el rol puede resolver.
$pendientes = [];
$agregarPendiente = static function (int $cantidad, string $tono, string $icono, string $titulo, string $detalle, string $url) use (&$pendientes): void {
    if ($cantidad > 0 && $url !== '') {
        $pendientes[] = ['tono' => $tono, 'icono' => $icono, 'titulo' => $titulo, 'detalle' => $detalle, 'url' => $url];
    }
};
$plural = static fn (int $cantidad, string $uno, string $varios): string => $cantidad . ' ' . ($cantidad === 1 ? $uno : $varios);
$filtroPendientes = $proximo ? '?sendero_id=' . (int) $proximo['id'] : '';
$agregarPendiente(
    (int) $stats['comprobantesPorRevisar'], 'alerta', 'paperclip',
    $plural((int) $stats['comprobantesPorRevisar'], 'comprobante por revisar', 'comprobantes por revisar'),
    'Registros con comprobante subido y sin pago registrado.',
    $adminUrl('finanzas.ingresos_sendero', 'mantenimientos/mantenimiento_ingresos_sendero.php' . $filtroPendientes)
);
$agregarPendiente(
    (int) $stats['registrosSinPago'], 'aviso', 'dollar-sign',
    $plural((int) $stats['registrosSinPago'], 'registro sin pago ni comprobante', 'registros sin pago ni comprobante'),
    'Inscritos en proximos senderos que aun no han pagado.',
    $adminUrl('operaciones.usuarios_senderos', 'mantenimientos/mantenimiento_usuarios_senderos.php' . $filtroPendientes)
);
$agregarPendiente(
    (int) $stats['mensajesNuevos'], 'alerta', 'mail',
    $plural((int) $stats['mensajesNuevos'], 'mensaje sin leer', 'mensajes sin leer'),
    'Solicitudes enviadas desde el formulario de contacto.',
    $adminUrl('reportes.contactos', 'pantallas/reporte_contacto.php')
);
$agregarPendiente(
    (int) $stats['senderosSinAsistencia'], 'aviso', 'check-square',
    $plural((int) $stats['senderosSinAsistencia'], 'sendero sin asistencia marcada', 'senderos sin asistencia marcada'),
    'Rutas ya realizadas donde nadie figura como asistente.',
    $adminUrl('operaciones.asistencia', 'mantenimientos/mantenimiento_asistencia_senderos.php')
);
$agregarPendiente(
    (int) $stats['senderosIncompletos'], 'aviso', 'map',
    $plural((int) $stats['senderosIncompletos'], 'proximo sendero incompleto', 'proximos senderos incompletos'),
    'Les falta el punto de encuentro o la imagen del flyer.',
    $adminUrl('operaciones.senderos', 'mantenimientos/mantenimiento_senderos.php')
);
$agregarPendiente(
    $stats['porCobrar'] > 0 ? 1 : 0, 'aviso', 'trending-up',
    admin_money($stats['porCobrar']) . ' por cobrar',
    'Saldos pendientes en pagos parciales o con deuda.',
    $adminUrl('finanzas.panel', 'pantallas/panel_financiero.php')
);

// Parte superior: el sendero destacado y la lista de pendientes se pueden ocultar por separado.
$mostrarProximo = (bool) $proximo && $ver('proximo_sendero_destacado');
$mostrarAtencion = $ver('atencion') && ($proximo || $pendientes);

include_once __DIR__ . '/../componentes/encabezado.php';
?>

<div class="admin-page">
    <div class="admin-shell">
        <div class="admin-sidebar-overlay" data-admin-sidebar-close></div>

        <aside class="admin-sidebar" data-admin-sidebar>
            <div class="admin-brand">
                <span class="brand-icon">
                    <?php if ($adminSiteIdentity['logo'] !== ''): ?>
                        <img src="<?= admin_h($adminSiteIdentity['logo']) ?>" alt="<?= admin_h($adminSiteIdentity['nombre']) ?>">
                    <?php else: ?>
                        <i data-feather="compass"></i>
                    <?php endif; ?>
                </span>
                <div>
                    <strong><?= admin_h($adminSiteIdentity['nombre']) ?></strong>
                    <small>Panel de control</small>
                </div>
            </div>

            <nav class="admin-nav" aria-label="Navegacion administrativa">
                <a href="#dashboard" class="admin-nav-home is-active">
                    <i data-feather="layout"></i>
                    <span>Panel general</span>
                </a>

                <?php foreach ($moduleGroups as $index => $group): ?>
                    <div class="admin-nav-section">
                        <span class="nav-section-label"><?= admin_h($group['label']) ?></span>
                        <button class="admin-nav-toggle" type="button" data-admin-nav-toggle aria-expanded="false">
                            <span><i data-feather="<?= admin_h($group['icon']) ?>"></i><?= admin_h($group['title']) ?></span>
                            <i data-feather="chevron-down"></i>
                        </button>
                        <div class="admin-nav-submenu" hidden>
                            <?php $navSections = $group['sections'] ?? [['label' => null, 'items' => $group['items'] ?? []]]; ?>
                            <?php foreach ($navSections as $navSection): ?>
                                <?php if (!empty($navSection['label'])): ?>
                                    <span class="admin-nav-subgroup"><?= admin_h($navSection['label']) ?></span>
                                <?php endif; ?>
                                <?php foreach ($navSection['items'] as $item): ?>
                                    <a href="<?= admin_h($item[3]) ?>">
                                        <i data-feather="<?= admin_h($item[2]) ?>"></i>
                                        <span><?= admin_h($item[0]) ?></span>
                                        <?php if (isset($item[4]) && (int) $item[4] > 0): ?>
                                            <small><?= (int) $item[4] ?></small>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="admin-user-box">
                <span class="admin-avatar"><?= admin_h(strtoupper(substr($firstAdminName, 0, 1))) ?></span>
                <div>
                    <strong><?= admin_h($adminName) ?></strong>
                    <small><?= admin_h($adminRole) ?></small>
                </div>
                <a class="admin-logout" href="<?= BASE_URL ?>sesion/cerrar_sesion.php" title="Cerrar sesion">
                    <i data-feather="log-out"></i>
                </a>
            </div>
        </aside>

        <main class="admin-main" id="dashboard">
            <header class="admin-topbar">
                <button class="admin-menu-btn" type="button" data-admin-sidebar-toggle aria-label="Abrir menu administrativo" aria-expanded="true">
                    <i data-feather="menu"></i>
                </button>
                <div>
                    <span class="admin-kicker">Centro de control</span>
                    <h1>Panel Administrativo</h1>
                    <p>Hola, <strong><?= admin_h($firstAdminName) ?></strong>.<span class="admin-topbar-extra"> Aqui tienes los indicadores que importan para operar la plataforma.</span></p>
                </div>
                <a class="admin-public-link" href="<?= BASE_URL ?>pantallas/inicio.php" title="Ver web">
                    <i data-feather="external-link"></i>
                    Ver web
                </a>
            </header>

            <?php if ($mostrarProximo || $mostrarAtencion): ?>
            <section class="admin-focus-grid">
                <?php if ($mostrarProximo): ?>
                    <article class="next-route-card">
                        <div class="next-route-head">
                            <span class="next-route-when"><?= admin_h(admin_days_left($proximo['fecha_sendero'])) ?></span>
                            <span class="next-route-label">Proximo sendero &middot; <?= admin_h(admin_date($proximo['fecha_sendero'])) ?></span>
                        </div>
                        <h2><a href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $proximo['id'] ?>"><?= admin_h($proximo['nombre']) ?></a></h2>
                        <p class="next-route-place"><i data-feather="map-pin"></i><?= admin_h(trim(($proximo['lugar'] ?? '') . (!empty($proximo['provincia']) ? ', ' . $proximo['provincia'] : ''))) ?></p>

                        <div class="next-route-stats">
                            <div><strong><?= (int) $proximo['inscritos'] + (int) $proximo['menores'] ?></strong><span>Personas<?= (int) $proximo['menores'] > 0 ? ' (' . (int) $proximo['menores'] . ((int) $proximo['menores'] === 1 ? ' menor)' : ' menores)') : '' ?></span></div>
                            <?php if ($puedeVerFinanzas): ?>
                                <div><strong><?= admin_h(admin_money($proximo['cobrado'])) ?></strong><span>Cobrado</span></div>
                                <div><strong><?= (int) $proximo['sinPago'] ?></strong><span>Sin pago registrado</span></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($puedeVerFinanzas && (int) $proximo['inscritos'] > 0): ?>
                            <?php $porcentajePago = (int) round(((int) $proximo['inscritos'] - (int) $proximo['sinPago']) * 100 / (int) $proximo['inscritos']); ?>
                            <div class="next-route-progress" role="img" aria-label="<?= $porcentajePago ?>% de los registros con pago">
                                <span style="width: <?= max(0, min(100, $porcentajePago)) ?>%"></span>
                            </div>
                            <p class="next-route-progress-note"><?= $porcentajePago ?>% de los registros ya tiene pago</p>
                        <?php endif; ?>

                        <?php if ($accionesProximo): ?>
                            <div class="next-route-actions">
                                <?php foreach ($accionesProximo as $accion): ?>
                                    <a href="<?= admin_h($accion[2]) ?>"><i data-feather="<?= admin_h($accion[1]) ?>"></i><?= admin_h($accion[0]) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endif; ?>

                <?php if ($mostrarAtencion): ?>
                <article class="admin-card attention-card">
                    <div class="admin-card-head">
                        <div>
                            <span class="card-label">Pendientes</span>
                            <h2>Requiere tu atencion</h2>
                        </div>
                        <?php if ($pendientes): ?><span class="attention-count"><?= count($pendientes) ?></span><?php endif; ?>
                    </div>
                    <?php if ($pendientes): ?>
                        <div class="attention-list">
                            <?php foreach ($pendientes as $pendiente): ?>
                                <a class="attention-item is-<?= admin_h($pendiente['tono']) ?>" href="<?= admin_h($pendiente['url']) ?>">
                                    <span class="attention-icon"><i data-feather="<?= admin_h($pendiente['icono']) ?>"></i></span>
                                    <span class="attention-copy">
                                        <strong><?= admin_h($pendiente['titulo']) ?></strong>
                                        <small><?= admin_h($pendiente['detalle']) ?></small>
                                    </span>
                                    <i data-feather="chevron-right"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-card compact">
                            <i data-feather="check-circle"></i>
                            <strong>Todo al dia</strong>
                            <span>No hay pendientes por revisar.</span>
                        </div>
                    <?php endif; ?>
                </article>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($quickActions): ?>
            <div class="quick-access" role="navigation" aria-label="Accesos rapidos">
                <span class="quick-access-title">Accesos rapidos</span>
                <div class="quick-access-list">
                    <?php foreach ($quickActions as $action): ?>
                        <a href="<?= admin_h($action[3]) ?>" title="<?= admin_h($action[1]) ?>">
                            <span><i data-feather="<?= admin_h($action[2]) ?>"></i></span>
                            <?= admin_h($action[0]) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <section class="admin-kpi-grid" aria-label="Indicadores principales">
                <?php foreach ($indicadores as $indicador): ?>
                    <?php $etiqueta = $indicador['url'] !== '' ? 'a' : 'article'; ?>
                    <<?= $etiqueta ?> class="kpi-card <?= $indicador['principal'] ? 'kpi-primary' : '' ?>"<?= $indicador['url'] !== '' ? ' href="' . admin_h($indicador['url']) . '"' : '' ?>>
                        <div class="kpi-head">
                            <span><i data-feather="<?= admin_h($indicador['icono']) ?>"></i></span>
                            <small><?= admin_h($indicador['titulo']) ?></small>
                        </div>
                        <div class="kpi-value">
                            <strong><?= (int) $indicador['valor'] ?></strong>
                            <?php if ($indicador['tendencia'] !== null): ?>
                                <em class="kpi-trend <?= $indicador['tendencia'] > 0 ? 'is-up' : ($indicador['tendencia'] < 0 ? 'is-down' : '') ?>" title="Comparado con los 30 dias anteriores">
                                    <i data-feather="<?= $indicador['tendencia'] > 0 ? 'trending-up' : ($indicador['tendencia'] < 0 ? 'trending-down' : 'minus') ?>"></i><?= ($indicador['tendencia'] > 0 ? '+' : '') . (int) $indicador['tendencia'] ?>
                                </em>
                            <?php endif; ?>
                        </div>
                        <p><?= admin_h($indicador['detalle']) ?></p>
                        <?php if ($indicador['url'] !== ''): ?><span class="kpi-open">Abrir <i data-feather="arrow-right"></i></span><?php endif; ?>
                    </<?= $etiqueta ?>>
                <?php endforeach; ?>
            </section>

            <?php $bloquesPanel = []; ?>
            <section class="admin-dashboard-grid">
                <?php ob_start(); ?>
                <article class="admin-card admin-card-wide">
                    <div class="admin-card-head">
                        <div>
                            <span class="card-label">Agenda</span>
                            <h2>Proximos senderos</h2>
                        </div>
                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_senderos.php">Gestionar</a>
                    </div>

                    <?php if (!empty($stats['proximosSenderos'])): ?>
                        <div class="route-list">
                            <?php foreach ($stats['proximosSenderos'] as $sendero): ?>
                                <a class="route-row" href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $sendero['id'] ?>">
                                    <span class="route-date">
                                        <strong><?= admin_h(date('d', strtotime($sendero['fecha_sendero']))) ?></strong>
                                        <small><?= admin_h(strtoupper(date('M', strtotime($sendero['fecha_sendero'])))) ?></small>
                                    </span>
                                    <span class="route-copy">
                                        <strong><?= admin_h($sendero['nombre']) ?></strong>
                                        <small><?= admin_h(trim(($sendero['lugar'] ?? '') . (!empty($sendero['provincia']) ? ', ' . $sendero['provincia'] : ''))) ?></small>
                                    </span>
                                    <span class="route-status"><?= admin_h(admin_days_left($sendero['fecha_sendero'])) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-card">
                            <i data-feather="map"></i>
                            <strong>No hay senderos proximos</strong>
                            <span>Crea o publica una ruta con fecha futura para verla aqui.</span>
                        </div>
                    <?php endif; ?>
                </article>
                <?php $bloquesPanel['proximos_senderos'] = ob_get_clean(); ?>

                <?php ob_start(); ?>
                <?php if ($puedeVerFinanzas): ?>
                <article class="admin-card finance-card">
                    <div class="admin-card-head">
                        <div>
                            <span class="card-label">Finanzas</span>
                            <h2>Resumen financiero</h2>
                        </div>
                        <a href="<?= BASE_URL ?>pantallas/panel_financiero.php">Abrir panel</a>
                    </div>
                    <div class="finance-balance">
                        <small>Balance</small>
                        <strong class="<?= $balanceTotal >= 0 ? 'is-positive' : 'is-negative' ?>"><?= admin_h(admin_money($balanceTotal)) ?></strong>
                        <?php $baseFinanzas = max((float) $stats['ingresosTotales'], (float) $stats['gastosTotales'], 1); ?>
                        <div class="finance-bars">
                            <div><span>Ingresos</span><b><i class="is-income" style="width: <?= round((float) $stats['ingresosTotales'] * 100 / $baseFinanzas) ?>%"></i></b><em><?= admin_h(admin_money($stats['ingresosTotales'])) ?></em></div>
                            <div><span>Gastos</span><b><i class="is-expense" style="width: <?= round((float) $stats['gastosTotales'] * 100 / $baseFinanzas) ?>%"></i></b><em><?= admin_h(admin_money($stats['gastosTotales'])) ?></em></div>
                        </div>
                    </div>
                    <div class="finance-grid">
                        <div>
                            <small>Ingresos del mes</small>
                            <strong><?= admin_h(admin_money($stats['ingresosMes'])) ?></strong>
                        </div>
                        <div>
                            <small>Por cobrar</small>
                            <strong><?= admin_h(admin_money($stats['porCobrar'])) ?></strong>
                        </div>
                    </div>
                </article>
                <?php endif; ?>
                <?php $bloquesPanel['resumen_financiero'] = ob_get_clean(); ?>

                <?php ob_start(); ?>
                <article class="admin-card admin-card-wide">
                    <div class="admin-card-head">
                        <div>
                            <span class="card-label">Actividad</span>
                            <h2>Registros recientes</h2>
                        </div>
                        <a href="<?= BASE_URL ?>pantallas/reporte_usuarios_sendero.php">Ver participantes</a>
                    </div>

                    <?php if (!empty($stats['actividadReciente'])): ?>
                        <div class="activity-table-wrap">
                            <table class="activity-table">
                                <thead>
                                    <tr>
                                        <th>Participante</th>
                                        <th>Sendero</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['actividadReciente'] as $row): ?>
                                        <tr>
                                            <td><?= admin_h($row['principal']) ?></td>
                                            <td><?= admin_h($row['secundario'] ?: 'Sin sendero') ?></td>
                                            <td><?= admin_h(admin_date($row['fecha'])) ?></td>
                                            <td><span class="state-pill"><?= admin_h($row['estado']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-card">
                            <i data-feather="clock"></i>
                            <strong>Sin actividad reciente</strong>
                            <span>Cuando existan registros nuevos apareceran aqui.</span>
                        </div>
                    <?php endif; ?>
                </article>
                <?php $bloquesPanel['registros_recientes'] = ob_get_clean(); ?>

                <?php ob_start(); ?>
                <article class="admin-card">
                    <div class="admin-card-head">
                        <div>
                            <span class="card-label">Salud operativa</span>
                            <h2>Lectura rapida</h2>
                        </div>
                    </div>
                    <div class="health-list">
                        <div><span>Senderos visitados</span><strong><?= (int) $stats['senderosVisitados'] ?></strong></div>
                        <div><span>Imagenes activas</span><strong><?= (int) $stats['galeria'] ?></strong></div>
                        <div><span>Logins / 7 dias</span><strong><?= (int) $stats['logins7d'] ?></strong></div>
                        <?php if ($puedeVerFinanzas): ?>
                            <div><span>Creditos activos</span><strong><?= admin_h(admin_money($stats['creditosActivos'])) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php $bloquesPanel['lectura_rapida'] = ob_get_clean(); ?>

                <?php foreach (sg_panel_tarjetas_ordenar(array_keys($bloquesPanel), $tarjetasPanel, static fn(string $codigo): string => $codigo) as $codigoBloque): ?>
                    <?php if ($ver($codigoBloque)) { echo $bloquesPanel[$codigoBloque]; } ?>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
