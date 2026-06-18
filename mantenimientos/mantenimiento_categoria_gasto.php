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

$pageTitle = "Categorias de Gasto | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contabilidad.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function cg_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM contabilidad_categoria_gasto WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
}

$categorias = [];
$res = mysqli_query($conn, "
    SELECT ccg.*, COUNT(cg.id) AS gastos_usando
    FROM contabilidad_categoria_gasto ccg
    LEFT JOIN contabilidad_gastos_catalogo cg ON cg.categoria_gasto_id = ccg.id
    GROUP BY ccg.id
    ORDER BY ccg.activo DESC, ccg.nombre ASC
");
while ($res && $row = mysqli_fetch_assoc($res)) {
    $categorias[] = $row;
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Categorias de gasto</h1>
                <p>Administra las categorias usadas para clasificar gastos operativos. Este catalogo es independiente de futuras categorias de senderos.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </header>

        <?php if (!empty($_SESSION['categoria_gasto_success'])): ?>
            <div class="fin-alert success"><?= cg_h($_SESSION['categoria_gasto_success']) ?></div>
            <?php unset($_SESSION['categoria_gasto_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['categoria_gasto_error'])): ?>
            <div class="fin-alert error"><?= cg_h($_SESSION['categoria_gasto_error']) ?></div>
            <?php unset($_SESSION['categoria_gasto_error']); ?>
        <?php endif; ?>

        <div class="fin-layout">
            <section class="fin-card">
                <div class="fin-card-head">
                    <div>
                        <span><?= $edit ? 'Editar' : 'Nueva' ?></span>
                        <h2><?= $edit ? 'Actualizar categoria' : 'Crear categoria' ?></h2>
                    </div>
                    <i data-feather="folder"></i>
                </div>

                <form class="fin-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_categoria_gasto.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                    <label>
                        <span>Nombre *</span>
                        <input type="text" name="nombre" maxlength="120" required placeholder="Ej: Alimentacion, equipos, transporte" value="<?= cg_h($edit['nombre'] ?? '') ?>">
                    </label>

                    <label>
                        <span>Descripcion</span>
                        <textarea name="descripcion" maxlength="255" rows="3" placeholder="Detalle opcional."><?= cg_h($edit['descripcion'] ?? '') ?></textarea>
                    </label>

                    <label class="fin-check-row">
                        <input type="checkbox" name="activo" value="1" <?= (int) ($edit['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <span>Activa</span>
                    </label>

                    <div class="fin-actions">
                        <button type="submit"><?= $edit ? 'Actualizar categoria' : 'Guardar categoria' ?></button>
                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_categoria_gasto.php">Limpiar</a>
                    </div>
                </form>
            </section>

            <section class="fin-card fin-card-wide">
                <div class="fin-card-head">
                    <div>
                        <span>Catalogo</span>
                        <h2>Categorias registradas</h2>
                    </div>
                    <strong class="fin-count"><?= count($categorias) ?></strong>
                </div>

                <?php if (empty($categorias)): ?>
                    <div class="fin-empty">
                        <i data-feather="folder"></i>
                        <h2>Sin categorias</h2>
                        <p>Crea la primera categoria para clasificar tus gastos.</p>
                    </div>
                <?php else: ?>
                    <div class="fin-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Descripcion</th>
                                    <th>Gastos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $categoria): ?>
                                    <tr>
                                        <td><strong><?= cg_h($categoria['nombre']) ?></strong></td>
                                        <td><?= cg_h($categoria['descripcion'] ?: 'Sin descripcion') ?></td>
                                        <td><strong><?= (int) $categoria['gastos_usando'] ?></strong></td>
                                        <td>
                                            <span class="fin-pill <?= (int) $categoria['activo'] === 1 ? 'ok' : 'off' ?>">
                                                <?= (int) $categoria['activo'] === 1 ? 'Activa' : 'Inactiva' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fin-row-actions">
                                                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_categoria_gasto.php?edit=<?= (int) $categoria['id'] ?>">Editar</a>
                                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_categoria_gasto.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int) $categoria['id'] ?>">
                                                    <input type="hidden" name="activo" value="<?= (int) $categoria['activo'] === 1 ? 0 : 1 ?>">
                                                    <button type="submit"><?= (int) $categoria['activo'] === 1 ? 'Inactivar' : 'Activar' ?></button>
                                                </form>
                                                <?php if ((int) $categoria['activo'] === 0): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_categoria_gasto.php" onsubmit="return confirm('Eliminar esta categoria inactiva? Esta accion no se puede deshacer.');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int) $categoria['id'] ?>">
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
