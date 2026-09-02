<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Mantenimiento Nosotros | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/nosotros_admin.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/nosotros_admin.js"
];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
mysqli_set_charset($conn, 'utf8mb4');

$config = [];
$res = mysqli_query($conn, "SELECT * FROM configuracion_nosotros WHERE id = 1 LIMIT 1");
if ($res) {
    $config = mysqli_fetch_assoc($res) ?: [];
}

function cargar_items_nosotros(mysqli $conn, string $tabla): array
{
    $items = [];
    $res = mysqli_query($conn, "SELECT * FROM {$tabla} ORDER BY orden ASC, id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    return $items;
}

$indicadores = cargar_items_nosotros($conn, 'nosotros_indicadores');
$valores = cargar_items_nosotros($conn, 'nosotros_valores');
$pasos = cargar_items_nosotros($conn, 'nosotros_pasos');
$equipo = cargar_items_nosotros($conn, 'nosotros_equipo');

$editTipo = (string) ($_GET['tipo'] ?? '');
$editId = max(0, (int) ($_GET['item_id'] ?? 0));
$seccionAbierta = (string) ($_GET['seccion'] ?? '');

$bloques = [
    'indicador' => ['tipo' => 'indicador', 'titulo' => 'Indicadores', 'items' => $indicadores, 'campos' => ['valor', 'etiqueta']],
    'valor' => ['tipo' => 'valor', 'titulo' => 'Lista de valores', 'items' => $valores, 'campos' => ['icono', 'titulo', 'texto']],
    'paso' => ['tipo' => 'paso', 'titulo' => 'Pasos del proceso', 'items' => $pasos, 'campos' => ['numero', 'titulo', 'texto']],
    'equipo' => ['tipo' => 'equipo', 'titulo' => 'Integrantes del equipo', 'items' => $equipo, 'campos' => ['nombre', 'rol']],
];

$editItems = [];
foreach ($bloques as $tipoBloque => $bloque) {
    $editItems[$tipoBloque] = null;
    if ($editTipo !== $tipoBloque || $editId <= 0) {
        continue;
    }
    foreach ($bloque['items'] as $item) {
        if ((int) $item['id'] === $editId) {
            $editItems[$tipoBloque] = $item;
            break;
        }
    }
}

function render_gestor_nosotros(array $bloque, ?array $editItem): void
{
    ?>
    <section class="nosotros-inline-manager">
        <div class="inline-manager-head">
            <div>
                <strong><?= h($bloque['titulo']) ?></strong>
                <span><?= $editItem ? 'Editando registro seleccionado.' : 'Agrega y administra los elementos visibles.' ?></span>
            </div>
            <b><?= count($bloque['items']) ?> registros</b>
        </div>

        <form class="nosotros-mini-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="guardar_item">
            <input type="hidden" name="tipo" value="<?= h($bloque['tipo']) ?>">
            <input type="hidden" name="item_id" value="<?= (int) ($editItem['id'] ?? 0) ?>">
            <div class="mini-grid">
                <?php foreach ($bloque['campos'] as $campo): ?>
                    <label>
                        <span><?= h(ucfirst($campo)) ?> *</span>
                        <?php if ($campo === 'texto'): ?>
                            <textarea name="texto" required maxlength="1600" rows="2"><?= h($editItem['texto'] ?? '') ?></textarea>
                        <?php else: ?>
                            <input name="<?= h($campo === 'etiqueta' ? 'etiqueta' : $campo) ?>" required maxlength="160" value="<?= h($editItem[$campo] ?? '') ?>">
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
                <?php if ($bloque['tipo'] === 'equipo'): ?>
                    <label>
                        <span>Imagen</span>
                        <input type="file" name="imagen_equipo" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($editItem['imagen'])): ?><small>Actual: <?= h($editItem['imagen']) ?></small><?php endif; ?>
                    </label>
                <?php endif; ?>
                <label><span>Orden</span><input type="number" name="orden" value="<?= (int) ($editItem['orden'] ?? 0) ?>"></label>
            </div>
            <div class="inline-manager-actions">
                <label class="check-line"><input type="checkbox" name="activo" <?= (int) ($editItem['activo'] ?? 1) === 1 ? 'checked' : '' ?>> Activo</label>
                <div class="nosotros-form-actions">
                    <button class="nosotros-submit" type="submit"><?= $editItem ? 'Actualizar' : 'Agregar' ?></button>
                    <?php if ($editItem): ?><a class="nosotros-admin-link soft" href="<?= BASE_URL ?>mantenimientos/mantenimiento_nosotros.php">Nuevo</a><?php endif; ?>
                </div>
            </div>
        </form>

        <div class="nosotros-item-list">
            <?php foreach ($bloque['items'] as $item): ?>
                <div class="nosotros-item <?= (int) $item['activo'] === 1 ? '' : 'inactive' ?>">
                    <div>
                        <strong><?= h($item['titulo'] ?? $item['nombre'] ?? $item['valor'] ?? '') ?></strong>
                        <small><?= h($item['etiqueta'] ?? $item['rol'] ?? $item['texto'] ?? '') ?></small>
                    </div>
                    <div class="nosotros-item-actions">
                        <a class="small-action" href="<?= BASE_URL ?>mantenimientos/mantenimiento_nosotros.php?tipo=<?= h($bloque['tipo']) ?>&item_id=<?= (int) $item['id'] ?>">Editar</a>
                        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php">
                            <input type="hidden" name="accion" value="toggle_item">
                            <input type="hidden" name="tipo" value="<?= h($bloque['tipo']) ?>">
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <?php if ((int) $item['activo'] === 1): ?>
                                <button class="small-action warn" type="submit">Inactivar</button>
                            <?php else: ?>
                                <input type="hidden" name="activo" value="1">
                                <button class="small-action ok" type="submit">Activar</button>
                            <?php endif; ?>
                        </form>
                        <?php if ((int) $item['activo'] === 0): ?>
                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php" onsubmit="return confirm('Deseas eliminar este registro?');">
                                <input type="hidden" name="accion" value="eliminar_item">
                                <input type="hidden" name="tipo" value="<?= h($bloque['tipo']) ?>">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <button class="small-action danger" type="submit">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="nosotros-admin-page">
    <div class="nosotros-admin-container">
        <header class="nosotros-admin-header">
            <div>
                <span class="nosotros-admin-kicker">Contenido publico</span>
                <h1>Mantenimiento Nosotros</h1>
                <p>Personaliza la historia, indicadores, valores, pasos de trabajo y equipo que se muestran al visitante.</p>
            </div>
            <div class="nosotros-admin-actions">
                <a href="<?= BASE_URL ?>pantallas/nosotros.php" target="_blank" class="nosotros-admin-link">Ver pagina publica</a>
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="nosotros-admin-link soft">Volver al panel</a>
            </div>
        </header>

        <?php if (!empty($_SESSION['nosotros_admin_success'])): ?>
            <div class="nosotros-alert success"><?= h($_SESSION['nosotros_admin_success']) ?></div>
            <?php unset($_SESSION['nosotros_admin_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['nosotros_admin_error'])): ?>
            <div class="nosotros-alert error"><?= h($_SESSION['nosotros_admin_error']) ?></div>
            <?php unset($_SESSION['nosotros_admin_error']); ?>
        <?php endif; ?>

        <section class="nosotros-form">
            <div class="nosotros-content-head">
                <div>
                    <h2>Contenido principal</h2>
                    <p>Edita cada bloque de la pagina de forma independiente.</p>
                </div>
                <span>7 secciones</span>
            </div>

            <div class="nosotros-config-grid">
                <details class="nosotros-config-section wide" <?= $seccionAbierta === 'hero' ? 'open' : '' ?>>
                    <summary>
                        <span class="config-section-icon"><i data-feather="image"></i></span>
                        <span class="config-section-title"><strong>Hero</strong><small>Portada, mensaje y botones principales</small></span>
                        <span class="config-section-count">8 campos</span>
                        <i class="config-section-arrow" data-feather="chevron-down"></i>
                    </summary>
                    <div class="config-section-body">
                        <form class="config-section-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="guardar_seccion">
                            <input type="hidden" name="seccion" value="hero">
                            <div class="form-grid two">
                            <label><span>Etiqueta *</span><input name="hero_kicker" required maxlength="80" value="<?= h($config['hero_kicker'] ?? '') ?>"></label>
                            <label><span>Imagen hero</span><input type="file" name="hero_imagen" accept="image/jpeg,image/png,image/webp"><small>Actual: <?= h($config['hero_imagen'] ?? '') ?></small></label>
                            <label><span>Titulo *</span><textarea name="hero_titulo" required maxlength="180" rows="2"><?= h($config['hero_titulo'] ?? '') ?></textarea></label>
                            <label><span>Subtitulo *</span><textarea name="hero_subtitulo" required maxlength="1200" rows="2"><?= h($config['hero_subtitulo'] ?? '') ?></textarea></label>
                            <label><span>Boton principal *</span><input name="boton_principal_texto" required maxlength="80" value="<?= h($config['boton_principal_texto'] ?? '') ?>"></label>
                            <label><span>URL principal *</span><input name="boton_principal_url" required maxlength="255" value="<?= h($config['boton_principal_url'] ?? '') ?>"></label>
                            <label><span>Boton secundario *</span><input name="boton_secundario_texto" required maxlength="80" value="<?= h($config['boton_secundario_texto'] ?? '') ?>"></label>
                            <label><span>URL secundaria *</span><input name="boton_secundario_url" required maxlength="255" value="<?= h($config['boton_secundario_url'] ?? '') ?>"></label>
                            </div>
                            <button type="submit" class="nosotros-submit section-save"><i data-feather="save"></i> Guardar Hero</button>
                        </form>
                    </div>
                </details>

                <details class="nosotros-config-section wide" <?= ($seccionAbierta === 'indicadores' || $editTipo === 'indicador') ? 'open' : '' ?>>
                    <summary>
                        <span class="config-section-icon"><i data-feather="bar-chart-2"></i></span>
                        <span class="config-section-title"><strong>Indicadores</strong><small>Cifras destacadas debajo de la portada</small></span>
                        <span class="config-section-count"><?= count($indicadores) ?> registros</span>
                        <i class="config-section-arrow" data-feather="chevron-down"></i>
                    </summary>
                    <div class="config-section-body manager-only">
                        <?php render_gestor_nosotros($bloques['indicador'], $editItems['indicador']); ?>
                    </div>
                </details>

                <details class="nosotros-config-section wide" <?= $seccionAbierta === 'historia' ? 'open' : '' ?>>
                    <summary>
                        <span class="config-section-icon"><i data-feather="book-open"></i></span>
                        <span class="config-section-title"><strong>Historia</strong><small>Origen, imagen y relato de la comunidad</small></span>
                        <span class="config-section-count">7 campos</span>
                        <i class="config-section-arrow" data-feather="chevron-down"></i>
                    </summary>
                    <div class="config-section-body">
                        <form class="config-section-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="guardar_seccion">
                            <input type="hidden" name="seccion" value="historia">
                            <div class="form-grid two">
                            <label><span>Imagen historia</span><input type="file" name="historia_imagen" accept="image/jpeg,image/png,image/webp"><small>Actual: <?= h($config['historia_imagen'] ?? '') ?></small></label>
                            <label><span>Etiqueta historia *</span><input name="historia_kicker" required maxlength="80" value="<?= h($config['historia_kicker'] ?? '') ?>"></label>
                            <label><span>Badge titulo *</span><input name="historia_badge_titulo" required maxlength="80" value="<?= h($config['historia_badge_titulo'] ?? '') ?>"></label>
                            <label><span>Badge texto *</span><input name="historia_badge_texto" required maxlength="120" value="<?= h($config['historia_badge_texto'] ?? '') ?>"></label>
                            <label class="span-2"><span>Titulo historia *</span><textarea name="historia_titulo" required maxlength="180" rows="2"><?= h($config['historia_titulo'] ?? '') ?></textarea></label>
                            <label><span>Texto 1 *</span><textarea name="historia_texto_1" required maxlength="1600" rows="3"><?= h($config['historia_texto_1'] ?? '') ?></textarea></label>
                            <label><span>Texto 2 *</span><textarea name="historia_texto_2" required maxlength="1600" rows="3"><?= h($config['historia_texto_2'] ?? '') ?></textarea></label>
                            </div>
                            <button type="submit" class="nosotros-submit section-save"><i data-feather="save"></i> Guardar Historia</button>
                        </form>
                    </div>
                </details>

                <details class="nosotros-config-section managed" <?= ($seccionAbierta === 'valores' || $editTipo === 'valor') ? 'open' : '' ?>>
                    <summary>
                        <span class="config-section-icon"><i data-feather="heart"></i></span>
                        <span class="config-section-title"><strong>Valores</strong><small>Compromiso que comunica la marca</small></span>
                        <span class="config-section-count">3 campos / <?= count($valores) ?> valores</span>
                        <i class="config-section-arrow" data-feather="chevron-down"></i>
                    </summary>
                    <div class="config-section-body compact-fields">
                        <form class="config-section-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php">
                            <input type="hidden" name="accion" value="guardar_seccion">
                            <input type="hidden" name="seccion" value="valores">
                            <label><span>Etiqueta *</span><input name="valores_kicker" required maxlength="80" value="<?= h($config['valores_kicker'] ?? '') ?>"></label>
                            <label><span>Titulo *</span><input name="valores_titulo" required maxlength="180" value="<?= h($config['valores_titulo'] ?? '') ?>"></label>
                            <label><span>Texto *</span><textarea name="valores_texto" required maxlength="1200" rows="2"><?= h($config['valores_texto'] ?? '') ?></textarea></label>
                            <button type="submit" class="nosotros-submit section-save"><i data-feather="save"></i> Guardar textos</button>
                        </form>
                        <?php render_gestor_nosotros($bloques['valor'], $editItems['valor']); ?>
                    </div>
                </details>

                <details class="nosotros-config-section managed" <?= ($seccionAbierta === 'proceso' || $editTipo === 'paso') ? 'open' : '' ?>>
                    <summary>
                        <span class="config-section-icon"><i data-feather="compass"></i></span>
                        <span class="config-section-title"><strong>Proceso</strong><small>Forma de preparar cada experiencia</small></span>
                        <span class="config-section-count">3 campos / <?= count($pasos) ?> pasos</span>
                        <i class="config-section-arrow" data-feather="chevron-down"></i>
                    </summary>
                    <div class="config-section-body compact-fields">
                        <form class="config-section-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php">
                            <input type="hidden" name="accion" value="guardar_seccion">
                            <input type="hidden" name="seccion" value="proceso">
                            <label><span>Etiqueta *</span><input name="proceso_kicker" required maxlength="80" value="<?= h($config['proceso_kicker'] ?? '') ?>"></label>
                            <label><span>Titulo *</span><input name="proceso_titulo" required maxlength="180" value="<?= h($config['proceso_titulo'] ?? '') ?>"></label>
                            <label><span>Texto *</span><textarea name="proceso_texto" required maxlength="1200" rows="2"><?= h($config['proceso_texto'] ?? '') ?></textarea></label>
                            <button type="submit" class="nosotros-submit section-save"><i data-feather="save"></i> Guardar textos</button>
                        </form>
                        <?php render_gestor_nosotros($bloques['paso'], $editItems['paso']); ?>
                    </div>
                </details>

                <details class="nosotros-config-section managed" <?= ($seccionAbierta === 'equipo' || $editTipo === 'equipo') ? 'open' : '' ?>>
                    <summary>
                        <span class="config-section-icon"><i data-feather="users"></i></span>
                        <span class="config-section-title"><strong>Equipo</strong><small>Presentacion de las personas</small></span>
                        <span class="config-section-count">3 campos / <?= count($equipo) ?> integrantes</span>
                        <i class="config-section-arrow" data-feather="chevron-down"></i>
                    </summary>
                    <div class="config-section-body compact-fields">
                        <form class="config-section-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php">
                            <input type="hidden" name="accion" value="guardar_seccion">
                            <input type="hidden" name="seccion" value="equipo">
                            <label><span>Etiqueta *</span><input name="equipo_kicker" required maxlength="80" value="<?= h($config['equipo_kicker'] ?? '') ?>"></label>
                            <label><span>Titulo *</span><input name="equipo_titulo" required maxlength="180" value="<?= h($config['equipo_titulo'] ?? '') ?>"></label>
                            <label><span>Texto *</span><textarea name="equipo_texto" required maxlength="1200" rows="2"><?= h($config['equipo_texto'] ?? '') ?></textarea></label>
                            <button type="submit" class="nosotros-submit section-save"><i data-feather="save"></i> Guardar textos</button>
                        </form>
                        <?php render_gestor_nosotros($bloques['equipo'], $editItems['equipo']); ?>
                    </div>
                </details>

                <details class="nosotros-config-section" <?= $seccionAbierta === 'cta' ? 'open' : '' ?>>
                    <summary>
                        <span class="config-section-icon"><i data-feather="flag"></i></span>
                        <span class="config-section-title"><strong>Llamado final</strong><small>Cierre y accesos de conversion</small></span>
                        <span class="config-section-count">7 campos</span>
                        <i class="config-section-arrow" data-feather="chevron-down"></i>
                    </summary>
                    <div class="config-section-body compact-fields">
                        <form class="config-section-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_nosotros.php">
                            <input type="hidden" name="accion" value="guardar_seccion">
                            <input type="hidden" name="seccion" value="cta">
                            <div class="form-grid two">
                            <label><span>Etiqueta *</span><input name="cta_kicker" required maxlength="80" value="<?= h($config['cta_kicker'] ?? '') ?>"></label>
                            <label><span>Titulo *</span><input name="cta_titulo" required maxlength="180" value="<?= h($config['cta_titulo'] ?? '') ?>"></label>
                            <label class="span-2"><span>Texto *</span><textarea name="cta_texto" required maxlength="1200" rows="2"><?= h($config['cta_texto'] ?? '') ?></textarea></label>
                            <label><span>Boton principal *</span><input name="cta_boton_principal_texto" required maxlength="80" value="<?= h($config['cta_boton_principal_texto'] ?? '') ?>"></label>
                            <label><span>URL principal *</span><input name="cta_boton_principal_url" required maxlength="255" value="<?= h($config['cta_boton_principal_url'] ?? '') ?>"></label>
                            <label><span>Boton secundario *</span><input name="cta_boton_secundario_texto" required maxlength="80" value="<?= h($config['cta_boton_secundario_texto'] ?? '') ?>"></label>
                            <label><span>URL secundaria *</span><input name="cta_boton_secundario_url" required maxlength="255" value="<?= h($config['cta_boton_secundario_url'] ?? '') ?>"></label>
                            </div>
                            <button type="submit" class="nosotros-submit section-save"><i data-feather="save"></i> Guardar llamado final</button>
                        </form>
                    </div>
                </details>
            </div>
        </section>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
