<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Mantenimiento Contacto | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contacto_admin.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

$sqlTabla = "
    CREATE TABLE IF NOT EXISTS configuracion_contacto (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
        titulo VARCHAR(160) NOT NULL,
        subtitulo VARCHAR(255) NOT NULL,
        horario VARCHAR(160) NOT NULL,
        ubicacion VARCHAR(160) NOT NULL,
        telefono VARCHAR(40) NOT NULL,
        whatsapp VARCHAR(40) NOT NULL,
        email VARCHAR(160) NOT NULL,
        instagram VARCHAR(80) NOT NULL,
        instagram_url VARCHAR(255) NOT NULL,
        texto_formulario TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
mysqli_query($conn, $sqlTabla);

$contacto = [
    'hero_imagen' => 'imagenes/paisajes/hero.jpg',
    'titulo' => 'Hablemos de tu proxima ruta',
    'subtitulo' => 'Reserva experiencias privadas, pregunta por nuestros proximos senderos o coordinemos una aventura para tu grupo.',
    'horario' => 'Lunes a viernes, 8:00 a.m. - 6:00 p.m.',
    'ubicacion' => 'Republica Dominicana',
    'telefono' => '+1 (849) 472-1200',
    'whatsapp' => '18494721200',
    'email' => 'info@senderismogo.com',
    'instagram' => '@senderismogo',
    'instagram_url' => 'https://www.instagram.com/senderismogo',
    'texto_formulario' => 'Cuentanos que necesitas y te responderemos con orientacion clara para que puedas planificar con confianza.',
];

$res = mysqli_query($conn, "SELECT * FROM configuracion_contacto WHERE id = 1 LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    foreach ($contacto as $campo => $valor) {
        if (isset($row[$campo]) && trim((string) $row[$campo]) !== '') {
            $contacto[$campo] = $row[$campo];
        }
    }
}

function hc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="contacto-admin-page">
    <div class="contacto-admin-container">
        <header class="contacto-admin-header">
            <div>
                <span class="contacto-admin-kicker">Contenido publico</span>
                <h1>Mantenimiento Contacto</h1>
                <p>Actualiza los datos, redes, telefono, correo, ubicacion e imagen principal de la pagina de contacto.</p>
            </div>
            <div class="contacto-admin-actions">
                <a href="<?= BASE_URL ?>pantallas/contacto.php" target="_blank" class="contacto-admin-link">Ver pagina publica</a>
                <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="contacto-admin-link soft">Volver al panel</a>
            </div>
        </header>

        <?php if (!empty($_SESSION['contacto_admin_success'])): ?>
            <div class="contacto-alert success"><?= hc($_SESSION['contacto_admin_success']) ?></div>
            <?php unset($_SESSION['contacto_admin_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['contacto_admin_error'])): ?>
            <div class="contacto-alert error"><?= hc($_SESSION['contacto_admin_error']) ?></div>
            <?php unset($_SESSION['contacto_admin_error']); ?>
        <?php endif; ?>

        <section class="contacto-admin-grid">
            <form class="contacto-admin-card contacto-admin-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_contacto.php" enctype="multipart/form-data">
                <div class="contacto-card-head">
                    <div>
                        <h2>Datos de contacto</h2>
                        <p>Estos valores se muestran directamente al visitante.</p>
                    </div>
                    <span>Editable</span>
                </div>

                <div class="form-grid two">
                    <label>
                        <span>Titulo principal *</span>
                        <input type="text" name="titulo" maxlength="160" required value="<?= hc($contacto['titulo']) ?>">
                    </label>
                    <label>
                        <span>Horario *</span>
                        <input type="text" name="horario" maxlength="160" required value="<?= hc($contacto['horario']) ?>">
                    </label>
                </div>

                <label>
                    <span>Subtitulo *</span>
                    <textarea name="subtitulo" maxlength="255" rows="3" required><?= hc($contacto['subtitulo']) ?></textarea>
                </label>

                <div class="form-grid two">
                    <label>
                        <span>Ubicacion *</span>
                        <input type="text" name="ubicacion" maxlength="160" required value="<?= hc($contacto['ubicacion']) ?>">
                    </label>
                    <label>
                        <span>Telefono visible *</span>
                        <input type="text" name="telefono" maxlength="40" required value="<?= hc($contacto['telefono']) ?>">
                    </label>
                </div>

                <div class="form-grid two">
                    <label>
                        <span>WhatsApp sin simbolos *</span>
                        <input type="text" name="whatsapp" maxlength="40" required value="<?= hc($contacto['whatsapp']) ?>">
                    </label>
                    <label>
                        <span>Correo *</span>
                        <input type="email" name="email" maxlength="160" required value="<?= hc($contacto['email']) ?>">
                    </label>
                </div>

                <div class="form-grid two">
                    <label>
                        <span>Instagram *</span>
                        <input type="text" name="instagram" maxlength="80" required value="<?= hc($contacto['instagram']) ?>">
                    </label>
                    <label>
                        <span>URL Instagram *</span>
                        <input type="url" name="instagram_url" maxlength="255" required value="<?= hc($contacto['instagram_url']) ?>">
                    </label>
                </div>

                <label>
                    <span>Texto del formulario *</span>
                    <textarea name="texto_formulario" maxlength="1200" rows="4" required><?= hc($contacto['texto_formulario']) ?></textarea>
                </label>

                <label>
                    <span>Imagen principal</span>
                    <input type="file" name="hero_imagen" accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG o WEBP. Maximo 4 MB.</small>
                </label>

                <button type="submit" class="contacto-submit">
                    <i data-feather="save"></i>
                    Guardar cambios
                </button>
            </form>

            <aside class="contacto-admin-card preview-card">
                <div class="contacto-card-head">
                    <div>
                        <h2>Vista rapida</h2>
                        <p>Resumen de lo que vera el visitante.</p>
                    </div>
                </div>
                <img src="<?= BASE_URL . hc($contacto['hero_imagen']) ?>" alt="Imagen actual de contacto">
                <div class="preview-copy">
                    <h3><?= hc($contacto['titulo']) ?></h3>
                    <p><?= hc($contacto['subtitulo']) ?></p>
                    <ul>
                        <li><i data-feather="clock"></i><?= hc($contacto['horario']) ?></li>
                        <li><i data-feather="map-pin"></i><?= hc($contacto['ubicacion']) ?></li>
                        <li><i data-feather="phone"></i><?= hc($contacto['telefono']) ?></li>
                        <li><i data-feather="mail"></i><?= hc($contacto['email']) ?></li>
                        <li><i data-feather="instagram"></i><?= hc($contacto['instagram']) ?></li>
                    </ul>
                </div>
            </aside>
        </section>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
