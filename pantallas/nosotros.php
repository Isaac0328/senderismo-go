<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Nosotros | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/nosotros.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/nosotros.js"
];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$config = [];
$indicadores = [];
$valores = [];
$pasos = [];
$equipo = [];
$connNosotros = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($connNosotros) {
    mysqli_set_charset($connNosotros, 'utf8mb4');

    $res = mysqli_query($connNosotros, "SELECT * FROM configuracion_nosotros WHERE id = 1 LIMIT 1");
    $config = $res ? (mysqli_fetch_assoc($res) ?: []) : [];

    $res = mysqli_query($connNosotros, "SELECT * FROM nosotros_indicadores WHERE activo = 1 ORDER BY orden ASC, id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $indicadores[] = $row;
    }
    $res = mysqli_query($connNosotros, "SELECT * FROM nosotros_valores WHERE activo = 1 ORDER BY orden ASC, id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $valores[] = $row;
    }
    $res = mysqli_query($connNosotros, "SELECT * FROM nosotros_pasos WHERE activo = 1 ORDER BY orden ASC, id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $pasos[] = $row;
    }
    $res = mysqli_query($connNosotros, "SELECT * FROM nosotros_equipo WHERE activo = 1 ORDER BY orden ASC, id ASC");
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $equipo[] = $row;
    }
    mysqli_close($connNosotros);
}

$url = static function (string $path): string {
    return BASE_URL . ltrim($path, '/');
};

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<main class="about-page">
    <section class="about-hero" id="nosotros-hero">
        <img src="<?= $url($config['hero_imagen'] ?? 'imagenes/paisajes/hero.jpg') ?>" alt="Senderismo Go en la montana" class="about-hero-img">
        <div class="about-hero-overlay"></div>

        <div class="about-hero-content">
            <span class="about-kicker"><i data-feather="compass"></i> <?= h($config['hero_kicker'] ?? 'Nosotros') ?></span>
            <h1><?= h($config['hero_titulo'] ?? '') ?></h1>
            <p><?= h($config['hero_subtitulo'] ?? '') ?></p>
            <div class="about-actions">
                <a href="<?= $url($config['boton_principal_url'] ?? 'pantallas/senderos.php') ?>" class="about-btn primary">
                    <i data-feather="map"></i>
                    <?= h($config['boton_principal_texto'] ?? 'Ver senderos') ?>
                </a>
                <a href="<?= $url($config['boton_secundario_url'] ?? 'pantallas/contacto.php') ?>" class="about-btn secondary">
                    <i data-feather="message-circle"></i>
                    <?= h($config['boton_secundario_texto'] ?? 'Coordinar una ruta') ?>
                </a>
            </div>
        </div>
    </section>

    <section class="about-stats" aria-label="Indicadores de Senderismo Go">
        <?php foreach ($indicadores as $stat): ?>
            <article>
                <strong><?= h($stat['valor']) ?></strong>
                <span><?= h($stat['etiqueta']) ?></span>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="about-section about-story">
        <div class="about-container story-grid">
            <div class="story-media">
                <img src="<?= $url($config['historia_imagen'] ?? 'imagenes/paisajes/img4.jpg') ?>" alt="Paisaje recorrido por Senderismo Go">
                <div class="story-badge">
                    <strong><?= h($config['historia_badge_titulo'] ?? '') ?></strong>
                    <span><?= h($config['historia_badge_texto'] ?? '') ?></span>
                </div>
            </div>

            <div class="story-copy">
                <span class="section-label"><?= h($config['historia_kicker'] ?? 'Nuestra historia') ?></span>
                <h2><?= h($config['historia_titulo'] ?? '') ?></h2>
                <p><?= h($config['historia_texto_1'] ?? '') ?></p>
                <p><?= h($config['historia_texto_2'] ?? '') ?></p>
            </div>
        </div>
    </section>

    <section class="about-section about-values">
        <div class="about-container">
            <div class="section-heading">
                <span class="section-label"><?= h($config['valores_kicker'] ?? 'Nuestro compromiso') ?></span>
                <h2><?= h($config['valores_titulo'] ?? '') ?></h2>
                <p><?= h($config['valores_texto'] ?? '') ?></p>
            </div>

            <div class="values-grid">
                <?php foreach ($valores as $valor): ?>
                    <article class="value-card">
                        <span><i data-feather="<?= h($valor['icono']) ?>"></i></span>
                        <h3><?= h($valor['titulo']) ?></h3>
                        <p><?= h($valor['texto']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section about-process">
        <div class="about-container process-grid">
            <div>
                <span class="section-label"><?= h($config['proceso_kicker'] ?? 'Como trabajamos') ?></span>
                <h2><?= h($config['proceso_titulo'] ?? '') ?></h2>
                <p><?= h($config['proceso_texto'] ?? '') ?></p>
            </div>

            <div class="process-list">
                <?php foreach ($pasos as $paso): ?>
                    <article>
                        <strong><?= h($paso['numero']) ?></strong>
                        <div>
                            <h3><?= h($paso['titulo']) ?></h3>
                            <p><?= h($paso['texto']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section about-team" id="equipo">
        <div class="about-container">
            <div class="section-heading">
                <span class="section-label"><?= h($config['equipo_kicker'] ?? 'Equipo') ?></span>
                <h2><?= h($config['equipo_titulo'] ?? '') ?></h2>
                <p><?= h($config['equipo_texto'] ?? '') ?></p>
            </div>

            <div class="team-grid">
                <?php foreach ($equipo as $persona): ?>
                    <article class="team-card">
                        <button type="button"
                                class="team-image-button"
                                data-team-image
                                data-image-src="<?= h($url($persona['imagen'])) ?>"
                                data-image-title="<?= h($persona['nombre']) ?>"
                                data-image-subtitle="<?= h($persona['rol']) ?>"
                                aria-label="Ampliar imagen de <?= h($persona['nombre']) ?>">
                            <img src="<?= $url($persona['imagen']) ?>" alt="<?= h($persona['nombre']) ?>">
                            <span class="team-image-zoom" aria-hidden="true"><i data-feather="maximize-2"></i></span>
                        </button>
                        <div>
                            <h3><?= h($persona['nombre']) ?></h3>
                            <p><?= h($persona['rol']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <dialog class="team-lightbox" data-team-lightbox aria-labelledby="teamLightboxTitle">
        <section class="team-lightbox-panel">
            <button type="button" class="team-lightbox-close" data-team-lightbox-close aria-label="Cerrar imagen ampliada">
                <i data-feather="x"></i>
            </button>
            <div class="team-lightbox-media">
                <img src="" alt="" data-team-lightbox-image>
            </div>
            <div class="team-lightbox-caption">
                <strong id="teamLightboxTitle" data-team-lightbox-title></strong>
                <span data-team-lightbox-subtitle></span>
            </div>
        </section>
    </dialog>

    <section class="about-section about-cta">
        <div class="about-container cta-inner">
            <div>
                <span class="section-label"><?= h($config['cta_kicker'] ?? 'Proxima aventura') ?></span>
                <h2><?= h($config['cta_titulo'] ?? '') ?></h2>
                <p><?= h($config['cta_texto'] ?? '') ?></p>
            </div>
            <div class="about-actions">
                <a href="<?= $url($config['cta_boton_principal_url'] ?? 'pantallas/senderos.php') ?>" class="about-btn primary"><?= h($config['cta_boton_principal_texto'] ?? 'Ver proximos') ?></a>
                <a href="<?= $url($config['cta_boton_secundario_url'] ?? 'pantallas/contacto.php') ?>" class="about-btn secondary dark"><?= h($config['cta_boton_secundario_texto'] ?? 'Contactar') ?></a>
            </div>
        </div>
    </section>
</main>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
