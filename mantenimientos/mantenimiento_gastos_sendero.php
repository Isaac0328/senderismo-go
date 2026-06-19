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
require_once __DIR__ . '/../componentes/filtro_senderos.php';

contabilidad_bootstrap($conn);

$pageTitle = "Gastos por Sendero | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function fgs_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fgs_fecha(?string $fecha): string
{
    $time = $fecha ? strtotime($fecha) : false;
    return $time ? date('d/m/Y', $time) : 'Sin fecha';
}

function fgs_money($value): string
{
    return 'RD$ ' . number_format((float) $value, 2);
}

$senderoId = (int) ($_GET['sendero_id'] ?? 0);
$senderoFiltros = sgf_params();
$nivelesDificultad = sgf_niveles_dificultad($conn);
[$senderoWhere, $senderoTypes, $senderoValues] = sgf_where($senderoFiltros, 's');

$senderos = [];
$resSenderos = sgf_execute_query($conn, "
    SELECT s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, nd.nombre AS dificultad_nombre, COALESCE(SUM(csg.total), 0) AS total_gastos
    FROM senderos s
    LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    LEFT JOIN contabilidad_sendero_gastos csg ON csg.sendero_id = s.id
    {$senderoWhere}
    GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, nd.nombre
    ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
", $senderoTypes, $senderoValues);
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

$gastosCatalogo = [];
$resGastos = mysqli_query($conn, "
    SELECT cg.*, ccg.nombre AS categoria_gasto_nombre
    FROM contabilidad_gastos_catalogo cg
    LEFT JOIN contabilidad_categoria_gasto ccg ON ccg.id = cg.categoria_gasto_id
    WHERE cg.activo = 1
    ORDER BY ccg.nombre ASC, cg.nombre ASC
");
while ($resGastos && $row = mysqli_fetch_assoc($resGastos)) {
    $gastosCatalogo[] = $row;
}

$gastosSendero = [];
if ($senderoSeleccionado) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM contabilidad_sendero_gastos WHERE sendero_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $gastosSendero[(int) $row['gasto_id']] = $row;
    }
    mysqli_stmt_close($stmt);
}

$totalActual = array_sum(array_map(static fn($row) => (float) $row['total'], $gastosSendero));

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Gastos por sendero</h1>
                <p>Selecciona los gastos usados en una ruta y registra la cantidad para conocer el costo total operativo de ese sendero.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['gastos_sendero_success'])): ?>
            <div class="fin-alert success"><?= fgs_h($_SESSION['gastos_sendero_success']) ?></div>
            <?php unset($_SESSION['gastos_sendero_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['gastos_sendero_error'])): ?>
            <div class="fin-alert error"><?= fgs_h($_SESSION['gastos_sendero_error']) ?></div>
            <?php unset($_SESSION['gastos_sendero_error']); ?>
        <?php endif; ?>

        <?php sgf_render([
            'params' => $senderoFiltros,
            'niveles' => $nivelesDificultad,
            'senderos' => $senderos,
            'selected_id' => $senderoId,
            'clear_url' => BASE_URL . 'mantenimientos/mantenimiento_gastos_sendero.php',
            'card_class' => 'fin-card fin-filter-card',
            'head_class' => 'fin-card-head',
            'form_class' => 'fin-filter-form',
            'icon' => 'map',
            'option_label' => static function (array $sendero): string {
                $km = $sendero['distancia_km'] !== null ? ' - ' . number_format((float) $sendero['distancia_km'], 1) . ' km' : '';
                $dificultad = !empty($sendero['dificultad_nombre']) ? ' - ' . $sendero['dificultad_nombre'] : '';
                return $sendero['nombre'] . ' - ' . fgs_fecha($sendero['fecha_sendero']) . $dificultad . $km . ' (' . fgs_money($sendero['total_gastos']) . ')';
            },
        ]); ?>

        <?php if (!$senderoSeleccionado): ?>
            <section class="fin-empty">
                <i data-feather="dollar-sign"></i>
                <h2>Selecciona un sendero</h2>
                <p>Luego podras marcar los gastos usados y ver el total operativo.</p>
            </section>
        <?php else: ?>
            <section class="fin-route-banner fin-route-compact">
                <div>
                    <span><?= fgs_h(ucfirst((string) $senderoSeleccionado['estado'])) ?></span>
                    <h2><?= fgs_h($senderoSeleccionado['nombre']) ?></h2>
                    <p>Fecha: <?= fgs_h(fgs_fecha($senderoSeleccionado['fecha_sendero'])) ?></p>
                </div>
                <article>
                    <span>Total gastos</span>
                    <strong><?= fgs_h(fgs_money($totalActual)) ?></strong>
                </article>
            </section>

            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span>Detalle</span>
                        <h2>Gastos usados</h2>
                    </div>
                    <a class="fin-head-link" href="<?= BASE_URL ?>mantenimientos/mantenimiento_gastos.php">Mantener catalogo</a>
                </div>

                <?php if (empty($gastosCatalogo)): ?>
                    <div class="fin-empty compact">
                        <i data-feather="tag"></i>
                        <h2>No hay gastos activos</h2>
                        <p>Primero crea gastos en el catalogo para asignarlos a un sendero.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_gastos_sendero.php" class="fin-expense-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
                        <div class="fin-table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Usar</th>
                                        <th>Gasto</th>
                                        <th>Unidad</th>
                                        <th>Cantidad</th>
                                        <th>Costo unitario</th>
                                        <th>Total</th>
                                        <th>Nota</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gastosCatalogo as $gasto): ?>
                                        <?php
                                        $gid = (int) $gasto['id'];
                                        $guardado = $gastosSendero[$gid] ?? null;
                                        $cantidad = $guardado ? (float) $guardado['cantidad'] : 0;
                                        $costo = $guardado ? (float) $guardado['costo_unitario'] : (float) $gasto['costo_unitario'];
                                        $disabled = $guardado ? '' : 'disabled';
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="gastos[<?= $gid ?>][usar]" value="1" <?= $guardado ? 'checked' : '' ?>>
                                            </td>
                                            <td>
                                                <strong><?= fgs_h($gasto['nombre']) ?></strong>
                                                <span><?= fgs_h($gasto['categoria_gasto_nombre'] ?: 'General') ?></span>
                                            </td>
                                            <td><?= fgs_h($gasto['unidad']) ?></td>
                                            <td><input class="fin-number" type="number" name="gastos[<?= $gid ?>][cantidad]" min="0" step="0.01" value="<?= fgs_h($cantidad) ?>" data-cantidad <?= $disabled ?>></td>
                                            <td><input class="fin-number" type="number" name="gastos[<?= $gid ?>][costo_unitario]" min="0" step="0.01" value="<?= fgs_h($costo) ?>" data-costo <?= $disabled ?>></td>
                                            <td><strong data-line-total><?= fgs_h(fgs_money($cantidad * $costo)) ?></strong></td>
                                            <td><input type="text" name="gastos[<?= $gid ?>][nota]" maxlength="255" value="<?= fgs_h($guardado['nota'] ?? '') ?>" placeholder="Opcional" data-nota <?= $disabled ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="fin-sticky-save">
                            <strong>Total: <span data-grand-total><?= fgs_h(fgs_money($totalActual)) ?></span></strong>
                            <button type="submit">Guardar gastos</button>
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
    const form = document.querySelector('.fin-expense-form');
    if (!form) return;
    const recalc = () => {
        let total = 0;
        form.querySelectorAll('tbody tr').forEach((row) => {
            const check = row.querySelector('input[type="checkbox"]');
            const cantidadInput = row.querySelector('[data-cantidad]');
            const costoInput = row.querySelector('[data-costo]');
            const notaInput = row.querySelector('[data-nota]');
            const checked = check?.checked;
            [cantidadInput, costoInput, notaInput].forEach((input) => {
                if (input) {
                    input.disabled = !checked;
                }
            });
            const cantidad = parseFloat(cantidadInput?.value || '0');
            const costo = parseFloat(costoInput?.value || '0');
            const line = checked ? Math.max(0, cantidad) * Math.max(0, costo) : 0;
            total += line;
            const target = row.querySelector('[data-line-total]');
            if (target) target.textContent = money.format(line);
        });
        const grand = form.querySelector('[data-grand-total]');
        if (grand) grand.textContent = money.format(total);
    };
    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);
    recalc();
});
</script>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
