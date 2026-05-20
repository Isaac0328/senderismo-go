<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Senderos Visitados | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/senderos.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/senderos.js"
];

function sendero_visitado_img_src(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    $path = $ruta !== '' ? __DIR__ . '/../' . $ruta : '';

    if ($ruta !== '' && file_exists($path)) {
        return BASE_URL . htmlspecialchars($ruta);
    }

    return '';
}

$senderosVisitados = [];
$sqlVisitados = "
    SELECT
        s.id,
        s.nombre,
        s.fecha_sendero,
        s.lugar,
        s.provincia,
        s.descripcion_corta,
        s.imagen_principal,
        s.imagen_flyer,
        s.imagen_catalogo,
        s.tiempo_sendero_min,
        s.distancia_km,
        nd.nombre AS nivel_dificultad,
        (SELECT COUNT(*) FROM sendero_imagenes si WHERE si.sendero_id = s.id AND si.activo = 1) AS total_fotos
    FROM senderos s
    INNER JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    WHERE s.estado = 'visitado'
      AND s.activo = 1
    ORDER BY s.fecha_sendero DESC, s.id DESC
";

$resVisitados = mysqli_query($conn, $sqlVisitados);
if ($resVisitados) {
    while ($row = mysqli_fetch_assoc($resVisitados)) {
        $senderosVisitados[] = $row;
    }
}

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="senderos-page">
    <section class="senderos-hero visited-hero">
        <img src="<?= BASE_URL ?>imagenes/paisajes/img4.jpg" alt="Senderos visitados" class="senderos-hero-img">
        <div class="senderos-hero-overlay"></div>

        <div class="senderos-hero-content container-senderos">
            <span class="senderos-badge">Rutas realizadas</span>
            <h1 class="senderos-title">Senderos visitados</h1>
            <p class="senderos-subtitle">
                Conoce las rutas que Senderismo Go ha recorrido y que tambien pueden coordinarse para servicios privados.
            </p>
        </div>
    </section>

    <main class="senderos-main container-senderos">
        <section class="senderos-section visited-section">
            <div class="section-heading-row">
                <div>
                    <span class="section-eyebrow">Catalogo de rutas</span>
                    <h2 class="section-title">Senderos disponibles para experiencias privadas</h2>
                    <p class="section-description">
                        Explora destinos que la asociacion conoce y puede preparar para grupos, empresas o aventuras personalizadas.
                    </p>
                </div>
                <a href="<?= BASE_URL ?>pantallas/senderos.php" class="section-link">Ver proximos</a>
            </div>

            <?php if (!empty($senderosVisitados)): ?>
                <div class="senderos-grid">
                    <?php foreach ($senderosVisitados as $sendero): ?>
                        <article class="sendero-card visited-card">
                            <a href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $sendero['id'] ?>" class="sendero-card-link">
                                <div class="sendero-image-wrap">
                                    <?php $imagenSrc = sendero_visitado_img_src($sendero['imagen_catalogo']); ?>
                                    <?php if ($imagenSrc !== ''): ?>
                                        <img src="<?= $imagenSrc ?>" alt="<?= htmlspecialchars($sendero['nombre']) ?>" class="sendero-card-image">
                                    <?php else: ?>
                                        <div class="sendero-no-image">
                                            <i data-feather="image"></i>
                                            <span>Sin imagen cargada</span>
                                        </div>
                                    <?php endif; ?>
                                    <span class="sendero-level">Visitado</span>
                                </div>

                                <div class="sendero-card-body">
                                    <div class="sendero-card-top">
                                        <p class="sendero-fecha">Ruta disponible</p>
                                        <span class="sendero-days"><?= (int) $sendero['total_fotos'] ?> fotos</span>
                                    </div>

                                    <h3 class="sendero-nombre"><?= htmlspecialchars($sendero['nombre']) ?></h3>

                                    <p class="sendero-lugar">
                                        <i data-feather="map-pin"></i>
                                        <?= htmlspecialchars($sendero['lugar']) ?><?= !empty($sendero['provincia']) ? ', ' . htmlspecialchars($sendero['provincia']) : '' ?>
                                    </p>

                                    <?php if (!empty($sendero['descripcion_corta'])): ?>
                                        <p class="sendero-card-desc"><?= htmlspecialchars($sendero['descripcion_corta']) ?></p>
                                    <?php endif; ?>

                                    <div class="sendero-info-row">
                                        <span><i data-feather="trending-up"></i><?= htmlspecialchars($sendero['nivel_dificultad']) ?></span>
                                        <?php if ($sendero['distancia_km'] !== null): ?>
                                            <span><i data-feather="navigation"></i><?= number_format((float) $sendero['distancia_km'], 2) ?> km</span>
                                        <?php endif; ?>
                                        <?php if ($sendero['tiempo_sendero_min'] !== null): ?>
                                            <span><i data-feather="clock"></i><?= (int) $sendero['tiempo_sendero_min'] ?> min</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-senderos">
                    <i data-feather="check-circle"></i>
                    <h3>No hay senderos visitados aun</h3>
                    <p>Muy pronto compartiremos aqui las rutas realizadas y sus mejores momentos.</p>
                    <a href="<?= BASE_URL ?>pantallas/senderos.php" class="section-link">Ver proximos senderos</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php
mysqli_close($conn);
include_once __DIR__ . "/../componentes/pie_pagina.php";
?>
