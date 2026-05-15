<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Reportes | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/reportes.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function scalar_int_report(mysqli $conn, string $sql): int
{
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return 0;
    }
    $row = mysqli_fetch_row($res);
    return (int) ($row[0] ?? 0);
}

function rows_report(mysqli $conn, string $sql): array
{
    $items = [];
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $items[] = $row;
        }
    }
    return $items;
}

$metricas = [
    'usuarios' => scalar_int_report($conn, "SELECT COUNT(*) FROM usuarios"),
    'usuarios_activos' => scalar_int_report($conn, "SELECT COUNT(*) FROM usuarios WHERE estado = 1"),
    'usuarios_nuevos_30' => scalar_int_report($conn, "SELECT COUNT(*) FROM usuarios WHERE created_at >= NOW() - INTERVAL 30 DAY"),
    'logins_7' => scalar_int_report($conn, "SELECT COUNT(*) FROM usuarios WHERE last_login >= NOW() - INTERVAL 7 DAY"),
    'senderos' => scalar_int_report($conn, "SELECT COUNT(*) FROM senderos"),
    'senderos_pendientes' => scalar_int_report($conn, "SELECT COUNT(*) FROM senderos WHERE estado = 'pendiente' AND activo = 1"),
    'senderos_visitados' => scalar_int_report($conn, "SELECT COUNT(*) FROM senderos WHERE estado = 'visitado'"),
    'registros' => scalar_int_report($conn, "SELECT COUNT(*) FROM registros_senderos WHERE estado = 'registrado'"),
    'registros_7' => scalar_int_report($conn, "SELECT COUNT(*) FROM registros_senderos WHERE fecha_registro >= NOW() - INTERVAL 7 DAY"),
    'imagenes_activas' => scalar_int_report($conn, "SELECT COUNT(*) FROM sendero_imagenes WHERE activo = 1"),
    'senderos_sin_galeria' => scalar_int_report($conn, "SELECT COUNT(*) FROM senderos s WHERE s.activo = 1 AND NOT EXISTS (SELECT 1 FROM sendero_imagenes si WHERE si.sendero_id = s.id AND si.activo = 1)"),
    'participantes_con_detalle' => scalar_int_report($conn, "SELECT COUNT(*) FROM detalles_usuarios"),
    'alergicos' => scalar_int_report($conn, "SELECT COUNT(*) FROM detalles_usuarios WHERE es_alergico = 1"),
];

$usuariosPorRol = rows_report($conn, "
    SELECT r.nombre AS rol, COUNT(u.id) AS total
    FROM roles r
    LEFT JOIN usuarios u ON u.rol_id = r.id
    GROUP BY r.id, r.nombre
    ORDER BY total DESC, r.nombre ASC
");

$ultimosUsuarios = rows_report($conn, "
    SELECT u.id, u.nombre, u.apellido, u.email, u.estado, u.created_at, u.last_login, r.nombre AS rol
    FROM usuarios u
    INNER JOIN roles r ON r.id = u.rol_id
    ORDER BY u.created_at DESC
    LIMIT 8
");

$senderosPorEstado = rows_report($conn, "
    SELECT estado, COUNT(*) AS total
    FROM senderos
    GROUP BY estado
    ORDER BY total DESC
");

$senderosPorDificultad = rows_report($conn, "
    SELECT nd.nombre AS dificultad, COUNT(s.id) AS total
    FROM niveles_dificultad nd
    LEFT JOIN senderos s ON s.nivel_dificultad_id = nd.id
    GROUP BY nd.id, nd.nombre
    ORDER BY total DESC, nd.nombre ASC
");

$registrosPorSendero = rows_report($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        COUNT(rs.id) AS registros,
        SUM(CASE WHEN rs.fecha_registro >= NOW() - INTERVAL 7 DAY THEN 1 ELSE 0 END) AS registros_7
    FROM senderos s
    LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id AND rs.estado = 'registrado'
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado
    ORDER BY registros DESC, s.fecha_sendero DESC
    LIMIT 10
");

$galeriaPorSendero = rows_report($conn, "
    SELECT
        s.id,
        s.nombre,
        COUNT(si.id) AS imagenes,
        SUM(CASE WHEN si.activo = 1 THEN 1 ELSE 0 END) AS activas
    FROM senderos s
    LEFT JOIN sendero_imagenes si ON si.sendero_id = s.id
    GROUP BY s.id, s.nombre
    ORDER BY activas ASC, imagenes ASC, s.nombre ASC
    LIMIT 10
");

$gruposSanguineos = rows_report($conn, "
    SELECT grupo_sanguineo, COUNT(*) AS total
    FROM detalles_usuarios
    GROUP BY grupo_sanguineo
    ORDER BY total DESC, grupo_sanguineo ASC
");

$viasEntero = rows_report($conn, "
    SELECT via_entero, COUNT(*) AS total
    FROM detalles_usuarios
    GROUP BY via_entero
    ORDER BY total DESC, via_entero ASC
");

$experienciaSenderismo = rows_report($conn, "
    SELECT experiencia_senderismo, COUNT(*) AS total
    FROM detalles_usuarios
    GROUP BY experiencia_senderismo
    ORDER BY total DESC, experiencia_senderismo ASC
");

$tiposTerreno = rows_report($conn, "
    SELECT tt.nombre, COUNT(stt.sendero_id) AS total
    FROM tipos_terreno tt
    LEFT JOIN sendero_tipos_terreno stt ON stt.tipo_terreno_id = tt.id
    GROUP BY tt.id, tt.nombre
    ORDER BY total DESC, tt.nombre ASC
    LIMIT 8
");

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="reportes-page">
    <div class="reportes-container">
        <header class="reportes-header">
            <div>
                <span class="reportes-kicker">Analitica administrativa</span>
                <h1>Reportes</h1>
                <p>Indicadores para medir usuarios, actividad, senderos, galeria, registros y parametros operativos importantes.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="reportes-back">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <section class="metrics-grid">
            <article class="metric-card">
                <span><i data-feather="users"></i></span>
                <p>Usuarios</p>
                <strong><?= $metricas['usuarios'] ?></strong>
                <small><?= $metricas['usuarios_activos'] ?> activos / <?= $metricas['usuarios_nuevos_30'] ?> nuevos 30 dias</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="activity"></i></span>
                <p>Actividad</p>
                <strong><?= $metricas['logins_7'] ?></strong>
                <small>Usuarios con login en 7 dias</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="map"></i></span>
                <p>Senderos</p>
                <strong><?= $metricas['senderos'] ?></strong>
                <small><?= $metricas['senderos_pendientes'] ?> pendientes / <?= $metricas['senderos_visitados'] ?> visitados</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="clipboard"></i></span>
                <p>Registros</p>
                <strong><?= $metricas['registros'] ?></strong>
                <small><?= $metricas['registros_7'] ?> en ultimos 7 dias</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="image"></i></span>
                <p>Galeria</p>
                <strong><?= $metricas['imagenes_activas'] ?></strong>
                <small><?= $metricas['senderos_sin_galeria'] ?> senderos sin galeria activa</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="heart"></i></span>
                <p>Salud</p>
                <strong><?= $metricas['alergicos'] ?></strong>
                <small><?= $metricas['participantes_con_detalle'] ?> participantes con detalle</small>
            </article>
        </section>

        <section class="report-grid" id="usuarios">
            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Usuarios</span>
                        <h2>Distribucion por rol</h2>
                    </div>
                    <i data-feather="user-check"></i>
                </div>
                <div class="mini-bars">
                    <?php foreach ($usuariosPorRol as $row): ?>
                        <?php $pct = $metricas['usuarios'] > 0 ? min(100, ((int) $row['total'] / $metricas['usuarios']) * 100) : 0; ?>
                        <div class="mini-bar">
                            <div><strong><?= h($row['rol']) ?></strong><span><?= (int) $row['total'] ?></span></div>
                            <b style="width: <?= number_format($pct, 2) ?>%"></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Senderos</span>
                        <h2>Estados y dificultad</h2>
                    </div>
                    <i data-feather="trending-up"></i>
                </div>
                <div class="pill-list">
                    <?php foreach ($senderosPorEstado as $row): ?>
                        <span><?= h($row['estado']) ?> <strong><?= (int) $row['total'] ?></strong></span>
                    <?php endforeach; ?>
                    <?php foreach ($senderosPorDificultad as $row): ?>
                        <span><?= h($row['dificultad']) ?> <strong><?= (int) $row['total'] ?></strong></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Participantes</span>
                        <h2>Grupo sanguineo</h2>
                    </div>
                    <i data-feather="droplet"></i>
                </div>
                <div class="pill-list">
                    <?php if (empty($gruposSanguineos)): ?>
                        <p class="empty-note">Aun no hay registros de participantes.</p>
                    <?php endif; ?>
                    <?php foreach ($gruposSanguineos as $row): ?>
                        <span><?= h($row['grupo_sanguineo']) ?> <strong><?= (int) $row['total'] ?></strong></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Marketing</span>
                        <h2>Vias de captacion</h2>
                    </div>
                    <i data-feather="radio"></i>
                </div>
                <div class="pill-list">
                    <?php if (empty($viasEntero)): ?>
                        <p class="empty-note">Aun no hay vias registradas.</p>
                    <?php endif; ?>
                    <?php foreach ($viasEntero as $row): ?>
                        <span><?= h($row['via_entero']) ?> <strong><?= (int) $row['total'] ?></strong></span>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="report-table-card" id="actividad">
            <div class="report-head">
                <div>
                    <span>Actividad</span>
                    <h2>Ultimos usuarios registrados</h2>
                </div>
                <i data-feather="clock"></i>
            </div>
            <div class="table-wrap-report">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Ultimo login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosUsuarios as $row): ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><strong><?= h($row['nombre'] . ' ' . $row['apellido']) ?></strong><span><?= h($row['email']) ?></span></td>
                                <td><?= h($row['rol']) ?></td>
                                <td><span class="state <?= (int) $row['estado'] === 1 ? 'ok' : 'off' ?>"><?= (int) $row['estado'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                                <td><?= h(date('d/m/Y', strtotime($row['created_at']))) ?></td>
                                <td><?= $row['last_login'] ? h(date('d/m/Y h:i A', strtotime($row['last_login']))) : 'Sin acceso' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-table-grid" id="senderos">
            <article class="report-table-card">
                <div class="report-head">
                    <div>
                        <span>Registros</span>
                        <h2>Senderos con mas interes</h2>
                    </div>
                    <i data-feather="clipboard"></i>
                </div>
                <div class="table-wrap-report">
                    <table>
                        <thead>
                            <tr>
                                <th>Sendero</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Registros</th>
                                <th>7 dias</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrosPorSendero as $row): ?>
                                <tr>
                                    <td><strong><?= h($row['nombre']) ?></strong></td>
                                    <td><?= h(date('d/m/Y', strtotime($row['fecha_sendero']))) ?></td>
                                    <td><?= h($row['estado']) ?></td>
                                    <td><?= (int) $row['registros'] ?></td>
                                    <td><?= (int) $row['registros_7'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="report-table-card" id="galeria">
                <div class="report-head">
                    <div>
                        <span>Galeria</span>
                        <h2>Cobertura por sendero</h2>
                    </div>
                    <i data-feather="camera"></i>
                </div>
                <div class="table-wrap-report">
                    <table>
                        <thead>
                            <tr>
                                <th>Sendero</th>
                                <th>Total</th>
                                <th>Activas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($galeriaPorSendero as $row): ?>
                                <tr>
                                    <td><strong><?= h($row['nombre']) ?></strong></td>
                                    <td><?= (int) $row['imagenes'] ?></td>
                                    <td><span class="state <?= (int) $row['activas'] > 0 ? 'ok' : 'warn' ?>"><?= (int) $row['activas'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="report-grid" id="otros">
            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Experiencia</span>
                        <h2>Nivel declarado</h2>
                    </div>
                    <i data-feather="award"></i>
                </div>
                <div class="pill-list">
                    <?php if (empty($experienciaSenderismo)): ?>
                        <p class="empty-note">Aun no hay experiencia registrada.</p>
                    <?php endif; ?>
                    <?php foreach ($experienciaSenderismo as $row): ?>
                        <span><?= h($row['experiencia_senderismo']) ?> <strong><?= (int) $row['total'] ?></strong></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Terrenos</span>
                        <h2>Tipos mas usados</h2>
                    </div>
                    <i data-feather="layers"></i>
                </div>
                <div class="pill-list">
                    <?php foreach ($tiposTerreno as $row): ?>
                        <span><?= h($row['nombre']) ?> <strong><?= (int) $row['total'] ?></strong></span>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
