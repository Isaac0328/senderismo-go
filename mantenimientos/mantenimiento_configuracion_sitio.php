<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'interfaz.configuracion_general';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/configuracion_sitio.php';

$site = sg_site_config($conn);
$menu = sg_site_menu($conn, false);

function cga_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Configuracion General | Senderismo Go!';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/configuracion_sitio_admin.css'];
$jsFiles = ['js/barra_navegacion.js', 'js/configuracion_sitio_admin.js'];

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="site-config-page">
    <div class="site-config-container">
        <header class="site-config-header">
            <div>
                <span class="site-config-kicker">Interfaz publica</span>
                <h1>Configuracion general</h1>
                <p>Administra identidad, elementos globales, menu, buscadores y pie de pagina desde un solo lugar.</p>
            </div>
            <div class="site-config-header-actions">
                <a href="<?= BASE_URL ?>pantallas/inicio.php" target="_blank" rel="noopener">Ver sitio</a>
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="soft">Volver al panel</a>
            </div>
        </header>

        <?php if (!empty($_SESSION['site_config_success'])): ?>
            <div class="site-config-alert success"><?= cga_h($_SESSION['site_config_success']) ?></div>
            <?php unset($_SESSION['site_config_success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['site_config_error'])): ?>
            <div class="site-config-alert error"><?= cga_h($_SESSION['site_config_error']) ?></div>
            <?php unset($_SESSION['site_config_error']); ?>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_configuracion_sitio.php" enctype="multipart/form-data" class="site-config-form">
            <?= csrf_field() ?>

            <details class="site-config-section">
                <summary>
                    <span class="section-icon"><i data-feather="flag"></i></span>
                    <span><strong>Identidad de la plataforma</strong><small>Nombre, eslogan, logos e icono del sitio.</small></span>
                    <i data-feather="chevron-down" class="section-chevron"></i>
                </summary>
                <div class="site-config-section-body">
                    <div class="site-config-grid two">
                        <label><span>Nombre del sitio *</span><input type="text" name="nombre_sitio" maxlength="120" required value="<?= cga_h($site['nombre_sitio']) ?>"></label>
                        <label><span>Nombre corto *</span><input type="text" name="nombre_corto" maxlength="80" required value="<?= cga_h($site['nombre_corto']) ?>"></label>
                        <label class="full"><span>Eslogan</span><input type="text" name="eslogan" maxlength="180" value="<?= cga_h($site['eslogan']) ?>"></label>
                    </div>
                    <div class="site-media-grid">
                        <?php foreach ([
                            ['logo_header', 'Logo del encabezado', $site['logo_header'], 'Logo horizontal recomendado.'],
                            ['logo_footer', 'Logo del pie', $site['logo_footer'], 'Puede ser una version clara del logo.'],
                            ['favicon', 'Favicon', $site['favicon'], 'Imagen cuadrada PNG o WEBP.'],
                            ['imagen_compartir', 'Imagen para compartir', $site['imagen_compartir'], 'Vista previa en redes y mensajeria.'],
                        ] as $media): ?>
                            <label class="site-media-field">
                                <span><?= cga_h($media[1]) ?></span>
                                <span class="site-media-preview"><img src="<?= cga_h(sg_site_asset($media[2])) ?>" alt="" data-preview-for="<?= cga_h($media[0]) ?>"></span>
                                <input type="file" name="<?= cga_h($media[0]) ?>" accept="image/jpeg,image/png,image/webp" data-image-input="<?= cga_h($media[0]) ?>">
                                <small><?= cga_h($media[3]) ?> Deja vacio para conservar la actual.</small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>

            <details class="site-config-section">
                <summary>
                    <span class="section-icon"><i data-feather="search"></i></span>
                    <span><strong>SEO y presentacion externa</strong><small>Informacion predeterminada para buscadores y enlaces compartidos.</small></span>
                    <i data-feather="chevron-down" class="section-chevron"></i>
                </summary>
                <div class="site-config-section-body">
                    <label><span>Descripcion general *</span><textarea name="meta_descripcion" maxlength="320" rows="4" required><?= cga_h($site['meta_descripcion']) ?></textarea><small>Resume la empresa y sus servicios en aproximadamente 150 caracteres.</small></label>
                </div>
            </details>

            <details class="site-config-section">
                <summary>
                    <span class="section-icon"><i data-feather="menu"></i></span>
                    <span><strong>Encabezado y menu</strong><small>Cambia etiquetas, orden y visibilidad de la navegacion publica.</small></span>
                    <i data-feather="chevron-down" class="section-chevron"></i>
                </summary>
                <div class="site-config-section-body">
                    <label class="login-label-field"><span>Texto del boton de acceso</span><input type="text" name="login_texto" maxlength="60" value="<?= cga_h($site['login_texto']) ?>"></label>
                    <div class="site-menu-list">
                        <?php foreach ($menu as $item): ?>
                            <article class="site-menu-item <?= !empty($item['parent_codigo']) ? 'is-child' : '' ?>">
                                <i data-feather="<?= cga_h($item['icono']) ?>"></i>
                                <div><strong><?= cga_h($item['codigo']) ?></strong><small><?= cga_h($item['ruta']) ?></small></div>
                                <label><span>Etiqueta</span><input type="text" name="menu[<?= cga_h($item['codigo']) ?>][etiqueta]" maxlength="80" required value="<?= cga_h($item['etiqueta']) ?>"></label>
                                <label class="order-field"><span>Orden</span><input type="number" name="menu[<?= cga_h($item['codigo']) ?>][orden]" min="0" max="999" value="<?= (int) $item['orden'] ?>"></label>
                                <label class="visible-field"><input type="checkbox" name="menu[<?= cga_h($item['codigo']) ?>][activo]" value="1" <?= (int) $item['activo'] === 1 ? 'checked' : '' ?>><span>Visible</span></label>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>

            <details class="site-config-section">
                <summary>
                    <span class="section-icon"><i data-feather="columns"></i></span>
                    <span><strong>Pie de pagina</strong><small>Descripcion, encabezados, derechos y datos reutilizados de Contacto.</small></span>
                    <i data-feather="chevron-down" class="section-chevron"></i>
                </summary>
                <div class="site-config-section-body">
                    <label><span>Descripcion institucional *</span><textarea name="footer_descripcion" maxlength="800" rows="4" required><?= cga_h($site['footer_descripcion']) ?></textarea></label>
                    <div class="site-config-grid two">
                        <label><span>Titulo de enlaces</span><input type="text" name="footer_enlaces_titulo" maxlength="80" value="<?= cga_h($site['footer_enlaces_titulo']) ?>"></label>
                        <label><span>Titulo de contacto</span><input type="text" name="footer_contacto_titulo" maxlength="80" value="<?= cga_h($site['footer_contacto_titulo']) ?>"></label>
                        <label class="full"><span>Copyright *</span><input type="text" name="footer_copyright" maxlength="255" required value="<?= cga_h($site['footer_copyright']) ?>"></label>
                    </div>
                    <div class="contact-source-note"><i data-feather="info"></i><span>Telefono, correo, ubicacion y redes se toman de <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_contacto.php">Mantenimiento Contacto</a> para evitar datos duplicados.</span></div>
                </div>
            </details>

            <div class="site-config-savebar">
                <span>Los cambios afectaran todas las pantallas publicas.</span>
                <button type="submit"><i data-feather="save"></i>Guardar configuracion</button>
            </div>
        </form>
    </div>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
