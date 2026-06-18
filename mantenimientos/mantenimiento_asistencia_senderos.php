<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/csrf.php';

$pageTitle = "Asistencia por Sendero | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/asistencia_senderos.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function asis_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function asis_fecha(?string $fecha, bool $conHora = false): string
{
    if (!$fecha) {
        return 'Sin fecha';
    }
    $time = strtotime($fecha);
    return $time ? date($conHora ? 'd/m/Y h:i A' : 'd/m/Y', $time) : 'Sin fecha';
}

function asis_money($monto): string
{
    return $monto === null || $monto === '' ? 'Sin monto' : 'RD$ ' . number_format((float) $monto, 2);
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);

$senderos = [];
$resSenderos = mysqli_query($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        COUNT(rs.id) AS registrados,
        SUM(CASE WHEN rs.asistio = 1 AND rs.estado = 'registrado' THEN 1 ELSE 0 END) AS asistieron
    FROM senderos s
    LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id AND rs.estado = 'registrado'
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado
    ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
");
while ($resSenderos && $row = mysqli_fetch_assoc($resSenderos)) {
    $senderos[] = $row;
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
$totalMenores = 0;
if ($senderoSeleccionado) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            rs.id AS registro_id,
            rs.fecha_registro,
            rs.asistio,
            rs.fecha_asistencia,
            rs.asistencia_notas,
            si.nombre AS inversion_nombre,
            si.monto AS inversion_monto,
            u.id AS usuario_id,
            u.nombre,
            u.apellido,
            u.user,
            u.email,
            du.telefono,
            COALESCE(m.menores, 0) AS total_menores
        FROM registros_senderos rs
        INNER JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        LEFT JOIN (
            SELECT registro_id, COUNT(*) AS menores
            FROM registro_sendero_menores
            GROUP BY registro_id
        ) m ON m.registro_id = rs.id
        WHERE rs.sendero_id = ? AND rs.estado = 'registrado'
        ORDER BY rs.asistio DESC, u.nombre ASC, u.apellido ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $totalMenores += (int) ($row['total_menores'] ?? 0);
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

$totalRegistros = count($registros);
$totalAsistieron = array_sum(array_map(static fn($r) => (int) ($r['asistio'] ?? 0), $registros));
$pendientes = max(0, $totalRegistros - $totalAsistieron);

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="asis-page">
    <section class="asis-container">
        <header class="asis-header">
            <div>
                <span class="asis-kicker">Control de asistencia</span>
                <h1>Asistencia por sendero</h1>
                <p>Marca quienes realmente asistieron. Estos datos alimentaran el historial del usuario y futuras metricas, premios e insignias.</p>
            </div>
            <a class="asis-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['asistencia_success'])): ?>
            <div class="asis-alert success"><?= asis_h($_SESSION['asistencia_success']) ?></div>
            <?php unset($_SESSION['asistencia_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['asistencia_error'])): ?>
            <div class="asis-alert error"><?= asis_h($_SESSION['asistencia_error']) ?></div>
            <?php unset($_SESSION['asistencia_error']); ?>
        <?php endif; ?>

        <section class="asis-card asis-filter">
            <div class="asis-card-head">
                <div>
                    <span>Filtro</span>
                    <h2>Seleccionar sendero</h2>
                </div>
                <i data-feather="map"></i>
            </div>
            <form method="GET" class="asis-filter-form">
                <select name="sendero_id" required>
                    <option value="">Elige un sendero</option>
                    <?php foreach ($senderos as $sendero): ?>
                        <?php $registrados = (int) ($sendero['registrados'] ?? 0); ?>
                        <?php $asistieron = (int) ($sendero['asistieron'] ?? 0); ?>
                        <option value="<?= (int) $sendero['id'] ?>" <?= (int) $sendero['id'] === $senderoId ? 'selected' : '' ?>>
                            <?= asis_h($sendero['nombre']) ?> - <?= asis_h(asis_fecha($sendero['fecha_sendero'])) ?> (<?= $asistieron ?>/<?= $registrados ?> asistieron)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">
                    <i data-feather="search"></i>
                    Consultar
                </button>
                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_asistencia_senderos.php">Limpiar</a>
            </form>
        </section>

        <?php if (!$senderoSeleccionado): ?>
            <section class="asis-empty">
                <i data-feather="check-circle"></i>
                <h2>Selecciona un sendero</h2>
                <p>Al elegir una ruta veras los inscritos activos para marcar asistencia real.</p>
            </section>
        <?php else: ?>
            <section class="asis-route-banner">
                <div>
                    <span><?= asis_h(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                    <h2><?= asis_h($senderoSeleccionado['nombre']) ?></h2>
                    <p>Fecha: <?= asis_h(asis_fecha($senderoSeleccionado['fecha_sendero'])) ?></p>
                </div>
                <div class="asis-route-stats">
                    <article>
                        <strong><?= $totalRegistros ?></strong>
                        <span>Inscritos</span>
                    </article>
                    <article class="ok">
                        <strong><?= $totalAsistieron ?></strong>
                        <span>Asistieron</span>
                    </article>
                    <article class="warn">
                        <strong><?= $pendientes ?></strong>
                        <span>Pendientes</span>
                    </article>
                    <article>
                        <strong><?= $totalMenores ?></strong>
                        <span>Menores</span>
                    </article>
                </div>
            </section>

            <section class="asis-card">
                <div class="asis-card-head">
                    <div>
                        <span>Listado</span>
                        <h2>Participantes inscritos</h2>
                    </div>
                    <div class="asis-card-tools">
                        <button type="button" class="asis-open-modal" data-open-participante>
                            <i data-feather="user-plus"></i>
                            Agregar asistente
                        </button>
                        <i data-feather="clipboard"></i>
                    </div>
                </div>

                <?php if (empty($registros)): ?>
                    <div class="asis-empty compact">
                        <i data-feather="user-x"></i>
                        <h2>Sin inscritos activos</h2>
                        <p>Este sendero no tiene reservas activas para marcar asistencia.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_asistencia_senderos.php" class="asis-attendance-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                        <div class="asis-tools">
                            <button type="button" data-check-all>Marcar todos</button>
                            <button type="button" data-uncheck-all>Limpiar marcas</button>
                            <button type="submit" class="primary">Guardar asistencia</button>
                        </div>

                        <div class="asis-table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Asistio</th>
                                        <th>Usuario</th>
                                        <th>Contacto</th>
                                        <th>Inversion</th>
                                        <th>Menores</th>
                                        <th>Registro</th>
                                        <th>Nota</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registros as $registro): ?>
                                        <?php $rid = (int) $registro['registro_id']; ?>
                                        <tr class="<?= (int) $registro['asistio'] === 1 ? 'is-present' : '' ?>">
                                            <td>
                                                <input type="hidden" name="registro_ids[]" value="<?= $rid ?>">
                                                <label class="asis-check">
                                                    <input type="checkbox" name="asistio[]" value="<?= $rid ?>" <?= (int) $registro['asistio'] === 1 ? 'checked' : '' ?>>
                                                    <span></span>
                                                </label>
                                            </td>
                                            <td>
                                                <strong><?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?></strong>
                                                <span>@<?= asis_h($registro['user']) ?> / ID <?= (int) $registro['usuario_id'] ?></span>
                                            </td>
                                            <td>
                                                <strong><?= asis_h($registro['telefono'] ?: 'Sin telefono') ?></strong>
                                                <span><?= asis_h($registro['email']) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= asis_h($registro['inversion_nombre'] ?: 'Sin inversion') ?></strong>
                                                <span><?= asis_h(asis_money($registro['inversion_monto'])) ?></span>
                                            </td>
                                            <td><strong><?= (int) $registro['total_menores'] ?></strong></td>
                                            <td>
                                                <strong><?= asis_h(asis_fecha($registro['fecha_registro'], true)) ?></strong>
                                                <span><?= (int) $registro['asistio'] === 1 ? 'Marcado: ' . asis_h(asis_fecha($registro['fecha_asistencia'], true)) : 'Sin asistencia marcada' ?></span>
                                            </td>
                                            <td>
                                                <input class="asis-note" type="text" name="notas[<?= $rid ?>]" maxlength="255" placeholder="Nota opcional" value="<?= asis_h($registro['asistencia_notas'] ?? '') ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="asis-mobile-list">
                            <?php foreach ($registros as $registro): ?>
                                <?php $rid = (int) $registro['registro_id']; ?>
                                <article class="asis-user-card <?= (int) $registro['asistio'] === 1 ? 'is-present' : '' ?>">
                                    <div class="asis-user-head">
                                        <div>
                                            <strong><?= asis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?></strong>
                                            <span>@<?= asis_h($registro['user']) ?></span>
                                        </div>
                                        <label class="asis-check mobile">
                                            <input type="checkbox" data-mobile-check="<?= $rid ?>" value="<?= $rid ?>" <?= (int) $registro['asistio'] === 1 ? 'checked' : '' ?>>
                                            <span></span>
                                        </label>
                                    </div>
                                    <p><?= asis_h($registro['telefono'] ?: 'Sin telefono') ?> / <?= asis_h($registro['email']) ?></p>
                                    <p><?= asis_h($registro['inversion_nombre'] ?: 'Sin inversion') ?> - <?= asis_h(asis_money($registro['inversion_monto'])) ?></p>
                                    <small>Menores: <?= (int) $registro['total_menores'] ?> | Registro: <?= asis_h(asis_fecha($registro['fecha_registro'], true)) ?></small>
                                    <input class="asis-note" type="text" data-mobile-note="<?= $rid ?>" maxlength="255" placeholder="Nota opcional" value="<?= asis_h($registro['asistencia_notas'] ?? '') ?>">
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="asis-sticky-save">
                            <button type="submit">Guardar asistencia</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <dialog class="asis-modal" data-participante-modal>
                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios_senderos.php" class="asis-modal-box">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="agregar_participante">
                    <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                    <input type="hidden" name="volver_a" value="asistencia">
                    <input type="hidden" name="marcar_asistio" value="1">

                    <div class="asis-modal-head">
                        <div>
                            <span>Asistencia manual</span>
                            <h2>Agregar asistente no inscrito</h2>
                        </div>
                        <button type="button" class="asis-modal-close" data-close-participante aria-label="Cerrar">
                            <i data-feather="x"></i>
                        </button>
                    </div>

                    <div class="asis-mode-switch">
                        <label>
                            <input type="radio" name="tipo_participante" value="existente" checked>
                            Usuario existente
                        </label>
                        <label>
                            <input type="radio" name="tipo_participante" value="nuevo">
                            Nuevo usuario
                        </label>
                    </div>

                    <div class="asis-form-grid">
                        <label class="asis-field asis-existing-field">
                            <span>Elegir usuario</span>
                            <select name="usuario_id">
                                <option value="">Selecciona un usuario</option>
                                <?php foreach ($usuariosDisponibles as $usuario): ?>
                                    <option value="<?= (int) $usuario['id'] ?>">
                                        <?= asis_h(trim($usuario['nombre'] . ' ' . $usuario['apellido'])) ?>
                                        - @<?= asis_h($usuario['user']) ?>
                                        <?= $usuario['telefono'] ? ' - ' . asis_h($usuario['telefono']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="asis-new-fields">
                            <label class="asis-field">
                                <span>Nombre</span>
                                <input type="text" name="nuevo_nombre" maxlength="100" placeholder="Nombre">
                            </label>
                            <label class="asis-field">
                                <span>Apellido</span>
                                <input type="text" name="nuevo_apellido" maxlength="100" placeholder="Apellido">
                            </label>
                            <label class="asis-field">
                                <span>Usuario</span>
                                <input type="text" name="nuevo_user" maxlength="50" placeholder="Opcional">
                            </label>
                            <label class="asis-field">
                                <span>Email</span>
                                <input type="email" name="nuevo_email" maxlength="100" placeholder="Opcional">
                            </label>
                            <label class="asis-field">
                                <span>Telefono</span>
                                <input type="text" name="nuevo_telefono" maxlength="20" placeholder="8090000000">
                            </label>
                        </div>

                        <label class="asis-field">
                            <span>Inversion</span>
                            <select name="inversion_id" required>
                                <option value="">Selecciona una inversion</option>
                                <?php foreach ($inversionesSendero as $inversion): ?>
                                    <option value="<?= (int) $inversion['id'] ?>">
                                        <?= asis_h($inversion['nombre']) ?> - RD$ <?= number_format((float) $inversion['monto'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <p class="asis-modal-note">Se registrara como inscrito activo y asistente de este sendero.</p>
                    </div>

                    <div class="asis-modal-actions">
                        <button type="button" class="secondary" data-close-participante>Cancelar</button>
                        <button type="submit">Guardar asistente</button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-participante-modal]');
    const openButton = document.querySelector('[data-open-participante]');
    const closeButtons = document.querySelectorAll('[data-close-participante]');
    const radios = document.querySelectorAll('input[name="tipo_participante"]');
    const existingField = document.querySelector('.asis-existing-field');
    const newFields = document.querySelector('.asis-new-fields');

    function syncMode() {
        const selected = document.querySelector('input[name="tipo_participante"]:checked');
        const isNew = selected && selected.value === 'nuevo';
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

    const root = document.querySelector('.asis-attendance-form');
    if (!root) {
        return;
    }
    const checks = () => root.querySelectorAll('input[type="checkbox"][name="asistio[]"]');
    const mobileChecks = () => root.querySelectorAll('input[type="checkbox"][data-mobile-check]');
    const syncRows = () => {
        checks().forEach(function (check) {
            const row = check.closest('tr, .asis-user-card');
            if (row) {
                row.classList.toggle('is-present', check.checked);
            }
            const mobileCheck = root.querySelector('input[data-mobile-check="' + check.value + '"]');
            if (mobileCheck && mobileCheck.checked !== check.checked) {
                mobileCheck.checked = check.checked;
            }
            const mobileCard = mobileCheck ? mobileCheck.closest('.asis-user-card') : null;
            if (mobileCard) {
                mobileCard.classList.toggle('is-present', check.checked);
            }
        });
    };
    root.querySelector('[data-check-all]')?.addEventListener('click', function () {
        checks().forEach(check => check.checked = true);
        syncRows();
    });
    root.querySelector('[data-uncheck-all]')?.addEventListener('click', function () {
        checks().forEach(check => check.checked = false);
        syncRows();
    });
    checks().forEach(check => check.addEventListener('change', syncRows));
    mobileChecks().forEach(function (mobileCheck) {
        mobileCheck.addEventListener('change', function () {
            const realCheck = root.querySelector('input[type="checkbox"][name="asistio[]"][value="' + mobileCheck.value + '"]');
            if (realCheck) {
                realCheck.checked = mobileCheck.checked;
            }
            syncRows();
        });
    });
    root.querySelectorAll('[data-mobile-note]').forEach(function (mobileNote) {
        mobileNote.addEventListener('input', function () {
            const realNote = root.querySelector('input[name="notas[' + mobileNote.dataset.mobileNote + ']"]');
            if (realNote) {
                realNote.value = mobileNote.value;
            }
        });
    });
    syncRows();
});
</script>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
