<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguridad: solo Admin
if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}
if (($_SESSION['usuario_rol_id'] ?? 0) != 1) {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

$pageTitle = "Mantenimiento Roles | Senderismo Go!";

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

// Traer roles (SP listar)
$roles = [];
$res = mysqli_query($conn, "CALL sp_roles_listar()");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $roles[] = $row;
    }
    mysqli_free_result($res);
}
// Limpiar resultados restantes del CALL (por seguridad)
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
                <h1 class="roles-title">Mantenimiento de Roles</h1>
                <p class="roles-subtitle">Crea, edita o elimina roles del sistema.</p>
            </div>
        </div>

        <?php if (!empty($_SESSION['roles_success'])): ?>
            <div class="roles-alert success">
                <?= htmlspecialchars($_SESSION['roles_success']) ?>
            </div>
            <?php unset($_SESSION['roles_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['roles_error'])): ?>
            <div class="roles-alert error">
                <?= htmlspecialchars($_SESSION['roles_error']) ?>
            </div>
            <?php unset($_SESSION['roles_error']); ?>
        <?php endif; ?>

        <div class="roles-grid">

            <!-- FORM -->
            <section class="roles-card">
                <div class="roles-card-head">
                    <h2 id="formTitle">Nuevo Rol</h2>
                    <p>Completa los datos y guarda.</p>
                </div>

                <form id="roleForm" class="roles-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_roles.php">
                    <input type="hidden" name="action" id="action" value="save">
                    <input type="hidden" name="id" id="roleId" value="0">

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" maxlength="50" required
                            placeholder="Ej: Invitado, Moderador, Editor...">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea name="descripcion" id="descripcion" maxlength="150" rows="4"
                            placeholder="Descripción breve del rol (opcional)"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">Guardar</button>
                        <button type="button" class="btn-secondary" id="resetBtn">Limpiar</button>
                    </div>
                </form>
            </section>

            <!-- TABLA -->
            <section class="roles-card">
                <div class="roles-card-head">
                    <h2>Roles existentes</h2>
                    <p>Busca y selecciona para editar.</p>
                </div>

                <div class="table-tools">
                    <input type="text" id="searchInput" placeholder="Buscar por nombre, descripción o ID...">
                </div>

                <div class="roles-table-wrap">
                    <table class="roles-table" id="rolesTable">
                        <thead>
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
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
                                    <tr data-id="<?= (int) $r['id'] ?>" data-nombre="<?= htmlspecialchars($r['nombre']) ?>"
                                        data-descripcion="<?= htmlspecialchars($r['descripcion'] ?? '') ?>">
                                        <td>
                                            <?= (int) $r['id'] ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($r['nombre']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($r['descripcion'] ?? '') ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-mini edit-btn">Editar</button>

                                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_roles.php"
                                                class="inline-form"
                                                onsubmit="return confirm('¿Seguro que deseas eliminar este rol?');">
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