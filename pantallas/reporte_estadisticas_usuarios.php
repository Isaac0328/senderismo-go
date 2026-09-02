<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';

sg_actualizar_senderos_vencidos($conn);

$pageTitle = "Estadisticas de Usuarios | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/reportes.css",
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/reporte_estadisticas_usuarios.js",
];

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$rolId = max(0, (int) ($_GET['rol_id'] ?? 0));
$estado = (string) ($_GET['estado'] ?? 'todos');
$orden = (string) ($_GET['orden'] ?? 'senderos');
$ordenesValidos = ['senderos', 'kilometros', 'recientes', 'nombre'];
if (!in_array($orden, $ordenesValidos, true)) {
    $orden = 'senderos';
}

$roles = [];
$resRoles = mysqli_query($conn, "SELECT id, nombre FROM roles ORDER BY nombre ASC");
while ($resRoles && $row = mysqli_fetch_assoc($resRoles)) {
    $roles[] = $row;
}

function rue_scalar_int(mysqli $conn, string $sql): int
{
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return 0;
    }

    $row = mysqli_fetch_row($res);
    return (int) ($row[0] ?? 0);
}

function rue_scalar_float(mysqli $conn, string $sql): float
{
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return 0.0;
    }

    $row = mysqli_fetch_row($res);
    return (float) ($row[0] ?? 0);
}

$metricas = [
    'usuarios' => rue_scalar_int($conn, "SELECT COUNT(*) FROM usuarios"),
    'activos' => rue_scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE estado = 1"),
    'con_perfil' => rue_scalar_int($conn, "SELECT COUNT(*) FROM detalles_usuarios"),
    'asistencias' => rue_scalar_int($conn, "SELECT COUNT(*) FROM registros_senderos WHERE estado = 'registrado' AND asistio = 1 AND usuario_id IS NOT NULL"),
    'km' => rue_scalar_float($conn, "
        SELECT COALESCE(SUM(COALESCE(s.distancia_km, 0)), 0)
        FROM registros_senderos rs
        INNER JOIN senderos s ON s.id = rs.sendero_id
        WHERE rs.estado = 'registrado' AND rs.asistio = 1 AND rs.usuario_id IS NOT NULL
    "),
];

$where = [];
$types = '';
$values = [];

if ($buscar !== '') {
    $where[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR u.user LIKE ? OR u.email LIKE ? OR du.telefono LIKE ?)";
    $like = '%' . $buscar . '%';
    array_push($values, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($rolId > 0) {
    $where[] = "u.rol_id = ?";
    $values[] = $rolId;
    $types .= 'i';
}

if ($estado === 'activo') {
    $where[] = "u.estado = 1";
} elseif ($estado === 'inactivo') {
    $where[] = "u.estado = 0";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderSql = match ($orden) {
    'kilometros' => 'stats.km_asistidos DESC, stats.senderos_asistidos DESC, u.nombre ASC',
    'recientes' => 'stats.ultima_asistencia DESC, u.created_at DESC, u.nombre ASC',
    'nombre' => 'u.nombre ASC, u.apellido ASC',
    default => 'stats.senderos_asistidos DESC, stats.km_asistidos DESC, u.nombre ASC',
};

$sqlUsuarios = "
    SELECT
        u.id,
        u.nombre,
        u.apellido,
        u.user,
        u.email,
        u.estado,
        u.created_at,
        u.last_login,
        r.nombre AS rol_nombre,
        COALESCE(du.telefono, '') AS telefono,
        COALESCE(du.rango_edad, '') AS rango_edad,
        COALESCE(du.identificacion, '') AS identificacion,
        COALESCE(du.grupo_sanguineo, '') AS grupo_sanguineo,
        COALESCE(du.es_alergico, 0) AS es_alergico,
        COALESCE(du.alergias_detalle, '') AS alergias_detalle,
        COALESCE(du.enfermedad, '') AS enfermedad,
        COALESCE(du.seguro_medico, '') AS seguro_medico,
        COALESCE(du.experiencia_senderismo, '') AS experiencia_senderismo,
        COALESCE(du.via_entero, '') AS via_entero,
        COALESCE(du.referido_nombre, '') AS referido_nombre,
        COALESCE(du.emergencia_nombre, '') AS emergencia_nombre,
        COALESCE(du.emergencia_parentesco, '') AS emergencia_parentesco,
        COALESCE(du.emergencia_telefono, '') AS emergencia_telefono,
        COALESCE(du.imagen_perfil, '') AS imagen_perfil,
        COALESCE(stats.registros, 0) AS registros,
        COALESCE(stats.senderos_asistidos, 0) AS senderos_asistidos,
        COALESCE(stats.km_asistidos, 0) AS km_asistidos,
        stats.ultima_asistencia,
        COALESCE(mins.menores, 0) AS menores
    FROM usuarios u
    INNER JOIN roles r ON r.id = u.rol_id
    LEFT JOIN detalles_usuarios du ON du.usuario_id = u.id
    LEFT JOIN (
        SELECT
            rs.usuario_id,
            COUNT(*) AS registros,
            COUNT(DISTINCT CASE WHEN rs.asistio = 1 THEN rs.sendero_id END) AS senderos_asistidos,
            SUM(CASE WHEN rs.asistio = 1 THEN COALESCE(s.distancia_km, 0) ELSE 0 END) AS km_asistidos,
            MAX(CASE WHEN rs.asistio = 1 THEN COALESCE(rs.fecha_asistencia, s.fecha_sendero) END) AS ultima_asistencia
        FROM registros_senderos rs
        INNER JOIN senderos s ON s.id = rs.sendero_id
        WHERE rs.estado = 'registrado' AND rs.usuario_id IS NOT NULL
        GROUP BY rs.usuario_id
    ) stats ON stats.usuario_id = u.id
    LEFT JOIN (
        SELECT rs.usuario_id, COUNT(rm.id) AS menores
        FROM registros_senderos rs
        INNER JOIN registro_sendero_menores rm ON rm.registro_id = rs.id
        WHERE rs.usuario_id IS NOT NULL AND rs.estado = 'registrado'
        GROUP BY rs.usuario_id
    ) mins ON mins.usuario_id = u.id
    $whereSql
    ORDER BY $orderSql
    LIMIT 80
";

$stmt = mysqli_prepare($conn, $sqlUsuarios);
if ($stmt && $types !== '') {
    $bindValues = [$types];
    foreach ($values as $key => $value) {
        $bindValues[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $resUsuarios = mysqli_stmt_get_result($stmt);
} else {
    $resUsuarios = false;
}

$usuarios = [];
while ($resUsuarios && $row = mysqli_fetch_assoc($resUsuarios)) {
    $usuarios[] = $row;
}
if ($stmt) {
    mysqli_stmt_close($stmt);
}

$idsUsuarios = array_map(static fn ($row) => (int) $row['id'], $usuarios);
$idsSql = implode(',', array_unique(array_filter($idsUsuarios)));
$rutasPorUsuario = [];
if ($idsSql !== '') {
    $resRutas = mysqli_query($conn, "
        SELECT
            rs.usuario_id,
            s.id AS sendero_id,
            s.nombre,
            s.estado,
            s.fecha_sendero,
            s.distancia_km,
            rs.asistio,
            rs.fecha_registro
        FROM registros_senderos rs
        INNER JOIN senderos s ON s.id = rs.sendero_id
        WHERE rs.usuario_id IN ($idsSql) AND rs.estado = 'registrado'
        ORDER BY rs.usuario_id ASC, rs.asistio DESC, s.fecha_sendero DESC, rs.fecha_registro DESC
    ");
    while ($resRutas && $ruta = mysqli_fetch_assoc($resRutas)) {
        $uid = (int) $ruta['usuario_id'];
        if (!isset($rutasPorUsuario[$uid])) {
            $rutasPorUsuario[$uid] = [];
        }
        if (count($rutasPorUsuario[$uid]) < 8) {
            $rutasPorUsuario[$uid][] = $ruta;
        }
    }
}

$topSenderos = array_slice($usuarios, 0, 6);
$topKilometros = $usuarios;
usort($topKilometros, static function (array $a, array $b): int {
    $cmpKm = (float) $b['km_asistidos'] <=> (float) $a['km_asistidos'];
    return $cmpKm !== 0 ? $cmpKm : ((int) $b['senderos_asistidos'] <=> (int) $a['senderos_asistidos']);
});
$topKilometros = array_slice($topKilometros, 0, 6);

$detalleUsuarios = [];
foreach ($usuarios as $usuario) {
    $uid = (int) $usuario['id'];
    $rutasDetalle = [];
    foreach ($rutasPorUsuario[$uid] ?? [] as $ruta) {
        $rutasDetalle[] = [
            'nombre' => (string) $ruta['nombre'],
            'fecha' => sg_fecha($ruta['fecha_sendero']),
            'km' => number_format((float) ($ruta['distancia_km'] ?? 0), 2) . ' km',
            'asistio' => (int) $ruta['asistio'] === 1 ? 'Asistio' : 'Registrado',
            'url' => BASE_URL . 'pantallas/senderos_detalle.php?id=' . (int) $ruta['sendero_id'],
        ];
    }

    $detalleUsuarios[(string) $uid] = [
        'id' => $uid,
        'nombre' => trim((string) ($usuario['nombre'] . ' ' . $usuario['apellido'])),
        'usuario' => (string) $usuario['user'],
        'rol' => (string) $usuario['rol_nombre'],
        'estado' => (int) $usuario['estado'] === 1 ? 'Activo' : 'Inactivo',
        'email' => (string) $usuario['email'],
        'telefono' => (string) $usuario['telefono'],
        'edad' => (string) $usuario['rango_edad'],
        'identificacion' => (string) $usuario['identificacion'],
        'grupo_sanguineo' => (string) $usuario['grupo_sanguineo'],
        'alergias' => (int) $usuario['es_alergico'] === 1 ? ((string) $usuario['alergias_detalle'] ?: 'No especificado') : 'No alergico',
        'enfermedad' => (string) $usuario['enfermedad'],
        'seguro' => (string) $usuario['seguro_medico'],
        'experiencia' => (string) $usuario['experiencia_senderismo'],
        'via' => (string) $usuario['via_entero'],
        'referido' => (string) $usuario['referido_nombre'],
        'emergencia' => trim((string) ($usuario['emergencia_nombre'] . ' / ' . $usuario['emergencia_parentesco'] . ' / ' . $usuario['emergencia_telefono']), ' /'),
        'senderos' => (int) $usuario['senderos_asistidos'],
        'registros' => (int) $usuario['registros'],
        'km' => number_format((float) $usuario['km_asistidos'], 2) . ' km',
        'menores' => (int) $usuario['menores'],
        'creado' => sg_fecha($usuario['created_at'], true),
        'ultimo_login' => sg_fecha($usuario['last_login'], true, 'Sin login'),
        'ultima_asistencia' => sg_fecha($usuario['ultima_asistencia'], false, 'Sin asistencia'),
        'mantenimiento_url' => BASE_URL . 'mantenimientos/mantenimiento_usuarios.php?usuario_id=' . $uid,
        'rutas' => $rutasDetalle,
    ];
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="reportes-page user-stats-page">
    <div class="reportes-container">
        <header class="reportes-header">
            <div>
                <span class="reportes-kicker">Analitica de usuarios</span>
                <h1>Reporte de Usuarios</h1>
                <p>Consulta rendimiento, kilometraje, asistencia y perfil operativo de cada usuario desde una vista limpia.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="reportes-back">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <section class="user-stats-hero">
            <article>
                <span><i data-feather="users"></i></span>
                <p>Usuarios activos</p>
                <strong><?= (int) $metricas['activos'] ?></strong>
                <small>de <?= (int) $metricas['usuarios'] ?> registrados</small>
            </article>
            <article>
                <span><i data-feather="user-check"></i></span>
                <p>Perfiles completos</p>
                <strong><?= (int) $metricas['con_perfil'] ?></strong>
                <small>con datos de salud y emergencia</small>
            </article>
            <article>
                <span><i data-feather="check-circle"></i></span>
                <p>Asistencias</p>
                <strong><?= (int) $metricas['asistencias'] ?></strong>
                <small>participaciones confirmadas</small>
            </article>
            <article>
                <span><i data-feather="navigation"></i></span>
                <p>Kilometraje</p>
                <strong><?= number_format((float) $metricas['km'], 1) ?></strong>
                <small>km caminados registrados</small>
            </article>
        </section>

        <section class="report-card user-report-filter">
            <div class="report-head">
                <div>
                    <span>Filtro</span>
                    <h2>Buscar usuarios</h2>
                </div>
                <i data-feather="filter"></i>
            </div>
            <form method="GET" class="user-report-filter-grid">
                <label>
                    Buscar
                    <input type="search" name="buscar" value="<?= sg_h($buscar) ?>" placeholder="Nombre, usuario, correo o telefono">
                </label>
                <label>
                    Rol
                    <select name="rol_id">
                        <option value="0">Todos</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= (int) $rol['id'] ?>" <?= (int) $rol['id'] === $rolId ? 'selected' : '' ?>><?= sg_h($rol['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Estado
                    <select name="estado">
                        <option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activos</option>
                        <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </label>
                <label>
                    Ordenar por
                    <select name="orden">
                        <option value="senderos" <?= $orden === 'senderos' ? 'selected' : '' ?>>Mas senderos</option>
                        <option value="kilometros" <?= $orden === 'kilometros' ? 'selected' : '' ?>>Mas kilometraje</option>
                        <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Actividad reciente</option>
                        <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                    </select>
                </label>
                <div class="user-report-filter-actions">
                    <button type="submit"><i data-feather="search"></i> Buscar</button>
                    <a href="<?= BASE_URL ?>pantallas/reporte_estadisticas_usuarios.php">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="user-ranking-grid">
            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Ranking</span>
                        <h2>Mas senderos asistidos</h2>
                    </div>
                    <i data-feather="award"></i>
                </div>
                <div class="user-ranking-list">
                    <?php foreach ($topSenderos as $index => $row): ?>
                        <button type="button" data-user-stats-detail="<?= (int) $row['id'] ?>">
                            <b><?= $index + 1 ?></b>
                            <span>
                                <strong><?= sg_h(trim($row['nombre'] . ' ' . $row['apellido'])) ?></strong>
                                <small><?= (int) $row['senderos_asistidos'] ?> senderos / <?= number_format((float) $row['km_asistidos'], 1) ?> km</small>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Distancia</span>
                        <h2>Mas kilometraje acumulado</h2>
                    </div>
                    <i data-feather="trending-up"></i>
                </div>
                <div class="user-ranking-list km">
                    <?php foreach ($topKilometros as $index => $row): ?>
                        <button type="button" data-user-stats-detail="<?= (int) $row['id'] ?>">
                            <b><?= $index + 1 ?></b>
                            <span>
                                <strong><?= sg_h(trim($row['nombre'] . ' ' . $row['apellido'])) ?></strong>
                                <small><?= number_format((float) $row['km_asistidos'], 1) ?> km / <?= (int) $row['senderos_asistidos'] ?> senderos</small>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="report-table-card user-stats-table-card">
            <div class="report-head">
                <div>
                    <span>Listado</span>
                    <h2><?= count($usuarios) ?> usuarios encontrados</h2>
                </div>
                <i data-feather="clipboard"></i>
            </div>
            <?php if (empty($usuarios)): ?>
                <div class="report-empty-state compact">
                    <i data-feather="user-x"></i>
                    <h2>Sin resultados</h2>
                    <p>No encontramos usuarios con los criterios seleccionados.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap-report user-stats-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Contacto</th>
                                <th>Rol</th>
                                <th>Senderos</th>
                                <th>Kilometros</th>
                                <th>Ultima asistencia</th>
                                <th>Perfil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $row): ?>
                                <tr>
                                    <td>
                                        <button type="button" class="report-user-link" data-user-stats-detail="<?= (int) $row['id'] ?>">
                                            <?= sg_h(trim($row['nombre'] . ' ' . $row['apellido'])) ?>
                                        </button>
                                        <span>@<?= sg_h($row['user']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= sg_h($row['telefono'] ?: 'Sin telefono') ?></strong>
                                        <span><?= sg_h($row['email']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= sg_h($row['rol_nombre']) ?></strong>
                                        <span class="state <?= (int) $row['estado'] === 1 ? 'ok' : 'off' ?>"><?= (int) $row['estado'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                    </td>
                                    <td><strong><?= (int) $row['senderos_asistidos'] ?></strong><span><?= (int) $row['registros'] ?> registros</span></td>
                                    <td><strong><?= number_format((float) $row['km_asistidos'], 2) ?> km</strong><span><?= (int) $row['menores'] ?> menores asociados</span></td>
                                    <td>
                                        <strong><?= sg_fecha($row['ultima_asistencia'], false, 'Sin asistencia') ?></strong>
                                        <span>Login: <?= sg_fecha($row['last_login'], true, 'Sin login') ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="report-row-link as-button" data-user-stats-detail="<?= (int) $row['id'] ?>">Ver perfil</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <dialog class="report-user-modal" data-user-stats-modal>
            <section class="report-user-modal-box">
                <div class="report-user-modal-head">
                    <div>
                        <span>Perfil del usuario</span>
                        <h2 data-user-stats-name>Usuario</h2>
                        <p data-user-stats-account></p>
                    </div>
                    <button type="button" data-user-stats-close aria-label="Cerrar">
                        <i data-feather="x"></i>
                    </button>
                </div>

                <section class="user-stats-modal-summary">
                    <article><span>Senderos</span><strong data-user-stats-senderos>0</strong></article>
                    <article><span>Kilometros</span><strong data-user-stats-km>0 km</strong></article>
                    <article><span>Registros</span><strong data-user-stats-registros>0</strong></article>
                    <article><span>Menores</span><strong data-user-stats-menores>0</strong></article>
                </section>

                <div class="report-user-detail-grid" data-user-stats-grid></div>

                <section class="report-user-minors user-stats-routes" data-user-stats-routes-wrap hidden>
                    <h3>Rutas recientes</h3>
                    <div data-user-stats-routes></div>
                </section>

                <div class="report-user-modal-actions user-stats-modal-actions">
                    <a href="#" data-user-stats-maintenance>Mantenimiento</a>
                    <button type="button" data-user-stats-close>Cerrar</button>
                </div>
            </section>
        </dialog>
        <script id="userStatsDetailsData" type="application/json"><?= json_encode($detalleUsuarios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
