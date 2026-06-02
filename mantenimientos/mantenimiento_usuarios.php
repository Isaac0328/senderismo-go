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

$pageTitle = "Mantenimiento Usuarios | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/usuarios.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/usuarios.js"
];

require_once __DIR__ . '/../bd/conexion.php';

// Roles para el select
$roles = [];
$rRoles = mysqli_query($conn, "SELECT id, nombre FROM roles ORDER BY id ASC");
if ($rRoles) {
    while ($row = mysqli_fetch_assoc($rRoles)) {
        $roles[] = $row;
    }
}

// Usuarios (SP listar)
$usuarios = [];
$res = mysqli_query($conn, "CALL sp_usuarios_listar()");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $usuarios[] = $row;
    }
    mysqli_free_result($res);
}
while (mysqli_more_results($conn)) {
    mysqli_next_result($conn);
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<div class="usuarios-page">
    <div class="usuarios-container">

        <div class="usuarios-header">
            <div>
                <h1 class="usuarios-title">Mantenimiento de Usuarios</h1>
                <p class="usuarios-subtitle">Crea, edita, asigna roles y activa/inactiva usuarios.</p>
            </div>
            <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="usuarios-panel-link">
                <i data-feather="arrow-left"></i>
                Volver al panel
            </a>
        </div>

        <?php if (!empty($_SESSION['usuarios_success'])): ?>
            <div class="alert success">
                <?= htmlspecialchars($_SESSION['usuarios_success']) ?>
            </div>
            <?php unset($_SESSION['usuarios_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['usuarios_error'])): ?>
            <div class="alert error">
                <?= htmlspecialchars($_SESSION['usuarios_error']) ?>
            </div>
            <?php unset($_SESSION['usuarios_error']); ?>
        <?php endif; ?>

        <div class="usuarios-grid">

            <!-- FORM -->
            <section class="card">
                <div class="card-head">
                    <h2 id="formTitle">Nuevo Usuario</h2>
                    <p>Los campos con * son obligatorios.</p>
                </div>

                <form id="userForm" class="user-form" method="POST"
                    action="<?= BASE_URL ?>procesos/proceso_usuarios.php">
                    <input type="hidden" name="action" id="action" value="save">
                    <input type="hidden" name="id" id="userId" value="0">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" required maxlength="100"
                                placeholder="Ej: Juan">
                        </div>
                        <div class="form-group">
                            <label for="apellido">Apellido *</label>
                            <input type="text" name="apellido" id="apellido" required maxlength="100"
                                placeholder="Ej: Pérez">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="user">Usuario *</label>
                            <input type="text" name="user" id="user" required maxlength="50" placeholder="Ej: jperez">
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" name="email" id="email" required maxlength="100"
                                placeholder="ejemplo@email.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="rol_id">Rol *</label>
                            <select name="rol_id" id="rol_id" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>">
                                        <?= htmlspecialchars($r['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="password">Contraseña <span class="hint">(obligatoria al crear, opcional al
                                    editar)</span></label>
                            <input type="password" name="password" id="password" minlength="6" maxlength="120"
                                placeholder="••••••••">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">Guardar</button>
                        <button type="button" class="btn-secondary" id="resetBtn">Limpiar</button>
                    </div>
                </form>
            </section>

            <!-- TABLA -->
            <section class="card">
                <div class="card-head">
                    <h2>Usuarios existentes</h2>
                    <p>Busca y selecciona para editar.</p>
                </div>

                <div class="table-tools">
                    <input type="text" id="searchInput" placeholder="Buscar por nombre, user, email, rol, estado...">
                </div>

                <div class="table-wrap">
                    <table class="usuarios-table" id="usuariosTable">
                        <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th>Nombre</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th style="width:90px;">Estado</th>
                                <th style="width:240px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios) === 0): ?>
                                <tr>
                                    <td colspan="7" class="empty">No hay usuarios para mostrar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $u): ?>
                                    <?php
                                    $estadoTxt = ((int) $u['estado'] === 1) ? 'Activo' : 'Inactivo';
                                    $estadoClass = ((int) $u['estado'] === 1) ? 'pill ok' : 'pill off';
                                    ?>
                                    <tr data-id="<?= (int) $u['id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                                        data-apellido="<?= htmlspecialchars($u['apellido']) ?>"
                                        data-user="<?= htmlspecialchars($u['user']) ?>"
                                        data-email="<?= htmlspecialchars($u['email']) ?>" data-rol_id="<?= (int) $u['rol_id'] ?>"
                                        data-estado="<?= (int) $u['estado'] ?>"
                                        data-rol_nombre="<?= htmlspecialchars($u['rol_nombre']) ?>">
                                        <td>
                                            <?= (int) $u['id'] ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['user']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['email']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($u['rol_nombre']) ?>
                                        </td>
                                        <td><span class="<?= $estadoClass ?>">
                                                <?= $estadoTxt ?>
                                            </span></td>
                                        <td>
                                            <button type="button" class="btn-mini edit-btn">Editar</button>

                                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios.php"
                                                class="inline-form">
                                                <input type="hidden" name="action" value="toggle_estado">
                                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                <input type="hidden" name="estado"
                                                    value="<?= ((int) $u['estado'] === 1) ? 0 : 1 ?>">
                                                <button type="submit"
                                                    class="btn-mini <?= ((int) $u['estado'] === 1) ? 'warn' : 'ok' ?>">
                                                    <?= ((int) $u['estado'] === 1) ? 'Inactivar' : 'Activar' ?>
                                                </button>
                                            </form>

                                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_usuarios.php"
                                                class="inline-form"
                                                onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
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
