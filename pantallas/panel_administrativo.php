<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';

sg_actualizar_senderos_vencidos($conn);

$pageTitle = "Panel Administrativo | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/panel_administrativo.css"
];

$jsFiles = [
    "js/panel_administrativo.js"
];

$stats = require __DIR__ . '/../componentes/estadisticas_admin.php';

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

$adminName = $_SESSION['usuario_nombre'] ?? 'Administrador';
$adminRole = $_SESSION['usuario_rol'] ?? $_SESSION['rol_nombre'] ?? 'Administrador';
$firstAdminName = trim(explode(' ', trim($adminName))[0] ?? '') ?: 'Admin';

$moduleGroups = [
    [
        'label' => 'Contenido',
        'title' => 'Interfaz publica',
        'icon' => 'globe',
        'items' => [
            ['Inicio', 'Portada, tarjetas, galeria y llamados', 'home', BASE_URL . 'mantenimientos/mantenimiento_inicio.php'],
            ['Nosotros', 'Historia, valores, pasos, indicadores y equipo', 'users', BASE_URL . 'mantenimientos/mantenimiento_nosotros.php'],
            ['Contacto', 'Redes, telefonos, ubicacion y mensajes', 'phone-call', BASE_URL . 'mantenimientos/mantenimiento_contacto.php'],
            ['Tema visual', 'Paletas, colores globales y personalizacion', 'sliders', BASE_URL . 'mantenimientos/mantenimiento_tema.php'],
        ],
    ],
    [
        'label' => 'Usuarios',
        'title' => 'Seguridad y accesos',
        'icon' => 'shield',
        'items' => [
            ['Usuarios', 'Datos, salud, menores, perfil y estado', 'user-check', BASE_URL . 'mantenimientos/mantenimiento_usuarios.php', $stats['totalUsuarios']],
            ['Roles y permisos', 'Perfiles administrativos y control de acceso', 'key', BASE_URL . 'mantenimientos/mantenimiento_roles.php'],
        ],
    ],
    [
        'label' => 'Operaciones',
        'title' => 'Senderos y logistica',
        'icon' => 'map-pin',
        'items' => [
            ['Senderos', 'Rutas, fechas, inversiones, imagenes y detalle', 'map', BASE_URL . 'mantenimientos/mantenimiento_senderos.php', $stats['senderos']],
            ['Usuarios por sendero', 'Acciones sobre reservas y participantes', 'users', BASE_URL . 'mantenimientos/mantenimiento_usuarios_senderos.php'],
            ['Asistencia', 'Marcar quienes fueron realmente', 'check-square', BASE_URL . 'mantenimientos/mantenimiento_asistencia_senderos.php'],
            ['Puntos de encuentro', 'Ubicaciones reutilizables para salidas', 'map', BASE_URL . 'mantenimientos/mantenimiento_puntos_encuentro.php'],
            ['Detalles', 'Terrenos, anotaciones, incluye y dificultad', 'file-text', BASE_URL . 'mantenimientos/mantenimiento_detalles.php'],
        ],
    ],
    [
        'label' => 'Finanzas',
        'title' => 'Contabilidad y pagos',
        'icon' => 'dollar-sign',
        'items' => [
            ['Tarjeta de pago', 'Datos bancarios visibles en el detalle', 'credit-card', BASE_URL . 'mantenimientos/mantenimiento_tarjeta_pago.php'],
            ['Categorias de gasto', 'Clasificacion financiera de costos', 'folder', BASE_URL . 'mantenimientos/mantenimiento_categoria_gasto.php'],
            ['Gastos catalogo', 'Costos frecuentes por alimento, equipo o servicio', 'tag', BASE_URL . 'mantenimientos/mantenimiento_gastos.php'],
            ['Gastos por sendero', 'Costos reales por ruta', 'shopping-bag', BASE_URL . 'mantenimientos/mantenimiento_gastos_sendero.php'],
            ['Metodos de pago', 'Formas de cobro para los ingresos', 'briefcase', BASE_URL . 'mantenimientos/mantenimiento_metodo_pago.php'],
            ['Ingresos por sendero', 'Pagos, creditos, cortesias y asistencia financiera', 'trending-up', BASE_URL . 'mantenimientos/mantenimiento_ingresos_sendero.php'],
        ],
    ],
    [
        'label' => 'Informes',
        'title' => 'Reportes y analisis',
        'icon' => 'bar-chart-2',
        'sections' => [
            [
                'label' => 'General',
                'items' => [
                    ['Reporte de Usuarios', 'Altas, roles, estado y datos generales', 'users', BASE_URL . 'pantallas/reportes.php#usuarios'],
                    ['Reporte de Actividad', 'Movimiento reciente de la plataforma', 'activity', BASE_URL . 'pantallas/reportes.php#actividad'],
                    ['Contactos Recibidos', 'Solicitudes enviadas desde la web', 'mail', BASE_URL . 'pantallas/reporte_contacto.php', $stats['mensajesNuevos']],
                ],
            ],
            [
                'label' => 'Operaciones',
                'items' => [
                    ['Senderos y Galeria', 'Rutas, imagenes y comportamiento general', 'git-branch', BASE_URL . 'pantallas/reportes.php#senderos'],
                    ['Usuarios por Sendero', 'Participantes, salud, emergencia y menores', 'user', BASE_URL . 'pantallas/reporte_usuarios_sendero.php'],
                ],
            ],
            [
                'label' => 'Finanzas',
                'items' => [
                    ['Rentabilidad por Sendero', 'Ingresos, gastos, utilidad y margen', 'trending-up', BASE_URL . 'pantallas/reporte_rentabilidad_sendero.php'],
                    ['Rentabilidad por Fechas', 'Resumen financiero por periodo', 'calendar', BASE_URL . 'pantallas/reporte_rentabilidad_fechas.php'],
                ],
            ],
        ],
    ],
];

$quickActions = [
    ['Nuevo sendero', 'Crear una salida o ruta de catalogo', 'plus-circle', BASE_URL . 'mantenimientos/mantenimiento_senderos.php'],
    ['Registrar usuario', 'Agregar o completar un participante', 'user-plus', BASE_URL . 'mantenimientos/mantenimiento_usuarios.php'],
    ['Marcar asistencia', 'Confirmar quienes fueron al sendero', 'check-square', BASE_URL . 'mantenimientos/mantenimiento_asistencia_senderos.php'],
    ['Registrar ingresos', 'Pagos, creditos y cortesias', 'credit-card', BASE_URL . 'mantenimientos/mantenimiento_ingresos_sendero.php'],
];

$balanceTotal = (float) $stats['ingresosTotales'] - (float) $stats['gastosTotales'];

include_once __DIR__ . '/../componentes/encabezado.php';
?>

<div class="admin-page">
    <div class="admin-shell">
        <div class="admin-sidebar-overlay" data-admin-sidebar-close></div>

        <aside class="admin-sidebar" data-admin-sidebar>
            <div class="admin-brand">
                <span class="brand-icon">
                    <img src="<?= BASE_URL ?>imagenes/logo/logo_sg.png" alt="Senderismo Go">
                </span>
                <div>
                    <strong>Senderismo Go</strong>
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
                    <p>Hola, <strong><?= admin_h($firstAdminName) ?></strong>. Aqui tienes los indicadores que importan para operar la plataforma.</p>
                </div>
                <a class="admin-public-link" href="<?= BASE_URL ?>pantallas/inicio.php">
                    <i data-feather="external-link"></i>
                    Ver web
                </a>
            </header>

            <section class="admin-kpi-grid" aria-label="Indicadores principales">
                <article class="kpi-card kpi-primary">
                    <div class="kpi-head">
                        <span><i data-feather="calendar"></i></span>
                        <small>Proximas rutas</small>
                    </div>
                    <strong><?= (int) $stats['senderosProximos'] ?></strong>
                    <p><?= (int) $stats['registrosActivos'] ?> registros activos en la plataforma</p>
                </article>

                <article class="kpi-card">
                    <div class="kpi-head">
                        <span><i data-feather="users"></i></span>
                        <small>Usuarios</small>
                    </div>
                    <strong><?= (int) $stats['totalUsuarios'] ?></strong>
                    <p><?= (int) $stats['nuevos30d'] ?> nuevos en 30 dias / <?= (int) $stats['usuariosActivos'] ?> activos</p>
                </article>

                <article class="kpi-card">
                    <div class="kpi-head">
                        <span><i data-feather="check-circle"></i></span>
                        <small>Asistencias</small>
                    </div>
                    <strong><?= (int) $stats['asistencias'] ?></strong>
                    <p>Participaciones confirmadas para historial y beneficios</p>
                </article>

                <article class="kpi-card">
                    <div class="kpi-head">
                        <span><i data-feather="mail"></i></span>
                        <small>Contacto</small>
                    </div>
                    <strong><?= (int) $stats['mensajesNuevos'] ?></strong>
                    <p><?= (int) $stats['mensajes7d'] ?> mensajes recibidos en los ultimos 7 dias</p>
                </article>
            </section>

            <section class="admin-dashboard-grid">
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

                <article class="admin-card action-card">
                    <div class="admin-card-head">
                        <div>
                            <span class="card-label">Atajos</span>
                            <h2>Acciones rapidas</h2>
                        </div>
                    </div>
                    <div class="quick-actions">
                        <?php foreach ($quickActions as $action): ?>
                            <a href="<?= admin_h($action[3]) ?>">
                                <i data-feather="<?= admin_h($action[2]) ?>"></i>
                                <span>
                                    <strong><?= admin_h($action[0]) ?></strong>
                                    <small><?= admin_h($action[1]) ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="admin-card finance-card">
                    <div class="admin-card-head">
                        <div>
                            <span class="card-label">Finanzas</span>
                            <h2>Resumen financiero</h2>
                        </div>
                        <a href="<?= BASE_URL ?>pantallas/reporte_rentabilidad_sendero.php">Ver reporte</a>
                    </div>
                    <div class="finance-grid">
                        <div>
                            <small>Ingresos totales</small>
                            <strong><?= admin_h(admin_money($stats['ingresosTotales'])) ?></strong>
                        </div>
                        <div>
                            <small>Gastos totales</small>
                            <strong><?= admin_h(admin_money($stats['gastosTotales'])) ?></strong>
                        </div>
                        <div>
                            <small>Balance</small>
                            <strong class="<?= $balanceTotal >= 0 ? 'is-positive' : 'is-negative' ?>"><?= admin_h(admin_money($balanceTotal)) ?></strong>
                        </div>
                        <div>
                            <small>Por cobrar</small>
                            <strong><?= admin_h(admin_money($stats['porCobrar'])) ?></strong>
                        </div>
                    </div>
                </article>

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
                        <div><span>Creditos activos</span><strong><?= admin_h(admin_money($stats['creditosActivos'])) ?></strong></div>
                    </div>
                </article>

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
            </section>
        </main>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
