<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Reporte Contacto | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/reportes.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function hr($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function contacto_count(mysqli $conn, string $where = '1=1'): int
{
    $res = mysqli_query($conn, "SELECT COUNT(*) FROM mensajes_contacto WHERE {$where}");
    if (!$res) {
        return 0;
    }
    $row = mysqli_fetch_row($res);
    return (int) ($row[0] ?? 0);
}

$estadoFiltro = trim((string) ($_GET['estado'] ?? ''));
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$permitidos = ['nuevo', 'leido', 'respondido', 'archivado'];

$where = [];
$params = [];
$types = '';

if (in_array($estadoFiltro, $permitidos, true)) {
    $where[] = 'estado = ?';
    $params[] = $estadoFiltro;
    $types .= 's';
}

if ($buscar !== '') {
    $where[] = '(nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR asunto LIKE ? OR mensaje LIKE ?)';
    $like = '%' . $buscar . '%';
    for ($i = 0; $i < 5; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}

$whereSql = $where ? implode(' AND ', $where) : '1=1';
$sql = "SELECT * FROM mensajes_contacto WHERE {$whereSql} ORDER BY fecha_creacion DESC LIMIT 200";
$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$mensajes = [];
while ($row = mysqli_fetch_assoc($res)) {
    $mensajes[] = $row;
}
mysqli_stmt_close($stmt);

$metricas = [
    'total' => contacto_count($conn),
    'nuevo' => contacto_count($conn, "estado = 'nuevo'"),
    'respondido' => contacto_count($conn, "estado = 'respondido'"),
    'semana' => contacto_count($conn, "fecha_creacion >= NOW() - INTERVAL 7 DAY"),
];

$asuntos = [];
$resAsuntos = mysqli_query($conn, "
    SELECT asunto, COUNT(*) AS total
    FROM mensajes_contacto
    GROUP BY asunto
    ORDER BY total DESC, asunto ASC
    LIMIT 8
");
if ($resAsuntos) {
    while ($row = mysqli_fetch_assoc($resAsuntos)) {
        $asuntos[] = $row;
    }
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="reportes-page">
    <div class="reportes-container">
        <header class="reportes-header">
            <div>
                <span class="reportes-kicker">Buzon de contacto</span>
                <h1>Reporte Contacto</h1>
                <p>Consulta los mensajes que completan los visitantes desde la pagina de contacto.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="reportes-back">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['reporte_contacto_success'])): ?>
            <div class="contacto-alert success"><?= hr($_SESSION['reporte_contacto_success']) ?></div>
            <?php unset($_SESSION['reporte_contacto_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['reporte_contacto_error'])): ?>
            <div class="contacto-alert error"><?= hr($_SESSION['reporte_contacto_error']) ?></div>
            <?php unset($_SESSION['reporte_contacto_error']); ?>
        <?php endif; ?>

        <section class="metrics-grid">
            <article class="metric-card">
                <span><i data-feather="mail"></i></span>
                <p>Total mensajes</p>
                <strong><?= $metricas['total'] ?></strong>
                <small>Solicitudes registradas</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="bell"></i></span>
                <p>Nuevos</p>
                <strong><?= $metricas['nuevo'] ?></strong>
                <small>Pendientes de revisar</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="check-circle"></i></span>
                <p>Respondidos</p>
                <strong><?= $metricas['respondido'] ?></strong>
                <small>Marcados como gestionados</small>
            </article>
            <article class="metric-card">
                <span><i data-feather="calendar"></i></span>
                <p>Ultimos 7 dias</p>
                <strong><?= $metricas['semana'] ?></strong>
                <small>Actividad reciente</small>
            </article>
        </section>

        <section class="report-card" style="margin-bottom:12px;">
            <div class="report-head">
                <div>
                    <span>Filtros</span>
                    <h2>Buscar mensajes</h2>
                </div>
                <i data-feather="search"></i>
            </div>
            <form class="contact-report-filter" method="GET">
                <input type="text" name="buscar" value="<?= hr($buscar) ?>" placeholder="Buscar por nombre, email, asunto o mensaje">
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach ($permitidos as $estado): ?>
                        <option value="<?= hr($estado) ?>" <?= $estadoFiltro === $estado ? 'selected' : '' ?>><?= hr(ucfirst($estado)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filtrar</button>
                <a href="<?= BASE_URL ?>pantallas/reporte_contacto.php">Limpiar</a>
            </form>
        </section>

        <section class="report-grid">
            <article class="report-card">
                <div class="report-head">
                    <div>
                        <span>Asuntos</span>
                        <h2>Temas mas consultados</h2>
                    </div>
                    <i data-feather="tag"></i>
                </div>
                <div class="pill-list">
                    <?php if (empty($asuntos)): ?>
                        <p class="empty-note">Aun no hay mensajes registrados.</p>
                    <?php endif; ?>
                    <?php foreach ($asuntos as $row): ?>
                        <span><?= hr($row['asunto']) ?> <strong><?= (int) $row['total'] ?></strong></span>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="report-table-card">
            <div class="report-head">
                <div>
                    <span>Mensajes</span>
                    <h2>Solicitudes recibidas</h2>
                </div>
                <i data-feather="inbox"></i>
            </div>
            <div class="table-wrap-report">
                <table>
                    <thead>
                        <tr>
                            <th>Contacto</th>
                            <th>Asunto</th>
                            <th>Mensaje</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mensajes)): ?>
                            <tr>
                                <td colspan="6">No hay mensajes para los filtros seleccionados.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($mensajes as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= hr(trim($row['nombre'] . ' ' . $row['apellido'])) ?></strong>
                                    <span><?= hr($row['email']) ?><?= $row['telefono'] ? ' / ' . hr($row['telefono']) : '' ?></span>
                                </td>
                                <td><?= hr($row['asunto']) ?></td>
                                <td><span><?= hr($row['mensaje']) ?></span></td>
                                <td><?= hr(date('d/m/Y h:i A', strtotime($row['fecha_creacion']))) ?></td>
                                <td><span class="state <?= $row['estado'] === 'respondido' ? 'ok' : ($row['estado'] === 'archivado' ? 'off' : 'warn') ?>"><?= hr($row['estado']) ?></span></td>
                                <td>
                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_reporte_contacto.php" class="inline-report-form">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <select name="estado">
                                            <?php foreach ($permitidos as $estado): ?>
                                                <option value="<?= hr($estado) ?>" <?= $row['estado'] === $estado ? 'selected' : '' ?>><?= hr(ucfirst($estado)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">Guardar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
