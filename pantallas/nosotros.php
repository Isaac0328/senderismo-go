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

function nosotros_crear_tablas(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS configuracion_nosotros (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
            hero_kicker VARCHAR(80) NOT NULL DEFAULT 'Nosotros',
            hero_titulo VARCHAR(180) NOT NULL,
            hero_subtitulo TEXT NOT NULL,
            boton_principal_texto VARCHAR(80) NOT NULL DEFAULT 'Ver senderos',
            boton_principal_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
            boton_secundario_texto VARCHAR(80) NOT NULL DEFAULT 'Coordinar una ruta',
            boton_secundario_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
            historia_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/img4.jpg',
            historia_badge_titulo VARCHAR(80) NOT NULL DEFAULT 'Desde 2015',
            historia_badge_texto VARCHAR(120) NOT NULL DEFAULT 'Creando comunidad outdoor',
            historia_kicker VARCHAR(80) NOT NULL DEFAULT 'Nuestra historia',
            historia_titulo VARCHAR(180) NOT NULL,
            historia_texto_1 TEXT NOT NULL,
            historia_texto_2 TEXT NOT NULL,
            valores_kicker VARCHAR(80) NOT NULL DEFAULT 'Nuestro compromiso',
            valores_titulo VARCHAR(180) NOT NULL,
            valores_texto TEXT NOT NULL,
            proceso_kicker VARCHAR(80) NOT NULL DEFAULT 'Como trabajamos',
            proceso_titulo VARCHAR(180) NOT NULL,
            proceso_texto TEXT NOT NULL,
            equipo_kicker VARCHAR(80) NOT NULL DEFAULT 'Equipo',
            equipo_titulo VARCHAR(180) NOT NULL,
            equipo_texto TEXT NOT NULL,
            cta_kicker VARCHAR(80) NOT NULL DEFAULT 'Proxima aventura',
            cta_titulo VARCHAR(180) NOT NULL,
            cta_texto TEXT NOT NULL,
            cta_boton_principal_texto VARCHAR(80) NOT NULL DEFAULT 'Ver proximos',
            cta_boton_principal_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/senderos.php',
            cta_boton_secundario_texto VARCHAR(80) NOT NULL DEFAULT 'Contactar',
            cta_boton_secundario_url VARCHAR(255) NOT NULL DEFAULT 'pantallas/contacto.php',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS nosotros_indicadores (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            valor VARCHAR(40) NOT NULL,
            etiqueta VARCHAR(120) NOT NULL,
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS nosotros_valores (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            icono VARCHAR(60) NOT NULL DEFAULT 'leaf',
            titulo VARCHAR(120) NOT NULL,
            texto TEXT NOT NULL,
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS nosotros_pasos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            numero VARCHAR(10) NOT NULL,
            titulo VARCHAR(140) NOT NULL,
            texto TEXT NOT NULL,
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS nosotros_equipo (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            rol VARCHAR(160) NOT NULL,
            imagen VARCHAR(255) NOT NULL,
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function nosotros_sembrar(mysqli $conn): void
{
    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM configuracion_nosotros");
    $total = ($res && ($row = mysqli_fetch_assoc($res))) ? (int) $row['total'] : 0;
    if ($total === 0) {
        mysqli_query($conn, "
            INSERT INTO configuracion_nosotros (
                id, hero_titulo, hero_subtitulo, historia_titulo, historia_texto_1, historia_texto_2,
                valores_titulo, valores_texto, proceso_titulo, proceso_texto, equipo_titulo, equipo_texto,
                cta_titulo, cta_texto
            ) VALUES (
                1,
                'Guiamos experiencias que conectan personas con la naturaleza.',
                'Senderismo Go nace para que cada persona pueda descubrir rutas, paisajes y comunidades con una experiencia organizada, cercana y responsable.',
                'De una caminata entre amigos a una comunidad de aventura.',
                'Lo que comenzo como recorridos entre personas amantes de la montana se convirtio en una forma de compartir naturaleza, bienestar y companerismo. Hoy organizamos experiencias para quienes desean caminar con mayor seguridad, aprender sobre cada ruta y vivir momentos memorables.',
                'Trabajamos con planificacion, guias preparados, comunicacion clara y respeto por cada espacio natural. Nuestro objetivo es que el visitante se sienta acompanado desde que pregunta por una ruta hasta que termina la experiencia.',
                'Lo que cuidamos en cada salida',
                'La aventura debe sentirse emocionante, pero tambien organizada, clara y humana.',
                'Una ruta bien vivida empieza antes de caminar.',
                'Por eso cada experiencia se prepara con informacion practica: dificultad, distancia, tiempos, puntos de encuentro, terreno, recomendaciones y lo que cada participante debe llevar.',
                'Personas detras de cada experiencia',
                'Un equipo enfocado en seguridad, logistica, orientacion y buen trato.',
                'Quieres caminar con nosotros?',
                'Explora los proximos senderos o escribenos para coordinar una experiencia privada.'
            )
        ");
    }

    $semillas = [
        'nosotros_indicadores' => [
            ['150+', 'Rutas exploradas', 1],
            ['5K+', 'Aventureros guiados', 2],
            ['98%', 'Experiencias positivas', 3],
        ],
        'nosotros_valores' => [
            ['shield', 'Seguridad', 'Planificamos cada salida con criterio, informacion clara y acompanamiento responsable.', 1],
            ['map', 'Conocimiento local', 'Exploramos rutas, puntos de encuentro y condiciones reales para orientar mejor a cada grupo.', 2],
            ['leaf', 'Respeto natural', 'Promovemos senderismo consciente, bajo impacto y cuidado de los espacios que visitamos.', 3],
        ],
        'nosotros_pasos' => [
            ['01', 'Escuchamos tu objetivo', 'Identificamos si buscas una ruta familiar, privada, corporativa, fotografica o de mayor desafio.', 1],
            ['02', 'Elegimos la experiencia', 'Recomendamos dificultad, distancia, horario, equipo y condiciones segun el perfil del grupo.', 2],
            ['03', 'Acompanamos la aventura', 'Coordinamos el recorrido, los puntos de encuentro y las recomendaciones antes y durante la salida.', 3],
        ],
        'nosotros_equipo' => [
            ['Equipo de guias', 'Orientacion y seguridad', 'imagenes/paisajes/img1.jpg', 1],
            ['Coordinacion de rutas', 'Logistica y experiencia', 'imagenes/paisajes/img3.jpg', 2],
            ['Comunidad Senderismo Go', 'Aventureros y aliados', 'imagenes/paisajes/img5.jpg', 3],
        ],
    ];

    foreach ($semillas as $tabla => $items) {
        $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM {$tabla}");
        $total = ($res && ($row = mysqli_fetch_assoc($res))) ? (int) $row['total'] : 0;
        if ($total > 0) {
            continue;
        }

        if ($tabla === 'nosotros_indicadores') {
            $stmt = mysqli_prepare($conn, "INSERT INTO {$tabla} (valor, etiqueta, orden) VALUES (?, ?, ?)");
            foreach ($items as $item) {
                mysqli_stmt_bind_param($stmt, 'ssi', $item[0], $item[1], $item[2]);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        } elseif ($tabla === 'nosotros_valores') {
            $stmt = mysqli_prepare($conn, "INSERT INTO {$tabla} (icono, titulo, texto, orden) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                mysqli_stmt_bind_param($stmt, 'sssi', $item[0], $item[1], $item[2], $item[3]);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        } elseif ($tabla === 'nosotros_pasos') {
            $stmt = mysqli_prepare($conn, "INSERT INTO {$tabla} (numero, titulo, texto, orden) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                mysqli_stmt_bind_param($stmt, 'sssi', $item[0], $item[1], $item[2], $item[3]);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO {$tabla} (nombre, rol, imagen, orden) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                mysqli_stmt_bind_param($stmt, 'sssi', $item[0], $item[1], $item[2], $item[3]);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$config = [];
$indicadores = [];
$valores = [];
$pasos = [];
$equipo = [];
$connNosotros = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($connNosotros) {
    mysqli_set_charset($connNosotros, 'utf8mb4');
    nosotros_crear_tablas($connNosotros);
    nosotros_sembrar($connNosotros);

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
                        <img src="<?= $url($persona['imagen']) ?>" alt="<?= h($persona['nombre']) ?>">
                        <div>
                            <h3><?= h($persona['nombre']) ?></h3>
                            <p><?= h($persona['rol']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

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
