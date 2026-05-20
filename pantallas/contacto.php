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
    'horario' => 'Lunes a viernes, 8:00 a.m. - 6:00 p.m.',
    'ubicacion' => 'Republica Dominicana',
    'telefono' => '+1 (849) 472-1200',
    'whatsapp' => '18494721200',
    'email' => 'info@senderismogo.com',
    'instagram' => '@senderismogo',
    'instagram_url' => 'https://www.instagram.com/senderismogo',
    'texto_formulario' => 'Cuentanos que necesitas y te responderemos con orientacion clara para que puedas planificar con confianza.'
];

$connContacto = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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
    mysqli_close($connContacto);
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
                    Escribir mensaje
                </a>
                <a href="https://wa.me/<?= htmlspecialchars($contacto['whatsapp']) ?>?text=Hola%20Senderismo%20Go,%20quiero%20informacion%20sobre%20sus%20rutas."
                   target="_blank" rel="noopener noreferrer" class="contact-secondary-action">
                    <i data-feather="phone-call"></i>
                    WhatsApp
                </a>
            </div>
        </div>
    </section>

    <main class="contact-main">
        <section class="contact-summary" aria-label="Informacion de contacto">
            <article>
                <span><i data-feather="clock"></i></span>
                <div>
                    <p>Horario de respuesta</p>
                    <strong><?= htmlspecialchars($contacto['horario']) ?></strong>
                </div>
            </article>
            <article>
                <span><i data-feather="map-pin"></i></span>
                <div>
                    <p>Ubicacion</p>
                    <strong><?= htmlspecialchars($contacto['ubicacion']) ?></strong>
                </div>
            </article>
            <article>
                <span><i data-feather="mail"></i></span>
                <div>
                    <p>Correo</p>
                    <strong><?= htmlspecialchars($contacto['email']) ?></strong>
                </div>
            </article>
        </section>

        <section id="contacto-form" class="contact-workspace">
            <div class="contact-panel contact-panel-info">
                <span class="contact-section-kicker">Atencion personalizada</span>
                <h2>Estamos listos para orientarte.</h2>
                <p>
                    <?= htmlspecialchars($contacto['texto_formulario']) ?>
                </p>

                <div class="contact-channel-list">
                    <a href="https://wa.me/<?= htmlspecialchars($contacto['whatsapp']) ?>?text=Hola%20Senderismo%20Go,%20quiero%20coordinar%20una%20ruta."
                       target="_blank" rel="noopener noreferrer" class="contact-channel">
                        <span class="channel-icon whatsapp"><i data-feather="phone"></i></span>
                        <span>
                            <strong>WhatsApp directo</strong>
                            <small><?= htmlspecialchars($contacto['telefono']) ?></small>
                        </span>
                        <i data-feather="arrow-up-right"></i>
                    </a>

                    <a href="mailto:<?= htmlspecialchars($contacto['email']) ?>" class="contact-channel">
                        <span class="channel-icon email"><i data-feather="mail"></i></span>
                        <span>
                            <strong>Correo electronico</strong>
                            <small><?= htmlspecialchars($contacto['email']) ?></small>
                        </span>
                        <i data-feather="arrow-up-right"></i>
                    </a>

                    <a href="<?= htmlspecialchars($contacto['instagram_url']) ?>" target="_blank" rel="noopener noreferrer" class="contact-channel">
                        <span class="channel-icon instagram"><i data-feather="instagram"></i></span>
                        <span>
                            <strong>Instagram</strong>
                            <small><?= htmlspecialchars($contacto['instagram']) ?></small>
                        </span>
                        <i data-feather="arrow-up-right"></i>
                    </a>
                </div>

                <div class="contact-note">
                    <i data-feather="compass"></i>
                    <p>Tambien puedes escribirnos para rutas privadas, actividades corporativas, grupos familiares o recomendaciones de dificultad.</p>
                </div>
            </div>

            <div class="contact-panel contact-form-panel">
                <div class="form-heading">
                    <span class="contact-section-kicker">Mensaje rapido</span>
                    <h2>Escribenos</h2>
                    <p>Completa estos datos y nos pondremos en contacto contigo.</p>
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
                            Enviar mensaje
                        </span>
                        <span class="submit-loading hidden">
                            <span class="spinner"></span>
                            Enviando
                        </span>
                    </button>

                    <p class="form-privacy">
                        <i data-feather="lock"></i>
                        Usaremos tu informacion solo para responder esta solicitud.
                    </p>
                </form>
            </div>
        </section>
    </main>
</div>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
