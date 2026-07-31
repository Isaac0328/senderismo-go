<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'usuarios.permisos_roles';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/permisos.php';

$pageTitle = "Permisos por Rol | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/roles.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/roles.js"
];

require_once __DIR__ . '/../bd/conexion.php';
sg_seed_permission_catalog($conn);

function permisos_roles_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$roles = [];
$res = mysqli_query($conn, "CALL sp_roles_listar()");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $roles[] = $row;
    }
    mysqli_free_result($res);
}
while (mysqli_more_results($conn)) {
    mysqli_next_result($conn);
}

$permissionGroups = sg_permission_catalog();
$rolePermissions = [];
$resPermisosRol = mysqli_query($conn, "
    SELECT rp.rol_id, p.nombre
    FROM rol_permiso rp
    INNER JOIN permisos p ON p.id = rp.permiso_id
    ORDER BY rp.rol_id, p.nombre
");
while ($resPermisosRol && $row = mysqli_fetch_assoc($resPermisosRol)) {
    $roleId = (int) $row['rol_id'];
    $rolePermissions[$roleId][] = (string) $row['nombre'];
}

foreach ($roles as $role) {
    $roleId = (int) $role['id'];
    if ($roleId === 1) {
        $rolePermissions[$roleId] = array_keys(sg_permission_flat_catalog());
    }
    $rolePermissions[$roleId] = array_values(array_unique($rolePermissions[$roleId] ?? []));
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="roles-page">
    <div class="roles-container">
        <div class="roles-header">
            <div>
                <span class="roles-kicker">Accesos</span>
                <h1 class="roles-title">Permisos por Rol</h1>
                <p class="roles-subtitle">Distribuye por rol que mantenimientos, reportes y modulos puede ver cada perfil.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="roles-panel-link">Volver al panel</a>
        </div>

        <?php if (!empty($_SESSION['roles_success'])): ?>
            <div class="roles-alert success">
                <?= permisos_roles_h($_SESSION['roles_success']) ?>
            </div>
            <?php unset($_SESSION['roles_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['roles_error'])): ?>
            <div class="roles-alert error">
                <?= permisos_roles_h($_SESSION['roles_error']) ?>
            </div>
            <?php unset($_SESSION['roles_error']); ?>
        <?php endif; ?>

        <section class="roles-card permissions-card is-standalone">
            <div class="roles-card-head permissions-head">
                <div>
                    <h2>Ventanas permitidas</h2>
                    <p>Selecciona un rol y marca las opciones que se mostraran en su panel administrativo.</p>
                </div>
                <span class="permissions-badge">Control por modulo</span>
            </div>

            <form class="roles-permissions-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_roles.php">
                <input type="hidden" name="action" value="save_permissions">

                <div class="permission-toolbar">
                    <label>
                        <span>Rol a configurar</span>
                        <select name="id" id="permissionRoleSelect" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int) $role['id'] ?>">
                                    <?= permisos_roles_h($role['nombre']) ?><?= (int) $role['id'] === 1 ? ' (acceso total)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="permission-actions">
                        <button type="button" class="btn-secondary" data-permission-check-all>Marcar visibles</button>
                        <button type="button" class="btn-secondary" data-permission-clear>Limpiar</button>
                        <button type="submit" class="btn-primary">Guardar permisos</button>
                    </div>
                </div>

                <div class="permissions-grid" data-role-permissions='<?= permisos_roles_h(json_encode($rolePermissions, JSON_UNESCAPED_SLASHES)) ?>'>
                    <?php foreach ($permissionGroups as $groupIndex => $group): ?>
                        <article class="permission-group is-collapsed" data-permission-group>
                            <button class="permission-group-head" type="button" data-permission-group-toggle aria-expanded="false">
                                <span class="permission-group-title">
                                    <i data-feather="<?= permisos_roles_h($group['icon']) ?>"></i>
                                    <span>
                                        <strong><?= permisos_roles_h($group['title']) ?></strong>
                                        <small><?= permisos_roles_h($group['label']) ?></small>
                                    </span>
                                </span>
                                <i data-feather="chevron-down" class="permission-group-chevron"></i>
                            </button>

                            <div class="permission-list" hidden>
                                <?php foreach ($group['items'] as $item): ?>
                                    <label class="permission-item">
                                        <input type="checkbox" name="permisos[]" value="<?= permisos_roles_h($item[0]) ?>" data-permission-box>
                                        <span>
                                            <strong><?= permisos_roles_h($item[1]) ?></strong>
                                            <small><?= permisos_roles_h($item[2]) ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </form>
        </section>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
