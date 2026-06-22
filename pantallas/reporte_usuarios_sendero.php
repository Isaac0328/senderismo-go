<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/filtro_senderos.php';

sg_actualizar_senderos_vencidos($conn);

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

function dinero_reporte_sendero($monto): string
{
    if ($monto === null || $monto === '') {
        return 'Sin monto';
    }

    return 'RD$ ' . number_format((float) $monto, 2);
}

$senderos = [];
$senderoFiltros = sgf_params();
$nivelesDificultad = sgf_niveles_dificultad($conn);
[$senderoWhere, $senderoTypes, $senderoValues] = sgf_where($senderoFiltros, 's');

$resSenderos = sgf_execute_query($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        s.distancia_km,
        nd.nombre AS dificultad_nombre,
        COUNT(rs.id) AS total_registros
    FROM senderos s
    LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id AND rs.estado = 'registrado'
    {$senderoWhere}
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, nd.nombre
    ORDER BY s.fecha_sendero DESC, s.nombre ASC
",
    $senderoTypes,
    $senderoValues
);

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
$menoresPorRegistro = [];
if ($senderoSeleccionado) {
    $sql = "
        SELECT
            rs.id AS registro_id,
            rs.estado AS estado_registro,
            rs.fecha_registro,
            rs.updated_at,
            si.nombre AS inversion_nombre,
            si.monto AS inversion_monto,
            COALESCE(u.id, 0) AS usuario_id,
            COALESCE(u.nombre, rs.manual_nombre, 'Asistente') AS nombre,
            COALESCE(u.apellido, rs.manual_apellido, 'manual') AS apellido,
            COALESCE(u.user, CONCAT('manual-', rs.id)) AS user,
            COALESCE(u.email, rs.manual_email, '') AS email,
            COALESCE(u.estado, 1) AS usuario_estado,
            COALESCE(du.telefono, rs.manual_telefono, '') AS telefono,
            COALESCE(du.rango_edad, '') AS rango_edad,
            COALESCE(du.identificacion, '') AS identificacion,
            COALESCE(du.es_alergico, 0) AS es_alergico,
            COALESCE(du.alergias_detalle, '') AS alergias_detalle,
            COALESCE(du.grupo_sanguineo, '') AS grupo_sanguineo,
            COALESCE(du.enfermedad, '') AS enfermedad,
            COALESCE(du.seguro_medico, '') AS seguro_medico,
            COALESCE(du.experiencia_senderismo, '') AS experiencia_senderismo,
            COALESCE(du.via_entero, '') AS via_entero,
            COALESCE(du.referido_nombre, '') AS referido_nombre,
            COALESCE(du.emergencia_nombre, '') AS emergencia_nombre,
            COALESCE(du.emergencia_parentesco, '') AS emergencia_parentesco,
            COALESCE(du.emergencia_telefono, '') AS emergencia_telefono
        FROM registros_senderos rs
        LEFT JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        WHERE rs.sendero_id = ? AND rs.estado = 'registrado'
        ORDER BY rs.fecha_registro DESC, COALESCE(u.nombre, rs.manual_nombre) ASC, COALESCE(u.apellido, rs.manual_apellido) ASC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $participantes[] = $row;
    }
    mysqli_stmt_close($stmt);

    $registroIds = array_map(static function ($row) {
        return (int) $row['registro_id'];
    }, $participantes);
    if (!empty($registroIds)) {
        $idsSql = implode(',', array_unique(array_filter($registroIds)));
        if ($idsSql !== '') {
            $resMenores = mysqli_query($conn, "
                SELECT
                    rm.*,
                    si.nombre AS inversion_nombre,
                    si.monto AS inversion_monto
                FROM registro_sendero_menores rm
                LEFT JOIN sendero_inversiones si ON si.id = rm.inversion_id
                WHERE rm.registro_id IN ($idsSql)
                ORDER BY rm.registro_id ASC, rm.id ASC
            ");

            if ($resMenores) {
                while ($menor = mysqli_fetch_assoc($resMenores)) {
                    $registroId = (int) $menor['registro_id'];
                    $menoresPorRegistro[$registroId][] = $menor;
                }
            }
        }
    }
}

$totalParticipantes = count($participantes);
$totalMenores = array_sum(array_map('count', $menoresPorRegistro));
$totalGeneral = $totalParticipantes + $totalMenores;
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
foreach ($menoresPorRegistro as $menores) {
    foreach ($menores as $menor) {
        if ((int) $menor['es_alergico'] === 1) {
            $totalAlergicos++;
        }
        if (trim((string) $menor['seguro_medico']) !== '') {
            $totalSeguros++;
        }
        $grupo = trim((string) $menor['grupo_sanguineo']);
        if ($grupo !== '') {
            $grupos[$grupo] = ($grupos[$grupo] ?? 0) + 1;
        }
    }
}

$exportExcelUrl = BASE_URL . 'procesos/proceso_exportar_usuarios_sendero.php?' . http_build_query([
    'sendero_id' => $senderoId,
    'formato' => 'excel',
]);
$exportPdfUrl = BASE_URL . 'procesos/proceso_exportar_usuarios_sendero.php?' . http_build_query([
    'sendero_id' => $senderoId,
    'formato' => 'pdf',
]);

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

        <?php sgf_render([
            'params' => $senderoFiltros,
            'niveles' => $nivelesDificultad,
            'senderos' => $senderos,
            'selected_id' => $senderoId,
            'clear_url' => BASE_URL . 'pantallas/reporte_usuarios_sendero.php',
            'card_class' => 'report-card report-filter-card',
            'head_class' => 'report-head',
            'form_class' => 'sendero-report-filter',
            'icon' => 'map',
            'option_label' => static function (array $senderoItem): string {
                $km = $senderoItem['distancia_km'] !== null ? ' - ' . number_format((float) $senderoItem['distancia_km'], 1) . ' km' : '';
                $dificultad = !empty($senderoItem['dificultad_nombre']) ? ' - ' . $senderoItem['dificultad_nombre'] : '';
                return $senderoItem['nombre'] . ' - ' . fecha_reporte_sendero($senderoItem['fecha_sendero']) . $dificultad . $km . ' (' . (int) $senderoItem['total_registros'] . ' registros)';
            },
        ]); ?>

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
                <div class="selected-sendero-actions">
                    <strong><?= $totalGeneral ?></strong>
                    <div class="report-export-actions">
                        <a href="<?= hrs($exportExcelUrl) ?>">
                            <i data-feather="download"></i>
                            Excel
                        </a>
                        <a href="<?= hrs($exportPdfUrl) ?>" target="_blank" rel="noopener">
                            <i data-feather="file-text"></i>
                            PDF
                        </a>
                    </div>
                </div>
            </section>

            <section class="metrics-grid">
                <article class="metric-card">
                    <span><i data-feather="users"></i></span>
                    <p>Registrados</p>
                    <strong><?= $totalParticipantes ?></strong>
                    <small>Participantes activos en este sendero</small>
                </article>
                <article class="metric-card">
                    <span><i data-feather="user-plus"></i></span>
                    <p>Menores</p>
                    <strong><?= $totalMenores ?></strong>
                    <small>Acompanantes menores registrados</small>
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
                                    <th>Inversion</th>
                                    <th>Experiencia</th>
                                    <th>Emergencia</th>
                                    <th>Menores</th>
                                    <th>Fecha registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participantes as $row): ?>
                                    <?php $menores = $menoresPorRegistro[(int) $row['registro_id']] ?? []; ?>
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
                                            <strong><?= hrs($row['inversion_nombre'] ?: 'Sin inversion') ?></strong>
                                            <span><?= hrs(dinero_reporte_sendero($row['inversion_monto'])) ?></span>
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
                                        <td>
                                            <?php if (empty($menores)): ?>
                                                <span class="muted-cell">Sin menores</span>
                                            <?php else: ?>
                                                <div class="minor-report-list compact">
                                                    <?php foreach ($menores as $menor): ?>
                                                        <article>
                                                            <strong><?= hrs(trim($menor['nombre'] . ' ' . $menor['apellido'])) ?></strong>
                                                            <span><?= hrs($menor['rango_edad']) ?> / <?= hrs($menor['grupo_sanguineo']) ?></span>
                                                            <span><?= hrs($menor['inversion_nombre'] ?: 'Sin inversion') ?> - <?= hrs(dinero_reporte_sendero($menor['inversion_monto'])) ?></span>
                                                            <span><?= (int) $menor['es_alergico'] === 1 ? 'Alergico: ' . hrs($menor['alergias_detalle'] ?: 'No especificado') : 'No alergico' ?></span>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= hrs(fecha_reporte_sendero($row['fecha_registro'], true)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="sendero-users-cards">
                        <?php foreach ($participantes as $row): ?>
                            <?php $menores = $menoresPorRegistro[(int) $row['registro_id']] ?? []; ?>
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
                                        <dt>Inversion</dt>
                                        <dd><?= hrs($row['inversion_nombre'] ?: 'Sin inversion') ?><br><?= hrs(dinero_reporte_sendero($row['inversion_monto'])) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Experiencia</dt>
                                        <dd><?= hrs($row['experiencia_senderismo']) ?><br>Via: <?= hrs($row['via_entero']) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Emergencia</dt>
                                        <dd><?= hrs($row['emergencia_nombre']) ?><br><?= hrs($row['emergencia_parentesco']) ?> / <?= hrs($row['emergencia_telefono']) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Menores acompanantes</dt>
                                        <dd>
                                            <?php if (empty($menores)): ?>
                                                Sin menores registrados
                                            <?php else: ?>
                                                <div class="minor-report-list">
                                                    <?php foreach ($menores as $menor): ?>
                                                        <article>
                                                            <strong><?= hrs(trim($menor['nombre'] . ' ' . $menor['apellido'])) ?></strong>
                                                            <span><?= hrs($menor['rango_edad']) ?> / <?= hrs($menor['grupo_sanguineo']) ?></span>
                                                            <span><?= hrs($menor['inversion_nombre'] ?: 'Sin inversion') ?> - <?= hrs(dinero_reporte_sendero($menor['inversion_monto'])) ?></span>
                                                            <span><?= (int) $menor['es_alergico'] === 1 ? 'Alergico: ' . hrs($menor['alergias_detalle'] ?: 'No especificado') : 'No alergico' ?></span>
                                                            <span>Emergencia: <?= hrs($menor['emergencia_nombre']) ?> / <?= hrs($menor['emergencia_telefono']) ?></span>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </dd>
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
