<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../bd/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
require_once __DIR__ . '/../componentes/encuestas_usuario.php';
sg_restaurar_sesion_recordada();

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

$sgEncuestasUsuarioResumen = ['total' => 0, 'items' => []];
$sgEncuestasUsuarioResumenCargado = false;
if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['logged_in'])) {
    $sgEncuestasUsuarioResumen = sg_encuestas_usuario_resumen($conn, (int) $_SESSION['usuario_id'], 1);
    $sgEncuestasUsuarioResumenCargado = true;
}
$inicioEncuestaPrincipal = $sgEncuestasUsuarioResumen['items'][0] ?? null;

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

<main class="inicio-page">
    <?php if ($inicioEncuestaPrincipal && (int) $sgEncuestasUsuarioResumen['total'] > 0): ?>
        <?php $inicioEncuestasTotal = (int) $sgEncuestasUsuarioResumen['total']; ?>
        <aside class="inicio-survey-notice" aria-label="Encuestas pendientes" aria-live="polite">
            <span class="inicio-survey-icon" aria-hidden="true"><i data-feather="clipboard"></i></span>
            <div class="inicio-survey-copy">
                <span>Tu opinion cuenta</span>
                <strong>
                    <?= $inicioEncuestasTotal === 1
                        ? 'Tienes una encuesta pendiente'
                        : 'Tienes ' . $inicioEncuestasTotal . ' encuestas pendientes' ?>
                </strong>
                <small>
                    <?= inicio_h($inicioEncuestaPrincipal['titulo']) ?>
                    <?php if (!empty($inicioEncuestaPrincipal['sendero_nombre'])): ?>
                        · <?= inicio_h($inicioEncuestaPrincipal['sendero_nombre']) ?>
                    <?php endif; ?>
                </small>
            </div>
            <div class="inicio-survey-actions">
                <a class="inicio-survey-secondary" href="<?= BASE_URL ?>pantallas/mi_perfil.php#encuestas-pendientes">Ver todas</a>
                <a class="inicio-survey-primary" href="<?= BASE_URL ?>pantallas/encuesta.php?envio_id=<?= (int) $inicioEncuestaPrincipal['envio_id'] ?>">
                    Responder ahora <i data-feather="arrow-right"></i>
                </a>
            </div>
        </aside>
    <?php endif; ?>

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
            <div class="porque-header text-center mb-14">
                <span class="porque-overline">Razones de confianza</span>
                <h2 class="text-3xl md:text-4xl font-bold">
                    <?= inicio_h($inicio['porque_titulo']) ?>
                </h2>
                <p>
                    Dise&ntilde;amos cada detalle para que vivas una experiencia segura, aut&eacute;ntica y memorable.
                </p>
            </div>

            <div class="porque-grid grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-10">
                <?php foreach ($tarjetasInicio as $tarjeta): ?>
                    <div class="card text-center">
                        <div class="icon-circle mb-6 flex justify-center">
                            <i data-feather="<?= inicio_h($tarjeta['icono']) ?>" class="w-12 h-12"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-4">
                            <?= inicio_h($tarjeta['titulo']) ?>
                        </h3>
                        <p class="leading-relaxed">
                            <?= nl2br(inicio_h($tarjeta['descripcion'])) ?>
                        </p>
                        <span class="card-divider" aria-hidden="true"></span>
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
</main>

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
