<?php
require_once __DIR__.'/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$PERMISO_REQUERIDO = 'panel.menu_panel';
require_once __DIR__.'/../componentes/proteccion_autenticacion.php';
require_once __DIR__.'/../bd/conexion.php';
require_once __DIR__.'/../componentes/csrf.php';
require_once __DIR__.'/../componentes/menu_panel.php';
require_once __DIR__.'/../componentes/configuracion_sitio.php';

function amp_h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$menuReady = sg_admin_menu_tables_ready($conn);
if ($menuReady) { sg_admin_menu_seed($conn, sg_admin_menu_default_groups()); sg_admin_menu_ensure_config_modules($conn); }
$sections = [];
$subsections = [];
$assignments = [];
if ($menuReady) {
$result = mysqli_query($conn, 'SELECT * FROM admin_menu_secciones ORDER BY orden,id');
while ($result && $row = mysqli_fetch_assoc($result)) { $sections[] = $row; }
$result = mysqli_query($conn, 'SELECT * FROM admin_menu_subsecciones ORDER BY seccion_id,orden,id');
while ($result && $row = mysqli_fetch_assoc($result)) { $subsections[] = $row; }
$result = mysqli_query($conn, 'SELECT * FROM admin_menu_modulos');
while ($result && $row = mysqli_fetch_assoc($result)) { $assignments[$row['permiso_codigo']] = $row; }
}
$subBySection = [];
foreach ($subsections as $subsection) { $subBySection[(int) $subsection['seccion_id']][] = $subsection; }
$catalog = sg_admin_menu_catalog();
$moduleGroups = [];
$unassignedModules = [];
foreach ($sections as $section) {
    $sectionId = (int) $section['id'];
    $moduleGroups[$sectionId] = ['section' => $section, 'direct' => [], 'subsections' => []];
    foreach ($subBySection[$sectionId] ?? [] as $subsection) {
        $moduleGroups[$sectionId]['subsections'][(int) $subsection['id']] = ['subsection' => $subsection, 'modules' => []];
    }
}
foreach ($catalog as $code => $module) {
    $assignment = $assignments[$code] ?? null;
    $sectionId = (int) ($assignment['seccion_id'] ?? 0);
    $subsectionId = ($assignment['subseccion_id'] ?? null) === null ? 0 : (int) $assignment['subseccion_id'];
    $entry = ['code' => $code, 'module' => $module, 'assignment' => $assignment];
    if (!isset($moduleGroups[$sectionId])) {
        $unassignedModules[] = $entry;
    } elseif ($subsectionId > 0 && isset($moduleGroups[$sectionId]['subsections'][$subsectionId])) {
        $moduleGroups[$sectionId]['subsections'][$subsectionId]['modules'][] = $entry;
    } else {
        $moduleGroups[$sectionId]['direct'][] = $entry;
    }
}
$sortModules = static function (array &$entries): void {
    usort($entries, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['assignment']['orden'] ?? 9999);
        $rightOrder = (int) ($right['assignment']['orden'] ?? 9999);
        return $leftOrder <=> $rightOrder ?: strcmp($left['code'], $right['code']);
    });
};
foreach ($moduleGroups as &$group) {
    $sortModules($group['direct']);
    foreach ($group['subsections'] as &$subgroup) {
        $sortModules($subgroup['modules']);
    }
    unset($subgroup);
}
unset($group);
$sortModules($unassignedModules);

$canEdit = sg_has_permission_action($conn, 'panel.menu_panel', 'editar');
$canAdd = sg_has_permission_action($conn, 'panel.menu_panel', 'agregar');
$canDelete = sg_has_permission_action($conn, 'panel.menu_panel', 'eliminar');
$icons = sg_admin_menu_icon_choices();
$processUrl = BASE_URL.'procesos/proceso_menu_panel.php';
$grip = '<svg viewBox="0 0 14 20" fill="currentColor" aria-hidden="true"><circle cx="4" cy="4" r="1.7"/><circle cx="10" cy="4" r="1.7"/><circle cx="4" cy="10" r="1.7"/><circle cx="10" cy="10" r="1.7"/><circle cx="4" cy="16" r="1.7"/><circle cx="10" cy="16" r="1.7"/></svg>';
$arrows = static function (string $what): string {
    return '<span class="menu-arrows">'
        . '<button type="button" data-move="-1" title="Subir '.$what.'" aria-label="Subir '.$what.'"><i data-feather="arrow-up"></i></button>'
        . '<button type="button" data-move="1" title="Bajar '.$what.'" aria-label="Bajar '.$what.'"><i data-feather="arrow-down"></i></button>'
        . '</span>';
};

$renderModuleRow = static function (array $entry, int $sectionId, string $groupKey) use ($sections, $grip, $arrows): void {
    $code = amp_h($entry['code']);
    $module = $entry['module'];
    $active = (int) ($entry['assignment']['activo'] ?? 1) === 1;
    ?>
    <article class="menu-module <?= $active ? '' : 'is-off' ?>" data-sort-item data-module-row data-section="<?= $sectionId ?>">
        <button type="button" class="menu-grip" data-drag-handle title="Arrastra para mover" aria-label="Arrastrar <?= amp_h($module['nombre']) ?>"><?= $grip ?></button>
        <span class="menu-module-icon"><i data-feather="<?= amp_h($module['icono']) ?>"></i></span>
        <span class="menu-module-text">
            <strong><?= amp_h($module['nombre']) ?></strong>
            <small><?= amp_h($module['descripcion']) ?></small>
            <em class="menu-module-leaving" data-module-leaving hidden></em>
        </span>
        <input type="hidden" name="modulos[<?= $code ?>][grupo]" value="<?= amp_h($groupKey) ?>" data-module-group>
        <label class="menu-move-to">
            <span>Seccion</span>
            <select name="modulos[<?= $code ?>][destino]" data-module-destination>
                <?php foreach ($sections as $destination): ?>
                    <option value="<?= (int) $destination['id'] ?>" <?= (int) $destination['id'] === $sectionId ? 'selected' : '' ?>><?= amp_h($destination['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="menu-switch" title="Mostrar u ocultar en el panel">
            <input type="checkbox" name="modulos[<?= $code ?>][activo]" value="1" <?= $active ? 'checked' : '' ?> data-module-visible>
            <span>Visible</span>
        </label>
        <?= $arrows('modulo') ?>
    </article>
    <?php
};

// Se usa para las subsecciones guardadas y como plantilla de las nuevas (clave __KEY__).
$renderSubsection = static function (string $key, string $name, bool $active, array $modules, int $sectionId) use ($renderModuleRow, $grip, $arrows, $canDelete): void {
    $fieldKey = amp_h($key);
    ?>
    <section class="menu-group is-subsection" data-sort-item data-subsection data-group="<?= $fieldKey ?>">
        <header class="menu-group-head">
            <button type="button" class="menu-grip" data-drag-handle title="Arrastra para mover la subseccion" aria-label="Arrastrar subseccion"><?= $grip ?></button>
            <input type="hidden" name="subs[<?= $fieldKey ?>][eliminar]" value="0" data-sub-delete-flag>
            <span class="menu-group-tag">Subseccion</span>
            <input class="menu-group-name" type="text" name="subs[<?= $fieldKey ?>][nombre]" maxlength="120" required value="<?= amp_h($name) ?>" placeholder="Nombre de la subseccion" aria-label="Nombre de la subseccion" data-sub-name>
            <span class="menu-count" data-group-count><?= count($modules) ?></span>
            <label class="menu-switch" title="Mostrar u ocultar en el panel">
                <input type="checkbox" name="subs[<?= $fieldKey ?>][activo]" value="1" <?= $active ? 'checked' : '' ?>>
                <span>Visible</span>
            </label>
            <?= $arrows('subseccion') ?>
            <?php if ($canDelete): ?>
                <button type="button" class="menu-icon-btn danger" data-sub-delete aria-label="Eliminar subseccion"><i data-feather="trash-2"></i></button>
            <?php endif; ?>
        </header>
        <div class="menu-module-list" data-sort-list data-sort-group="mod-<?= $sectionId ?>" data-group-key="<?= $fieldKey ?>" data-empty="Arrastra modulos aqui">
            <?php foreach ($modules as $entry) { $renderModuleRow($entry, $sectionId, $key); } ?>
        </div>
        <p class="menu-group-deleted">
            <i data-feather="trash-2"></i><span>Esta subseccion se eliminara al guardar.</span>
            <button type="button" data-sub-restore>Deshacer</button>
        </p>
    </section>
    <?php
};

$canSiteConfig = sg_has_permission($conn, 'interfaz.configuracion_general');
$pageTitle = 'Configuracion del panel';
$cssFiles = ['css/global.css','css/barra_navegacion.css','css/configuracion_sitio_admin.css','css/menu_panel_admin.css'];
$jsFiles = ['js/barra_navegacion.js','js/menu_panel_admin.js'];
include __DIR__.'/../componentes/encabezado.php'; include __DIR__.'/../componentes/barra_navegacion.php';
?>
<main class="menu-panel-page">
    <div class="menu-panel-top">
        <header class="site-config-header">
            <div><span class="site-config-kicker">Interfaz publica</span><h1>Panel lateral</h1><p>Organiza las secciones, subsecciones y modulos del panel administrativo.</p></div>
            <div class="site-config-header-actions"><a href="<?= BASE_URL ?>pantallas/inicio.php" target="_blank" rel="noopener">Ver sitio</a><a href="<?= BASE_URL ?>admin" class="soft">Volver al panel</a></div>
        </header>
        <?php sg_site_admin_subnav('panel', $canSiteConfig, true); ?>
    </div>

    <?php foreach (['success' => 'menu_panel_success', 'error' => 'menu_panel_error'] as $type => $key): if (!empty($_SESSION[$key])): ?>
        <div class="menu-panel-alert <?= $type ?>"><?= amp_h($_SESSION[$key]); unset($_SESSION[$key]); ?></div>
    <?php endif; endforeach; ?>

    <?php if (!$menuReady): ?>
        <div class="menu-panel-alert error">Instala primero la actualizacion de base de datos del menu administrativo.</div>
    <?php else: ?>
    <div class="menu-panel-shell" data-menu-panel data-process-url="<?= amp_h($processUrl) ?>" data-csrf="<?= amp_h(csrf_token()) ?>">
        <div class="menu-toolbar">
            <label class="menu-toolbar-search">
                <i data-feather="search"></i>
                <span class="menu-visually-hidden">Buscar modulo</span>
                <input type="search" data-module-search placeholder="Buscar un modulo o subseccion">
            </label>
            <button type="button" class="menu-btn ghost" data-toggle-all aria-pressed="false"><i data-feather="maximize-2"></i><span>Expandir todo</span></button>
            <?php if ($canAdd): ?>
                <button type="button" class="menu-btn primary" data-new-section-toggle aria-expanded="false" aria-controls="menu-new-section"><i data-feather="plus"></i>Nueva seccion</button>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
            <p class="menu-hint"><span class="menu-hint-grip"><?= $grip ?></span><span>Arrastra desde los puntos para cambiar el orden o pasar un modulo a otra subseccion. Los cambios de cada seccion se guardan con su boton <strong>Guardar seccion</strong>.</span></p>
        <?php else: ?>
            <p class="menu-hint"><i data-feather="lock"></i><span>Tu rol puede consultar el panel lateral, pero no modificarlo.</span></p>
        <?php endif; ?>

        <?php if ($canAdd): ?>
        <form method="post" action="<?= amp_h($processUrl) ?>" class="menu-card menu-new-section" id="menu-new-section" hidden>
            <?= csrf_field() ?><input type="hidden" name="action" value="create_section">
            <header class="menu-card-head">
                <span class="menu-section-symbol"><i data-feather="folder-plus"></i></span>
                <div><small>Nuevo</small><h2>Crear seccion</h2><p>Se agrega al final del panel; despues puedes arrastrarla a su lugar.</p></div>
            </header>
            <div class="menu-section-fields">
                <label><span>Etiqueta superior</span><input name="etiqueta" maxlength="80" required placeholder="Ej: Informes"></label>
                <label><span>Nombre de la seccion</span><input name="nombre" maxlength="120" required placeholder="Ej: Reportes y analisis"></label>
                <label><span>Icono</span><select name="icono" required><?php foreach ($icons as $icon => $name): ?><option value="<?= amp_h($icon) ?>" <?= $icon === 'grid' ? 'selected' : '' ?>><?= amp_h($name) ?></option><?php endforeach; ?></select></label>
            </div>
            <footer class="menu-card-foot">
                <button type="button" class="menu-btn ghost" data-new-section-cancel>Cancelar</button>
                <button type="submit" class="menu-btn primary"><i data-feather="plus"></i>Crear seccion</button>
            </footer>
        </form>
        <?php endif; ?>

        <?php if ($unassignedModules): ?>
        <form method="post" action="<?= amp_h($processUrl) ?>" class="menu-card menu-unassigned">
            <?= csrf_field() ?><input type="hidden" name="action" value="assign_modules">
            <header class="menu-card-head">
                <span class="menu-section-symbol"><i data-feather="inbox"></i></span>
                <div><small>Pendiente</small><h2>Modulos sin ubicar</h2><p>Estos modulos aun no aparecen en el panel. Elige en que seccion debe mostrarse cada uno.</p></div>
                <span class="menu-count"><?= count($unassignedModules) ?></span>
            </header>
            <div class="menu-module-list is-static">
                <?php foreach ($unassignedModules as $entry): ?>
                    <article class="menu-module is-unassigned" data-module-row>
                        <span class="menu-module-icon"><i data-feather="<?= amp_h($entry['module']['icono']) ?>"></i></span>
                        <span class="menu-module-text"><strong><?= amp_h($entry['module']['nombre']) ?></strong><small><?= amp_h($entry['module']['descripcion']) ?></small></span>
                        <label class="menu-move-to">
                            <span>Ubicar en</span>
                            <select name="modulos[<?= amp_h($entry['code']) ?>][destino]" <?= $canEdit ? '' : 'disabled' ?>>
                                <option value="">Dejar sin ubicar</option>
                                <?php foreach ($sections as $destination): ?><option value="<?= (int) $destination['id'] ?>"><?= amp_h($destination['nombre']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($canEdit): ?>
                <footer class="menu-card-foot"><button type="submit" class="menu-btn primary"><i data-feather="check"></i>Ubicar modulos</button></footer>
            <?php endif; ?>
        </form>
        <?php endif; ?>

        <div class="menu-section-list" data-sort-list data-sort-group="sections">
            <?php foreach ($moduleGroups as $group):
                $section = $group['section'];
                $sectionId = (int) $section['id'];
                $moduleCount = count($group['direct']);
                foreach ($group['subsections'] as $subgroup) { $moduleCount += count($subgroup['modules']); }
                $sectionActive = (int) $section['activo'] === 1;
            ?>
            <article class="menu-section" id="seccion-<?= $sectionId ?>" data-sort-item data-section-card data-section-id="<?= $sectionId ?>">
                <header class="menu-section-head" data-section-head>
                    <?php if ($canEdit): ?>
                        <button type="button" class="menu-grip" data-drag-handle title="Arrastra para mover la seccion" aria-label="Arrastrar <?= amp_h($section['nombre']) ?>"><?= $grip ?></button>
                    <?php endif; ?>
                    <span class="menu-section-symbol" data-section-symbol><i data-feather="<?= amp_h($section['icono']) ?>"></i></span>
                    <div class="menu-section-title">
                        <small data-section-label><?= amp_h($section['etiqueta']) ?></small>
                        <h2 data-section-name><?= amp_h($section['nombre']) ?></h2>
                        <p><span data-count-modules><?= $moduleCount ?></span> modulos · <span data-count-subs><?= count($group['subsections']) ?></span> subsecciones</p>
                    </div>
                    <div class="menu-section-tools">
                        <span class="menu-unsaved-flag">Sin guardar</span>
                        <span class="menu-state <?= $sectionActive ? 'on' : 'off' ?>" data-section-state><?= $sectionActive ? 'Visible' : 'Oculta' ?></span>
                        <?php if ($canEdit): ?><?= $arrows('seccion') ?><?php endif; ?>
                        <button type="button" class="menu-section-collapse" data-section-toggle aria-expanded="false" aria-controls="seccion-cuerpo-<?= $sectionId ?>" aria-label="Expandir <?= amp_h($section['nombre']) ?>"><i data-feather="chevron-down"></i></button>
                    </div>
                </header>

                <form method="post" action="<?= amp_h($processUrl) ?>" class="menu-section-form" id="seccion-cuerpo-<?= $sectionId ?>" data-section-form hidden>
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_layout"><input type="hidden" name="id" value="<?= $sectionId ?>">
                    <fieldset class="menu-fieldset" <?= $canEdit ? '' : 'disabled' ?>>
                        <div class="menu-block">
                            <h3 class="menu-block-title">Datos de la seccion</h3>
                            <div class="menu-section-fields">
                                <label><span>Etiqueta superior</span><input name="etiqueta" maxlength="80" required value="<?= amp_h($section['etiqueta']) ?>" data-field-label></label>
                                <label><span>Nombre de la seccion</span><input name="nombre" maxlength="120" required value="<?= amp_h($section['nombre']) ?>" data-field-name></label>
                                <label><span>Icono</span><select name="icono" required data-field-icon><?php foreach ($icons as $icon => $name): ?><option value="<?= amp_h($icon) ?>" <?= $section['icono'] === $icon ? 'selected' : '' ?>><?= amp_h($name) ?></option><?php endforeach; ?></select></label>
                                <label class="menu-switch is-field"><input type="checkbox" name="activo" value="1" <?= $sectionActive ? 'checked' : '' ?> data-field-active><span>Visible en el panel</span></label>
                            </div>
                        </div>

                        <div class="menu-block">
                            <div class="menu-block-head">
                                <h3 class="menu-block-title">Modulos y subsecciones</h3>
                                <?php if ($canAdd): ?>
                                    <button type="button" class="menu-btn ghost small" data-add-subsection><i data-feather="plus"></i>Nueva subseccion</button>
                                <?php endif; ?>
                            </div>
                            <section class="menu-group" data-group="direct">
                                <header class="menu-group-head">
                                    <span class="menu-group-tag">Directo</span>
                                    <strong class="menu-group-fixed-name">Sin subseccion</strong>
                                    <span class="menu-count" data-group-count><?= count($group['direct']) ?></span>
                                </header>
                                <div class="menu-module-list" data-sort-list data-sort-group="mod-<?= $sectionId ?>" data-group-key="direct" data-empty="Arrastra modulos aqui">
                                    <?php foreach ($group['direct'] as $entry) { $renderModuleRow($entry, $sectionId, 'direct'); } ?>
                                </div>
                            </section>
                            <div class="menu-subsection-list" data-sort-list data-sort-group="sub-<?= $sectionId ?>" data-subsection-list>
                                <?php foreach ($group['subsections'] as $subsectionId => $subgroup) {
                                    $renderSubsection((string) $subsectionId, (string) $subgroup['subsection']['nombre'], (int) $subgroup['subsection']['activo'] === 1, $subgroup['modules'], $sectionId);
                                } ?>
                            </div>
                            <?php if ($canAdd): ?>
                                <template data-subsection-template><?php $renderSubsection('__KEY__', '', true, [], $sectionId); ?></template>
                            <?php endif; ?>
                        </div>
                    </fieldset>

                    <?php if ($canEdit): ?>
                    <footer class="menu-section-foot">
                        <div class="menu-section-foot-left">
                            <?php if ($canDelete): ?>
                                <button type="submit" form="eliminar-seccion-<?= $sectionId ?>" class="menu-btn danger-soft small" data-section-delete><i data-feather="trash-2"></i>Eliminar seccion</button>
                                <span class="menu-lock-note" data-section-lock><i data-feather="lock"></i>Para eliminarla, primero dejala sin modulos ni subsecciones.</span>
                            <?php endif; ?>
                        </div>
                        <div class="menu-section-foot-right">
                            <span class="menu-unsaved-note"><i data-feather="alert-circle"></i>Cambios sin guardar</span>
                            <button type="button" class="menu-btn ghost" data-section-discard>Descartar</button>
                            <button type="submit" class="menu-btn primary" data-section-save><i data-feather="save"></i><span>Guardar seccion</span></button>
                        </div>
                    </footer>
                    <?php endif; ?>
                </form>
                <?php if ($canDelete): ?>
                    <form method="post" action="<?= amp_h($processUrl) ?>" id="eliminar-seccion-<?= $sectionId ?>" data-confirm="Eliminar la seccion «<?= amp_h($section['nombre']) ?>»? Esta accion no se puede deshacer." hidden>
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete_section"><input type="hidden" name="id" value="<?= $sectionId ?>">
                    </form>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <p class="menu-empty-search" data-module-empty hidden>No hay modulos que coincidan con esa busqueda.</p>
    </div>
    <div class="menu-toast" data-menu-toast role="status" aria-live="polite" hidden></div>
    <?php endif; ?>
</main>
<?php include __DIR__.'/../componentes/pie_pagina.php'; ?>
