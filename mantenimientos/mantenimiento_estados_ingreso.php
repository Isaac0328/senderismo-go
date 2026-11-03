<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';

$pageTitle = 'Estados de ingresos';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/contabilidad.css'];
$jsFiles = ['js/barra_navegacion.js', 'js/catalogos_tablas.js'];

function mei_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$codigo = trim((string) ($_GET['edit'] ?? ''));
$edit = null;
if ($codigo !== '') {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM contabilidad_estado_financiero WHERE codigo = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
}

$estados = [];
$res = mysqli_query($conn, 'SELECT * FROM contabilidad_estado_financiero ORDER BY orden ASC, codigo ASC');
while ($res && $row = mysqli_fetch_assoc($res)) {
    $estados[] = $row;
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="fin-page fin-status-maintenance">
    <section class="fin-container">
        <header class="fin-header">
            <div>
                <span class="fin-kicker">Contabilidad</span>
                <h1>Estados de ingresos</h1>
                <p>Personaliza nombres, colores, orden y visibilidad. Las reglas automáticas de cada estado permanecen protegidas.</p>
            </div>
            <a class="fin-back" href="<?= BASE_URL ?>pantallas/panel_administrativo.php"><i data-feather="arrow-left"></i>Volver al panel</a>
        </header>

        <?php foreach (['success' => 'estado_ingreso_success', 'error' => 'estado_ingreso_error'] as $type => $key): ?>
            <?php if (!empty($_SESSION[$key])): ?>
                <div class="fin-alert <?= $type ?>"><?= mei_h($_SESSION[$key]) ?></div>
                <?php unset($_SESSION[$key]); ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="fin-layout">
            <section class="fin-card">
                <div class="fin-card-head">
                    <div><span><?= $edit ? 'Editar' : 'Catalogo' ?></span><h2><?= $edit ? 'Actualizar estado' : 'Estados protegidos' ?></h2></div>
                    <i data-feather="tag"></i>
                </div>
                <?php if (!$edit): ?>
                    <div class="fin-empty compact"><i data-feather="shield"></i><h2>Selecciona un estado</h2><p>Usa Editar en la tabla para personalizar su presentación.</p></div>
                <?php else: ?>
                    <form class="fin-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_estados_ingreso.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="codigo" value="<?= mei_h($edit['codigo']) ?>">
                        <label><span>Codigo protegido</span><input type="text" value="<?= mei_h($edit['codigo']) ?>" readonly></label>
                        <label><span>Nombre visible *</span><input type="text" name="nombre" maxlength="120" required value="<?= mei_h($edit['nombre']) ?>"></label>
                        <label><span>Descripcion</span><textarea name="descripcion" maxlength="255" rows="3"><?= mei_h($edit['descripcion']) ?></textarea></label>
                        <label><span>Color</span><input type="color" name="color" value="<?= mei_h($edit['color']) ?>"></label>
                        <label><span>Orden</span><input type="number" name="orden" min="0" max="9999" value="<?= (int) $edit['orden'] ?>"></label>
                        <label class="fin-check-row"><input type="checkbox" name="activo" value="1" <?= (int) $edit['activo'] === 1 ? 'checked' : '' ?>><span>Visible en el catalogo</span></label>
                        <div class="fin-actions"><button type="submit">Guardar cambios</button><a href="<?= BASE_URL ?>mantenimientos/mantenimiento_estados_ingreso.php">Cancelar</a></div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="fin-card fin-card-wide">
                <div class="fin-card-head"><div><span>Catalogo</span><h2>Estados registrados</h2></div><strong class="fin-count"><?= count($estados) ?></strong></div>
                <div class="fin-table-search"><i data-feather="search"></i><input type="search" data-catalog-table-search placeholder="Buscar estado, codigo o descripcion..."></div>
                <div class="fin-table-wrap fin-catalog-table-wrap">
                    <table>
                        <thead><tr><th>Estado</th><th>Codigo</th><th>Descripcion</th><th>Orden</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($estados as $estado): ?>
                                <tr>
                                    <td><strong><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= mei_h($estado['color']) ?>;vertical-align:middle;margin-right:6px"></span><?= mei_h($estado['nombre']) ?></strong></td>
                                    <td><code><?= mei_h($estado['codigo']) ?></code></td>
                                    <td><?= mei_h($estado['descripcion'] ?: 'Sin descripcion') ?></td>
                                    <td><?= (int) $estado['orden'] ?></td>
                                    <td><span class="fin-pill <?= (int) $estado['activo'] === 1 ? 'ok' : 'off' ?>"><?= (int) $estado['activo'] === 1 ? 'Visible' : 'Oculto' ?></span></td>
                                    <td><div class="fin-row-actions"><a href="<?= BASE_URL ?>mantenimientos/mantenimiento_estados_ingreso.php?edit=<?= mei_h($estado['codigo']) ?>">Editar</a><form method="POST" action="<?= BASE_URL ?>procesos/proceso_estados_ingreso.php"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="codigo" value="<?= mei_h($estado['codigo']) ?>"><input type="hidden" name="activo" value="<?= (int) $estado['activo'] === 1 ? 0 : 1 ?>"><button type="submit"><?= (int) $estado['activo'] === 1 ? 'Ocultar' : 'Mostrar' ?></button></form></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
