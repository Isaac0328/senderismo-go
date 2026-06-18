<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';

contabilidad_bootstrap($conn);

$pageTitle = "Ingresos por Sendero | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function fis_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fis_fecha(?string $fecha, bool $conHora = false): string
{
    $time = $fecha ? strtotime($fecha) : false;
    return $time ? date($conHora ? 'd/m/Y h:i A' : 'd/m/Y', $time) : 'Sin fecha';
}

function fis_money($value): string
{
    return 'RD$ ' . number_format((float) $value, 2);
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);

$metodosPago = [];
$resMetodos = mysqli_query($conn, "SELECT id, nombre FROM contabilidad_metodo_pago WHERE activo = 1 ORDER BY nombre ASC");
while ($resMetodos && $row = mysqli_fetch_assoc($resMetodos)) {
    $metodosPago[] = $row;
}

$senderos = [];
$resSenderos = mysqli_query($conn, "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.estado,
        COUNT(rs.id) AS inscritos,
        SUM(CASE WHEN crp.pagado = 1 THEN 1 ELSE 0 END) AS pagados,
        COALESCE(SUM(CASE WHEN crp.pagado = 1 THEN crp.monto_pagado ELSE 0 END), 0) AS ingresos
    FROM senderos s
    LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id AND rs.estado = 'registrado'
    LEFT JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
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
$totales = [
    'inscritos' => 0,
    'pagados' => 0,
    'asistieron' => 0,
    'esperado' => 0.0,
    'cobrado' => 0.0,
];

if ($senderoSeleccionado) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            rs.id AS registro_id,
            rs.fecha_registro,
            rs.asistio,
            si.nombre AS inversion_nombre,
            si.monto AS inversion_monto,
            u.id AS usuario_id,
            u.nombre,
            u.apellido,
            u.user,
            u.email,
            du.telefono,
            COALESCE(m.total_menores, 0) AS total_menores,
            COALESCE(m.total_menores_monto, 0) AS total_menores_monto,
            crp.pagado,
            crp.monto_pagado,
            crp.fecha_pago,
            crp.metodo_pago,
            crp.metodo_pago_id,
            crp.nota AS pago_nota
        FROM registros_senderos rs
        INNER JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        LEFT JOIN (
            SELECT rsm.registro_id, COUNT(*) AS total_menores, COALESCE(SUM(si2.monto), 0) AS total_menores_monto
            FROM registro_sendero_menores rsm
            LEFT JOIN sendero_inversiones si2 ON si2.id = rsm.inversion_id
            GROUP BY rsm.registro_id
        ) m ON m.registro_id = rs.id
        LEFT JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
        WHERE rs.sendero_id = ? AND rs.estado = 'registrado'
        ORDER BY crp.pagado DESC, rs.asistio DESC, u.nombre ASC, u.apellido ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $esperado = (float) ($row['inversion_monto'] ?? 0) + (float) ($row['total_menores_monto'] ?? 0);
        $row['monto_esperado'] = $esperado;
        $row['monto_pagado'] = $row['monto_pagado'] !== null ? (float) $row['monto_pagado'] : $esperado;
        $totales['inscritos']++;
        $totales['pagados'] += (int) ($row['pagado'] ?? 0) === 1 ? 1 : 0;
        $totales['asistieron'] += (int) ($row['asistio'] ?? 0) === 1 ? 1 : 0;
        $totales['esperado'] += $esperado;
        $totales['cobrado'] += (int) ($row['pagado'] ?? 0) === 1 ? (float) $row['monto_pagado'] : 0;
        $registros[] = $row;
    }
    mysqli_stmt_close($stmt);
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Ingresos por sendero</h1>
                <p>Marca cuales inscritos pagaron, registra el monto cobrado y confirma la asistencia desde la misma vista financiera.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['ingresos_sendero_success'])): ?>
            <div class="fin-alert success"><?= fis_h($_SESSION['ingresos_sendero_success']) ?></div>
            <?php unset($_SESSION['ingresos_sendero_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['ingresos_sendero_error'])): ?>
            <div class="fin-alert error"><?= fis_h($_SESSION['ingresos_sendero_error']) ?></div>
            <?php unset($_SESSION['ingresos_sendero_error']); ?>
        <?php endif; ?>

        <section class="fin-card">
            <div class="fin-card-head">
                <div>
                    <span>Filtro</span>
                    <h2>Seleccionar sendero</h2>
                </div>
                <i data-feather="users"></i>
            </div>
            <form method="GET" class="fin-filter-form">
                <select name="sendero_id" required>
                    <option value="">Elige un sendero</option>
                    <?php foreach ($senderos as $sendero): ?>
                        <option value="<?= (int) $sendero['id'] ?>" <?= (int) $sendero['id'] === $senderoId ? 'selected' : '' ?>>
                            <?= fis_h($sendero['nombre']) ?> - <?= fis_h(fis_fecha($sendero['fecha_sendero'])) ?> (<?= (int) $sendero['pagados'] ?>/<?= (int) $sendero['inscritos'] ?> pagados)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">
                    <i data-feather="search"></i>
                    Consultar
                </button>
                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_ingresos_sendero.php">Limpiar</a>
            </form>
        </section>

        <?php if (!$senderoSeleccionado): ?>
            <section class="fin-empty">
                <i data-feather="credit-card"></i>
                <h2>Selecciona un sendero</h2>
                <p>Veras las personas inscritas para marcar pagos y asistencia.</p>
            </section>
        <?php else: ?>
            <section class="fin-route-banner fin-income-banner">
                <div>
                    <span><?= fis_h(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                    <h2><?= fis_h($senderoSeleccionado['nombre']) ?></h2>
                    <p>Fecha: <?= fis_h(fis_fecha($senderoSeleccionado['fecha_sendero'])) ?></p>
                </div>
                <div class="fin-stat-grid">
                    <article><span>Inscritos</span><strong><?= (int) $totales['inscritos'] ?></strong></article>
                    <article class="ok"><span>Pagados</span><strong><?= (int) $totales['pagados'] ?></strong></article>
                    <article><span>Asistieron</span><strong><?= (int) $totales['asistieron'] ?></strong></article>
                    <article class="money"><span>Cobrado</span><strong><?= fis_h(fis_money($totales['cobrado'])) ?></strong></article>
                    <article class="warn"><span>Esperado</span><strong><?= fis_h(fis_money($totales['esperado'])) ?></strong></article>
                </div>
            </section>

            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span>Listado</span>
                        <h2>Inscritos y pagos</h2>
                    </div>
                    <i data-feather="clipboard"></i>
                </div>

                <?php if (empty($registros)): ?>
                    <div class="fin-empty compact">
                        <i data-feather="user-x"></i>
                        <h2>Sin inscritos activos</h2>
                        <p>Este sendero no tiene reservas activas para registrar ingresos.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_ingresos_sendero.php" class="fin-income-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                        <div class="fin-tools">
                            <button type="button" data-paid-all>Marcar todos pagados</button>
                            <button type="button" data-paid-none>Limpiar pagos</button>
                            <button type="button" data-attended-all>Marcar todos asistieron</button>
                            <button type="submit" class="primary">Guardar ingresos</button>
                        </div>

                        <div class="fin-table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Pago</th>
                                        <th>Asistio</th>
                                        <th>Participante</th>
                                        <th>Inversion</th>
                                        <th>Esperado</th>
                                        <th>Monto pagado</th>
                                        <th>Fecha pago</th>
                                        <th>Metodo</th>
                                        <th>Nota</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registros as $registro): ?>
                                        <?php
                                        $rid = (int) $registro['registro_id'];
                                        $pagado = (int) ($registro['pagado'] ?? 0) === 1;
                                        $asistio = (int) ($registro['asistio'] ?? 0) === 1;
                                        ?>
                                        <tr class="<?= $pagado ? 'is-paid' : '' ?>">
                                            <td>
                                                <input type="hidden" name="registro_ids[]" value="<?= $rid ?>">
                                                <label class="fin-mini-check">
                                                    <input type="checkbox" name="pagado[]" value="<?= $rid ?>" <?= $pagado ? 'checked' : '' ?>>
                                                    <span></span>
                                                </label>
                                            </td>
                                            <td>
                                                <label class="fin-mini-check">
                                                    <input type="checkbox" name="asistio[]" value="<?= $rid ?>" <?= $asistio ? 'checked' : '' ?>>
                                                    <span></span>
                                                </label>
                                            </td>
                                            <td>
                                                <strong><?= fis_h(trim($registro['nombre'] . ' ' . $registro['apellido'])) ?></strong>
                                                <span>@<?= fis_h($registro['user']) ?> / <?= fis_h($registro['telefono'] ?: 'Sin telefono') ?></span>
                                            </td>
                                            <td>
                                                <strong><?= fis_h($registro['inversion_nombre'] ?: 'Sin inversion') ?></strong>
                                                <span>Menores: <?= (int) $registro['total_menores'] ?></span>
                                            </td>
                                            <td><strong><?= fis_h(fis_money($registro['monto_esperado'])) ?></strong></td>
                                            <td><input class="fin-number" type="number" name="monto_pagado[<?= $rid ?>]" min="0" step="0.01" value="<?= fis_h($registro['monto_pagado']) ?>" data-paid-amount></td>
                                            <td><input type="date" name="fecha_pago[<?= $rid ?>]" value="<?= fis_h($registro['fecha_pago'] ?? '') ?>"></td>
                                            <td>
                                                <select name="metodo_pago_id[<?= $rid ?>]">
                                                    <option value="">Seleccione...</option>
                                                    <?php foreach ($metodosPago as $metodo): ?>
                                                        <option value="<?= (int) $metodo['id'] ?>" <?= (int) ($registro['metodo_pago_id'] ?? 0) === (int) $metodo['id'] ? 'selected' : '' ?>>
                                                            <?= fis_h($metodo['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="text" name="nota[<?= $rid ?>]" maxlength="255" value="<?= fis_h($registro['pago_nota'] ?? '') ?>" placeholder="Opcional"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="fin-sticky-save">
                            <strong>Cobrado: <span data-income-total><?= fis_h(fis_money($totales['cobrado'])) ?></span></strong>
                            <button type="submit">Guardar ingresos</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const money = new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' });
    const form = document.querySelector('.fin-income-form');
    if (!form) return;
    const paidChecks = () => form.querySelectorAll('input[type="checkbox"][name="pagado[]"]');
    const attendedChecks = () => form.querySelectorAll('input[type="checkbox"][name="asistio[]"]');
    const recalc = () => {
        let total = 0;
        paidChecks().forEach((check) => {
            const row = check.closest('tr');
            row?.classList.toggle('is-paid', check.checked);
            const amount = parseFloat(row?.querySelector('[data-paid-amount]')?.value || '0');
            if (check.checked) total += Math.max(0, amount);
        });
        const target = form.querySelector('[data-income-total]');
        if (target) target.textContent = money.format(total);
    };
    form.querySelector('[data-paid-all]')?.addEventListener('click', () => {
        paidChecks().forEach(check => check.checked = true);
        recalc();
    });
    form.querySelector('[data-paid-none]')?.addEventListener('click', () => {
        paidChecks().forEach(check => check.checked = false);
        recalc();
    });
    form.querySelector('[data-attended-all]')?.addEventListener('click', () => {
        attendedChecks().forEach(check => check.checked = true);
    });
    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);
    recalc();
});
</script>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
