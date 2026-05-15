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

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="contact-page pt-16 md:pt-20">

    <!-- ═══════════ HERO ═══════════ -->
    <section class="contact-hero">

        <!-- Imagen de fondo -->
        <div class="contact-hero-bg">
            <img
                src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg"
                alt="Senderos de República Dominicana"
                class="contact-hero-img"
                loading="eager"
            >
            <div class="contact-hero-overlay"></div>
        </div>

        <!-- Contenido -->
        <div class="contact-hero-content">

            <div class="contact-hero-badge">
                <i data-feather="mail" class="w-4 h-4"></i>
                <span>Estamos para ayudarte</span>
            </div>

            <h1 class="contact-hero-title">
                ¡Conecta<br>con nosotros!
            </h1>

            <p class="contact-hero-sub">
                Cuéntanos tu próxima aventura o comparte<br>
                tu experiencia en los senderos dominicanos.
            </p>

            <a href="#contacto-form" class="contact-hero-scroll smooth-scroll">
                <i data-feather="arrow-down" class="w-5 h-5"></i>
                <span>Escríbenos</span>
            </a>
        </div>

        <!-- Decoración diagonal -->
        <div class="contact-hero-wave">
            <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,80 L0,40 Q360,0 720,40 Q1080,80 1440,30 L1440,80 Z" fill="#f7f8f4"/>
            </svg>
        </div>
    </section>

    <!-- ═══════════ INFO RÁPIDA ═══════════ -->
    <section class="contact-info-strip">
        <div class="contact-info-inner">

            <div class="contact-info-item">
                <div class="contact-info-icon">
                    <i data-feather="clock"></i>
                </div>
                <div>
                    <p class="contact-info-label">Horario de respuesta</p>
                    <p class="contact-info-value">Lun – Vie · 8am – 6pm</p>
                </div>
            </div>

            <div class="contact-info-divider"></div>

            <div class="contact-info-item">
                <div class="contact-info-icon">
                    <i data-feather="map-pin"></i>
                </div>
                <div>
                    <p class="contact-info-label">Ubicación</p>
                    <p class="contact-info-value">República Dominicana</p>
                </div>
            </div>

            <div class="contact-info-divider"></div>

            <div class="contact-info-item">
                <div class="contact-info-icon">
                    <i data-feather="phone"></i>
                </div>
                <div>
                    <p class="contact-info-label">WhatsApp directo</p>
                    <p class="contact-info-value">+1 (849) 472-1200</p>
                </div>
            </div>

        </div>
    </section>

    <!-- ═══════════ CUERPO PRINCIPAL ═══════════ -->
    <section id="contacto-form" class="contact-body">
        <div class="contact-body-inner">

            <!-- ── Columna izquierda: texto + canales alternativos ── -->
            <div class="contact-left">

                <div class="contact-left-head">
                    <span class="contact-tag">Formulario de contacto</span>
                    <h2 class="contact-left-title">Escríbenos<br>lo que necesitas</h2>
                    <p class="contact-left-desc">
                        ¿Preguntas sobre rutas, niveles de dificultad, equipo necesario
                        o quieres proponer un trail nuevo? Estamos listos para ayudarte
                        a planificar tu próxima salida.
                    </p>
                </div>

                <!-- Canales alternativos -->
                <div class="contact-channels">

                    <a href="https://wa.me/8494721200?text=¡Hola!%20Quiero%20info%20sobre%20rutas%20de%20Senderismo%20Go!"
                       target="_blank" rel="noopener noreferrer"
                       class="contact-channel-card channel-whatsapp">
                        <div class="channel-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </div>
                        <div class="channel-text">
                            <span class="channel-label">WhatsApp</span>
                            <span class="channel-sub">Respuesta inmediata</span>
                        </div>
                        <i data-feather="arrow-right" class="channel-arrow w-4 h-4"></i>
                    </a>

                    <a href="mailto:info@senderismogo.com"
                       class="contact-channel-card channel-email">
                        <div class="channel-icon">
                            <i data-feather="mail"></i>
                        </div>
                        <div class="channel-text">
                            <span class="channel-label">Correo electrónico</span>
                            <span class="channel-sub">info@senderismogo.com</span>
                        </div>
                        <i data-feather="arrow-right" class="channel-arrow w-4 h-4"></i>
                    </a>

                    <a href="https://www.instagram.com/senderismogo" target="_blank" rel="noopener noreferrer"
                       class="contact-channel-card channel-instagram">
                        <div class="channel-icon">
                            <i data-feather="instagram"></i>
                        </div>
                        <div class="channel-text">
                            <span class="channel-label">Instagram</span>
                            <span class="channel-sub">@senderismogo</span>
                        </div>
                        <i data-feather="arrow-right" class="channel-arrow w-4 h-4"></i>
                    </a>
                </div>

                <!-- Cita inspiracional -->
                <blockquote class="contact-quote">
                    <span class="contact-quote-mark">"</span>
                    Cada paso en el sendero es una historia nueva.
                    Cuéntanos la tuya y sigamos explorando juntos.
                    <span class="contact-quote-mark">"</span>
                </blockquote>

            </div>

            <!-- ── Columna derecha: formulario ── -->
            <div class="contact-right">

                <!-- Mensajes de feedback -->
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

                <form id="contactForm" method="POST"
                      action="<?= BASE_URL ?>procesos/proceso_contacto.php"
                      class="contact-form" novalidate>

                    <div class="form-row-two">
                        <div class="contact-field">
                            <label for="nombre">Nombre <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i data-feather="user" class="input-icon"></i>
                                <input type="text" id="nombre" name="nombre" required
                                       maxlength="100" placeholder="Tu nombre"
                                       autocomplete="given-name">
                            </div>
                        </div>

                        <div class="contact-field">
                            <label for="apellido">Apellido</label>
                            <div class="input-wrapper">
                                <i data-feather="user" class="input-icon"></i>
                                <input type="text" id="apellido" name="apellido"
                                       maxlength="100" placeholder="Tu apellido"
                                       autocomplete="family-name">
                            </div>
                        </div>
                    </div>

                    <div class="contact-field">
                        <label for="email">Correo electrónico <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i data-feather="mail" class="input-icon"></i>
                            <input type="email" id="email" name="email" required
                                   maxlength="150" placeholder="tu@email.com"
                                   autocomplete="email">
                        </div>
                    </div>

                    <div class="contact-field">
                        <label for="asunto">Asunto <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i data-feather="tag" class="input-icon"></i>
                            <select id="asunto" name="asunto" required>
                                <option value="" disabled selected>Selecciona un tema…</option>
                                <option value="informacion_ruta">Información sobre rutas</option>
                                <option value="reserva">Reservar una salida</option>
                                <option value="nivel_dificultad">Niveles de dificultad</option>
                                <option value="equipo">Equipo necesario</option>
                                <option value="proponer_trail">Proponer un trail</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="contact-field">
                        <label for="mensaje">Mensaje <span class="required">*</span></label>
                        <div class="input-wrapper textarea-wrapper">
                            <i data-feather="message-square" class="input-icon textarea-icon"></i>
                            <textarea id="mensaje" name="mensaje" rows="5" required
                                      maxlength="1000"
                                      placeholder="Cuéntanos en qué podemos ayudarte…"></textarea>
                        </div>
                        <p class="field-hint">Máximo 1000 caracteres</p>
                    </div>

                    <!-- Honeypot anti-spam -->
                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit" class="contact-submit" id="submitBtn">
                        <span class="submit-text">
                            <i data-feather="send" class="w-4 h-4"></i>
                            Enviar mensaje
                        </span>
                        <span class="submit-loading hidden">
                            <span class="spinner"></span>
                            Enviando…
                        </span>
                    </button>

                    <p class="form-privacy">
                        <i data-feather="lock" class="w-3 h-3"></i>
                        Tu información es confidencial y nunca será compartida.
                    </p>

                </form>
            </div>

        </div>
    </section>

</div>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>