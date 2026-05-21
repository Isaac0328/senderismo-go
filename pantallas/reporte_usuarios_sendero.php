<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Usuarios por Sendero | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/reportes.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function hrs($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fecha_reporte_sendero(?string $fecha, bool $conHora = false): string
{
    if (!$fecha) {
        return 'Sin fecha';
    }

    $timestamp = strtotime($fecha);
    if (!$timestamp) {
        return 'Sin fecha';
    }

    return date($conHora ? 'd/m/Y h:i A' : 'd/m/Y', $timestamp);
}

$senderos = [];
$resSenderos = mysqli_query($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        COUNT(rs.id) AS total_registros
    FROM senderos s
    LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id AND rs.estado = 'registrado'
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado
    ORDER BY s.fecha_sendero DESC, s.nombre ASC
");

if ($resSenderos) {
    while ($row = mysqli_fetch_assoc($resSenderos)) {
        $senderos[] = $row;
    }
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$senderoSeleccionado = null;
foreach ($senderos as $senderoItem) {
    if ((int) $senderoItem['id'] === $senderoId) {
        $senderoSeleccionado = $senderoItem;
        break;
    }
}

$participantes = [];
if ($senderoSeleccionado) {
    $sql = "
        SELECT
            rs.id AS registro_id,
            rs.estado AS estado_registro,
            rs.fecha_registro,
            rs.updated_at,
            u.id AS usuario_id,
            u.nombre,
            u.apellido,
            u.user,
            u.email,
            u.estado AS usuario_estado,
            du.telefono,
            du.rango_edad,
            du.identificacion,
            du.es_alergico,
            du.alergias_detalle,
            du.grupo_sanguineo,
            du.enfermedad,
            du.seguro_medico,
            du.experiencia_senderismo,
            du.via_entero,
            du.referido_nombre,
            du.emergencia_nombre,
            du.emergencia_parentesco,
            du.emergencia_telefono
        FROM registros_senderos rs
        INNER JOIN usuarios u ON u.id = rs.usuario_id
        INNER JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
        WHERE rs.sendero_id = ? AND rs.estado = 'registrado'
        ORDER BY rs.fecha_registro DESC, u.nombre ASC, u.apellido ASC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $participantes[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$totalParticipantes = count($participantes);
$totalAlergicos = 0;
$totalSeguros = 0;
$grupos = [];
foreach ($participantes as $participante) {
    if ((int) $participante['es_alergico'] === 1) {
        $totalAlergicos++;
    }
    if (trim((string) $participante['seguro_medico']) !== '') {
        $totalSeguros++;
    }
    $grupo = trim((string) $participante['grupo_sanguineo']);
    if ($grupo !== '') {
        $grupos[$grupo] = ($grupos[$grupo] ?? 0) + 1;
    }
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="reportes-page">
    <div class="reportes-container">
        <header class="reportes-header">
            <div>
                <span class="reportes-kicker">Participantes</span>
                <h1>Usuarios por Sendero</h1>
                <p>Selecciona una ruta para consultar los usuarios registrados, datos de salud, contacto y fecha de registro.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="reportes-back">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <section class="report-card report-filter-card">
            <div class="report-head">
                <div>
                    <span>Filtro</span>
                    <h2>Seleccionar sendero</h2>
                </div>
                <i data-feather="map"></i>
            </div>
            <form class="sendero-report-filter" method="GET">
                <select name="sendero_id" required>
                    <option value="">Elige un sendero</option>
                    <?php foreach ($senderos as $senderoItem): ?>
                        <option value="<?= (int) $senderoItem['id'] ?>" <?= (int) $senderoItem['id'] === $senderoId ? 'selected' : '' ?>>
                            <?= hrs($senderoItem['nombre']) ?> - <?= hrs(fecha_reporte_sendero($senderoItem['fecha_sendero'])) ?> (<?= (int) $senderoItem['total_registros'] ?> registros)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">
                    <i data-feather="search"></i>
                    Consultar
                </button>
                <a href="<?= BASE_URL ?>pantallas/reporte_usuarios_sendero.php">Limpiar</a>
            </form>
        </section>

        <?php if (!$senderoSeleccionado): ?>
            <section class="report-empty-state">
                <i data-feather="users"></i>
                <h2>Selecciona un sendero</h2>
                <p>Al consultar una ruta se mostraran aqui sus participantes registrados con la informacion necesaria para gestionarlos.</p>
            </section>
        <?php else: ?>
            <section class="selected-sendero-banner">
                <div>
                    <span><?= hrs(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                    <h2><?= hrs($senderoSeleccionado['nombre']) ?></h2>
                    <p>Fecha del sendero: <?= hrs(fecha_reporte_sendero($senderoSeleccionado['fecha_sendero'])) ?></p>
                </div>
                <strong><?= $totalParticipantes ?></strong>
            </section>

            <section class="metrics-grid">
                <article class="metric-card">
                    <span><i data-feather="users"></i></span>
                    <p>Registrados</p>
                    <strong><?= $totalParticipantes ?></strong>
                    <small>Participantes activos en este sendero</small>
                </article>
                <article class="metric-card">
                    <span><i data-feather="alert-circle"></i></span>
                    <p>Alergias</p>
                    <strong><?= $totalAlergicos ?></strong>
                    <small>Personas que indicaron alergias</small>
                </article>
                <article class="metric-card">
                    <span><i data-feather="shield"></i></span>
                    <p>Seguro medico</p>
                    <strong><?= $totalSeguros ?></strong>
                    <small>Registros con dato de seguro</small>
                </article>
                <article class="metric-card">
                    <span><i data-feather="droplet"></i></span>
                    <p>Grupos sangre</p>
                    <strong><?= count($grupos) ?></strong>
                    <small><?= $grupos ? hrs(implode(' / ', array_keys($grupos))) : 'Sin grupos registrados' ?></small>
                </article>
            </section>

            <section class="report-table-card">
                <div class="report-head">
                    <div>
                        <span>Listado</span>
                        <h2>Usuarios registrados</h2>
                    </div>
                    <i data-feather="clipboard"></i>
                </div>

                <?php if (empty($participantes)): ?>
                    <div class="report-empty-state compact">
                        <i data-feather="user-x"></i>
                        <h2>Sin usuarios registrados</h2>
                        <p>Este sendero todavia no tiene participantes registrados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap-report sendero-users-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Participante</th>
                                    <th>Contacto</th>
                                    <th>Salud</th>
                                    <th>Experiencia</th>
                                    <th>Emergencia</th>
                                    <th>Fecha registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participantes as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?= hrs(trim($row['nombre'] . ' ' . $row['apellido'])) ?></strong>
                                            <span>@<?= hrs($row['user']) ?> / ID <?= (int) $row['usuario_id'] ?></span>
                                            <span><?= hrs($row['rango_edad']) ?> / <?= hrs($row['identificacion']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= hrs($row['telefono']) ?></strong>
                                            <span><?= hrs($row['email']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= hrs($row['grupo_sanguineo']) ?></strong>
                                            <span><?= (int) $row['es_alergico'] === 1 ? 'Alergico: ' . hrs($row['alergias_detalle'] ?: 'No especificado') : 'No alergico' ?></span>
                                            <span><?= hrs($row['enfermedad']) ?></span>
                                            <span>Seguro: <?= hrs($row['seguro_medico']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= hrs($row['experiencia_senderismo']) ?></strong>
                                            <span>Via: <?= hrs($row['via_entero']) ?></span>
                                            <?php if (!empty($row['referido_nombre'])): ?>
                                                <span>Referido: <?= hrs($row['referido_nombre']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= hrs($row['emergencia_nombre']) ?></strong>
                                            <span><?= hrs($row['emergencia_parentesco']) ?></span>
                                            <span><?= hrs($row['emergencia_telefono']) ?></span>
                                        </td>
                                        <td><?= hrs(fecha_reporte_sendero($row['fecha_registro'], true)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="sendero-users-cards">
                        <?php foreach ($participantes as $row): ?>
                            <article class="sendero-user-card">
                                <div class="sendero-user-card-head">
                                    <div>
                                        <strong><?= hrs(trim($row['nombre'] . ' ' . $row['apellido'])) ?></strong>
                                        <span>@<?= hrs($row['user']) ?> / <?= hrs($row['rango_edad']) ?></span>
                                    </div>
                                    <b><?= hrs($row['grupo_sanguineo']) ?></b>
                                </div>
                                <dl>
                                    <div>
                                        <dt>Registro</dt>
                                        <dd><?= hrs(fecha_reporte_sendero($row['fecha_registro'], true)) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Contacto</dt>
                                        <dd><?= hrs($row['telefono']) ?><br><?= hrs($row['email']) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Identificacion</dt>
                                        <dd><?= hrs($row['identificacion']) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Salud</dt>
                                        <dd>
                                            <?= (int) $row['es_alergico'] === 1 ? 'Alergico: ' . hrs($row['alergias_detalle'] ?: 'No especificado') : 'No alergico' ?><br>
                                            <?= hrs($row['enfermedad']) ?><br>
                                            Seguro: <?= hrs($row['seguro_medico']) ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Experiencia</dt>
                                        <dd><?= hrs($row['experiencia_senderismo']) ?><br>Via: <?= hrs($row['via_entero']) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Emergencia</dt>
                                        <dd><?= hrs($row['emergencia_nombre']) ?><br><?= hrs($row['emergencia_parentesco']) ?> / <?= hrs($row['emergencia_telefono']) ?></dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
