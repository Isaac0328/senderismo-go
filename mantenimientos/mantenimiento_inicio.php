<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Mantenimiento Inicio | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/inicio_admin.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

function inicio_admin_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function inicio_admin_url(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    if ($ruta === '') {
        return BASE_URL . 'imagenes/paisajes/hero.jpg';
    }
    if (str_starts_with($ruta, '#')) {
        return $ruta;
    }
    if (preg_match('/^https?:\/\//i', $ruta)) {
        return $ruta;
    }
    return BASE_URL . ltrim($ruta, '/');
}

function inicio_admin_crear_tablas(mysqli $conn): void
{
    // La estructura se gestiona desde scripts_bd/migracion_estructura_configuracion_2026_06_17.sql.
}

inicio_admin_crear_tablas($conn);

$inicio = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM configuracion_inicio WHERE id = 1 LIMIT 1")) ?: [];
$tarjetas = [];
$resTarjetas = mysqli_query($conn, "SELECT * FROM inicio_tarjetas ORDER BY orden ASC, id ASC");
if ($resTarjetas) {
    while ($row = mysqli_fetch_assoc($resTarjetas)) {
        $tarjetas[] = $row;
    }
}
$galeria = [];
$resGaleria = mysqli_query($conn, "SELECT * FROM inicio_galeria ORDER BY orden ASC, id ASC");
if ($resGaleria) {
    while ($row = mysqli_fetch_assoc($resGaleria)) {
        $galeria[] = $row;
    }
}

$editTarjetaId = (int) ($_GET['edit_card'] ?? 0);
$editTarjeta = null;
foreach ($tarjetas as $tarjeta) {
    if ((int) $tarjeta['id'] === $editTarjetaId) {
        $editTarjeta = $tarjeta;
        break;
    }
}

$editImagenId = (int) ($_GET['edit_image'] ?? 0);
$editImagen = null;
foreach ($galeria as $imagen) {
    if ((int) $imagen['id'] === $editImagenId) {
        $editImagen = $imagen;
        break;
    }
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="inicio-admin-page">
    <section class="inicio-admin-container">
        <header class="inicio-admin-header">
            <div>
                <span class="inicio-admin-kicker">Contenido publico</span>
                <h1>Mantenimiento Inicio</h1>
                <p>Personaliza la portada, tarjetas, galeria y llamados a la accion de la pagina principal.</p>
            </div>
            <div class="inicio-admin-actions">
                <a href="<?= BASE_URL ?>pantallas/inicio.php" target="_blank">Ver pagina publica</a>
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="soft">Volver al panel</a>
            </div>
        </header>

        <?php if (!empty($_SESSION['inicio_admin_success'])): ?>
            <div class="inicio-alert success"><?= inicio_admin_h($_SESSION['inicio_admin_success']) ?></div>
            <?php unset($_SESSION['inicio_admin_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['inicio_admin_error'])): ?>
            <div class="inicio-alert error"><?= inicio_admin_h($_SESSION['inicio_admin_error']) ?></div>
            <?php unset($_SESSION['inicio_admin_error']); ?>
        <?php endif; ?>

        <section class="inicio-admin-grid">
            <form class="inicio-admin-card inicio-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_inicio.php" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="guardar_config">
                <div class="inicio-card-head">
                    <div>
                        <h2>Contenido principal</h2>
                        <p>Hero, logo, titulos y botones de la portada.</p>
                    </div>
                    <span>Inicio</span>
                </div>

                <div class="form-grid two">
                    <label><span>Titulo hero *</span><input type="text" name="hero_titulo" maxlength="160" required value="<?= inicio_admin_h($inicio['hero_titulo'] ?? '') ?>"></label>
                    <label><span>Subtitulo hero *</span><input type="text" name="hero_subtitulo" maxlength="255" required value="<?= inicio_admin_h($inicio['hero_subtitulo'] ?? '') ?>"></label>
                </div>

                <div class="form-grid two">
                    <label><span>Texto boton principal *</span><input type="text" name="boton_texto" maxlength="80" required value="<?= inicio_admin_h($inicio['boton_texto'] ?? '') ?>"></label>
                    <label><span>URL boton principal *</span><input type="text" name="boton_url" maxlength="255" required value="<?= inicio_admin_h($inicio['boton_url'] ?? '') ?>"></label>
                </div>

                <div class="form-grid three">
                    <label><span>Acceso rapido *</span><input type="text" name="acceso_rapido_texto" maxlength="120" required value="<?= inicio_admin_h($inicio['acceso_rapido_texto'] ?? '') ?>"></label>
                    <label><span>Badge acceso *</span><input type="text" name="acceso_rapido_badge" maxlength="40" required value="<?= inicio_admin_h($inicio['acceso_rapido_badge'] ?? '') ?>"></label>
                    <label><span>URL acceso *</span><input type="text" name="acceso_rapido_url" maxlength="255" required value="<?= inicio_admin_h($inicio['acceso_rapido_url'] ?? '') ?>"></label>
                </div>

                <div class="form-grid two">
                    <label><span>Titulo tarjetas *</span><input type="text" name="porque_titulo" maxlength="160" required value="<?= inicio_admin_h($inicio['porque_titulo'] ?? '') ?>"></label>
                    <label><span>Titulo galeria *</span><input type="text" name="galeria_titulo" maxlength="180" required value="<?= inicio_admin_h($inicio['galeria_titulo'] ?? '') ?>"></label>
                </div>

                <label><span>Subtitulo galeria *</span><input type="text" name="galeria_subtitulo" maxlength="255" required value="<?= inicio_admin_h($inicio['galeria_subtitulo'] ?? '') ?>"></label>

                <div class="form-grid two">
                    <label><span>Titulo CTA *</span><input type="text" name="cta_titulo" maxlength="180" required value="<?= inicio_admin_h($inicio['cta_titulo'] ?? '') ?>"></label>
                    <label><span>Boton CTA *</span><input type="text" name="cta_boton_texto" maxlength="80" required value="<?= inicio_admin_h($inicio['cta_boton_texto'] ?? '') ?>"></label>
                </div>

                <label><span>URL boton CTA *</span><input type="text" name="cta_boton_url" maxlength="255" required value="<?= inicio_admin_h($inicio['cta_boton_url'] ?? '') ?>"></label>
                <label><span>Texto CTA *</span><textarea name="cta_texto" maxlength="1200" rows="4" required><?= inicio_admin_h($inicio['cta_texto'] ?? '') ?></textarea></label>

                <div class="form-grid two">
                    <label><span>Imagen hero</span><input type="file" name="hero_imagen" accept="image/jpeg,image/png,image/webp"><small>Deja vacio para conservar la actual.</small></label>
                    <label><span>Logo hero</span><input type="file" name="logo_imagen" accept="image/jpeg,image/png,image/webp"><small>Deja vacio para conservar el actual.</small></label>
                </div>

                <button type="submit" class="inicio-submit"><i data-feather="save"></i>Guardar contenido</button>
            </form>

            <aside class="inicio-admin-card preview-card">
                <div class="inicio-card-head"><div><h2>Bosquejo</h2><p>Vista rapida del inicio.</p></div></div>
                <div class="home-preview">
                    <img src="<?= inicio_admin_url($inicio['hero_imagen'] ?? '') ?>" alt="Hero">
                    <div>
                        <img src="<?= inicio_admin_url($inicio['logo_imagen'] ?? '') ?>" alt="Logo">
                        <h3><?= inicio_admin_h($inicio['hero_titulo'] ?? '') ?></h3>
                        <p><?= inicio_admin_h($inicio['hero_subtitulo'] ?? '') ?></p>
                    </div>
                </div>
            </aside>
        </section>

        <section class="inicio-admin-grid">
            <form class="inicio-admin-card inicio-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_inicio.php">
                <input type="hidden" name="accion" value="guardar_tarjeta">
                <input type="hidden" name="id" value="<?= (int) ($editTarjeta['id'] ?? 0) ?>">
                <div class="inicio-card-head">
                    <div>
                        <h2><?= $editTarjeta ? 'Editar tarjeta' : 'Agregar tarjeta' ?></h2>
                        <p>Iconos de Feather. Ej: map, users, image, compass, bike, camera.</p>
                    </div>
                    <span><?= count($tarjetas) ?> tarjetas</span>
                </div>
                <div class="form-grid two">
                    <label><span>Icono *</span><input type="text" name="icono" maxlength="60" required value="<?= inicio_admin_h($editTarjeta['icono'] ?? 'map') ?>"></label>
                    <label><span>Orden *</span><input type="number" name="orden" required value="<?= (int) ($editTarjeta['orden'] ?? (count($tarjetas) + 1)) ?>"></label>
                </div>
                <label><span>Titulo *</span><input type="text" name="titulo" maxlength="160" required value="<?= inicio_admin_h($editTarjeta['titulo'] ?? '') ?>"></label>
                <label><span>Descripcion *</span><textarea name="descripcion" rows="4" required><?= inicio_admin_h($editTarjeta['descripcion'] ?? '') ?></textarea></label>
                <label class="check-row"><input type="checkbox" name="activo" value="1" <?= !isset($editTarjeta['activo']) || (int) $editTarjeta['activo'] === 1 ? 'checked' : '' ?>> Activa</label>
                <button type="submit" class="inicio-submit"><i data-feather="save"></i><?= $editTarjeta ? 'Actualizar tarjeta' : 'Guardar tarjeta' ?></button>
                <?php if ($editTarjeta): ?><a class="inicio-clear" href="<?= BASE_URL ?>mantenimientos/mantenimiento_inicio.php">Nueva tarjeta</a><?php endif; ?>
            </form>

            <div class="inicio-admin-card">
                <div class="inicio-card-head"><div><h2>Tarjetas actuales</h2><p>Activa, inactiva o elimina tarjetas.</p></div></div>
                <div class="admin-list">
                    <?php foreach ($tarjetas as $tarjeta): ?>
                        <article>
                            <i data-feather="<?= inicio_admin_h($tarjeta['icono']) ?>"></i>
                            <div><strong><?= inicio_admin_h($tarjeta['titulo']) ?></strong><span><?= (int) $tarjeta['activo'] === 1 ? 'Activa' : 'Inactiva' ?> / Orden <?= (int) $tarjeta['orden'] ?></span></div>
                            <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_inicio.php?edit_card=<?= (int) $tarjeta['id'] ?>">Editar</a>
                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_inicio.php">
                                <input type="hidden" name="accion" value="toggle_tarjeta"><input type="hidden" name="id" value="<?= (int) $tarjeta['id'] ?>">
                                <button type="submit"><?= (int) $tarjeta['activo'] === 1 ? 'Inactivar' : 'Activar' ?></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="inicio-admin-grid">
            <form class="inicio-admin-card inicio-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_inicio.php" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="guardar_imagen">
                <input type="hidden" name="id" value="<?= (int) ($editImagen['id'] ?? 0) ?>">
                <div class="inicio-card-head">
                    <div><h2><?= $editImagen ? 'Editar imagen' : 'Agregar imagen' ?></h2><p>Cuadritos de imagen de la seccion de paisajes.</p></div>
                    <span><?= count($galeria) ?> imagenes</span>
                </div>
                <div class="form-grid two">
                    <label><span>Titulo</span><input type="text" name="titulo" maxlength="160" value="<?= inicio_admin_h($editImagen['titulo'] ?? '') ?>"></label>
                    <label><span>Orden *</span><input type="number" name="orden" required value="<?= (int) ($editImagen['orden'] ?? (count($galeria) + 1)) ?>"></label>
                </div>
                <label><span>Imagen <?= $editImagen ? '' : '*' ?></span><input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" <?= $editImagen ? '' : 'required' ?>><small>JPG, PNG o WEBP. Maximo 4 MB.</small></label>
                <label class="check-row"><input type="checkbox" name="activo" value="1" <?= !isset($editImagen['activo']) || (int) $editImagen['activo'] === 1 ? 'checked' : '' ?>> Activa</label>
                <button type="submit" class="inicio-submit"><i data-feather="image"></i><?= $editImagen ? 'Actualizar imagen' : 'Guardar imagen' ?></button>
                <?php if ($editImagen): ?><a class="inicio-clear" href="<?= BASE_URL ?>mantenimientos/mantenimiento_inicio.php">Nueva imagen</a><?php endif; ?>
            </form>

            <div class="inicio-admin-card">
                <div class="inicio-card-head"><div><h2>Galeria actual</h2><p>Imagenes visibles en la pagina de inicio.</p></div></div>
                <div class="gallery-admin-list">
                    <?php foreach ($galeria as $imagen): ?>
                        <article>
                            <img src="<?= inicio_admin_url($imagen['imagen']) ?>" alt="<?= inicio_admin_h($imagen['titulo'] ?? '') ?>">
                            <div><strong><?= inicio_admin_h($imagen['titulo'] ?: 'Sin titulo') ?></strong><span><?= (int) $imagen['activo'] === 1 ? 'Activa' : 'Inactiva' ?> / Orden <?= (int) $imagen['orden'] ?></span></div>
                            <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_inicio.php?edit_image=<?= (int) $imagen['id'] ?>">Editar</a>
                            <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_inicio.php">
                                <input type="hidden" name="accion" value="toggle_imagen"><input type="hidden" name="id" value="<?= (int) $imagen['id'] ?>">
                                <button type="submit"><?= (int) $imagen['activo'] === 1 ? 'Inactivar' : 'Activar' ?></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </section>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>

