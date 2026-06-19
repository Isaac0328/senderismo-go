<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Contacto | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/contacto.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/contacto.js"
];

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
    'boton_formulario' => 'Enviar mensaje'
];

$connContacto = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$bloquesResumen = [];
$bloquesCanales = [];
if ($connContacto) {
    mysqli_set_charset($connContacto, "utf8mb4");
    $resContacto = mysqli_query($connContacto, "SELECT * FROM configuracion_contacto WHERE id = 1 LIMIT 1");
    if ($resContacto && ($rowContacto = mysqli_fetch_assoc($resContacto))) {
        foreach ($contacto as $campo => $valorPredeterminado) {
            if (isset($rowContacto[$campo]) && trim((string) $rowContacto[$campo]) !== '') {
                $contacto[$campo] = $rowContacto[$campo];
            }
        }
    }
    $resBloques = mysqli_query($connContacto, "SELECT * FROM contacto_bloques WHERE activo = 1 ORDER BY grupo ASC, orden ASC, id ASC");
    if ($resBloques) {
        while ($rowBloque = mysqli_fetch_assoc($resBloques)) {
            if (($rowBloque['grupo'] ?? '') === 'resumen') {
                $bloquesResumen[] = $rowBloque;
            } elseif (($rowBloque['grupo'] ?? '') === 'canal') {
                $bloquesCanales[] = $rowBloque;
            }
        }
    }
    mysqli_close($connContacto);
}

if (!$bloquesResumen) {
    $bloquesResumen = [
        ['icono' => 'clock', 'titulo' => 'Horario de respuesta', 'texto' => $contacto['horario'], 'url' => ''],
        ['icono' => 'map-pin', 'titulo' => 'Ubicacion', 'texto' => $contacto['ubicacion'], 'url' => ''],
        ['icono' => 'mail', 'titulo' => 'Correo', 'texto' => $contacto['email'], 'url' => ''],
    ];
}

if (!$bloquesCanales) {
    $bloquesCanales = [
        ['icono' => 'phone', 'titulo' => 'WhatsApp directo', 'texto' => $contacto['telefono'], 'url' => 'https://wa.me/' . $contacto['whatsapp'] . '?text=Hola%20Senderismo%20Go,%20quiero%20coordinar%20una%20ruta.'],
        ['icono' => 'mail', 'titulo' => 'Correo electronico', 'texto' => $contacto['email'], 'url' => 'mailto:' . $contacto['email']],
        ['icono' => 'instagram', 'titulo' => 'Instagram', 'texto' => $contacto['instagram'], 'url' => $contacto['instagram_url']],
    ];
}

$asuntos = [
    'informacion_ruta' => 'Informacion sobre rutas',
    'servicio_privado' => 'Servicio privado para grupo',
    'proximo_sendero' => 'Proximo sendero',
    'dificultad_equipo' => 'Dificultad o equipo necesario',
    'alianza' => 'Alianza o empresa',
    'otro' => 'Otro'
];

$old = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_old']);

function contacto_valor(array $old, string $campo): string
{
    return htmlspecialchars((string) ($old[$campo] ?? ''), ENT_QUOTES, 'UTF-8');
}

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="contact-page">
    <section class="contact-hero">
        <img src="<?= BASE_URL . $contacto['hero_imagen'] ?>" alt="Paisaje de Senderismo Go" class="contact-hero-img">
        <div class="contact-hero-overlay"></div>

        <div class="contact-hero-content">
            <span class="contact-eyebrow">
                <i data-feather="message-circle"></i>
                Contacto
            </span>
            <h1><?= htmlspecialchars($contacto['titulo']) ?></h1>
            <p><?= htmlspecialchars($contacto['subtitulo']) ?></p>
            <div class="contact-hero-actions">
                <a href="#contacto-form" class="contact-primary-action smooth-scroll">
                    <i data-feather="send"></i>
                    <?= htmlspecialchars($contacto['hero_boton_texto']) ?>
                </a>
                <a href="https://wa.me/<?= htmlspecialchars($contacto['whatsapp']) ?>?text=Hola%20Senderismo%20Go,%20quiero%20informacion%20sobre%20sus%20rutas."
                   target="_blank" rel="noopener noreferrer" class="contact-secondary-action">
                    <i data-feather="phone-call"></i>
                    <?= htmlspecialchars($contacto['hero_whatsapp_texto']) ?>
                </a>
            </div>
        </div>
    </section>

    <main class="contact-main">
        <section class="contact-summary" aria-label="Informacion de contacto">
            <?php foreach ($bloquesResumen as $bloque): ?>
                <article>
                    <span><i data-feather="<?= htmlspecialchars($bloque['icono'] ?: 'circle') ?>"></i></span>
                    <div>
                        <p><?= htmlspecialchars($bloque['titulo']) ?></p>
                        <strong><?= htmlspecialchars($bloque['texto']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section id="contacto-form" class="contact-workspace">
            <div class="contact-panel contact-panel-info">
                <span class="contact-section-kicker"><?= htmlspecialchars($contacto['seccion_kicker']) ?></span>
                <h2><?= htmlspecialchars($contacto['seccion_titulo']) ?></h2>
                <p>
                    <?= htmlspecialchars($contacto['texto_formulario']) ?>
                </p>

                <div class="contact-channel-list">
                    <?php foreach ($bloquesCanales as $bloque): ?>
                        <?php $urlCanal = trim((string) ($bloque['url'] ?? '')); ?>
                        <a href="<?= htmlspecialchars($urlCanal !== '' ? $urlCanal : '#') ?>"
                           <?= $urlCanal !== '' && !str_starts_with($urlCanal, 'mailto:') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                           class="contact-channel">
                            <span class="channel-icon"><i data-feather="<?= htmlspecialchars($bloque['icono'] ?: 'circle') ?>"></i></span>
                            <span>
                                <strong><?= htmlspecialchars($bloque['titulo']) ?></strong>
                                <small><?= htmlspecialchars($bloque['texto']) ?></small>
                            </span>
                            <i data-feather="arrow-up-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="contact-note">
                    <i data-feather="compass"></i>
                    <p><?= htmlspecialchars($contacto['nota_contacto']) ?></p>
                </div>
            </div>

            <div class="contact-panel contact-form-panel">
                <div class="form-heading">
                    <span class="contact-section-kicker"><?= htmlspecialchars($contacto['form_kicker']) ?></span>
                    <h2><?= htmlspecialchars($contacto['form_titulo']) ?></h2>
                    <p><?= htmlspecialchars($contacto['form_subtitulo']) ?></p>
                </div>

                <?php if (!empty($_SESSION['contact_success'])): ?>
                    <div class="contact-alert contact-alert-success">
                        <i data-feather="check-circle"></i>
                        <span><?= htmlspecialchars($_SESSION['contact_success']) ?></span>
                    </div>
                    <?php unset($_SESSION['contact_success']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['contact_error'])): ?>
                    <div class="contact-alert contact-alert-error">
                        <i data-feather="alert-circle"></i>
                        <span><?= htmlspecialchars($_SESSION['contact_error']) ?></span>
                    </div>
                    <?php unset($_SESSION['contact_error']); ?>
                <?php endif; ?>

                <form id="contactForm" method="POST" action="<?= BASE_URL ?>procesos/proceso_contacto.php" class="contact-form" novalidate>
                    <div class="form-row-two">
                        <label class="contact-field" for="nombre">
                            <span>Nombre *</span>
                            <input type="text" id="nombre" name="nombre" maxlength="100" required placeholder="Tu nombre" autocomplete="given-name" value="<?= contacto_valor($old, 'nombre') ?>">
                        </label>

                        <label class="contact-field" for="apellido">
                            <span>Apellido</span>
                            <input type="text" id="apellido" name="apellido" maxlength="100" placeholder="Tu apellido" autocomplete="family-name" value="<?= contacto_valor($old, 'apellido') ?>">
                        </label>
                    </div>

                    <label class="contact-field" for="email">
                        <span>Correo electronico *</span>
                        <input type="email" id="email" name="email" maxlength="150" required placeholder="tu@email.com" autocomplete="email" value="<?= contacto_valor($old, 'email') ?>">
                    </label>

                    <label class="contact-field" for="telefono">
                        <span>Telefono</span>
                        <input type="tel" id="telefono" name="telefono" maxlength="30" placeholder="8090000000" autocomplete="tel" value="<?= contacto_valor($old, 'telefono') ?>">
                    </label>

                    <label class="contact-field" for="asunto">
                        <span>Asunto *</span>
                        <select id="asunto" name="asunto" required>
                            <option value="" disabled <?= empty($old['asunto']) ? 'selected' : '' ?>>Selecciona un tema</option>
                            <?php foreach ($asuntos as $valor => $texto): ?>
                                <option value="<?= htmlspecialchars($valor) ?>" <?= (($old['asunto'] ?? '') === $valor) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($texto) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="contact-field" for="mensaje">
                        <span>Mensaje *</span>
                        <textarea id="mensaje" name="mensaje" rows="5" maxlength="1000" required placeholder="Cuentanos en que podemos ayudarte"><?= contacto_valor($old, 'mensaje') ?></textarea>
                    </label>
                    <p class="field-hint" data-character-counter="mensaje">Maximo 1000 caracteres</p>

                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit" class="contact-submit" id="submitBtn">
                        <span class="submit-text">
                            <i data-feather="send"></i>
                            <?= htmlspecialchars($contacto['boton_formulario']) ?>
                        </span>
                        <span class="submit-loading hidden">
                            <span class="spinner"></span>
                            Enviando
                        </span>
                    </button>

                    <p class="form-privacy">
                        <i data-feather="lock"></i>
                        <?= htmlspecialchars($contacto['form_privacidad']) ?>
                    </p>
                </form>
            </div>
        </section>
    </main>
</div>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
