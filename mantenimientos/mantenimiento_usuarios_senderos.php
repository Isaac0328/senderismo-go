<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/filtro_senderos.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/cargos.php';
require_once __DIR__ . '/../componentes/menores_sendero.php';
require_once __DIR__ . '/../componentes/personas_sin_cuenta.php';

sg_actualizar_senderos_vencidos($conn);

$pageTitle = "Mantenimiento Usuarios Senderos";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/mantenimiento_usuarios_senderos.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/mantenimiento_usuarios_senderos.js",
    "js/directiva_manual.js"
];

function mus_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mus_fecha(?string $fecha, bool $conHora = false): string
{
    if (!$fecha) {
        return 'Sin fecha';
    }

    $time = strtotime($fecha);
    if (!$time) {
        return 'Sin fecha';
    }

    return date($conHora ? 'd/m/Y h:i A' : 'd/m/Y', $time);
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$senderoFiltros = sgf_params();
$nivelesDificultad = sgf_niveles_dificultad($conn);
[$senderoWhere, $senderoTypes, $senderoValues] = sgf_where($senderoFiltros, 's');

$senderos = [];
$resSenderos = sgf_execute_query($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        s.distancia_km,
        s.incluye_chaleco_salvavidas,
        s.menores_misma_inversion,
        nd.nombre AS dificultad_nombre,
        SUM(CASE WHEN rs.estado = 'registrado' THEN 1 + COALESCE(m.total_menores, 0) ELSE 0 END) AS activos,
        SUM(CASE WHEN rs.estado = 'cancelado' THEN 1 + COALESCE(m.total_menores, 0) ELSE 0 END) AS cancelados,
        COUNT(rs.id) AS total
    FROM senderos s
    LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id
    LEFT JOIN (
        SELECT registro_id, COUNT(*) AS total_menores
        FROM registro_sendero_menores
        GROUP BY registro_id
    ) m ON m.registro_id = rs.id
    {$senderoWhere}
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, s.incluye_chaleco_salvavidas, s.menores_misma_inversion, nd.nombre
    ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
",
    $senderoTypes,
    $senderoValues
);

if ($resSenderos) {
    while ($row = mysqli_fetch_assoc($resSenderos)) {
        $senderos[] = $row;
    }
}

$senderoSeleccionado = null;
foreach ($senderos as $sendero) {
    if ((int) $sendero['id'] === $senderoId) {
        $senderoSeleccionado = $sendero;
        break;
    }
}

// Si el sendero no admite menores, no se ofrece agregarlos.
$menoresAdmitidosSendero = true;
if ($senderoSeleccionado) {
    $stmtModo = mysqli_prepare($conn, "SELECT * FROM senderos WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtModo, 'i', $senderoId);
    mysqli_stmt_execute($stmtModo);
    $menoresAdmitidosSendero = sg_menores_admitidos(mysqli_fetch_assoc(mysqli_stmt_get_result($stmtModo)) ?: []);
    mysqli_stmt_close($stmtModo);
}

$registros = [];
$menoresPorRegistro = [];
$inversionesSendero = [];
$inversionesMenoresSendero = [];
$usuariosDisponibles = [];
$personasSinCuenta = [];
$tallasChalecos = [];
if ($senderoSeleccionado) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            rs.id AS registro_id,
            rs.estado AS estado_registro,
            rs.fecha_registro,
            rs.updated_at,
            si.nombre AS inversion_nombre,
            si.monto AS inversion_monto,
            tc.nombre AS chaleco_talla_nombre,
            spe.nombre_punto AS punto_encuentro_nombre,
            spe.hora_encuentro AS punto_encuentro_hora,
            rs.comprobante_pago_ruta,
            rs.comprobante_pago_nombre,
            rs.comprobante_pago_mime,
            COALESCE(u.id, 0) AS usuario_id,
            COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(rs.manual_nombre), ''), 'Sin nombre') AS nombre,
            COALESCE(NULLIF(TRIM(u.apellido), ''), NULLIF(TRIM(rs.manual_apellido), ''), '') AS apellido,
            COALESCE(u.user, CONCAT('manual-', rs.id)) AS user,
            COALESCE(u.email, rs.manual_email, '') AS email,
            COALESCE(u.estado, 1) AS usuario_estado,
            COALESCE(NULLIF(TRIM(du.telefono), ''), NULLIF(TRIM(dup.telefono), ''), NULLIF(TRIM(rs.manual_telefono), ''), '') AS telefono,
            COALESCE(du.rango_edad, dup.rango_edad, '') AS rango_edad,
            COALESCE(du.identificacion, dup.identificacion, '') AS identificacion,
            COALESCE(du.es_alergico, dup.es_alergico, 0) AS es_alergico,
            COALESCE(du.alergias_detalle, dup.alergias_detalle, '') AS alergias_detalle,
            COALESCE(du.grupo_sanguineo, dup.grupo_sanguineo, '') AS grupo_sanguineo,
            COALESCE(du.enfermedad, dup.enfermedad, '') AS enfermedad,
            COALESCE(du.seguro_medico, dup.seguro_medico, '') AS seguro_medico,
            COALESCE(du.experiencia_senderismo, dup.experiencia_senderismo, '') AS experiencia_senderismo,
            COALESCE(du.via_entero, dup.via_entero, '') AS via_entero,
            COALESCE(du.referido_nombre, dup.referido_nombre, '') AS referido_nombre,
            COALESCE(du.emergencia_nombre, dup.emergencia_nombre, '') AS emergencia_nombre,
            COALESCE(du.emergencia_parentesco, dup.emergencia_parentesco, '') AS emergencia_parentesco,
            COALESCE(du.emergencia_telefono, dup.emergencia_telefono, '') AS emergencia_telefono,
            COALESCE(m.total_menores, 0) AS total_menores,
            rs.registro_origen
        FROM registros_senderos rs
        LEFT JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
        LEFT JOIN detalles_usuarios dup ON dup.usuario_id = u.id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        LEFT JOIN tallas_chalecos_salvavidas tc ON tc.id = rs.chaleco_talla_id
        LEFT JOIN sendero_puntos_encuentro spe ON spe.id = rs.sendero_punto_encuentro_id
        LEFT JOIN (
            SELECT registro_id, COUNT(*) AS total_menores
            FROM registro_sendero_menores
            GROUP BY registro_id
        ) m ON m.registro_id = rs.id
        WHERE rs.sendero_id = ?
        ORDER BY
            CASE rs.estado WHEN 'registrado' THEN 0 ELSE 1 END,
            rs.fecha_registro DESC,
            COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(rs.manual_nombre), ''), 'zzz') ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $registros[] = $row;
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT rm.*, si.nombre AS inversion_nombre
         FROM registro_sendero_menores rm
         INNER JOIN registros_senderos rs ON rs.id = rm.registro_id
         LEFT JOIN sendero_inversiones si ON si.id = rm.inversion_id
         WHERE rs.sendero_id = ?
         ORDER BY rm.registro_id, rm.id"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $resMenores = mysqli_stmt_get_result($stmt);
    while ($menor = mysqli_fetch_assoc($resMenores)) {
        $menoresPorRegistro[(int) $menor['registro_id']][] = $menor;
    }
    mysqli_stmt_close($stmt);

    if ((int) ($senderoSeleccionado['incluye_chaleco_salvavidas'] ?? 0) === 1) {
        $resTallas = mysqli_query(
            $conn,
            "SELECT id, nombre, descripcion
             FROM tallas_chalecos_salvavidas
             WHERE activo = 1
             ORDER BY orden ASC, nombre ASC"
        );
        while ($resTallas && $row = mysqli_fetch_assoc($resTallas)) {
            $tallasChalecos[] = $row;
        }
    }

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
        if ((int) ($row['es_menor'] ?? 0) === 1) {
            $inversionesMenoresSendero[] = $row;
        } else {
            $inversionesSendero[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    if ((int) ($senderoSeleccionado['menores_misma_inversion'] ?? 1) === 1) {
        $inversionesMenoresSendero = $inversionesSendero;
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

$activos = 0;
$cancelados = 0;
foreach ($registros as $registro) {
    if ($registro['estado_registro'] === 'registrado') {
        $activos += 1 + (int) ($registro['total_menores'] ?? 0);
    } else {
        $cancelados += 1 + (int) ($registro['total_menores'] ?? 0);
    }
}

$puedeMantenerUsuarios = function_exists('sg_has_permission_action')
    ? sg_has_permission_action($conn, 'usuarios.usuarios', 'editar')
    : (function_exists('sg_has_permission')
        ? sg_has_permission($conn, 'usuarios.usuarios')
        : (int) ($_SESSION['usuario_rol_id'] ?? 0) === 1);
$detallesUsuariosModal = [];
foreach ($registros as $registro) {
    $detallesUsuariosModal[(string) $registro['registro_id']] = [
        'usuario_id' => (int) $registro['usuario_id'],
        'nombre' => trim((string) $registro['nombre'] . ' ' . (string) $registro['apellido']),
        'usuario' => (string) $registro['user'],
        'email' => (string) $registro['email'],
        'telefono' => (string) $registro['telefono'],
        'rango_edad' => (string) $registro['rango_edad'],
        'identificacion' => (string) $registro['identificacion'],
        'grupo_sanguineo' => (string) $registro['grupo_sanguineo'],
        'es_alergico' => (int) $registro['es_alergico'] === 1 ? 'Si' : 'No',
        'alergias_detalle' => (string) $registro['alergias_detalle'],
        'enfermedad' => (string) $registro['enfermedad'],
        'seguro_medico' => (string) $registro['seguro_medico'],
        'experiencia_senderismo' => (string) $registro['experiencia_senderismo'],
        'via_entero' => (string) $registro['via_entero'],
        'referido_nombre' => (string) $registro['referido_nombre'],
        'emergencia_nombre' => (string) $registro['emergencia_nombre'],
        'emergencia_parentesco' => (string) $registro['emergencia_parentesco'],
        'emergencia_telefono' => (string) $registro['emergencia_telefono'],
        'inversion' => (string) ($registro['inversion_nombre'] ?: 'Sin inversion'),
        'chaleco_talla' => (string) ($registro['chaleco_talla_nombre'] ?: 'No aplica'),
        'punto_encuentro' => $registro['punto_encuentro_nombre']
            ? (string) $registro['punto_encuentro_nombre'] . (!empty($registro['punto_encuentro_hora']) ? ' / ' . date('h:i A', strtotime($registro['punto_encuentro_hora'])) : '')
            : 'Sin seleccionar',
        'comprobante' => !empty($registro['comprobante_pago_ruta']) ? 'Adjunto disponible' : 'Sin comprobante',
        'registro' => mus_fecha($registro['fecha_registro'], true),
        'es_temporal' => (int) $registro['usuario_id'] <= 0,
    ];
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="mus-page">
    <section class="mus-container">
        <header class="mus-header">
            <div>
                <span class="mus-kicker">Gestion de reservas</span>
                <h1>Usuarios por sendero</h1>
                <p>Administra los usuarios registrados en cada ruta sin exponer sus datos de salud del reporte.</p>
            </div>
            <a class="mus-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['usuarios_senderos_success'])): ?>
            <div class="mus-alert success"><?= mus_h($_SESSION['usuarios_senderos_success']) ?></div>
            <?php unset($_SESSION['usuarios_senderos_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['usuarios_senderos_error'])): ?>
            <div class="mus-alert error"><?= mus_h($_SESSION['usuarios_senderos_error']) ?></div>
            <?php unset($_SESSION['usuarios_senderos_error']); ?>
        <?php endif; ?>

        <?php sgf_render([
            'params' => $senderoFiltros,
            'niveles' => $nivelesDificultad,
            'senderos' => $senderos,
            'selected_id' => $senderoId,
            'clear_url' => BASE_URL . 'mantenimientos/mantenimiento_usuarios_senderos.php',
            'card_class' => 'mus-card mus-filter',
            'head_class' => 'mus-card-head',
            'form_class' => 'mus-filter-form',
            'icon' => 'map',
            'option_label' => static function (array $sendero): string {
                $km = $sendero['distancia_km'] !== null ? ' - ' . number_format((float) $sendero['distancia_km'], 1) . ' km' : '';
                $dificultad = !empty($sendero['dificultad_nombre']) ? ' - ' . $sendero['dificultad_nombre'] : '';
                return $sendero['nombre'] . ' - ' . mus_fecha($sendero['fecha_sendero']) . $dificultad . $km . ' (' . (int) $sendero['activos'] . ' activos)';
            },
        ]); ?>

        <?php if (!$senderoSeleccionado): ?>
            <section class="mus-empty">
                <i data-feather="users"></i>
                <h2>Selecciona un sendero</h2>
                <p>Al elegir una ruta veras sus usuarios registrados y las acciones disponibles.</p>
            </section>
        <?php else: ?>
            <section class="mus-route-banner">
                <div>
                    <span><?= mus_h(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                    <h2><?= mus_h($senderoSeleccionado['nombre']) ?></h2>
                    <p>Fecha: <?= mus_h(mus_fecha($senderoSeleccionado['fecha_sendero'])) ?></p>
                </div>
                <div class="mus-route-stats">
                    <strong><?= $activos ?></strong>
                    <span>Activos</span>
                    <b><?= $cancelados ?> cancelados</b>
                </div>
            </section>

            <section class="mus-card">
                <div class="mus-card-head">
                    <div>
                        <span>Acciones</span>
                        <h2>Registros del sendero</h2>
                    </div>
                    <div class="mus-card-tools">
                        <button type="button" class="mus-open-modal" data-open-participante>
                            <i data-feather="user-plus"></i>
                            Agregar participante
                        </button>
                        <i data-feather="user-check"></i>
                    </div>
                </div>

                <?php if (empty($registros)): ?>
                    <div class="mus-empty compact">
                        <i data-feather="user-x"></i>
                        <h2>Sin registros</h2>
                        <p>Este sendero aun no tiene usuarios registrados.</p>
                    </div>
                <?php else: ?>
                    <div class="mus-table-search">
                        <i data-feather="search" aria-hidden="true"></i>
                        <label class="mus-sr-only" for="mus-record-search">Buscar registro</label>
                        <input id="mus-record-search" type="search" data-record-search placeholder="Buscar participante o menor por nombre, telefono, inversion o estado..." autocomplete="off" aria-controls="mus-records-table mus-records-mobile">
                        <select data-minor-filter aria-label="Filtrar participantes por menores asociados">
                            <option value="all">Todos</option>
                            <option value="with">Con menores</option>
                            <option value="without">Sin menores</option>
                        </select>
                    </div>
                    <div class="mus-table-wrap">
                        <table id="mus-records-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Contacto</th>
                                    <th>Inversion</th>
                                    <th>Chaleco</th>
                                    <th>Estado</th>
                                    <th>Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $registro): ?>
                                    <?php
                                    $estaActivo = $registro['estado_registro'] === 'registrado';
                                    $menoresRegistro = $menoresPorRegistro[(int) $registro['registro_id']] ?? [];
                                    $nombresMenores = array_map(static fn($m) => trim($m['nombre'] . ' ' . $m['apellido']), $menoresRegistro);
                                    $busquedaRegistro = trim($registro['nombre'] . ' ' . $registro['apellido']) . ' ' . $registro['user'] . ' ' . $registro['telefono'] . ' ' . $registro['email'] . ' ' . $registro['inversion_nombre'] . ' ' . ($estaActivo ? 'activo' : 'cancelado') . ' ' . $registro['punto_encuentro_nombre'] . ' ' . implode(' ', $nombresMenores);
                                    ?>
                                    <tr data-record-row data-minor-count="<?= count($menoresRegistro) ?>" data-record-search="<?= mus_h($busquedaRegistro) ?>">
        <td>
            <button type="button" class="mus-user-link" data-user-detail-trigger="<?= (int) $registro['registro_id'] ?>">
                <?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>
            </button>
            <span>@<?= mus_h($registro['user']) ?> / <?= (int) $registro['usuario_id'] > 0 ? 'ID ' . (int) $registro['usuario_id'] : 'Temporal' ?></span>
            <?php if ($menoresRegistro): ?>
                <details class="mus-minors-detail">
                    <summary>Menores (<?= count($menoresRegistro) ?>): <?= mus_h(implode(', ', $nombresMenores)) ?></summary>
                    <div class="mus-minors-list">
                        <?php foreach ($menoresRegistro as $menor): ?>
                            <div class="mus-minor-item">
                                <strong><?= mus_h(trim($menor['nombre'] . ' ' . $menor['apellido'])) ?></strong>
                                <span>Edad: <?= mus_h($menor['rango_edad'] ?: 'No indicada') ?> · Inversion: <?= mus_h($menor['inversion_nombre'] ?: 'No indicada') ?></span>
                                <span>Alergias: <?= (int) ($menor['es_alergico'] ?? 0) === 1 ? mus_h($menor['alergias_detalle'] ?: 'No especificadas') : 'No registradas' ?></span>
                                <?php if (!empty($menor['enfermedad'])): ?><span>Condicion: <?= mus_h($menor['enfermedad']) ?></span><?php endif; ?>
                                <?php if (!empty($menor['emergencia_nombre']) || !empty($menor['emergencia_telefono'])): ?><span>Emergencia: <?= mus_h(trim(($menor['emergencia_nombre'] ?? '') . ' / ' . ($menor['emergencia_telefono'] ?? ''), ' /')) ?></span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php else: ?><span>Sin menores</span><?php endif; ?>
            <span>Punto: <?= mus_h($registro['punto_encuentro_nombre'] ?: 'Sin seleccionar') ?><?= !empty($registro['punto_encuentro_hora']) ? ' / ' . mus_h(date('h:i A', strtotime($registro['punto_encuentro_hora']))) : '' ?></span>
        </td>
                                        <td>
                                            <strong><?= mus_h($registro['telefono'] ?: 'Sin telefono') ?></strong>
                                            <span><?= mus_h($registro['email']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= mus_h($registro['inversion_nombre'] ?: 'Sin inversion') ?></strong>
                                            <span><?= $registro['inversion_monto'] !== null ? 'RD$ ' . number_format((float) $registro['inversion_monto'], 2) : 'Sin monto' ?></span>
                                            <?php if (!empty($registro['comprobante_pago_ruta'])): ?>
                                                <a class="mus-proof-link" href="<?= BASE_URL ?>procesos/proceso_ver_comprobante_pago.php?registro_id=<?= (int) $registro['registro_id'] ?>" target="_blank" rel="noopener">
                                                    <i data-feather="paperclip"></i>
                                                    Ver comprobante
                                                </a>
                                            <?php else: ?>
                                                <span class="mus-proof-empty">Sin comprobante</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= mus_h($registro['chaleco_talla_nombre'] ?: 'No aplica') ?></strong>
                                            <span><?= $registro['chaleco_talla_nombre'] ? 'Talla seleccionada' : 'Sin chaleco' ?></span>
                                        </td>
                                        <td>
                                            <span class="mus-state <?= $estaActivo ? 'active' : 'cancelled' ?>">
                                                <?= $estaActivo ? 'Activo' : 'Cancelado' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= mus_h(mus_fecha($registro['fecha_registro'], true)) ?></strong>
                                            <span>Actualizado: <?= mus_h(mus_fecha($registro['updated_at'], true)) ?></span>
                                        </td>
                                        <td>
                                            <div class="mus-actions">
                                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" onsubmit="return confirm('Seguro que deseas <?= $estaActivo ? 'inactivar' : 'reactivar' ?> este registro?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="registro_id" value="<?= (int) $registro['registro_id'] ?>">
                                                    <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                                                    <input type="hidden" name="accion" value="<?= $estaActivo ? 'cancelar' : 'reactivar' ?>">
                                                    <button type="submit" class="<?= $estaActivo ? 'warn' : 'ok' ?>">
                                                        <?= $estaActivo ? 'Inactivar' : 'Reactivar' ?>
                                                    </button>
                                                </form>

                                                <?php if (!$estaActivo): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" data-delete-user-form data-user-name="<?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="registro_id" value="<?= (int) $registro['registro_id'] ?>">
                                                        <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <button type="submit" class="danger">Eliminar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mus-search-empty" data-record-empty hidden>No encontramos registros con esa busqueda.</div>

                    <div class="mus-mobile-list" id="mus-records-mobile">
                        <?php foreach ($registros as $registro): ?>
                            <?php
                            $estaActivo = $registro['estado_registro'] === 'registrado';
                            $menoresRegistro = $menoresPorRegistro[(int) $registro['registro_id']] ?? [];
                            $nombresMenores = array_map(static fn($m) => trim($m['nombre'] . ' ' . $m['apellido']), $menoresRegistro);
                            $busquedaRegistro = trim($registro['nombre'] . ' ' . $registro['apellido']) . ' ' . $registro['user'] . ' ' . $registro['telefono'] . ' ' . $registro['email'] . ' ' . $registro['inversion_nombre'] . ' ' . ($estaActivo ? 'activo' : 'cancelado') . ' ' . $registro['punto_encuentro_nombre'] . ' ' . implode(' ', $nombresMenores);
                            ?>
                            <article class="mus-user-card" data-record-card data-minor-count="<?= count($menoresRegistro) ?>" data-record-search="<?= mus_h($busquedaRegistro) ?>">
                                <div class="mus-user-head">
                                    <div>
                                        <button type="button" class="mus-user-link" data-user-detail-trigger="<?= (int) $registro['registro_id'] ?>">
                                            <?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>
                                        </button>
                                        <span>@<?= mus_h($registro['user']) ?></span>
                                        <?php if ($menoresRegistro): ?>
                                            <details class="mus-minors-detail">
                                                <summary>Menores (<?= count($menoresRegistro) ?>): <?= mus_h(implode(', ', $nombresMenores)) ?></summary>
                                                <div class="mus-minors-list">
                                                    <?php foreach ($menoresRegistro as $menor): ?>
                                                        <div class="mus-minor-item">
                                                            <strong><?= mus_h(trim($menor['nombre'] . ' ' . $menor['apellido'])) ?></strong>
                                                            <span>Edad: <?= mus_h($menor['rango_edad'] ?: 'No indicada') ?> · Inversion: <?= mus_h($menor['inversion_nombre'] ?: 'No indicada') ?></span>
                                                            <span>Alergias: <?= (int) ($menor['es_alergico'] ?? 0) === 1 ? mus_h($menor['alergias_detalle'] ?: 'No especificadas') : 'No registradas' ?></span>
                                                            <?php if (!empty($menor['enfermedad'])): ?><span>Condicion: <?= mus_h($menor['enfermedad']) ?></span><?php endif; ?>
                                                            <?php if (!empty($menor['emergencia_nombre']) || !empty($menor['emergencia_telefono'])): ?><span>Emergencia: <?= mus_h(trim(($menor['emergencia_nombre'] ?? '') . ' / ' . ($menor['emergencia_telefono'] ?? ''), ' /')) ?></span><?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </details>
                                        <?php else: ?><span>Sin menores</span><?php endif; ?>
                                        <span>Punto: <?= mus_h($registro['punto_encuentro_nombre'] ?: 'Sin seleccionar') ?><?= !empty($registro['punto_encuentro_hora']) ? ' / ' . mus_h(date('h:i A', strtotime($registro['punto_encuentro_hora']))) : '' ?></span>
                                    </div>
                                    <span class="mus-state <?= $estaActivo ? 'active' : 'cancelled' ?>">
                                        <?= $estaActivo ? 'Activo' : 'Cancelado' ?>
                                    </span>
                                </div>
                                <p><?= mus_h($registro['telefono'] ?: 'Sin telefono') ?> / <?= mus_h($registro['email']) ?></p>
                                <p><?= mus_h($registro['inversion_nombre'] ?: 'Sin inversion') ?> - <?= $registro['inversion_monto'] !== null ? 'RD$ ' . number_format((float) $registro['inversion_monto'], 2) : 'Sin monto' ?></p>
                                <?php if (!empty($registro['comprobante_pago_ruta'])): ?>
                                    <a class="mus-proof-link" href="<?= BASE_URL ?>procesos/proceso_ver_comprobante_pago.php?registro_id=<?= (int) $registro['registro_id'] ?>" target="_blank" rel="noopener">
                                        <i data-feather="paperclip"></i>
                                        Ver comprobante
                                    </a>
                                <?php else: ?>
                                    <span class="mus-proof-empty">Sin comprobante de pago</span>
                                <?php endif; ?>
                                <p>Chaleco: <strong><?= mus_h($registro['chaleco_talla_nombre'] ?: 'No aplica') ?></strong></p>
                                <small>Registro: <?= mus_h(mus_fecha($registro['fecha_registro'], true)) ?></small>
                                <div class="mus-actions">
                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="registro_id" value="<?= (int) $registro['registro_id'] ?>">
                                        <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                                        <input type="hidden" name="accion" value="<?= $estaActivo ? 'cancelar' : 'reactivar' ?>">
                                        <button type="submit" class="<?= $estaActivo ? 'warn' : 'ok' ?>">
                                            <?= $estaActivo ? 'Inactivar' : 'Reactivar' ?>
                                        </button>
                                    </form>
                                    <?php if (!$estaActivo): ?>
                                        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" data-delete-user-form data-user-name="<?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="registro_id" value="<?= (int) $registro['registro_id'] ?>">
                                            <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <button type="submit" class="danger">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <dialog class="mus-modal" data-participante-modal>
                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" class="mus-modal-box" data-participante-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="agregar_participante">
                    <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                    <input type="hidden" name="registro_id" value="" data-registered-id>

                    <div class="mus-modal-head">
                        <div>
                            <span>Asistencia manual</span>
                            <h2 data-participante-title>Agregar participante al sendero</h2>
                        </div>
                        <button type="button" class="mus-modal-close" data-close-participante aria-label="Cerrar">
                            <i data-feather="x"></i>
                        </button>
                    </div>

                    <div class="mus-mode-switch">
                        <label>
                            <input type="radio" name="tipo_participante" value="existente" checked>
                            Usuario existente
                        </label>
                        <label>
                            <input type="radio" name="tipo_participante" value="nuevo">
                            Persona sin cuenta
                        </label>
                        <label <?= $menoresAdmitidosSendero ? '' : 'hidden' ?>>
                            <input type="radio" name="tipo_participante" value="registrado" <?= $menoresAdmitidosSendero ? '' : 'disabled' ?>>
                            Agregar menor
                        </label>
                    </div>

                    <div class="mus-form-grid">
                        <label class="mus-field mus-registered-field" hidden>
                            <span>Usuario registrado en este sendero</span>
                            <select data-registered-select>
                                <option value="">Selecciona un usuario</option>
                                <?php foreach ($registros as $registro): ?>
                                    <?php if ($registro['estado_registro'] === 'registrado' && (int) $registro['usuario_id'] > 0): ?>
                                        <option value="<?= (int) $registro['registro_id'] ?>">
                                            <?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?> - @<?= mus_h($registro['user']) ?> (<?= (int) $registro['total_menores'] ?> menores)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="mus-field mus-existing-field" data-user-search-root>
                            <span>Elegir usuario</span>
                            <input type="hidden" name="usuario_id" value="" data-user-id-input>
                            <input type="search" placeholder="Escribe nombre, usuario, correo o telefono" autocomplete="off" data-user-search-input>
                            <div class="mus-user-results" data-user-results>
                                <?php foreach ($usuariosDisponibles as $usuario): ?>
                                    <?php
                                    $nombreUsuario = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
                                    $detalleUsuario = '@' . $usuario['user'] . ($usuario['telefono'] ? ' / ' . $usuario['telefono'] : '') . ($usuario['email'] ? ' / ' . $usuario['email'] : '');
                                    $busquedaUsuario = strtolower($nombreUsuario . ' ' . $usuario['user'] . ' ' . $usuario['email'] . ' ' . $usuario['telefono']);
                                    ?>
                                    <button type="button" data-user-option data-directiva="<?= (int) ($usuario['es_directiva'] ?? 0) === 1 ? '1' : '0' ?>" data-user-id="<?= (int) $usuario['id'] ?>" data-user-label="<?= mus_h($nombreUsuario . ' - @' . $usuario['user']) ?>" data-user-search="<?= mus_h($busquedaUsuario) ?>">
                                        <strong><?= mus_h($nombreUsuario) ?></strong>
                                        <span><?= mus_h($detalleUsuario) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <small class="mus-search-note" data-user-empty style="display:none;">No hay coincidencias con esa busqueda.</small>
                        </div>

                        <div class="mus-new-fields">
                            <?php if ($personasSinCuenta): ?>
                            <label class="mus-field mus-person-field">
                                <span>Persona sin cuenta</span>
                                <select name="persona_sin_cuenta_id" data-person-select>
                                    <option value="">Nueva persona: escribir sus datos</option>
                                    <?php foreach ($personasSinCuenta as $persona): ?>
                                        <option value="<?= (int) $persona['id'] ?>"><?= mus_h(trim($persona['nombre'] . ' ' . $persona['apellido']) . ($persona['telefono'] ? ' - ' . $persona['telefono'] : '') . ' (' . (int) $persona['inscripciones'] . ' senderos)') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Si ya vino antes, eligela para que su historial quede junto. Si luego crea su cuenta, se reconcilia en Usuarios sin cuenta.</small>
                            </label>
                            <?php endif; ?>
                            <label class="mus-field">
                                <span>Nombre</span>
                                <input type="text" name="nuevo_nombre" maxlength="100" placeholder="Nombre">
                            </label>
                            <label class="mus-field">
                                <span>Apellido</span>
                                <input type="text" name="nuevo_apellido" maxlength="100" placeholder="Apellido">
                            </label>
                            <label class="mus-field">
                                <span>Email</span>
                                <input type="email" name="nuevo_email" maxlength="100" placeholder="Opcional">
                            </label>
                            <label class="mus-field">
                                <span>Telefono</span>
                                <input type="text" name="nuevo_telefono" maxlength="20" placeholder="8090000000">
                            </label>
                        </div>

                        <label class="mus-field" data-adult-investment>
                            <span>Inversion</span>
                            <select name="inversion_id" required>
                                <option value="">Selecciona una inversion</option>
                                <?php foreach ($inversionesSendero as $inversion): ?>
                                    <option value="<?= (int) $inversion['id'] ?>">
                                        <?= mus_h($inversion['nombre']) ?> - RD$ <?= number_format((float) $inversion['monto'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <?php if ((int) ($senderoSeleccionado['incluye_chaleco_salvavidas'] ?? 0) === 1): ?>
                            <label class="mus-field" data-adult-vest>
                                <span>Talla de chaleco salvavidas</span>
                                <select name="chaleco_talla_id" required>
                                    <option value="">Selecciona una talla</option>
                                    <?php foreach ($tallasChalecos as $talla): ?>
                                        <option value="<?= (int) $talla['id'] ?>">
                                            <?= mus_h($talla['nombre']) ?><?= !empty($talla['descripcion']) ? ' - ' . mus_h($talla['descripcion']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>

                        <?php if (!$menoresAdmitidosSendero): ?>
                            <p class="mus-search-note">Este sendero no admite menores de edad.</p>
                        <?php endif; ?>
                        <section class="mus-minors-box" data-minors-root <?= $menoresAdmitidosSendero ? '' : 'hidden' ?>>
                            <div class="mus-minors-head">
                                <div>
                                    <span>Menores asociados</span>
                                    <strong>Acompañantes del participante</strong>
                                </div>
                                <button type="button" class="mus-add-minor" data-add-minor>
                                    <i data-feather="plus"></i>
                                    Agregar menor
                                </button>
                            </div>
                            <?php if (!$inversionesMenoresSendero): ?>
                                <p class="mus-search-note">Este sendero necesita una inversion activa para menores antes de agregarlos.</p>
                            <?php endif; ?>
                            <div class="mus-minors-list" data-minors-list></div>
                        </section>

                        <label class="mus-checkline" data-adult-attendance>
                            <input type="checkbox" name="marcar_asistio" value="1" checked>
                            Marcar como asistio a este sendero
                        </label>
                    </div>

                    <div class="mus-modal-actions">
                        <button type="button" class="secondary" data-close-participante>Cancelar</button>
                        <button type="submit" data-participante-submit>Guardar participante</button>
                    </div>
                </form>
            </dialog>

            <template id="musMinorTemplate">
                <section class="mus-minor-card" data-minor-card>
                    <div class="mus-minor-card-head">
                        <strong data-minor-title>Menor</strong>
                        <button type="button" data-remove-minor aria-label="Quitar menor">
                            <i data-feather="trash-2"></i>
                        </button>
                    </div>
                    <div class="mus-minor-grid">
                        <label class="mus-field">
                            <span>Nombre</span>
                            <input type="text" data-minor-name="nombre" maxlength="100" required placeholder="Nombre">
                        </label>
                        <label class="mus-field">
                            <span>Apellido</span>
                            <input type="text" data-minor-name="apellido" maxlength="100" required placeholder="Apellido">
                        </label>
                        <label class="mus-field">
                            <span>Telefono</span>
                            <input type="text" data-minor-name="telefono" maxlength="30" placeholder="Opcional">
                        </label>
                        <label class="mus-field">
                            <span>Inversion del menor</span>
                            <select data-minor-name="inversion_id" required>
                                <option value="">Selecciona una inversion</option>
                                <?php foreach ($inversionesMenoresSendero as $inversion): ?>
                                    <?php $montoMenor = (int) ($senderoSeleccionado['menores_misma_inversion'] ?? 1) === 1
                                        ? ($inversion['monto_menor'] ?? $inversion['monto'])
                                        : $inversion['monto']; ?>
                                    <option value="<?= (int) $inversion['id'] ?>">
                                        <?= mus_h($inversion['nombre']) ?> - RD$ <?= number_format((float) $montoMenor, 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="mus-field">
                            <span>Edad</span>
                            <select data-minor-name="rango_edad" required>
                                <option value="">Selecciona</option>
                                <option value="8-12">8-12</option>
                                <option value="13-17">13-17</option>
                            </select>
                        </label>
                        <label class="mus-field">
                            <span>Grupo sanguineo</span>
                            <select data-minor-name="grupo_sanguineo" required>
                                <option value="">Selecciona</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                            </select>
                        </label>
                        <label class="mus-field">
                            <span>Alergico</span>
                            <select data-minor-name="es_alergico" data-minor-allergy>
                                <option value="0">No</option>
                                <option value="1">Si</option>
                            </select>
                        </label>
                        <label class="mus-field">
                            <span>Detalle alergia</span>
                            <input type="text" data-minor-name="alergias_detalle" maxlength="255" placeholder="Solo si aplica" data-minor-allergy-detail>
                        </label>
                        <label class="mus-field">
                            <span>Enfermedad</span>
                            <input type="text" data-minor-name="enfermedad" maxlength="255" required placeholder="Ninguna / detalle">
                        </label>
                        <label class="mus-field">
                            <span>Seguro medico</span>
                            <input type="text" data-minor-name="seguro_medico" maxlength="255" required placeholder="Ninguno / nombre">
                        </label>
                        <label class="mus-field">
                            <span>Experiencia</span>
                            <select data-minor-name="experiencia_senderismo" required>
                                <option value="">Selecciona</option>
                                <option value="Primera vez">Primera vez</option>
                                <option value="Principiante">Principiante</option>
                                <option value="Intermedio">Intermedio</option>
                                <option value="Avanzado">Avanzado</option>
                            </select>
                        </label>
                        <label class="mus-field">
                            <span>Emergencia nombre</span>
                            <input type="text" data-minor-name="emergencia_nombre" maxlength="150" required placeholder="Nombre">
                        </label>
                        <label class="mus-field">
                            <span>Parentesco</span>
                            <input type="text" data-minor-name="emergencia_parentesco" maxlength="80" required placeholder="Madre, padre, tutor">
                        </label>
                        <label class="mus-field">
                            <span>Telefono emergencia</span>
                            <input type="text" data-minor-name="emergencia_telefono" maxlength="30" required placeholder="8090000000">
                        </label>
                    </div>
                </section>
            </template>

            <dialog class="mus-modal mus-detail-dialog" data-user-detail-modal>
                <section class="mus-modal-box">
                    <div class="mus-modal-head">
                        <div>
                            <span>Ficha del participante</span>
                            <h2 data-detail-name>Detalles del usuario</h2>
                            <p class="mus-detail-subtitle" data-detail-account></p>
                        </div>
                        <button type="button" class="mus-modal-close" data-close-user-detail aria-label="Cerrar">
                            <i data-feather="x"></i>
                        </button>
                    </div>

                    <div class="mus-detail-sections">
                        <section>
                            <h3>Contacto y cuenta</h3>
                            <div class="mus-detail-grid" data-detail-contact></div>
                        </section>
                        <section>
                            <h3>Salud y experiencia</h3>
                            <div class="mus-detail-grid" data-detail-health></div>
                        </section>
                        <section>
                            <h3>Contacto de emergencia</h3>
                            <div class="mus-detail-grid" data-detail-emergency></div>
                        </section>
                    </div>

                    <div class="mus-modal-actions">
                        <button type="button" class="secondary" data-close-user-detail>Cerrar</button>
                        <?php if ($puedeMantenerUsuarios): ?>
                            <a class="mus-maintenance-link" href="#" data-user-maintenance-link data-base-url="<?= BASE_URL ?>mantenimientos/mantenimiento_usuarios.php">
                                <i data-feather="settings"></i>
                                Mantenimiento
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            </dialog>

            <dialog class="mus-modal mus-confirm-dialog" data-delete-user-modal>
                <section class="mus-modal-box">
                    <div class="mus-confirm-icon"><i data-feather="trash-2"></i></div>
                    <div class="mus-confirm-copy">
                        <span>Eliminar registro</span>
                        <h2>Eliminar este usuario del sendero?</h2>
                        <p>Estas a punto de eliminar a <strong data-delete-user-name></strong> de este sendero. Esta accion no se puede deshacer.</p>
                    </div>
                    <div class="mus-modal-actions">
                        <button type="button" class="secondary" data-cancel-delete-user>Cancelar</button>
                        <button type="button" class="danger-confirm" data-confirm-delete-user>Eliminar</button>
                    </div>
                </section>
            </dialog>

            <script type="application/json" id="musUserDetailsData"><?= json_encode($detallesUsuariosModal, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <?php endif; ?>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
