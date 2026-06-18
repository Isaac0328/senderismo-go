<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/csrf.php';

$pageTitle = "Mantenimiento Usuarios Senderos | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/mantenimiento_usuarios_senderos.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
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

$senderos = [];
$resSenderos = mysqli_query($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        SUM(CASE WHEN rs.estado = 'registrado' THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN rs.estado = 'cancelado' THEN 1 ELSE 0 END) AS cancelados,
        COUNT(rs.id) AS total
    FROM senderos s
    LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado
    ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
");

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
            u.id AS usuario_id,
            u.nombre,
            u.apellido,
            u.user,
            u.email,
            u.estado AS usuario_estado,
            du.telefono
        FROM registros_senderos rs
        INNER JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        WHERE rs.sendero_id = ?
        ORDER BY
            CASE rs.estado WHEN 'registrado' THEN 0 ELSE 1 END,
            rs.fecha_registro DESC,
            u.nombre ASC"
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
        $activos++;
    } else {
        $cancelados++;
    }
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

        <section class="mus-card mus-filter">
            <div class="mus-card-head">
                <div>
                    <span>Filtro</span>
                    <h2>Seleccionar sendero</h2>
                </div>
                <i data-feather="map"></i>
            </div>
            <form method="GET" class="mus-filter-form">
                <select name="sendero_id" required>
                    <option value="">Elige un sendero</option>
                    <?php foreach ($senderos as $sendero): ?>
                        <option value="<?= (int) $sendero['id'] ?>" <?= (int) $sendero['id'] === $senderoId ? 'selected' : '' ?>>
                            <?= mus_h($sendero['nombre']) ?> - <?= mus_h(mus_fecha($sendero['fecha_sendero'])) ?> (<?= (int) $sendero['activos'] ?> activos)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">
                    <i data-feather="search"></i>
                    Consultar
                </button>
                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_usuarios_senderos.php">Limpiar</a>
            </form>
        </section>

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
                                            <strong><?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?></strong>
                                            <span>@<?= mus_h($registro['user']) ?> / ID <?= (int) $registro['usuario_id'] ?></span>
                                        </td>
                                        <td>
                                            <strong><?= mus_h($registro['telefono'] ?: 'Sin telefono') ?></strong>
                                            <span><?= mus_h($registro['email']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= mus_h($registro['inversion_nombre'] ?: 'Sin inversion') ?></strong>
                                            <span><?= $registro['inversion_monto'] !== null ? 'RD$ ' . number_format((float) $registro['inversion_monto'], 2) : 'Sin monto' ?></span>
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
                                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" onsubmit="return confirm('Eliminar permanentemente este registro cancelado?');">
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
                                        <strong><?= mus_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?></strong>
                                        <span>@<?= mus_h($registro['user']) ?></span>
                                    </div>
                                    <span class="mus-state <?= $estaActivo ? 'active' : 'cancelled' ?>">
                                        <?= $estaActivo ? 'Activo' : 'Cancelado' ?>
                                    </span>
                                </div>
                                <p><?= mus_h($registro['telefono'] ?: 'Sin telefono') ?> / <?= mus_h($registro['email']) ?></p>
                                <p><?= mus_h($registro['inversion_nombre'] ?: 'Sin inversion') ?> - <?= $registro['inversion_monto'] !== null ? 'RD$ ' . number_format((float) $registro['inversion_monto'], 2) : 'Sin monto' ?></p>
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
                                        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php">
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
                            Nuevo usuario
                        </label>
                    </div>

                    <div class="mus-form-grid">
                        <label class="mus-field mus-existing-field">
                            <span>Elegir usuario</span>
                            <select name="usuario_id">
                                <option value="">Selecciona un usuario</option>
                                <?php foreach ($usuariosDisponibles as $usuario): ?>
                                    <option value="<?= (int) $usuario['id'] ?>">
                                        <?= mus_h(trim($usuario['nombre'] . ' ' . $usuario['apellido'])) ?>
                                        - @<?= mus_h($usuario['user']) ?>
                                        <?= $usuario['telefono'] ? ' - ' . mus_h($usuario['telefono']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

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
                                <span>Usuario</span>
                                <input type="text" name="nuevo_user" maxlength="50" placeholder="Opcional, se puede generar">
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
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.querySelector('[data-participante-modal]');
    var openButton = document.querySelector('[data-open-participante]');
    var closeButtons = document.querySelectorAll('[data-close-participante]');
    var radios = document.querySelectorAll('input[name="tipo_participante"]');
    var existingField = document.querySelector('.mus-existing-field');
    var newFields = document.querySelector('.mus-new-fields');

    function syncMode() {
        var selected = document.querySelector('input[name="tipo_participante"]:checked');
        var isNew = selected && selected.value === 'nuevo';
        if (existingField) {
            existingField.style.display = isNew ? 'none' : 'grid';
        }
        if (newFields) {
            newFields.style.display = isNew ? 'grid' : 'none';
        }
    }

    if (openButton && modal) {
        openButton.addEventListener('click', function () {
            if (typeof modal.showModal === 'function') {
                modal.showModal();
            } else {
                modal.setAttribute('open', 'open');
            }
        });
    }

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            modal.close();
        });
    });

    radios.forEach(function (radio) {
        radio.addEventListener('change', syncMode);
    });
    syncMode();
});
</script>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
