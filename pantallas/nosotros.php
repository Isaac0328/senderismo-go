<?php
$pageTitle = "Nosotros | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/nosotros.css",
    "css/barra_navegacion.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/nosotros.js"
];

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<!-- CONTENEDOR GENERAL -->
<div class="w-full pt-16 md:pt-20">

    <!-- ================= HERO NOSOTROS ================= -->
    <section id="nosotros-hero" class="relative w-full min-h-[70vh] md:min-h-[80vh]">
        <div class="absolute inset-0">
            <img src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Nuestro equipo en la montaña"
                class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-transparent"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-6 h-full flex items-center">
            <div class="text-white max-w-2xl">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 animate-text-up">
                    Más que senderismo,<br>
                    <span class="text-green-400">somos una comunidad</span>
                </h1>
                <p class="text-xl md:text-2xl mb-8 opacity-90 animate-text-up delay-100">
                    Conectando personas con la naturaleza desde 2015
                </p>
                <div class="flex flex-wrap gap-4">
                    <div class="stat-box animate-fade-in delay-300">
                        <span class="text-3xl font-bold text-green-400">5K+</span>
                        <p class="text-sm">Aventureros felices</p>
                    </div>
                    <div class="stat-box animate-fade-in delay-400">
                        <span class="text-3xl font-bold text-green-400">150+</span>
                        <p class="text-sm">Rutas exploradas</p>
                    </div>
                    <div class="stat-box animate-fade-in delay-500">
                        <span class="text-3xl font-bold text-green-400">98%</span>
                        <p class="text-sm">Satisfacción</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scroll indicator -->
        <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 animate-bounce">
            <div class="w-6 h-10 border-2 border-white/50 rounded-full flex justify-center">
                <div class="w-1 h-3 bg-white/70 rounded-full mt-2"></div>
            </div>
        </div>
    </section>

    <!-- ================= QUIÉNES SOMOS ================= -->
    <section id="quienes-somos" class="section-padding bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="section-title">Nuestra Historia</h2>
                <p class="section-subtitle">De una pasión a un movimiento</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="animate-fade-left">
                    <div
                        class="relative rounded-2xl overflow-hidden shadow-2xl transform hover:scale-[1.02] transition-transform duration-500">
                        <img src="<?= BASE_URL ?>imagenes/paisajes/img3.jpg" alt="Inicios de Senderismo Go"
                            class="w-full h-[400px] object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                        <div class="absolute bottom-6 left-6 text-white">
                            <span class="text-sm opacity-80">2015 - Primer recorrido</span>
                        </div>
                    </div>
                </div>

                <div class="animate-fade-right">
                    <h3 class="text-3xl font-bold mb-6">Nacimos de una simple caminata</h3>
                    <div class="space-y-4">
                        <p class="text-gray-600 leading-relaxed">
                            Todo comenzó con un grupo de amigos amantes de la montaña que buscaban compartir
                            su pasión por el senderismo. Lo que empezó como excursiones ocasionales se transformó
                            en una comunidad vibrante.
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            Hoy somos un equipo de <span class="font-semibold text-green-600">guías certificados</span>,
                            <span class="font-semibold text-green-600">fotógrafos de naturaleza</span> y
                            <span class="font-semibold text-green-600">apasionados outdoor</span> comprometidos
                            con crear experiencias memorables.
                        </p>
                        <div class="pt-4">
                            <a href="#equipo" class="btn-secondary inline-flex items-center gap-2">
                                Conoce a nuestro equipo
                                <i data-feather="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= MISIÓN, VISIÓN, VALORES ================= -->
    <section id="mision-vision-valores" class="section-padding bg-gray-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="section-title">Nuestro Compromiso</h2>
                <p class="section-subtitle">Los pilares que nos guían</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Misión -->
                <div class="mv-card group animate-fade-up delay-100">
                    <div class="mv-icon bg-green-100 text-green-600 group-hover:bg-green-600 group-hover:text-white">
                        <i data-feather="target" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Misión</h3>
                    <p class="text-gray-600">
                        Conectar a las personas con la naturaleza a través de experiencias de senderismo
                        seguras, educativas y transformadoras, fomentando el respeto por el medio ambiente
                        y creando comunidades conscientes.
                    </p>
                </div>

                <!-- Visión -->
                <div class="mv-card group animate-fade-up delay-200">
                    <div class="mv-icon bg-red-100 text-red-600 group-hover:bg-red-600 group-hover:text-white">
                        <i data-feather="eye" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Visión</h3>
                    <p class="text-gray-600">
                        Ser la comunidad de senderismo líder en Latinoamérica, reconocida por nuestra
                        innovación en rutas, compromiso con la sostenibilidad y capacidad para transformar
                        vidas a través de la aventura outdoor.
                    </p>
                </div>

                <!-- Valores -->
                <div class="mv-card group animate-fade-up delay-300">
                    <div class="mv-icon bg-blue-100 text-blue-600 group-hover:bg-blue-600 group-hover:text-white">
                        <i data-feather="heart" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Valores</h3>
                    <div class="space-y-3">
                        <div class="flex items-start gap-2">
                            <i data-feather="shield" class="w-4 h-4 text-green-500 mt-1"></i>
                            <span class="text-gray-600">Seguridad ante todo</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <i data-feather="users" class="w-4 h-4 text-green-500 mt-1"></i>
                            <span class="text-gray-600">Comunidad inclusiva</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <i data-feather="sun" class="w-4 h-4 text-green-500 mt-1"></i>
                            <span class="text-gray-600">Sostenibilidad ambiental</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= EQUIPO / CEOS ================= -->
    <section id="equipo" class="section-padding bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="section-title">Nuestro Equipo</h2>
                <p class="section-subtitle">Los aventureros detrás de cada experiencia</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- CEO 1 -->
                <div class="team-card animate-team-card">
                    <div class="team-img-container">
                        <img src="<?= BASE_URL ?>imagenes/paisajes/img1.jpg" alt="Carlos Mendoza - Fundador" class="team-img">
                        <div class="team-overlay">
                            <div class="flex gap-3">
                                <a href="#" class="social-icon">
                                    <i data-feather="linkedin" class="w-5 h-5"></i>
                                </a>
                                <a href="#" class="social-icon">
                                    <i data-feather="instagram" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Carlos Mendoza</h3>
                        <p class="team-role">Fundador & Guía Principal</p>
                        <p class="team-desc">15 años de experiencia en montañismo y rescate alpino.</p>
                    </div>
                </div>

                <!-- CEO 2 -->
                <div class="team-card animate-team-card delay-100">
                    <div class="team-img-container">
                        <img src="<?= BASE_URL ?>imagenes/paisajes/img2.jpg" alt="Ana Rodríguez - Directora de Rutas" class="team-img">
                        <div class="team-overlay">
                            <div class="flex gap-3">
                                <a href="#" class="social-icon">
                                    <i data-feather="linkedin" class="w-5 h-5"></i>
                                </a>
                                <a href="#" class="social-icon">
                                    <i data-feather="instagram" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Ana Rodríguez</h3>
                        <p class="team-role">Directora de Rutas</p>
                        <p class="team-desc">Bióloga especializada en ecosistemas de montaña.</p>
                    </div>
                </div>

                <!-- Guía 3 -->
                <div class="team-card animate-team-card delay-200">
                    <div class="team-img-container">
                        <img src="<?= BASE_URL ?>imagenes/paisajes/img4.jpg" alt="Miguel Torres - Guía Senior" class="team-img">
                        <div class="team-overlay">
                            <div class="flex gap-3">
                                <a href="#" class="social-icon">
                                    <i data-feather="linkedin" class="w-5 h-5"></i>
                                </a>
                                <a href="#" class="social-icon">
                                    <i data-feather="instagram" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Miguel Torres</h3>
                        <p class="team-role">Guía Senior & Fotógrafo</p>
                        <p class="team-desc">Especialista en fotografía de naturaleza y orientación.</p>
                    </div>
                </div>

                <!-- Guía 4 -->
                <div class="team-card animate-team-card delay-300">
                    <div class="team-img-container">
                        <img src="<?= BASE_URL ?>imagenes/paisajes/img5.jpg" alt="Laura Gómez - Coordinadora de Experiencias"
                            class="team-img">
                        <div class="team-overlay">
                            <div class="flex gap-3">
                                <a href="#" class="social-icon">
                                    <i data-feather="linkedin" class="w-5 h-5"></i>
                                </a>
                                <a href="#" class="social-icon">
                                    <i data-feather="instagram" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="team-info">
                        <h3 class="team-name">Laura Gómez</h3>
                        <p class="team-role">Coordinadora de Experiencias</p>
                        <p class="team-desc">Experta en logística y atención al aventurero.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= VIDEO INSPIRADOR ================= -->
    <section id="video-inspirador" class="relative py-20 bg-black">
        <div class="max-w-6xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Vive la experiencia</h2>
                <p class="text-xl text-gray-300">Un vistazo a lo que te espera</p>
            </div>

            <div class="relative rounded-2xl overflow-hidden shadow-2xl video-container animate-fade-up">
                <!-- Video placeholder - puedes reemplazar con video real -->
                <div class="relative w-full h-[500px] bg-gradient-to-br from-green-900/30 to-blue-900/30">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <button id="playVideo" class="video-play-btn">
                            <i data-feather="play" class="w-12 h-12"></i>
                        </button>
                    </div>

                    <!-- Thumbnail overlay -->
                    <img src="<?= BASE_URL ?>imagenes/paisajes/img6.jpg" alt="Video de aventuras"
                        class="absolute inset-0 w-full h-full object-cover opacity-60">
                </div>

                <!-- Video Modal -->
                <div id="videoModal" class="fixed inset-0 bg-black/90 hidden items-center justify-center z-50">
                    <div class="relative w-full max-w-4xl mx-4">
                        <button id="closeVideo"
                            class="absolute -top-12 right-0 text-white text-3xl hover:text-green-400">
                            &times;
                        </button>
                        <div class="relative pt-[56.25%]"> <!-- 16:9 Aspect Ratio -->
                            <iframe id="videoFrame" class="absolute top-0 left-0 w-full h-full rounded-lg"
                                src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Nuestra aventura" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-12">
                <p class="text-gray-400 max-w-2xl mx-auto">
                    Cada aventura es única. Descubre paisajes impresionantes, conoce personas increíbles
                    y crea recuerdos que durarán toda la vida.
                </p>
            </div>
        </div>
    </section>

    <!-- ================= LOGROS Y CERTIFICACIONES ================= -->
    <section id="logros" class="section-padding bg-gray-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="section-title">Nuestros Logros</h2>
                <p class="section-subtitle">Reconocimientos que avalan nuestro trabajo</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="logro-card animate-scale-up">
                    <div class="logro-icon">
                        <i data-feather="award" class="w-10 h-10"></i>
                    </div>
                    <h3 class="logro-title">Premio Ecoturismo 2023</h3>
                    <p class="logro-desc">Mejor empresa de turismo sostenible</p>
                </div>

                <div class="logro-card animate-scale-up delay-100">
                    <div class="logro-icon">
                        <i data-feather="shield" class="w-10 h-10"></i>
                    </div>
                    <h3 class="logro-title">Certificación Seguridad</h3>
                    <p class="logro-desc">Estándar internacional de seguridad en montaña</p>
                </div>

                <div class="logro-card animate-scale-up delay-200">
                    <div class="logro-icon">
                        <i data-feather="users" class="w-10 h-10"></i>
                    </div>
                    <h3 class="logro-title">10K+ Miembros</h3>
                    <p class="logro-desc">Comunidad activa de aventureros</p>
                </div>

                <div class="logro-card animate-scale-up delay-300">
                    <div class="logro-icon">
                        <i data-feather="map" class="w-10 h-10"></i>
                    </div>
                    <h3 class="logro-title">5 Países</h3>
                    <p class="logro-desc">Expandiendo nuestras rutas internacionalmente</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CTA FINAL ================= -->
    <section id="cta-nosotros" class="section-padding bg-gradient-to-r from-green-600 to-green-700">
        <div class="max-w-6xl mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">
                ¿Listo para tu próxima aventura?
            </h2>
            <p class="text-xl text-green-100 mb-10 max-w-2xl mx-auto">
                Únete a nuestra comunidad y descubre los paisajes más impresionantes
                con guías expertos que se preocupan por tu experiencia.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?= BASE_URL ?>pantallas/senderos.php" class="btn-white">
                    <i data-feather="map" class="w-5 h-5 mr-2"></i>
                    Ver Rutas Disponibles
                </a>
                <a href="<?= BASE_URL ?>pantallas/contacto.php" class="btn-outline-white">
                    <i data-feather="message-circle" class="w-5 h-5 mr-2"></i>
                    Contáctanos
                </a>
            </div>
        </div>
    </section>

</div>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
