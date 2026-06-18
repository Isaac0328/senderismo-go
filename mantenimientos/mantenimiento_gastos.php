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

$pageTitle = "Mantenimiento Gastos | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function fin_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fin_money($value): string
{
    return 'RD$ ' . number_format((float) $value, 2);
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM contabilidad_gastos_catalogo WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
}

$categoriasGasto = [];
$resCategorias = mysqli_query($conn, "SELECT id, nombre FROM contabilidad_categoria_gasto WHERE activo = 1 ORDER BY nombre ASC");
while ($resCategorias && $row = mysqli_fetch_assoc($resCategorias)) {
    $categoriasGasto[] = $row;
}

$gastos = [];
$res = mysqli_query($conn, "
    SELECT cg.*, ccg.nombre AS categoria_gasto_nombre
    FROM contabilidad_gastos_catalogo cg
    LEFT JOIN contabilidad_categoria_gasto ccg ON ccg.id = cg.categoria_gasto_id
    ORDER BY cg.activo DESC, ccg.nombre ASC, cg.nombre ASC
");
while ($res && $row = mysqli_fetch_assoc($res)) {
    $gastos[] = $row;
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Mantenimiento gastos</h1>
                <p>Registra los costos base que luego podras usar en cada sendero: chalecos, frutas, bebidas, botiquin, transporte, staff y otros gastos operativos.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['gastos_success'])): ?>
            <div class="fin-alert success"><?= fin_h($_SESSION['gastos_success']) ?></div>
            <?php unset($_SESSION['gastos_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['gastos_error'])): ?>
            <div class="fin-alert error"><?= fin_h($_SESSION['gastos_error']) ?></div>
            <?php unset($_SESSION['gastos_error']); ?>
        <?php endif; ?>

        <div class="fin-layout">
            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span><?= $edit ? 'Editar' : 'Nuevo' ?></span>
                        <h2><?= $edit ? 'Actualizar gasto' : 'Crear gasto' ?></h2>
                    </div>
                    <i data-feather="tag"></i>
                </div>

                <form class="fin-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_gastos.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                    <label>
                        <span>Nombre *</span>
                        <input type="text" name="nombre" maxlength="160" required placeholder="Ej: Banana, jugo, chaleco" value="<?= fin_h($edit['nombre'] ?? '') ?>">
                    </label>

                    <label>
                        <span>Categoria de gasto</span>
                        <select name="categoria_gasto_id">
                            <option value="">Sin categoria</option>
                            <?php foreach ($categoriasGasto as $categoria): ?>
                                <option value="<?= (int) $categoria['id'] ?>" <?= (int) ($edit['categoria_gasto_id'] ?? 0) === (int) $categoria['id'] ? 'selected' : '' ?>>
                                    <?= fin_h($categoria['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="fin-field-note">
                            <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_categoria_gasto.php">Mantener categorias de gasto</a>
                        </small>
                    </label>

                    <div class="fin-two">
                        <label>
                            <span>Unidad *</span>
                            <input type="text" name="unidad" maxlength="40" required placeholder="unidad, paquete, caja" value="<?= fin_h($edit['unidad'] ?? 'unidad') ?>">
                        </label>
                        <label>
                            <span>Costo unitario *</span>
                            <input type="number" name="costo_unitario" min="0" step="0.01" required value="<?= fin_h($edit['costo_unitario'] ?? '0.00') ?>">
                        </label>
                    </div>

                    <label>
                        <span>Descripcion</span>
                        <textarea name="descripcion" maxlength="255" rows="3" placeholder="Detalle opcional del gasto."><?= fin_h($edit['descripcion'] ?? '') ?></textarea>
                    </label>

                    <label class="fin-check-row">
                        <input type="checkbox" name="activo" value="1" <?= (int) ($edit['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <span>Activo</span>
                    </label>

                    <div class="fin-actions">
                        <button type="submit"><?= $edit ? 'Actualizar gasto' : 'Guardar gasto' ?></button>
                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_gastos.php">Limpiar</a>
                    </div>
                </form>
            </section>

            <section class="fin-card fin-card-wide">
                <div class="fin-card-head">
                    <div>
                        <span>Catalogo</span>
                        <h2>Gastos registrados</h2>
                    </div>
                    <strong class="fin-count"><?= count($gastos) ?></strong>
                </div>

                <?php if (empty($gastos)): ?>
                    <div class="fin-empty">
                        <i data-feather="inbox"></i>
                        <h2>Sin gastos registrados</h2>
                        <p>Crea tu primer gasto para poder usarlo en los senderos.</p>
                    </div>
                <?php else: ?>
                    <div class="fin-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Gasto</th>
                                    <th>Categoria</th>
                                    <th>Unidad</th>
                                    <th>Costo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gastos as $gasto): ?>
                                    <tr>
                                        <td>
                                            <strong><?= fin_h($gasto['nombre']) ?></strong>
                                            <?php if (!empty($gasto['descripcion'])): ?>
                                                <span><?= fin_h($gasto['descripcion']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= fin_h($gasto['categoria_gasto_nombre'] ?: 'General') ?></td>
                                        <td><?= fin_h($gasto['unidad']) ?></td>
                                        <td><strong><?= fin_h(fin_money($gasto['costo_unitario'])) ?></strong></td>
                                        <td>
                                            <span class="fin-pill <?= (int) $gasto['activo'] === 1 ? 'ok' : 'off' ?>">
                                                <?= (int) $gasto['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fin-row-actions">
                                                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_gastos.php?edit=<?= (int) $gasto['id'] ?>">Editar</a>
                                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_gastos.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int) $gasto['id'] ?>">
                                                    <input type="hidden" name="activo" value="<?= (int) $gasto['activo'] === 1 ? 0 : 1 ?>">
                                                    <button type="submit"><?= (int) $gasto['activo'] === 1 ? 'Inactivar' : 'Activar' ?></button>
                                                </form>
                                                <?php if ((int) $gasto['activo'] === 0): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_gastos.php" onsubmit="return confirm('Eliminar este gasto inactivo? Esta accion no se puede deshacer.');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int) $gasto['id'] ?>">
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
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
