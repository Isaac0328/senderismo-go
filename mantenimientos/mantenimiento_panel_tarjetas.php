<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$PERMISO_REQUERIDO = 'panel.tarjetas';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/panel_tarjetas.php';

function pt_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$canEdit = sg_has_permission_action($conn, $PERMISO_REQUERIDO, 'editar');
$listo = sg_panel_tarjetas_listo($conn);
$config = sg_panel_tarjetas_config($conn);
$roles = [];
$res = mysqli_query($conn, 'SELECT id, nombre FROM roles ORDER BY id');
while ($res && $fila = mysqli_fetch_assoc($res)) {
    $roles[] = $fila;
}

// Las piezas por grupo, en el orden en que se muestran hoy.
$porGrupo = [];
foreach (sg_panel_tarjetas_catalogo() as $item) {
    $porGrupo[$item[2]][] = $item;
}
foreach ($porGrupo as $grupo => $items) {
    usort($items, static fn(array $a, array $b): int => $config[$a[0]]['orden'] <=> $config[$b[0]]['orden']);
    $porGrupo[$grupo] = $items;
}
$grupos = sg_panel_tarjetas_grupos();
$ocultas = count(array_filter($config, static fn(array $c): bool => !$c['activo']));
$limitadas = count(array_filter($config, static fn(array $c): bool => $c['roles'] !== []));

$pageTitle = 'Tarjetas del panel';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/contabilidad.css', 'css/panel_tarjetas.css'];
$jsFiles = ['js/barra_navegacion.js', 'js/panel_tarjetas.js'];
include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>
<main class="fin-page pt-page">
    <section class="fin-container">
        <header class="fin-header">
            <div><span class="fin-kicker">Panel</span><h1>Tarjetas del panel</h1><p>Elige qu&eacute; se muestra en el panel administrativo, en qu&eacute; orden y para qu&eacute; roles.</p></div>
            <div class="pt-header-actions">
                <a class="pt-btn" href="<?= pt_h(sg_clean_url('pantallas/panel_administrativo.php')) ?>" target="_blank" rel="noopener"><i data-feather="external-link"></i>Ver el panel</a>
                <a class="fin-back" href="<?= pt_h(sg_clean_url('pantallas/panel_administrativo.php')) ?>"><i data-feather="arrow-left"></i>Volver al panel</a>
            </div>
        </header>
        <?php foreach (['success', 'error'] as $tipo): $clave = 'panel_tarjetas_' . $tipo; ?>
            <?php if (!empty($_SESSION[$clave])): ?>
                <div class="fin-alert <?= $tipo ?>"><?= pt_h($_SESSION[$clave]); unset($_SESSION[$clave]); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$listo): ?>
            <div class="fin-alert error">Esta pantalla necesita la actualizaci&oacute;n pendiente del sistema. <a href="<?= pt_h(sg_clean_url('mantenimientos/mantenimiento_actualizaciones.php')) ?>">Actualizaciones del sistema</a></div>
        <?php else: ?>
        <div class="pt-summary">
            <div><i data-feather="eye-off"></i><span><strong><?= $ocultas ?></strong> <?= $ocultas === 1 ? 'pieza oculta' : 'piezas ocultas' ?></span></div>
            <div><i data-feather="users"></i><span><strong><?= $limitadas ?></strong> <?= $limitadas === 1 ? 'limitada a ciertos roles' : 'limitadas a ciertos roles' ?></span></div>
            <p><i data-feather="info"></i>Cada rol solo ve lo que su permiso le deja abrir. Esta pantalla puede ocultar piezas, pero nunca da acceso a algo que el rol no tiene (por ejemplo, el dinero requiere el permiso de Finanzas).</p>
        </div>

        <form method="post" action="<?= BASE_URL ?>procesos/proceso_panel_tarjetas.php" class="pt-form" data-pt-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guardar">
            <?php foreach ($grupos as $codigoGrupo => [$tituloGrupo, $ayudaGrupo]): ?>
                <section class="pt-group">
                    <header class="pt-group-head"><div><h2><?= pt_h($tituloGrupo) ?></h2><p><?= pt_h($ayudaGrupo) ?> Usa las flechas para cambiar el orden.</p></div></header>
                    <ul class="pt-list" data-pt-list>
                        <?php foreach ($porGrupo[$codigoGrupo] as [$codigo, $nombre, , $descripcion]): $c = $config[$codigo]; $limitada = $c['roles'] !== []; ?>
                            <li class="pt-row <?= $c['activo'] ? '' : 'is-off' ?>" data-pt-row>
                                <div class="pt-order">
                                    <button type="button" data-pt-up aria-label="Subir" <?= $canEdit ? '' : 'disabled' ?>><i data-feather="chevron-up"></i></button>
                                    <button type="button" data-pt-down aria-label="Bajar" <?= $canEdit ? '' : 'disabled' ?>><i data-feather="chevron-down"></i></button>
                                </div>
                                <div class="pt-info"><strong><?= pt_h($nombre) ?></strong><small><?= pt_h($descripcion) ?></small></div>
                                <label class="pt-switch" title="Mostrar u ocultar">
                                    <input type="checkbox" name="tarjetas[<?= pt_h($codigo) ?>][activo]" value="1" <?= $c['activo'] ? 'checked' : '' ?> data-pt-activo <?= $canEdit ? '' : 'disabled' ?>>
                                    <span class="pt-switch-track" aria-hidden="true"></span>
                                    <em data-pt-estado><?= $c['activo'] ? 'Visible' : 'Oculta' ?></em>
                                </label>
                                <div class="pt-roles">
                                    <select name="tarjetas[<?= pt_h($codigo) ?>][alcance]" data-pt-alcance aria-label="Para quien" <?= $canEdit ? '' : 'disabled' ?>>
                                        <option value="todos" <?= $limitada ? '' : 'selected' ?>>Todos los roles</option>
                                        <option value="elegidos" <?= $limitada ? 'selected' : '' ?>>Solo roles elegidos</option>
                                    </select>
                                    <div class="pt-role-list" data-pt-role-list <?= $limitada ? '' : 'hidden' ?>>
                                        <?php foreach ($roles as $rol): ?>
                                            <label><input type="checkbox" name="tarjetas[<?= pt_h($codigo) ?>][roles][]" value="<?= (int) $rol['id'] ?>" <?= in_array((int) $rol['id'], $c['roles'], true) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><span><?= pt_h($rol['nombre']) ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="tarjetas[<?= pt_h($codigo) ?>][orden]" value="<?= (int) $c['orden'] ?>" data-pt-orden>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>

            <?php if ($canEdit): ?>
            <div class="pt-actions">
                <button type="submit" class="pt-btn pt-btn-primary"><i data-feather="save"></i>Guardar cambios</button>
                <button type="submit" class="pt-btn pt-btn-danger" name="action" value="restablecer" formnovalidate data-pt-restablecer><i data-feather="rotate-ccw"></i>Restablecer todo</button>
            </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </section>
</main>
<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
