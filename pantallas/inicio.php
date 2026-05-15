<?php
$pageTitle = "Inicio | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/inicio.css",
    "css/barra_navegacion.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/inicio.js"
];

include_once "../componentes/encabezado.php";
include_once "../componentes/barra_navegacion.php";
?>

<!-- CONTENEDOR GENERAL -->
<div class="w-full pt-16 md:pt-20">

    <!-- ================= HERO / PORTADA ================= -->
    <section id="hero" class="relative w-full h-screen">
        <!-- Imagen de fondo -->
        <img src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Senderismo Go"
            class="absolute inset-0 w-full h-full object-cover">

        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/40"></div>

        <!-- Contenido centrado -->
        <div class="relative z-10 flex flex-col items-center justify-center h-full text-center px-4">

            <!-- Logo -->
            <img src="<?= BASE_URL ?>imagenes/logo/logo_sg.png" alt="Senderismo Go" class="w-32 md:w-40 lg:w-48 mb-6">

            <!-- Título -->
            <h1 class="text-white text-4xl md:text-5xl lg:text-6xl font-bold mb-4">
                Senderismo… Go!
            </h1>

            <!-- Subtítulo -->
            <p class="text-white text-lg md:text-xl mb-8">
                ¡Apasionados por la naturaleza!
            </p>

            <!-- Botón scroll -->
            <a href="#porque-elegirnos" class="btn flex items-center gap-2 smooth-scroll">
                CONOCER MÁS
                <span>↓</span>
            </a>
        </div>
    </section>

    <!-- ================= ¿POR QUÉ ELEGIRNOS? ================= -->
    <section id="porque-elegirnos" class="w-full py-20">
        <div class="max-w-7xl mx-auto px-6">

            <!-- Título -->
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold">
                    ¿Por qué elegirnos?
                </h2>
            </div>

            <!-- Contenedor de tarjetas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">

                <!-- Card 1 -->
                <div class="card text-center hover-card transition-transform duration-300">
                    <div class="mb-6 flex justify-center">
                        <i data-feather="map" class="w-12 h-12"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">
                        Rutas Exclusivas y Seguras
                    </h3>
                    <p class="leading-relaxed">
                        Explora senderos cuidadosamente seleccionados para ofrecerte
                        las mejores vistas y experiencias, con guías expertos que
                        garantizan tu seguridad en todo momento.
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="card text-center hover-card transition-transform duration-300">
                    <div class="mb-6 flex justify-center">
                        <i data-feather="users" class="w-12 h-12"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">
                        Experiencia para Todos los Niveles
                    </h3>
                    <p class="leading-relaxed">
                        Ofrecemos rutas adaptadas a principiantes y expertos,
                        asegurando que cada aventura se ajuste a tu nivel y
                        disfrutes sin preocupaciones.
                    </p>
                </div>

                <!-- Card 3 -->
                <div class="card text-center hover-card transition-transform duration-300">
                    <div class="mb-6 flex justify-center">
                        <i data-feather="image" class="w-12 h-12"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-4">
                        Conexión Auténtica con la Naturaleza
                    </h3>
                    <p class="leading-relaxed">
                        Más que un recorrido, nuestras experiencias te sumergen
                        en la naturaleza con actividades de observación, fotografía
                        y momentos de relajación en entornos únicos.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- ================= GALERÍA ================= -->
    <section id="galeria" class="w-full py-20">
        <div class="max-w-7xl mx-auto px-6">

            <!-- Título -->
            <div class="text-center mb-14">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    Algunos de los paisajes que verás con nosotros
                </h2>
                <p class="text-lg">
                    Descubre un vistazo de las experiencias que te esperan
                </p>
            </div>

            <!-- Grid de imágenes -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="cursor-pointer image-hover" data-image-index="<?= $i - 1 ?>">
                        <img src="<?= BASE_URL ?>imagenes/paisajes/img<?= $i ?>.jpg" alt="Paisaje <?= $i ?>"
                            class="w-full h-40 object-cover rounded-lg">
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <!-- ================= QUIÉNES SOMOS (CTA) ================= -->
    <section id="quienes-somos" class="w-full py-20">
        <div class="max-w-6xl mx-auto px-6 text-center">

            <!-- Título -->
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                Conoce un poco más sobre nosotros
            </h2>

            <!-- Texto -->
            <p class="text-lg leading-relaxed max-w-3xl mx-auto mb-10">
                Somos una comunidad apasionada por la naturaleza, dedicada a crear
                experiencias únicas de senderismo que conectan a las personas con
                paisajes increíbles y momentos inolvidables.
            </p>

            <!-- Botón -->
            <a href="<?= BASE_URL ?>pantallas/nosotros.php" class="btn inline-block">
                Saber más
            </a>

        </div>
    </section>

</div>

<!-- ================= MODAL GALERÍA ================= -->
<div id="galleryModal" class="fixed inset-0 hidden z-50">
    <!-- Fondo / overlay -->
    <div class="absolute inset-0 bg-black/80"></div>

    <!-- Botón cerrar -->
    <button aria-label="Cerrar galería" onclick="closeGallery()"
        class="absolute top-6 right-6 text-white text-4xl z-20 p-2 hover:scale-110 transition-all duration-300 close-btn">
        &times;
    </button>

    <!-- Botón anterior -->
    <button aria-label="Imagen anterior" onclick="prevImage()"
        class="absolute left-4 md:left-8 text-white text-3xl z-20 p-3 hover:scale-110 transition-all duration-300 prev-btn">
        &#10094;
    </button>

    <!-- Contenedor de imagen -->
    <div class="relative w-full h-full flex items-center justify-center p-4 z-10">
        <img id="galleryImage" class="max-w-[95%] max-h-[90%] object-contain rounded-lg shadow-2xl modal-image"
            alt="Galería">
    </div>

    <!-- Botón siguiente -->
    <button aria-label="Imagen siguiente" onclick="nextImage()"
        class="absolute right-4 md:right-8 text-white text-3xl z-20 p-3 hover:scale-110 transition-all duration-300 next-btn">
        &#10095;
    </button>

    <!-- Indicador de posición (ÚNICO) -->
    <div
        class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-black/60 text-white px-4 py-2 rounded-full text-sm backdrop-blur-sm indicator z-20">
        <span id="currentIndex">1</span> / <span id="totalImages">10</span>
    </div>
</div>

<?php include_once "../componentes/pie_pagina.php"; ?>