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
$editable = sg_has_permission_action($conn, 'interfaz.configuracion_general', 'editar');
$puedePanelLateral = sg_has_permission($conn, 'panel.menu_panel');
$secciones = sg_site_admin_sections();
$seccion = (string) ($_GET['seccion'] ?? '');
if ($seccion !== '' && !isset($secciones[$seccion])) {
    http_response_code(404);
    exit('Seccion no encontrada.');
}

function cga_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Configuracion General';
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
                <h1><?= $seccion === '' ? 'Configuracion general' : cga_h($secciones[$seccion][0]) ?></h1>
                <p><?= $seccion === '' ? 'Selecciona el area que deseas administrar.' : cga_h($secciones[$seccion][1]) ?></p>
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

        <?php if ($seccion === ''): ?>
        <?php
        // Resumen del estado actual de cada area, para verlo sin tener que entrar.
        $menuVisibles = count(array_filter($menu, static fn(array $item): bool => (int) ($item['activo'] ?? 0) === 1));
        $bloquesPie = array_filter(array_keys($site), static fn(string $campo): bool => str_starts_with($campo, 'footer_mostrar_'));
        $bloquesPieVisibles = count(array_filter($bloquesPie, static fn(string $campo): bool => (int) $site[$campo] === 1));
        $textosLegales = ['consentimiento_informado_texto', 'rgpd_texto', 'politica_privacidad_texto'];
        $textosLegalesListos = count(array_filter($textosLegales, static fn(string $campo): bool => trim((string) ($site[$campo] ?? '')) !== ''));
        $nombreSitio = trim((string) ($site['nombre_sitio'] ?? ''));
        $tieneDescripcionSeo = trim((string) ($site['meta_descripcion'] ?? '')) !== '';
        $estadoAreas = [
            'identidad' => [$nombreSitio !== '' ? $nombreSitio : 'Falta el nombre del sitio', $nombreSitio !== ''],
            'seo' => [$tieneDescripcionSeo ? 'Descripcion para buscadores lista' : 'Falta la descripcion para buscadores', $tieneDescripcionSeo],
            'menu' => [$menuVisibles . ' de ' . count($menu) . ' enlaces visibles', $menuVisibles > 0],
            'pie' => [$bloquesPieVisibles . ' de ' . count($bloquesPie) . ' bloques visibles', $bloquesPieVisibles > 0],
            'privacidad' => [$textosLegalesListos . ' de ' . count($textosLegales) . ' textos completos', $textosLegalesListos === count($textosLegales)],
        ];
        ?>
        <div class="site-config-hub" role="navigation" aria-label="Areas de configuracion">
            <?php foreach ($secciones as $codigo => [$titulo, $descripcion, $icono]): ?>
                <?php [$estadoTexto, $estadoCompleto] = $estadoAreas[$codigo] ?? ['', true]; ?>
                <a class="site-config-hub-link" href="<?= BASE_URL ?>admin/configuracion?seccion=<?= cga_h($codigo) ?>">
                    <span class="section-icon"><i data-feather="<?= cga_h($icono) ?>"></i></span>
                    <span class="site-config-hub-text"><strong><?= cga_h($titulo) ?></strong><small><?= cga_h($descripcion) ?></small></span>
                    <span class="site-config-hub-foot">
                        <span class="site-config-hub-status <?= $estadoCompleto ? 'is-ok' : 'is-pending' ?>"><?= cga_h($estadoTexto) ?></span>
                        <span class="site-config-hub-open">Abrir <i data-feather="arrow-right"></i></span>
                    </span>
                </a>
            <?php endforeach; ?>
            <?php if ($puedePanelLateral): ?>
                <a class="site-config-hub-link" href="<?= BASE_URL ?>admin/menu-panel">
                    <span class="section-icon"><i data-feather="sidebar"></i></span>
                    <span class="site-config-hub-text"><strong>Panel lateral</strong><small>Organiza las secciones, subsecciones y modulos del panel administrativo.</small></span>
                    <span class="site-config-hub-foot">
                        <span class="site-config-hub-status">Menu del panel administrativo</span>
                        <span class="site-config-hub-open">Abrir <i data-feather="arrow-right"></i></span>
                    </span>
                </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php sg_site_admin_subnav($seccion, true, $puedePanelLateral); ?>
        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_configuracion_sitio.php" enctype="multipart/form-data" class="site-config-form">
            <?= csrf_field() ?>
            <input type="hidden" name="seccion" value="<?= cga_h($seccion) ?>">

            <?php if ($seccion === 'identidad'): ?>
            <section class="site-config-section">
                <div class="site-config-section-heading">
                    <span class="section-icon"><i data-feather="flag"></i></span>
                    <span><strong>Identidad de la plataforma</strong><small>Nombre, eslogan, logos e icono del sitio.</small></span>
                </div>
                <div class="site-config-section-body">
                    <div class="site-config-grid two">
                        <label><span>Nombre del sitio *</span><input type="text" name="nombre_sitio" maxlength="120" required value="<?= cga_h($site['nombre_sitio']) ?>"></label>
                        <label><span>Nombre corto *</span><input type="text" name="nombre_corto" maxlength="80" required value="<?= cga_h($site['nombre_corto']) ?>"></label>
                        <label class="full"><span>Eslogan</span><input type="text" name="eslogan" maxlength="180" value="<?= cga_h($site['eslogan']) ?>"></label>
                    </div>
                    <div class="site-config-subhead">
                        <strong>Imagenes de la marca</strong>
                        <small>Se usan en el encabezado, el pie, la pestana del navegador y al compartir enlaces.</small>
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
            </section>
            <?php endif; ?>

            <?php if ($seccion === 'seo'): ?>
            <section class="site-config-section">
                <div class="site-config-section-heading">
                    <span class="section-icon"><i data-feather="search"></i></span>
                    <span><strong>SEO y presentacion externa</strong><small>Informacion predeterminada para buscadores y enlaces compartidos.</small></span>
                </div>
                <div class="site-config-section-body">
                    <label><span>Descripcion general *</span><textarea name="meta_descripcion" maxlength="320" rows="4" required><?= cga_h($site['meta_descripcion']) ?></textarea><small>Resume la empresa y sus servicios en aproximadamente 150 caracteres.</small></label>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($seccion === 'menu'): ?>
            <section class="site-config-section">
                <div class="site-config-section-heading">
                    <span class="section-icon"><i data-feather="menu"></i></span>
                    <span><strong>Encabezado y menu</strong><small>Cambia etiquetas, orden y visibilidad de la navegacion publica.</small></span>
                </div>
                <div class="site-config-section-body">
                    <label class="login-label-field"><span>Texto del boton de acceso</span><input type="text" name="login_texto" maxlength="60" value="<?= cga_h($site['login_texto']) ?>"></label>
                    <div class="site-config-subhead">
                        <strong>Enlaces del menu</strong>
                        <small>Cambia el texto, el orden y que enlaces se muestran en la navegacion publica.</small>
                    </div>
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
            </section>
            <?php endif; ?>

            <?php if ($seccion === 'pie'): ?>
            <section class="site-config-section">
                <div class="site-config-section-heading">
                    <span class="section-icon"><i data-feather="columns"></i></span>
                    <span><strong>Pie de pagina</strong><small>Descripcion, encabezados, derechos y datos reutilizados de Contacto.</small></span>
                </div>
                <div class="site-config-section-body">
                    <label><span>Descripcion institucional *</span><textarea name="footer_descripcion" maxlength="800" rows="4" required><?= cga_h($site['footer_descripcion']) ?></textarea></label>
                    <div class="site-config-grid two">
                        <label><span>Titulo de enlaces</span><input type="text" name="footer_enlaces_titulo" maxlength="80" value="<?= cga_h($site['footer_enlaces_titulo']) ?>"></label>
                        <label><span>Titulo de contacto</span><input type="text" name="footer_contacto_titulo" maxlength="80" value="<?= cga_h($site['footer_contacto_titulo']) ?>"></label>
                        <label class="full"><span>Copyright *</span><input type="text" name="footer_copyright" maxlength="255" required value="<?= cga_h($site['footer_copyright']) ?>"></label>
                    </div>
                    <div class="site-config-visibility">
                        <div class="site-config-subhead">
                            <strong>Elementos visibles</strong>
                            <small>Selecciona que partes deseas mostrar en todas las paginas publicas.</small>
                        </div>
                        <div class="site-config-checklist">
                            <?php foreach ([
                                ['Identidad', [
                                    ['footer_mostrar_identidad', 'Bloque de identidad', 'Nombre y descripcion institucional'],
                                    ['footer_mostrar_logo', 'Logo', 'Imagen del pie de pagina'],
                                    ['footer_mostrar_descripcion', 'Descripcion', 'Texto institucional'],
                                ]],
                                ['Navegacion', [
                                    ['footer_mostrar_enlaces', 'Enlaces', 'Columna de navegacion publica'],
                                ]],
                                ['Contacto', [
                                    ['footer_mostrar_contacto', 'Bloque de contacto', 'Titulo y datos de contacto'],
                                    ['footer_mostrar_ubicacion', 'Ubicacion', 'Direccion o pais'],
                                    ['footer_mostrar_telefono', 'Telefono', 'Numero telefonico visible'],
                                    ['footer_mostrar_email', 'Correo', 'Correo electronico visible'],
                                    ['footer_mostrar_whatsapp', 'WhatsApp', 'Acceso directo a WhatsApp'],
                                    ['footer_mostrar_instagram', 'Instagram', 'Red social de contacto'],
                                ]],
                                ['Pie legal', [
                                    ['footer_mostrar_copyright', 'Copyright', 'Ano, nombre corto y derechos reservados'],
                                ]],
                            ] as [$groupTitle, $items]): ?>
                                <div class="site-config-checkgroup">
                                    <strong class="site-config-checkgroup-title"><?= cga_h($groupTitle) ?></strong>
                                    <?php foreach ($items as [$key, $title, $description]): ?>
                                        <label class="site-config-checkitem">
                                            <input type="checkbox" name="<?= cga_h($key) ?>" value="1" <?= !empty($site[$key]) ? 'checked' : '' ?>>
                                            <span><strong><?= cga_h($title) ?></strong><small><?= cga_h($description) ?></small></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="contact-source-note"><i data-feather="info"></i><span>Telefono, correo, ubicacion y redes se toman de <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_contacto.php">Mantenimiento Contacto</a> para evitar datos duplicados.</span></div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($seccion === 'privacidad'): ?>
            <section class="site-config-section">
                <div class="site-config-section-heading">
                    <span class="section-icon"><i data-feather="file-text"></i></span>
                    <span><strong>Consentimientos y privacidad</strong><small>Textos legales que debe aceptar cada participante al registrarse.</small></span>
                </div>
                <div class="site-config-section-body legal-config-fields">
                    <label>
                        <span>Consentimiento informado *</span>
                        <textarea name="consentimiento_informado_texto" maxlength="10000" rows="10" required><?= cga_h($site['consentimiento_informado_texto']) ?></textarea>
                        <small>Describe los riesgos, responsabilidades y autorizaciones relacionados con la actividad.</small>
                    </label>
                    <label>
                        <span>Acuerdo RGPD *</span>
                        <textarea name="rgpd_texto" maxlength="2000" rows="5" required><?= cga_h($site['rgpd_texto']) ?></textarea>
                        <small>Texto breve mostrado junto a la casilla de aceptación de datos.</small>
                    </label>
                    <label>
                        <span>Política de privacidad *</span>
                        <textarea name="politica_privacidad_texto" maxlength="10000" rows="10" required><?= cga_h($site['politica_privacidad_texto']) ?></textarea>
                        <small>Explica el uso, conservación y protección de la información del participante.</small>
                    </label>
                </div>
            </section>
            <?php endif; ?>

            <div class="site-config-savebar">
                <span>Los cambios afectaran todas las pantallas publicas.</span>
                <?php if ($editable): ?><button type="submit"><i data-feather="save"></i>Guardar <?= cga_h($secciones[$seccion][0]) ?></button><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
