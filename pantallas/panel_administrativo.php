<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Panel Administrativo | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/panel_administrativo.css"
];

$jsFiles = [
    "js/barra_navegacion.js"
];

$stats = require __DIR__ . '/../componentes/estadisticas_admin.php';

$totalUsuarios = $stats['totalUsuarios'];
$usuariosActivos = $stats['usuariosActivos'];
$usuariosInact = $stats['usuariosInact'];
$nuevos30d = $stats['nuevos30d'];
$logins7d = $stats['logins7d'];
$admins = $stats['admins'];
$senderos = $stats['senderos'];
$galeria = $stats['galeria'];

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';

$adminName = $_SESSION['usuario_nombre'] ?? 'Administrador';
$firstAdminName = trim(explode(' ', trim($adminName))[0] ?? '');
?>

<div class="admin-page">
    <div class="admin-container">

        <div class="admin-header">
            <div class="admin-heading">
                <span class="admin-kicker">Centro de control</span>
                <h1 class="admin-title">Panel Administrativo</h1>
                <p class="admin-subtitle">
                    Hola, <strong><?= htmlspecialchars($firstAdminName ?: 'Admin') ?></strong>. Revisa el estado general y accede a los modulos de gestion.
                </p>
            </div>

            <div class="admin-badges">
                <span class="badge">
                    <i data-feather="shield"></i>
                    Admin
                </span>
                <span class="badge badge-soft">
                    <i data-feather="activity"></i>
                    <?= $nuevos30d ?> nuevos / 30 dias
                </span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-head">
                    <span class="stat-icon"><i data-feather="users"></i></span>
                    <p class="stat-label">Usuarios</p>
                </div>
                <p class="stat-value"><?= $totalUsuarios ?></p>
                <p class="stat-foot"><?= $usuariosActivos ?> activos / <?= $usuariosInact ?> inactivos</p>
            </div>

            <div class="stat-card">
                <div class="stat-head">
                    <span class="stat-icon"><i data-feather="map"></i></span>
                    <p class="stat-label">Senderos</p>
                </div>
                <p class="stat-value"><?= $senderos ?></p>
                <p class="stat-foot">Rutas publicadas</p>
            </div>

            <div class="stat-card">
                <div class="stat-head">
                    <span class="stat-icon"><i data-feather="image"></i></span>
                    <p class="stat-label">Galeria</p>
                </div>
                <p class="stat-value"><?= $galeria ?></p>
                <p class="stat-foot">Imagenes activas</p>
            </div>

            <div class="stat-card">
                <div class="stat-head">
                    <span class="stat-icon"><i data-feather="clock"></i></span>
                    <p class="stat-label">Actividad</p>
                </div>
                <p class="stat-value"><?= $logins7d ?></p>
                <p class="stat-foot">Logins en ultimos 7 dias</p>
            </div>
        </div>

        <div class="admin-grid">
            <section class="panel-card">
                <div class="panel-card-head">
                    <div>
                        <h2 class="panel-title">Mantenimientos</h2>
                        <p class="panel-desc">Modulos principales</p>
                    </div>
                    <span class="panel-count">12 activos</span>
                </div>

                <div class="panel-list">
                    <details class="panel-group" open>
                        <summary class="panel-group-title">
                            <span>Interfaz y contenido publico</span>
                            <small>Paginas visibles para visitantes</small>
                            <i data-feather="chevron-down"></i>
                        </summary>

                        <div class="panel-group-items">
                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_inicio.php">
                                <span class="panel-icon"><i data-feather="home"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Inicio</span>
                                    <span class="panel-item-sub">Portada, tarjetas, galeria y llamados</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_nosotros.php">
                                <span class="panel-icon"><i data-feather="users"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Nosotros</span>
                                    <span class="panel-item-sub">Historia, valores, pasos, indicadores y equipo</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_contacto.php">
                                <span class="panel-icon"><i data-feather="phone-call"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Contacto</span>
                                    <span class="panel-item-sub">Redes, telefono, correo, ubicacion e imagen</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_tema.php">
                                <span class="panel-icon"><i data-feather="sliders"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Tema visual</span>
                                    <span class="panel-item-sub">Paletas, colores globales y modo personalizado</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                        </div>
                    </details>

                    <details class="panel-group" open>
                        <summary class="panel-group-title">
                            <span>Operacion y configuracion</span>
                            <small>Usuarios, senderos, catalogos y pagos</small>
                            <i data-feather="chevron-down"></i>
                        </summary>

                        <div class="panel-group-items">
                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_usuarios.php">
                                <span class="panel-icon"><i data-feather="user-check"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Usuarios</span>
                                    <span class="panel-item-sub">Crear, editar, activar e inactivar</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_roles.php">
                                <span class="panel-icon"><i data-feather="lock"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Roles</span>
                                    <span class="panel-item-sub">Administrar perfiles y permisos</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_senderos.php">
                                <span class="panel-icon"><i data-feather="map-pin"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Senderos</span>
                                    <span class="panel-item-sub">Rutas, niveles, fechas y puntos de encuentro</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_usuarios_senderos.php">
                                <span class="panel-icon"><i data-feather="user-minus"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Usuarios por sendero</span>
                                    <span class="panel-item-sub">Inactivar, reactivar o eliminar reservas</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_asistencia_senderos.php">
                                <span class="panel-icon"><i data-feather="check-circle"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Asistencia por sendero</span>
                                    <span class="panel-item-sub">Marcar quienes asistieron realmente</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_puntos_encuentro.php">
                                <span class="panel-icon"><i data-feather="navigation"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Puntos de encuentro</span>
                                    <span class="panel-item-sub">Ubicaciones reutilizables para salidas</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_detalles.php">
                                <span class="panel-icon"><i data-feather="list"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Detalles</span>
                                    <span class="panel-item-sub">Terrenos, anotaciones e incluye</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>mantenimientos/mantenimiento_tarjeta_pago.php">
                                <span class="panel-icon"><i data-feather="credit-card"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Tarjeta de pago</span>
                                    <span class="panel-item-sub">Banco, cuenta, titular y nota importante</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                        </div>
                    </details>
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-card-head">
                    <div>
                        <h2 class="panel-title">Reportes</h2>
                        <p class="panel-desc">Seguimiento y metricas</p>
                    </div>
                    <span class="panel-count">Activo</span>
                </div>

                <div class="panel-list">
                    <details class="panel-group" open>
                        <summary class="panel-group-title">
                            <span>Usuarios y actividad</span>
                            <small>Estado, accesos y movimiento del sistema</small>
                            <i data-feather="chevron-down"></i>
                        </summary>

                        <div class="panel-group-items">
                            <a class="panel-item" href="<?= BASE_URL ?>pantallas/reportes.php#usuarios">
                                <span class="panel-icon"><i data-feather="bar-chart-2"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Usuarios</span>
                                    <span class="panel-item-sub">Altas, roles, estado y ultimos accesos</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>pantallas/reportes.php#actividad">
                                <span class="panel-icon"><i data-feather="activity"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Actividad</span>
                                    <span class="panel-item-sub">Ingresos, cambios y eventos del sistema</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                        </div>
                    </details>

                    <details class="panel-group" open>
                        <summary class="panel-group-title">
                            <span>Senderos y registros</span>
                            <small>Rutas, galeria, reservas y participantes</small>
                            <i data-feather="chevron-down"></i>
                        </summary>

                        <div class="panel-group-items">
                            <a class="panel-item" href="<?= BASE_URL ?>pantallas/reportes.php#senderos">
                                <span class="panel-icon"><i data-feather="camera"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Senderos / Galeria</span>
                                    <span class="panel-item-sub">Rutas, imagenes, asistencia y reservas</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>

                            <a class="panel-item" href="<?= BASE_URL ?>pantallas/reporte_usuarios_sendero.php">
                                <span class="panel-icon"><i data-feather="users"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Usuarios por sendero</span>
                                    <span class="panel-item-sub">Participantes, salud, emergencia y fecha de registro</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                        </div>
                    </details>

                    <details class="panel-group" open>
                        <summary class="panel-group-title">
                            <span>Comunicacion</span>
                            <small>Solicitudes recibidas desde la web</small>
                            <i data-feather="chevron-down"></i>
                        </summary>

                        <div class="panel-group-items">
                            <a class="panel-item" href="<?= BASE_URL ?>pantallas/reporte_contacto.php">
                                <span class="panel-icon"><i data-feather="inbox"></i></span>
                                <span class="panel-copy">
                                    <span class="panel-item-title">Contacto</span>
                                    <span class="panel-item-sub">Mensajes enviados desde la pagina publica</span>
                                </span>
                                <span class="panel-arrow"><i data-feather="chevron-right"></i></span>
                            </a>
                        </div>
                    </details>
                </div>
            </section>

            <section class="panel-card panel-card-wide">
                <div class="panel-card-head">
                    <div>
                        <h2 class="panel-title">Resumen Operativo</h2>
                        <p class="panel-desc">Lectura rapida del estado actual</p>
                    </div>
                </div>

                <div class="health-grid">
                    <div class="health-item">
                        <span class="health-label">Administradores</span>
                        <strong><?= $admins ?></strong>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Usuarios inactivos</span>
                        <strong><?= $usuariosInact ?></strong>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Nuevos / 30 dias</span>
                        <strong><?= $nuevos30d ?></strong>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Galeria activa</span>
                        <strong><?= $galeria ?></strong>
                    </div>
                </div>
            </section>
        </div>

        <div class="admin-note">
            <i data-feather="info"></i>
            <p>
                Usa los reportes para medir registros por sendero, actividad de usuarios, galeria, salud de participantes y vias de captacion.
            </p>
        </div>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
