<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../bd/conexion.php';

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

function inicio_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function inicio_url(?string $ruta): string
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

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS configuracion_inicio (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
        logo_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/logo/logo_sg.png',
        hero_titulo VARCHAR(160) NOT NULL,
        hero_subtitulo VARCHAR(255) NOT NULL,
        boton_texto VARCHAR(80) NOT NULL DEFAULT 'CONOCER MAS',
        boton_url VARCHAR(255) NOT NULL DEFAULT '#porque-elegirnos',
        acceso_rapido_texto VARCHAR(120) NOT NULL DEFAULT 'Ver proximos senderos',
        acceso_rapido_badge VARCHAR(40) NOT NULL DEFAULT 'Agenda',
        acceso_rapido_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
        porque_titulo VARCHAR(160) NOT NULL,
        galeria_titulo VARCHAR(180) NOT NULL,
        galeria_subtitulo VARCHAR(255) NOT NULL,
        cta_titulo VARCHAR(180) NOT NULL,
        cta_texto TEXT NOT NULL,
        cta_boton_texto VARCHAR(80) NOT NULL,
        cta_boton_url VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS inicio_tarjetas (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        icono VARCHAR(60) NOT NULL DEFAULT 'map',
        titulo VARCHAR(160) NOT NULL,
        descripcion TEXT NOT NULL,
        orden INT NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS inicio_galeria (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        imagen VARCHAR(255) NOT NULL,
        titulo VARCHAR(160) DEFAULT NULL,
        orden INT NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

mysqli_query($conn, "
    INSERT IGNORE INTO configuracion_inicio
        (id, hero_imagen, logo_imagen, hero_titulo, hero_subtitulo, boton_texto, boton_url, acceso_rapido_texto, acceso_rapido_badge, acceso_rapido_url, porque_titulo, galeria_titulo, galeria_subtitulo, cta_titulo, cta_texto, cta_boton_texto, cta_boton_url)
    VALUES
        (1, 'imagenes/paisajes/hero.jpg', 'imagenes/logo/logo_sg.png', 'Senderismo... Go!', 'Apasionados por la naturaleza!', 'CONOCER MAS', '#porque-elegirnos', 'Ver proximos senderos', 'Agenda', 'pantallas/senderos.php', 'Por que elegirnos?', 'Algunos de los paisajes que veras con nosotros', 'Descubre un vistazo de las experiencias que te esperan', 'Conoce un poco mas sobre nosotros', 'Somos una comunidad apasionada por la naturaleza, dedicada a crear experiencias unicas de senderismo que conectan a las personas con paisajes increibles y momentos inolvidables.', 'Saber mas', 'pantallas/nosotros.php')
");

$resCardsCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inicio_tarjetas");
$cardsCount = $resCardsCount ? (int) (mysqli_fetch_assoc($resCardsCount)['total'] ?? 0) : 0;
if ($cardsCount === 0) {
    mysqli_query($conn, "
        INSERT INTO inicio_tarjetas (icono, titulo, descripcion, orden, activo) VALUES
        ('map', 'Rutas Exclusivas y Seguras', 'Explora senderos cuidadosamente seleccionados para ofrecerte las mejores vistas y experiencias, con guias expertos que garantizan tu seguridad en todo momento.', 1, 1),
        ('users', 'Experiencia para Todos los Niveles', 'Ofrecemos rutas adaptadas a principiantes y expertos, asegurando que cada aventura se ajuste a tu nivel y disfrutes sin preocupaciones.', 2, 1),
        ('image', 'Conexion Autentica con la Naturaleza', 'Mas que un recorrido, nuestras experiencias te sumergen en la naturaleza con actividades de observacion, fotografia y momentos de relajacion en entornos unicos.', 3, 1)
    ");
}

$resGalleryCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM inicio_galeria");
$galleryCount = $resGalleryCount ? (int) (mysqli_fetch_assoc($resGalleryCount)['total'] ?? 0) : 0;
if ($galleryCount === 0) {
    for ($i = 1; $i <= 10; $i++) {
        $ruta = 'imagenes/paisajes/img' . $i . '.jpg';
        $titulo = 'Paisaje ' . $i;
        $stmt = mysqli_prepare($conn, "INSERT INTO inicio_galeria (imagen, titulo, orden, activo) VALUES (?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'ssi', $ruta, $titulo, $i);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$inicio = [
    'hero_imagen' => 'imagenes/paisajes/hero.jpg',
    'logo_imagen' => 'imagenes/logo/logo_sg.png',
    'hero_titulo' => 'Senderismo... Go!',
    'hero_subtitulo' => 'Apasionados por la naturaleza!',
    'boton_texto' => 'CONOCER MAS',
    'boton_url' => '#porque-elegirnos',
    'acceso_rapido_texto' => 'Ver proximos senderos',
    'acceso_rapido_badge' => 'Agenda',
    'acceso_rapido_url' => 'pantallas/senderos.php',
    'porque_titulo' => 'Por que elegirnos?',
    'galeria_titulo' => 'Algunos de los paisajes que veras con nosotros',
    'galeria_subtitulo' => 'Descubre un vistazo de las experiencias que te esperan',
    'cta_titulo' => 'Conoce un poco mas sobre nosotros',
    'cta_texto' => 'Somos una comunidad apasionada por la naturaleza, dedicada a crear experiencias unicas de senderismo que conectan a las personas con paisajes increibles y momentos inolvidables.',
    'cta_boton_texto' => 'Saber mas',
    'cta_boton_url' => 'pantallas/nosotros.php',
];

$resInicio = mysqli_query($conn, "SELECT * FROM configuracion_inicio WHERE id = 1 LIMIT 1");
if ($resInicio && ($row = mysqli_fetch_assoc($resInicio))) {
    foreach ($inicio as $campo => $valor) {
        if (isset($row[$campo]) && trim((string) $row[$campo]) !== '') {
            $inicio[$campo] = $row[$campo];
        }
    }
}

$tarjetasInicio = [];
$resTarjetas = mysqli_query($conn, "SELECT * FROM inicio_tarjetas WHERE activo = 1 ORDER BY orden ASC, id ASC");
if ($resTarjetas) {
    while ($row = mysqli_fetch_assoc($resTarjetas)) {
        $tarjetasInicio[] = $row;
    }
}

$galeriaInicio = [];
$resGaleria = mysqli_query($conn, "SELECT * FROM inicio_galeria WHERE activo = 1 ORDER BY orden ASC, id ASC");
if ($resGaleria) {
    while ($row = mysqli_fetch_assoc($resGaleria)) {
        $galeriaInicio[] = $row;
    }
}

include_once "../componentes/encabezado.php";
include_once "../componentes/barra_navegacion.php";
?>

<div class="w-full pt-16 md:pt-20">
    <section id="hero" class="relative w-full h-screen">
        <img src="<?= inicio_h(inicio_url($inicio['hero_imagen'])) ?>" alt="<?= inicio_h($inicio['hero_titulo']) ?>" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/40"></div>

        <div class="relative z-10 flex flex-col items-center justify-center h-full text-center px-4">
            <img src="<?= inicio_h(inicio_url($inicio['logo_imagen'])) ?>" alt="<?= inicio_h($inicio['hero_titulo']) ?>" class="w-32 md:w-40 lg:w-48 mb-6">

            <h1 class="text-white text-4xl md:text-5xl lg:text-6xl font-bold mb-4">
                <?= inicio_h($inicio['hero_titulo']) ?>
            </h1>

            <p class="text-white text-lg md:text-xl mb-8">
                <?= inicio_h($inicio['hero_subtitulo']) ?>
            </p>

            <a href="<?= inicio_h($inicio['boton_url']) ?>" class="btn flex items-center gap-2 smooth-scroll">
                <?= inicio_h($inicio['boton_texto']) ?>
                <span>↓</span>
            </a>

            <a href="<?= inicio_h(inicio_url($inicio['acceso_rapido_url'])) ?>" class="hero-quick-link" aria-label="<?= inicio_h($inicio['acceso_rapido_texto']) ?>">
                <i data-feather="calendar"></i>
                <span><?= inicio_h($inicio['acceso_rapido_texto']) ?></span>
                <strong><?= inicio_h($inicio['acceso_rapido_badge']) ?></strong>
            </a>
        </div>
    </section>

    <section id="porque-elegirnos" class="w-full py-20">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold">
                    <?= inicio_h($inicio['porque_titulo']) ?>
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <?php foreach ($tarjetasInicio as $tarjeta): ?>
                    <div class="card text-center hover-card transition-transform duration-300">
                        <div class="mb-6 flex justify-center">
                            <i data-feather="<?= inicio_h($tarjeta['icono']) ?>" class="w-12 h-12"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-4">
                            <?= inicio_h($tarjeta['titulo']) ?>
                        </h3>
                        <p class="leading-relaxed">
                            <?= nl2br(inicio_h($tarjeta['descripcion'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="galeria" class="w-full py-20">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-14">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    <?= inicio_h($inicio['galeria_titulo']) ?>
                </h2>
                <p class="text-lg">
                    <?= inicio_h($inicio['galeria_subtitulo']) ?>
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <?php foreach ($galeriaInicio as $i => $imagen): ?>
                    <div class="cursor-pointer image-hover" data-image-index="<?= (int) $i ?>">
                        <img src="<?= inicio_h(inicio_url($imagen['imagen'])) ?>" alt="<?= inicio_h($imagen['titulo'] ?: 'Paisaje ' . ($i + 1)) ?>" class="w-full h-40 object-cover rounded-lg">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="quienes-somos" class="w-full py-20">
        <div class="max-w-6xl mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                <?= inicio_h($inicio['cta_titulo']) ?>
            </h2>

            <p class="text-lg leading-relaxed max-w-3xl mx-auto mb-10">
                <?= nl2br(inicio_h($inicio['cta_texto'])) ?>
            </p>

            <a href="<?= inicio_h(inicio_url($inicio['cta_boton_url'])) ?>" class="btn inline-block">
                <?= inicio_h($inicio['cta_boton_texto']) ?>
            </a>
        </div>
    </section>
</div>

<div id="galleryModal" class="fixed inset-0 hidden z-50">
    <div class="absolute inset-0 bg-black/80"></div>

    <button aria-label="Cerrar galeria" onclick="closeGallery()" class="absolute top-6 right-6 text-white text-4xl z-20 p-2 hover:scale-110 transition-all duration-300 close-btn">
        &times;
    </button>

    <button aria-label="Imagen anterior" onclick="prevImage()" class="absolute left-4 md:left-8 text-white text-3xl z-20 p-3 hover:scale-110 transition-all duration-300 prev-btn">
        &#10094;
    </button>

    <div class="relative w-full h-full flex items-center justify-center p-4 z-10">
        <img id="galleryImage" class="max-w-[95%] max-h-[90%] object-contain rounded-lg shadow-2xl modal-image" alt="Galeria">
    </div>

    <button aria-label="Imagen siguiente" onclick="nextImage()" class="absolute right-4 md:right-8 text-white text-3xl z-20 p-3 hover:scale-110 transition-all duration-300 next-btn">
        &#10095;
    </button>

    <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-black/60 text-white px-4 py-2 rounded-full text-sm backdrop-blur-sm indicator z-20">
        <span id="currentIndex">1</span> / <span id="totalImages"><?= count($galeriaInicio) ?></span>
    </div>
</div>

<?php mysqli_close($conn); ?>
<?php include_once "../componentes/pie_pagina.php"; ?>
