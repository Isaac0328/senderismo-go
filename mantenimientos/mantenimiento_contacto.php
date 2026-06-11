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

function contacto_crear_tablas(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS configuracion_contacto (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
            titulo VARCHAR(160) NOT NULL,
            subtitulo VARCHAR(255) NOT NULL,
            hero_boton_texto VARCHAR(80) NOT NULL DEFAULT 'Escribir mensaje',
            hero_whatsapp_texto VARCHAR(80) NOT NULL DEFAULT 'WhatsApp',
            horario VARCHAR(160) NOT NULL,
            ubicacion VARCHAR(160) NOT NULL,
            telefono VARCHAR(40) NOT NULL,
            whatsapp VARCHAR(40) NOT NULL,
            email VARCHAR(160) NOT NULL,
            instagram VARCHAR(80) NOT NULL,
            instagram_url VARCHAR(255) NOT NULL,
            seccion_kicker VARCHAR(80) NOT NULL DEFAULT 'Atencion personalizada',
            seccion_titulo VARCHAR(160) NOT NULL DEFAULT 'Estamos listos para orientarte.',
            texto_formulario TEXT NOT NULL,
            nota_contacto TEXT NULL,
            form_kicker VARCHAR(80) NOT NULL DEFAULT 'Mensaje rapido',
            form_titulo VARCHAR(120) NOT NULL DEFAULT 'Escribenos',
            form_subtitulo VARCHAR(255) NOT NULL DEFAULT 'Completa estos datos y nos pondremos en contacto contigo.',
            form_privacidad VARCHAR(255) NOT NULL DEFAULT 'Usaremos tu informacion solo para responder esta solicitud.',
            boton_formulario VARCHAR(80) NOT NULL DEFAULT 'Enviar mensaje',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $columnas = [
        "hero_boton_texto VARCHAR(80) NOT NULL DEFAULT 'Escribir mensaje'",
        "hero_whatsapp_texto VARCHAR(80) NOT NULL DEFAULT 'WhatsApp'",
        "seccion_kicker VARCHAR(80) NOT NULL DEFAULT 'Atencion personalizada'",
        "seccion_titulo VARCHAR(160) NOT NULL DEFAULT 'Estamos listos para orientarte.'",
        "nota_contacto TEXT NULL",
        "form_kicker VARCHAR(80) NOT NULL DEFAULT 'Mensaje rapido'",
        "form_titulo VARCHAR(120) NOT NULL DEFAULT 'Escribenos'",
        "form_subtitulo VARCHAR(255) NOT NULL DEFAULT 'Completa estos datos y nos pondremos en contacto contigo.'",
        "form_privacidad VARCHAR(255) NOT NULL DEFAULT 'Usaremos tu informacion solo para responder esta solicitud.'",
        "boton_formulario VARCHAR(80) NOT NULL DEFAULT 'Enviar mensaje'",
    ];
    foreach ($columnas as $definicion) {
        $nombre = strtok($definicion, ' ');
        $existe = mysqli_query($conn, "SHOW COLUMNS FROM configuracion_contacto LIKE '" . mysqli_real_escape_string($conn, $nombre) . "'");
        if ($existe && mysqli_num_rows($existe) === 0) {
            mysqli_query($conn, "ALTER TABLE configuracion_contacto ADD COLUMN {$definicion}");
        }
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS contacto_bloques (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            grupo VARCHAR(30) NOT NULL,
            icono VARCHAR(60) NOT NULL DEFAULT 'circle',
            titulo VARCHAR(120) NOT NULL,
            texto VARCHAR(255) NOT NULL,
            url VARCHAR(255) DEFAULT NULL,
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_contacto_bloques_grupo (grupo, activo, orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function contacto_sembrar_bloques(mysqli $conn): void
{
    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM contacto_bloques");
    $total = ($res && ($row = mysqli_fetch_assoc($res))) ? (int) $row['total'] : 0;
    if ($total > 0) {
        return;
    }

    $bloques = [
        ['resumen', 'clock', 'Horario de respuesta', 'Lunes a viernes, 8:00 a.m. - 6:00 p.m.', '', 1, 1],
        ['resumen', 'map-pin', 'Ubicacion', 'Republica Dominicana', '', 2, 1],
        ['resumen', 'mail', 'Correo', 'info@senderismogo.com', '', 3, 1],
        ['canal', 'phone', 'WhatsApp directo', '+1 (849) 472-1200', 'https://wa.me/18494721200?text=Hola%20Senderismo%20Go,%20quiero%20coordinar%20una%20ruta.', 1, 1],
        ['canal', 'mail', 'Correo electronico', 'info@senderismogo.com', 'mailto:info@senderismogo.com', 2, 1],
        ['canal', 'instagram', 'Instagram', '@senderismogo', 'https://www.instagram.com/senderismogo', 3, 1],
    ];

    $stmt = mysqli_prepare($conn, "INSERT INTO contacto_bloques (grupo, icono, titulo, texto, url, orden, activo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($bloques as $bloque) {
        mysqli_stmt_bind_param($stmt, 'sssssii', $bloque[0], $bloque[1], $bloque[2], $bloque[3], $bloque[4], $bloque[5], $bloque[6]);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

function hc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

contacto_crear_tablas($conn);
contacto_sembrar_bloques($conn);

$contacto = [
    'hero_imagen' => 'imagenes/paisajes/hero.jpg',
    'titulo' => 'Hablemos de tu proxima ruta',
    'subtitulo' => 'Reserva experiencias privadas, pregunta por nuestros proximos senderos o coordinemos una aventura para tu grupo.',
    'hero_boton_texto' => 'Escribir mensaje',
    'hero_whatsapp_texto' => 'WhatsApp',
    'horario' => 'Lunes a viernes, 8:00 a.m. - 6:00 p.m.',
    'ubicacion' => 'Republica Dominicana',
    'telefono' => '+1 (849) 472-1200',
    'whatsapp' => '18494721200',
    'email' => 'info@senderismogo.com',
    'instagram' => '@senderismogo',
    'instagram_url' => 'https://www.instagram.com/senderismogo',
    'seccion_kicker' => 'Atencion personalizada',
    'seccion_titulo' => 'Estamos listos para orientarte.',
    'texto_formulario' => 'Cuentanos que necesitas y te responderemos con orientacion clara para que puedas planificar con confianza.',
    'nota_contacto' => 'Tambien puedes escribirnos para rutas privadas, actividades corporativas, grupos familiares o recomendaciones de dificultad.',
    'form_kicker' => 'Mensaje rapido',
    'form_titulo' => 'Escribenos',
    'form_subtitulo' => 'Completa estos datos y nos pondremos en contacto contigo.',
    'form_privacidad' => 'Usaremos tu informacion solo para responder esta solicitud.',
    'boton_formulario' => 'Enviar mensaje',
];

$res = mysqli_query($conn, "SELECT * FROM configuracion_contacto WHERE id = 1 LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    foreach ($contacto as $campo => $valor) {
        if (isset($row[$campo]) && trim((string) $row[$campo]) !== '') {
            $contacto[$campo] = $row[$campo];
        }
    }
}

$bloques = [];
$resBloques = mysqli_query($conn, "SELECT * FROM contacto_bloques ORDER BY grupo ASC, orden ASC, id ASC");
if ($resBloques) {
    while ($rowBloque = mysqli_fetch_assoc($resBloques)) {
        $bloques[] = $rowBloque;
    }
}

$bloqueEditar = [
    'id' => 0,
    'grupo' => 'resumen',
    'icono' => 'info',
    'titulo' => '',
    'texto' => '',
    'url' => '',
    'orden' => 0,
    'activo' => 1,
];
$editarId = max(0, (int) ($_GET['bloque_id'] ?? 0));
if ($editarId > 0) {
    foreach ($bloques as $bloque) {
        if ((int) $bloque['id'] === $editarId) {
            $bloqueEditar = $bloque;
            break;
        }
    }
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
                <p>Personaliza la pagina de contacto, sus textos, canales visibles y accesos rapidos sin tocar codigo.</p>
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
                <input type="hidden" name="accion" value="guardar_config">
                <div class="contacto-card-head">
                    <div>
                        <h2>Contenido principal</h2>
                        <p>Controla la imagen, textos de la cabecera, formulario y datos base.</p>
                    </div>
                    <span>Editable</span>
                </div>

                <div class="contacto-section-line">Cabecera</div>
                <div class="form-grid two">
                    <label>
                        <span>Titulo principal *</span>
                        <input type="text" name="titulo" maxlength="160" required value="<?= hc($contacto['titulo']) ?>">
                    </label>
                    <label>
                        <span>Imagen principal</span>
                        <input type="file" name="hero_imagen" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG o WEBP. Maximo 4 MB.</small>
                    </label>
                </div>

                <label>
                    <span>Subtitulo *</span>
                    <textarea name="subtitulo" maxlength="255" rows="3" required><?= hc($contacto['subtitulo']) ?></textarea>
                </label>

                <div class="form-grid two">
                    <label>
                        <span>Texto boton formulario *</span>
                        <input type="text" name="hero_boton_texto" maxlength="80" required value="<?= hc($contacto['hero_boton_texto']) ?>">
                    </label>
                    <label>
                        <span>Texto boton WhatsApp *</span>
                        <input type="text" name="hero_whatsapp_texto" maxlength="80" required value="<?= hc($contacto['hero_whatsapp_texto']) ?>">
                    </label>
                </div>

                <div class="contacto-section-line">Datos base</div>
                <div class="form-grid two">
                    <label>
                        <span>Horario *</span>
                        <input type="text" name="horario" maxlength="160" required value="<?= hc($contacto['horario']) ?>">
                    </label>
                    <label>
                        <span>Ubicacion *</span>
                        <input type="text" name="ubicacion" maxlength="160" required value="<?= hc($contacto['ubicacion']) ?>">
                    </label>
                </div>

                <div class="form-grid two">
                    <label>
                        <span>Telefono visible *</span>
                        <input type="text" name="telefono" maxlength="40" required value="<?= hc($contacto['telefono']) ?>">
                    </label>
                    <label>
                        <span>WhatsApp sin simbolos *</span>
                        <input type="text" name="whatsapp" maxlength="40" required value="<?= hc($contacto['whatsapp']) ?>">
                    </label>
                </div>

                <div class="form-grid two">
                    <label>
                        <span>Correo *</span>
                        <input type="email" name="email" maxlength="160" required value="<?= hc($contacto['email']) ?>">
                    </label>
                    <label>
                        <span>Instagram *</span>
                        <input type="text" name="instagram" maxlength="80" required value="<?= hc($contacto['instagram']) ?>">
                    </label>
                </div>

                <label>
                    <span>URL Instagram *</span>
                    <input type="url" name="instagram_url" maxlength="255" required value="<?= hc($contacto['instagram_url']) ?>">
                </label>

                <div class="contacto-section-line">Bloque de mensaje</div>
                <div class="form-grid two">
                    <label>
                        <span>Etiqueta de seccion *</span>
                        <input type="text" name="seccion_kicker" maxlength="80" required value="<?= hc($contacto['seccion_kicker']) ?>">
                    </label>
                    <label>
                        <span>Titulo de seccion *</span>
                        <input type="text" name="seccion_titulo" maxlength="160" required value="<?= hc($contacto['seccion_titulo']) ?>">
                    </label>
                </div>

                <label>
                    <span>Texto informativo *</span>
                    <textarea name="texto_formulario" maxlength="1200" rows="4" required><?= hc($contacto['texto_formulario']) ?></textarea>
                </label>

                <label>
                    <span>Nota inferior *</span>
                    <textarea name="nota_contacto" maxlength="1200" rows="3" required><?= hc($contacto['nota_contacto']) ?></textarea>
                </label>

                <div class="contacto-section-line">Formulario publico</div>
                <div class="form-grid two">
                    <label>
                        <span>Etiqueta formulario *</span>
                        <input type="text" name="form_kicker" maxlength="80" required value="<?= hc($contacto['form_kicker']) ?>">
                    </label>
                    <label>
                        <span>Titulo formulario *</span>
                        <input type="text" name="form_titulo" maxlength="120" required value="<?= hc($contacto['form_titulo']) ?>">
                    </label>
                </div>

                <label>
                    <span>Subtitulo formulario *</span>
                    <input type="text" name="form_subtitulo" maxlength="255" required value="<?= hc($contacto['form_subtitulo']) ?>">
                </label>

                <div class="form-grid two">
                    <label>
                        <span>Texto privacidad *</span>
                        <input type="text" name="form_privacidad" maxlength="255" required value="<?= hc($contacto['form_privacidad']) ?>">
                    </label>
                    <label>
                        <span>Texto boton enviar *</span>
                        <input type="text" name="boton_formulario" maxlength="80" required value="<?= hc($contacto['boton_formulario']) ?>">
                    </label>
                </div>

                <button type="submit" class="contacto-submit">
                    <i data-feather="save"></i>
                    Guardar contenido
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

        <section class="contacto-blocks-layout">
            <form class="contacto-admin-card contacto-admin-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_contacto.php">
                <input type="hidden" name="accion" value="guardar_bloque">
                <input type="hidden" name="bloque_id" value="<?= (int) $bloqueEditar['id'] ?>">
                <div class="contacto-card-head">
                    <div>
                        <h2><?= (int) $bloqueEditar['id'] > 0 ? 'Editar bloque' : 'Nuevo bloque' ?></h2>
                        <p>Agrega tarjetas superiores o canales de contacto para la pagina publica.</p>
                    </div>
                    <span><?= (int) $bloqueEditar['id'] > 0 ? 'Editando' : 'Nuevo' ?></span>
                </div>

                <div class="form-grid two">
                    <label>
                        <span>Grupo *</span>
                        <select name="grupo" required>
                            <option value="resumen" <?= ($bloqueEditar['grupo'] ?? '') === 'resumen' ? 'selected' : '' ?>>Tarjeta superior</option>
                            <option value="canal" <?= ($bloqueEditar['grupo'] ?? '') === 'canal' ? 'selected' : '' ?>>Canal / red</option>
                        </select>
                    </label>
                    <label>
                        <span>Icono Feather *</span>
                        <input type="text" name="icono" maxlength="60" required placeholder="Ej: phone, mail, map-pin" value="<?= hc($bloqueEditar['icono']) ?>">
                    </label>
                </div>

                <div class="form-grid two">
                    <label>
                        <span>Titulo *</span>
                        <input type="text" name="titulo_bloque" maxlength="120" required value="<?= hc($bloqueEditar['titulo']) ?>">
                    </label>
                    <label>
                        <span>Orden *</span>
                        <input type="number" name="orden" min="0" max="999" required value="<?= (int) $bloqueEditar['orden'] ?>">
                    </label>
                </div>

                <label>
                    <span>Texto visible *</span>
                    <input type="text" name="texto_bloque" maxlength="255" required value="<?= hc($bloqueEditar['texto']) ?>">
                </label>

                <label>
                    <span>Enlace</span>
                    <input type="text" name="url" maxlength="255" placeholder="https://... o mailto:correo@dominio.com" value="<?= hc($bloqueEditar['url']) ?>">
                </label>

                <label class="contacto-check">
                    <input type="checkbox" name="activo" value="1" <?= (int) ($bloqueEditar['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span>Activo</span>
                </label>

                <div class="contacto-form-actions">
                    <button type="submit" class="contacto-submit">
                        <i data-feather="save"></i>
                        <?= (int) $bloqueEditar['id'] > 0 ? 'Actualizar bloque' : 'Guardar bloque' ?>
                    </button>
                    <?php if ((int) $bloqueEditar['id'] > 0): ?>
                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_contacto.php" class="contacto-admin-link soft">Nuevo bloque</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="contacto-admin-card contacto-list-card">
                <div class="contacto-card-head">
                    <div>
                        <h2>Bloques registrados</h2>
                        <p>Los activos se muestran automaticamente en la pagina publica.</p>
                    </div>
                    <span><?= count($bloques) ?> registros</span>
                </div>

                <div class="contacto-block-list">
                    <?php foreach ($bloques as $bloque): ?>
                        <article class="contacto-block-item <?= (int) $bloque['activo'] === 1 ? '' : 'inactive' ?>">
                            <div class="contacto-block-icon">
                                <i data-feather="<?= hc($bloque['icono'] ?: 'circle') ?>"></i>
                            </div>
                            <div class="contacto-block-copy">
                                <div>
                                    <strong><?= hc($bloque['titulo']) ?></strong>
                                    <span><?= ($bloque['grupo'] ?? '') === 'resumen' ? 'Tarjeta superior' : 'Canal / red' ?></span>
                                </div>
                                <p><?= hc($bloque['texto']) ?></p>
                                <?php if (!empty($bloque['url'])): ?>
                                    <small><?= hc($bloque['url']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="contacto-block-actions">
                                <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_contacto.php?bloque_id=<?= (int) $bloque['id'] ?>" class="contacto-small-action">Editar</a>
                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_contacto.php">
                                    <input type="hidden" name="accion" value="toggle_bloque">
                                    <input type="hidden" name="bloque_id" value="<?= (int) $bloque['id'] ?>">
                                    <?php if ((int) $bloque['activo'] === 1): ?>
                                        <button type="submit" class="contacto-small-action warn">Inactivar</button>
                                    <?php else: ?>
                                        <input type="hidden" name="activo" value="1">
                                        <button type="submit" class="contacto-small-action ok">Activar</button>
                                    <?php endif; ?>
                                </form>
                                <?php if ((int) $bloque['activo'] === 0): ?>
                                    <form method="POST" action="<?= BASE_URL ?>procesos/proceso_mantenimiento_contacto.php" onsubmit="return confirm('Deseas eliminar este bloque?');">
                                        <input type="hidden" name="accion" value="eliminar_bloque">
                                        <input type="hidden" name="bloque_id" value="<?= (int) $bloque['id'] ?>">
                                        <button type="submit" class="contacto-small-action danger">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
