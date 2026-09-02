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

sg_actualizar_senderos_vencidos($conn);

$pageTitle = "Mantenimiento Usuarios Senderos | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/mantenimiento_usuarios_senderos.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/mantenimiento_usuarios_senderos.js"
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
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, s.incluye_chaleco_salvavidas, nd.nombre
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

$registros = [];
$inversionesSendero = [];
$usuariosDisponibles = [];
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
        "SELECT id, nombre, monto
        FROM sendero_inversiones
        WHERE sendero_id = ? AND activo = 1
        ORDER BY orden ASC, monto ASC, nombre ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $inversionesSendero[] = $row;
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            u.id,
            u.nombre,
            u.apellido,
            u.user,
            u.email,
            du.telefono
        FROM usuarios u
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
                    <div class="mus-table-wrap">
                        <table>
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
                                    <?php $estaActivo = $registro['estado_registro'] === 'registrado'; ?>
                                    <tr>
        <td>
            <button type="button" class="mus-user-link" data-user-detail-trigger="<?= (int) $registro['registro_id'] ?>">
                <?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>
            </button>
            <span>@<?= mus_h($registro['user']) ?> / <?= (int) $registro['usuario_id'] > 0 ? 'ID ' . (int) $registro['usuario_id'] : 'Temporal' ?></span>
            <span>Menores: <?= (int) $registro['total_menores'] ?></span>
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

                    <div class="mus-mobile-list">
                        <?php foreach ($registros as $registro): ?>
                            <?php $estaActivo = $registro['estado_registro'] === 'registrado'; ?>
                            <article class="mus-user-card">
                                <div class="mus-user-head">
                                    <div>
                                        <button type="button" class="mus-user-link" data-user-detail-trigger="<?= (int) $registro['registro_id'] ?>">
                                            <?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?>
                                        </button>
                                        <span>@<?= mus_h($registro['user']) ?></span>
                                        <span>Menores: <?= (int) $registro['total_menores'] ?></span>
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
                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" class="mus-modal-box">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="agregar_participante">
                    <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">

                    <div class="mus-modal-head">
                        <div>
                            <span>Asistencia manual</span>
                            <h2>Agregar participante al sendero</h2>
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
                            Asistente temporal
                        </label>
                    </div>

                    <div class="mus-form-grid">
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
                                    <button type="button" data-user-option data-user-id="<?= (int) $usuario['id'] ?>" data-user-label="<?= mus_h($nombreUsuario . ' - @' . $usuario['user']) ?>" data-user-search="<?= mus_h($busquedaUsuario) ?>">
                                        <strong><?= mus_h($nombreUsuario) ?></strong>
                                        <span><?= mus_h($detalleUsuario) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <small class="mus-search-note" data-user-empty style="display:none;">No hay coincidencias con esa busqueda.</small>
                        </div>

                        <div class="mus-new-fields">
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

                        <label class="mus-field">
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
                            <label class="mus-field">
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

                        <section class="mus-minors-box" data-minors-root>
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
                            <div class="mus-minors-list" data-minors-list></div>
                        </section>

                        <label class="mus-checkline">
                            <input type="checkbox" name="marcar_asistio" value="1" checked>
                            Marcar como asistio a este sendero
                        </label>
                    </div>

                    <div class="mus-modal-actions">
                        <button type="button" class="secondary" data-close-participante>Cancelar</button>
                        <button type="submit">Guardar participante</button>
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
                            <span>Inversion</span>
                            <select data-minor-name="inversion_id" required>
                                <option value="">Selecciona una inversion</option>
                                <?php foreach ($inversionesSendero as $inversion): ?>
                                    <option value="<?= (int) $inversion['id'] ?>">
                                        <?= mus_h($inversion['nombre']) ?> - RD$ <?= number_format((float) $inversion['monto'], 2) ?>
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
