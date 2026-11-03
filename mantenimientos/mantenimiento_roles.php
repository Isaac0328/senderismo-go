<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'usuarios.roles';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/cargos.php';

$pageTitle = "Roles";

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

function roles_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Tipo de cada rol: acceso (Administrador, Invitado, Normal) o cargo (Contable, Directiva...).
$cargosListo = sg_cargos_listo($conn);
$tipos = [];
if ($cargosListo) {
    $rTipos = mysqli_query($conn, 'SELECT id, tipo FROM roles');
    while ($rTipos && $fila = mysqli_fetch_assoc($rTipos)) {
        $tipos[(int) $fila['id']] = $fila['tipo'];
    }
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

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="roles-page">
    <div class="roles-container">
        <div class="roles-header">
            <div>
                <span class="roles-kicker">Seguridad</span>
                <h1 class="roles-title">Roles</h1>
                <p class="roles-subtitle">Crea y edita los perfiles administrativos de la plataforma.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="roles-panel-link">Volver al panel</a>
        </div>

        <?php if (!empty($_SESSION['roles_success'])): ?>
            <div class="roles-alert success">
                <?= roles_h($_SESSION['roles_success']) ?>
            </div>
            <?php unset($_SESSION['roles_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['roles_error'])): ?>
            <div class="roles-alert error">
                <?= roles_h($_SESSION['roles_error']) ?>
            </div>
            <?php unset($_SESSION['roles_error']); ?>
        <?php endif; ?>

        <div class="roles-grid">
            <section class="roles-card">
                <div class="roles-card-head">
                    <h2 id="formTitle">Nuevo rol</h2>
                    <p>Completa los datos principales del rol.</p>
                </div>

                <form id="roleForm" class="roles-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_roles.php">
                    <input type="hidden" name="action" id="action" value="save">
                    <input type="hidden" name="id" id="roleId" value="0">

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" maxlength="50" required placeholder="Ej: Contable, Directiva, Operaciones">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripcion</label>
                        <textarea name="descripcion" id="descripcion" maxlength="150" rows="4" placeholder="Descripcion breve del rol (opcional)"></textarea>
                    </div>

                    <?php if ($cargosListo): ?>
                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select name="tipo" id="tipo">
                            <option value="acceso">Acceso (lo que puede abrir: Administrador, Invitado, Normal)</option>
                            <option value="cargo">Cargo (grupo aparte: Contable, Directiva...)</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">Guardar</button>
                        <button type="button" class="btn-secondary" id="resetBtn">Limpiar</button>
                    </div>
                </form>
            </section>

            <section class="roles-card">
                <div class="roles-card-head">
                    <h2>Roles existentes</h2>
                    <p>Busca y selecciona para editar.</p>
                </div>

                <div class="table-tools">
                    <input type="text" id="searchInput" placeholder="Buscar por nombre, descripcion o ID...">
                </div>

                <div class="roles-table-wrap">
                    <table class="roles-table" id="rolesTable">
                        <thead>
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th>Nombre</th>
                                <th>Descripcion</th>
                                <th style="width:170px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($roles) === 0): ?>
                                <tr>
                                    <td colspan="4" class="empty">No hay roles para mostrar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($roles as $r): ?>
                                    <tr data-id="<?= (int) $r['id'] ?>" data-nombre="<?= roles_h($r['nombre']) ?>" data-descripcion="<?= roles_h($r['descripcion'] ?? '') ?>" data-tipo="<?= roles_h($tipos[(int) $r['id']] ?? 'acceso') ?>">
                                        <td><?= (int) $r['id'] ?></td>
                                        <td><?= roles_h($r['nombre']) ?><?php if ($cargosListo): ?> <small class="cargo-chip"><?= ($tipos[(int) $r['id']] ?? 'acceso') === 'cargo' ? 'Cargo' : 'Acceso' ?></small><?php endif; ?></td>
                                        <td><?= roles_h($r['descripcion'] ?? '') ?></td>
                                        <td>
                                            <button type="button" class="btn-mini edit-btn">Editar</button>
                                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_roles.php" class="inline-form" onsubmit="return confirm('Seguro que deseas eliminar este rol?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <button type="submit" class="btn-mini danger">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
