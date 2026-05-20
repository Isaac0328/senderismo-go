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

$stats = [
    ['valor' => '150+', 'label' => 'Rutas exploradas'],
    ['valor' => '5K+', 'label' => 'Aventureros guiados'],
    ['valor' => '98%', 'label' => 'Experiencias positivas'],
];

$valores = [
    [
        'icono' => 'shield',
        'titulo' => 'Seguridad',
        'texto' => 'Planificamos cada salida con criterio, informacion clara y acompañamiento responsable.'
    ],
    [
        'icono' => 'map',
        'titulo' => 'Conocimiento local',
        'texto' => 'Exploramos rutas, puntos de encuentro y condiciones reales para orientar mejor a cada grupo.'
    ],
    [
        'icono' => 'leaf',
        'titulo' => 'Respeto natural',
        'texto' => 'Promovemos senderismo consciente, bajo impacto y cuidado de los espacios que visitamos.'
    ],
];

$pasos = [
    ['numero' => '01', 'titulo' => 'Escuchamos tu objetivo', 'texto' => 'Identificamos si buscas una ruta familiar, privada, corporativa, fotografica o de mayor desafio.'],
    ['numero' => '02', 'titulo' => 'Elegimos la experiencia', 'texto' => 'Recomendamos dificultad, distancia, horario, equipo y condiciones segun el perfil del grupo.'],
    ['numero' => '03', 'titulo' => 'Acompañamos la aventura', 'texto' => 'Coordinamos el recorrido, los puntos de encuentro y las recomendaciones antes y durante la salida.'],
];

$equipo = [
    ['nombre' => 'Equipo de guias', 'rol' => 'Orientacion y seguridad', 'imagen' => 'imagenes/paisajes/img1.jpg'],
    ['nombre' => 'Coordinacion de rutas', 'rol' => 'Logistica y experiencia', 'imagen' => 'imagenes/paisajes/img3.jpg'],
    ['nombre' => 'Comunidad Senderismo Go', 'rol' => 'Aventureros y aliados', 'imagen' => 'imagenes/paisajes/img5.jpg'],
];

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<main class="about-page">
    <section class="about-hero" id="nosotros-hero">
        <img src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Senderismo Go en la montana" class="about-hero-img">
        <div class="about-hero-overlay"></div>

        <div class="about-hero-content">
            <span class="about-kicker"><i data-feather="compass"></i> Nosotros</span>
            <h1>Guiamos experiencias que conectan personas con la naturaleza.</h1>
            <p>
                Senderismo Go nace para que cada persona pueda descubrir rutas, paisajes y comunidades con una
                experiencia organizada, cercana y responsable.
            </p>
            <div class="about-actions">
                <a href="<?= BASE_URL ?>pantallas/senderos.php" class="about-btn primary">
                    <i data-feather="map"></i>
                    Ver senderos
                </a>
                <a href="<?= BASE_URL ?>pantallas/contacto.php" class="about-btn secondary">
                    <i data-feather="message-circle"></i>
                    Coordinar una ruta
                </a>
            </div>
        </div>
    </section>

    <section class="about-stats" aria-label="Indicadores de Senderismo Go">
        <?php foreach ($stats as $stat): ?>
            <article>
                <strong><?= htmlspecialchars($stat['valor']) ?></strong>
                <span><?= htmlspecialchars($stat['label']) ?></span>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="about-section about-story">
        <div class="about-container story-grid">
            <div class="story-media">
                <img src="<?= BASE_URL ?>imagenes/paisajes/img4.jpg" alt="Paisaje recorrido por Senderismo Go">
                <div class="story-badge">
                    <strong>Desde 2015</strong>
                    <span>Creando comunidad outdoor</span>
                </div>
            </div>

            <div class="story-copy">
                <span class="section-label">Nuestra historia</span>
                <h2>De una caminata entre amigos a una comunidad de aventura.</h2>
                <p>
                    Lo que comenzo como recorridos entre personas amantes de la montana se convirtio en una forma de
                    compartir naturaleza, bienestar y compañerismo. Hoy organizamos experiencias para quienes desean
                    caminar con mayor seguridad, aprender sobre cada ruta y vivir momentos memorables.
                </p>
                <p>
                    Trabajamos con planificacion, guias preparados, comunicacion clara y respeto por cada espacio
                    natural. Nuestro objetivo es que el visitante se sienta acompañado desde que pregunta por una ruta
                    hasta que termina la experiencia.
                </p>
            </div>
        </div>
    </section>

    <section class="about-section about-values">
        <div class="about-container">
            <div class="section-heading">
                <span class="section-label">Nuestro compromiso</span>
                <h2>Lo que cuidamos en cada salida</h2>
                <p>La aventura debe sentirse emocionante, pero tambien organizada, clara y humana.</p>
            </div>

            <div class="values-grid">
                <?php foreach ($valores as $valor): ?>
                    <article class="value-card">
                        <span><i data-feather="<?= htmlspecialchars($valor['icono']) ?>"></i></span>
                        <h3><?= htmlspecialchars($valor['titulo']) ?></h3>
                        <p><?= htmlspecialchars($valor['texto']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section about-process">
        <div class="about-container process-grid">
            <div>
                <span class="section-label">Como trabajamos</span>
                <h2>Una ruta bien vivida empieza antes de caminar.</h2>
                <p>
                    Por eso cada experiencia se prepara con informacion practica: dificultad, distancia, tiempos,
                    puntos de encuentro, terreno, recomendaciones y lo que cada participante debe llevar.
                </p>
            </div>

            <div class="process-list">
                <?php foreach ($pasos as $paso): ?>
                    <article>
                        <strong><?= htmlspecialchars($paso['numero']) ?></strong>
                        <div>
                            <h3><?= htmlspecialchars($paso['titulo']) ?></h3>
                            <p><?= htmlspecialchars($paso['texto']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section about-team" id="equipo">
        <div class="about-container">
            <div class="section-heading">
                <span class="section-label">Equipo</span>
                <h2>Personas detras de cada experiencia</h2>
                <p>Un equipo enfocado en seguridad, logistica, orientacion y buen trato.</p>
            </div>

            <div class="team-grid">
                <?php foreach ($equipo as $persona): ?>
                    <article class="team-card">
                        <img src="<?= BASE_URL . htmlspecialchars($persona['imagen']) ?>" alt="<?= htmlspecialchars($persona['nombre']) ?>">
                        <div>
                            <h3><?= htmlspecialchars($persona['nombre']) ?></h3>
                            <p><?= htmlspecialchars($persona['rol']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section about-cta">
        <div class="about-container cta-inner">
            <div>
                <span class="section-label">Proxima aventura</span>
                <h2>Quieres caminar con nosotros?</h2>
                <p>Explora los proximos senderos o escribenos para coordinar una experiencia privada.</p>
            </div>
            <div class="about-actions">
                <a href="<?= BASE_URL ?>pantallas/senderos.php" class="about-btn primary">Ver proximos</a>
                <a href="<?= BASE_URL ?>pantallas/contacto.php" class="about-btn secondary dark">Contactar</a>
            </div>
        </div>
    </section>
</main>

<?php include_once __DIR__ . "/../componentes/pie_pagina.php"; ?>
