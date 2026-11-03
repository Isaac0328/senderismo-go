<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$PERMISO_REQUERIDO = 'operaciones.provincias';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';

function provincias_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$editId = max(0, (int) ($_GET['edit'] ?? 0));
$edit = null;
$provincias = [];
$error = '';
$canAdd = sg_has_permission_action($conn, $PERMISO_REQUERIDO, 'agregar');
$canEdit = sg_has_permission_action($conn, $PERMISO_REQUERIDO, 'editar');
$result = mysqli_query($conn, 'SELECT id, nombre, tipo, activo FROM provincias ORDER BY nombre, id');
if (!$result) {
    $error = 'El catalogo de provincias necesita la actualizacion pendiente del sistema.';
} else {
    while ($row = mysqli_fetch_assoc($result)) {
        $provincias[] = $row;
        if ((int) $row['id'] === $editId && $canEdit) {
            $edit = $row;
        }
    }
    mysqli_free_result($result);
}
$pageTitle = 'Provincias';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/contabilidad.css', 'css/provincias.css'];
$jsFiles = ['js/barra_navegacion.js', 'js/catalogos_tablas.js'];
include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>
<main class="fin-page provincias-page">
    <section class="fin-container">
        <header class="fin-header">
            <div><span class="fin-kicker">Operaciones</span><h1>Provincias</h1><p>Rep&uacute;blica Dominicana</p></div>
            <a class="fin-back" href="<?= provincias_h(sg_clean_url('pantallas/panel_administrativo.php')) ?>"><i data-feather="arrow-left"></i>Volver al panel</a>
        </header>
        <?php foreach (['success', 'error'] as $type): $key = 'provincias_' . $type; ?>
            <?php if (!empty($_SESSION[$key])): ?>
                <div class="fin-alert <?= $type ?>"><?= provincias_h($_SESSION[$key]); unset($_SESSION[$key]); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($error): ?>
            <div class="fin-alert error"><?= provincias_h($error) ?> <a href="<?= provincias_h(sg_clean_url('mantenimientos/mantenimiento_actualizaciones.php')) ?>">Actualizaciones del sistema</a></div>
        <?php else: ?>
        <div class="fin-layout">
            <?php if ($edit || $canAdd): ?>
            <section class="fin-card">
                <div class="fin-card-head"><div><span><?= $edit ? 'Editar' : 'Nuevo' ?></span><h2><?= $edit ? 'Editar provincia' : 'Nueva provincia' ?></h2></div><i data-feather="map-pin"></i></div>
                <form class="fin-form" method="post" action="<?= BASE_URL ?>procesos/proceso_provincias.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                    <label><span>Nombre *</span><input type="text" name="nombre" required maxlength="120" value="<?= provincias_h($edit['nombre'] ?? '') ?>"></label>
                    <label><span>Tipo</span><select name="tipo"><option value="provincia">Provincia</option><option value="distrito_nacional" <?= ($edit['tipo'] ?? '') === 'distrito_nacional' ? 'selected' : '' ?>>Distrito Nacional</option></select></label>
                    <label class="fin-check-row"><input type="checkbox" name="activo" value="1" <?= (int) ($edit['activo'] ?? 1) === 1 ? 'checked' : '' ?>><span>Activo</span></label>
                    <div class="fin-actions"><button type="submit"><i data-feather="save"></i><?= $edit ? 'Guardar cambios' : 'Guardar provincia' ?></button><a href="<?= provincias_h(sg_clean_url('mantenimientos/mantenimiento_provincias.php')) ?>">Cancelar</a></div>
                </form>
            </section>
            <?php endif; ?>
            <section class="fin-card provincias-list <?= !$canAdd && !$edit ? 'provincias-list-full' : '' ?>">
                <div class="fin-card-head"><div><span>Cat&aacute;logo</span><h2>Provincias registradas</h2></div><strong class="fin-count"><?= count($provincias) ?></strong></div>
                <div class="fin-table-search"><i data-feather="search"></i><input type="search" aria-label="Buscar provincia" data-catalog-table-search placeholder="Buscar provincia, tipo o estado..."></div>
                <div class="fin-table-wrap fin-catalog-table-wrap">
                    <table><thead><tr><th>Provincia</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                        <?php foreach ($provincias as $provincia): ?>
                        <tr>
                            <td><strong><?= provincias_h($provincia['nombre']) ?></strong></td>
                            <td><?= $provincia['tipo'] === 'distrito_nacional' ? 'Distrito Nacional' : 'Provincia' ?></td>
                            <td><span class="fin-pill <?= (int) $provincia['activo'] === 1 ? 'ok' : 'off' ?>"><?= (int) $provincia['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td><?php if ($canEdit): ?><div class="fin-row-actions">
                                <a href="<?= provincias_h(sg_clean_url('mantenimientos/mantenimiento_provincias.php?edit=' . (int) $provincia['id'])) ?>"><i data-feather="edit-2"></i>Editar</a>
                                <form method="post" action="<?= BASE_URL ?>procesos/proceso_provincias.php">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $provincia['id'] ?>"><input type="hidden" name="activo" value="<?= (int) $provincia['activo'] === 1 ? 0 : 1 ?>">
                                    <button type="submit"><i data-feather="power"></i><?= (int) $provincia['activo'] === 1 ? 'Inactivar' : 'Activar' ?></button>
                                </form>
                            </div><?php else: ?>&mdash;<?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$provincias): ?><tr><td colspan="4">Sin provincias registradas.</td></tr><?php endif; ?>
                    </tbody></table>
                </div>
            </section>
        </div>
        <?php endif; ?>
    </section>
</main>
<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
