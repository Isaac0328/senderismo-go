<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'usuarios.cargos';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/cargos.php';
require_once __DIR__ . '/../componentes/csrf.php';

$pageTitle = "Cargos";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/roles.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/cargos.js"
];

require_once __DIR__ . '/../bd/conexion.php';

function cargos_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$cargosListo = sg_cargos_listo($conn);
$canEdit = sg_has_permission_action($conn, 'usuarios.cargos', 'editar');
$canAdd = sg_has_permission_action($conn, 'usuarios.cargos', 'agregar');
$canDelete = sg_has_permission_action($conn, 'usuarios.cargos', 'eliminar');
$canSeePermissions = sg_has_permission($conn, 'usuarios.permisos_roles');

$cargos = [];
if ($cargosListo) {
    $res = mysqli_query($conn, "
        SELECT r.id, r.nombre, r.descripcion,
               (SELECT COUNT(*) FROM usuario_cargos uc WHERE uc.cargo_id = r.id) AS usuarios,
               (SELECT COUNT(*) FROM rol_permiso rp WHERE rp.rol_id = r.id) AS permisos
        FROM roles r
        WHERE r.tipo = 'cargo'
        ORDER BY r.nombre
    ");
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $cargos[] = $row;
    }
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="roles-page">
    <div class="roles-container">
        <div class="roles-header">
            <div>
                <span class="roles-kicker">Seguridad</span>
                <h1 class="roles-title">Cargos</h1>
                <p class="roles-subtitle">Las funciones de las personas (Contable, Comunicaciones...). Una persona puede tener varios y sus permisos se suman a los de su rol de acceso.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="roles-panel-link">Volver al panel</a>
        </div>

        <?php if (!empty($_SESSION['cargos_success'])): ?>
            <div class="roles-alert success"><?= cargos_h($_SESSION['cargos_success']) ?></div>
            <?php unset($_SESSION['cargos_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['cargos_error'])): ?>
            <div class="roles-alert error"><?= cargos_h($_SESSION['cargos_error']) ?></div>
            <?php unset($_SESSION['cargos_error']); ?>
        <?php endif; ?>

        <?php if (!$cargosListo): ?>
            <div class="roles-alert error">Esta pantalla necesita la actualizaci&oacute;n pendiente del sistema. <a href="<?= cargos_h(sg_clean_url('mantenimientos/mantenimiento_actualizaciones.php')) ?>">Actualizaciones del sistema</a></div>
        <?php else: ?>
        <div class="roles-grid">
            <?php if ($canAdd || $canEdit): ?>
            <section class="roles-card">
                <div class="roles-card-head">
                    <h2 id="formTitle">Nuevo cargo</h2>
                    <p>Completa los datos principales del cargo.</p>
                </div>

                <form id="cargoForm" class="roles-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_cargos.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" id="action" value="save">
                    <input type="hidden" name="id" id="cargoId" value="0">

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" maxlength="50" required placeholder="Ej: Contable, Comunicaciones">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripcion</label>
                        <textarea name="descripcion" id="descripcion" maxlength="150" rows="4" placeholder="Que hace quien tiene este cargo (opcional)"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">Guardar</button>
                        <button type="button" class="btn-secondary" id="resetBtn">Limpiar</button>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <section class="roles-card">
                <div class="roles-card-head">
                    <h2>Cargos existentes</h2>
                    <p>Busca y selecciona para editar. Los permisos de cada cargo se asignan en &ldquo;Permisos&rdquo;.</p>
                </div>

                <div class="table-tools">
                    <input type="text" id="searchInput" placeholder="Buscar por nombre, descripcion o ID...">
                </div>

                <div class="roles-table-wrap">
                    <table class="roles-table" id="cargosTable">
                        <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th>Nombre</th>
                                <th>Descripcion</th>
                                <th style="width:90px;">Personas</th>
                                <th style="width:230px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($cargos) === 0): ?>
                                <tr>
                                    <td colspan="5" class="empty">No hay cargos para mostrar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cargos as $c): ?>
                                    <tr data-id="<?= (int) $c['id'] ?>" data-nombre="<?= cargos_h($c['nombre']) ?>" data-descripcion="<?= cargos_h($c['descripcion'] ?? '') ?>">
                                        <td><?= (int) $c['id'] ?></td>
                                        <td><?= cargos_h($c['nombre']) ?><small class="cargo-chip"><?= (int) $c['permisos'] ?> permisos</small></td>
                                        <td><?= cargos_h($c['descripcion'] ?? '') ?></td>
                                        <td><?= (int) $c['usuarios'] ?></td>
                                        <td>
                                            <?php if ($canEdit): ?><button type="button" class="btn-mini edit-btn">Editar</button><?php endif; ?>
                                            <?php if ($canSeePermissions): ?><a class="btn-mini" href="<?= BASE_URL ?>mantenimientos/mantenimiento_permisos_roles.php?id=<?= (int) $c['id'] ?>">Permisos</a><?php endif; ?>
                                            <?php if ($canDelete): ?>
                                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_cargos.php" class="inline-form" onsubmit="return confirm('Seguro que deseas eliminar este cargo?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                                <button type="submit" class="btn-mini danger">Eliminar</button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
