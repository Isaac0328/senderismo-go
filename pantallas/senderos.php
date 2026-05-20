<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Senderos | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/senderos.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/senderos.js"
];

function sendero_img_src(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    $path = $ruta !== '' ? __DIR__ . '/../' . $ruta : '';

    if ($ruta !== '' && file_exists($path)) {
        return BASE_URL . htmlspecialchars($ruta);
    }

    return '';
}

function tiempo_publico(?int $minutos): string
{
    if ($minutos === null) {
        return '';
    }
    $horas = intdiv(max(0, $minutos), 60);
    $mins = max(0, $minutos) % 60;
    if ($horas > 0 && $mins > 0) {
        return $horas . ' h ' . $mins . ' min';
    }
    if ($horas > 0) {
        return $horas . ' h';
    }
    return $mins . ' min';
}

function fecha_larga_es(string $fecha): string
{
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];

    $ts = strtotime($fecha);
    return date('d', $ts) . ' de ' . $meses[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
}

function dias_restantes(string $fecha): int
{
    $hoy = new DateTime('today');
    $evento = new DateTime($fecha);
    return max(0, (int) $hoy->diff($evento)->format('%r%a'));
}

$mesActual = (int) date('n');
$anioActual = (int) date('Y');

$primerDiaMes = new DateTime("$anioActual-$mesActual-01");
$diasMes = (int) $primerDiaMes->format('t');
$inicioSemana = (int) $primerDiaMes->format('N');

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$proximosSenderos = [];

$sqlProximos = "
    SELECT
        s.id,
        s.nombre,
        s.slug,
        s.fecha_sendero,
        s.lugar,
        s.provincia,
        s.descripcion_corta,
        s.imagen_principal,
        s.imagen_flyer,
        s.estado,
        s.tiempo_sendero_min,
        s.distancia_km,
        s.cobertura_senal_pct,
        nd.nombre AS nivel_dificultad,
        (
            SELECT GROUP_CONCAT(tt.nombre ORDER BY tt.nombre SEPARATOR ', ')
            FROM sendero_tipos_terreno stt
            INNER JOIN tipos_terreno tt ON tt.id = stt.tipo_terreno_id
            WHERE stt.sendero_id = s.id
        ) AS tipos_terreno,
        (
            SELECT COUNT(*)
            FROM sendero_imagenes si
            WHERE si.sendero_id = s.id
              AND si.activo = 1
        ) AS total_imagenes,
        (
            SELECT COUNT(*)
            FROM sendero_puntos_encuentro spe
            WHERE spe.sendero_id = s.id
              AND spe.activo = 1
        ) AS total_puntos,
        (
            SELECT MIN(spe.hora_salida)
            FROM sendero_puntos_encuentro spe
            WHERE spe.sendero_id = s.id
              AND spe.activo = 1
        ) AS hora_salida
    FROM senderos s
    INNER JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    WHERE s.estado = 'pendiente'
      AND s.activo = 1
      AND s.fecha_sendero >= CURDATE()
    ORDER BY s.fecha_sendero ASC
    LIMIT 6
";

$resProximos = mysqli_query($conn, $sqlProximos);

if ($resProximos) {
    while ($row = mysqli_fetch_assoc($resProximos)) {
        $proximosSenderos[] = $row;
    }
}

$senderoDestacado = $proximosSenderos[0] ?? null;
$senderosSecundarios = array_slice($proximosSenderos, 1);

$eventosCalendario = [];

$sqlCalendario = "
    SELECT id, nombre, fecha_sendero
    FROM senderos
    WHERE activo = 1
      AND estado = 'pendiente'
      AND MONTH(fecha_sendero) = ?
      AND YEAR(fecha_sendero) = ?
    ORDER BY fecha_sendero ASC
";

$stmtCalendario = mysqli_prepare($conn, $sqlCalendario);

if ($stmtCalendario) {
    mysqli_stmt_bind_param($stmtCalendario, "ii", $mesActual, $anioActual);
    mysqli_stmt_execute($stmtCalendario);
    $resCalendario = mysqli_stmt_get_result($stmtCalendario);

    if ($resCalendario) {
        while ($row = mysqli_fetch_assoc($resCalendario)) {
            $fechaKey = $row['fecha_sendero'];

            if (!isset($eventosCalendario[$fechaKey])) {
                $eventosCalendario[$fechaKey] = [];
            }

            $eventosCalendario[$fechaKey][] = [
                'id' => $row['id'],
                'nombre' => $row['nombre']
            ];
        }
    }

    mysqli_stmt_close($stmtCalendario);
}

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<div class="senderos-page">
    <section class="senderos-hero">
        <img src="<?= BASE_URL ?>imagenes/paisajes/hero.jpg" alt="Senderismo Go" class="senderos-hero-img">
        <div class="senderos-hero-overlay"></div>

        <div class="senderos-hero-content container-senderos">
            <span class="senderos-badge">Proximas experiencias</span>
            <h1 class="senderos-title">Senderos para vivir la naturaleza de cerca</h1>
            <p class="senderos-subtitle">
                Explora las rutas programadas, revisa fechas, puntos de encuentro y detalles antes de reservar tu proxima aventura.
            </p>
        </div>
    </section>

    <main class="senderos-main container-senderos">
        <?php if ($senderoDestacado): ?>
            <?php
            $destacadoImg = sendero_img_src($senderoDestacado['imagen_flyer']);
            $dias = dias_restantes($senderoDestacado['fecha_sendero']);
            ?>
            <section class="featured-sendero">
                <div class="featured-media">
                    <?php if ($destacadoImg !== ''): ?>
                        <img src="<?= $destacadoImg ?>" alt="<?= htmlspecialchars($senderoDestacado['nombre']) ?>">
                    <?php else: ?>
                        <div class="sendero-no-image featured-no-image">
                            <i data-feather="image"></i>
                            <span>Sin imagen cargada</span>
                        </div>
                    <?php endif; ?>
                    <span class="featured-date">
                        <?= date('d', strtotime($senderoDestacado['fecha_sendero'])) ?>
                        <small><?= strtoupper(date('M', strtotime($senderoDestacado['fecha_sendero']))) ?></small>
                    </span>
                </div>

                <div class="featured-content">
                    <span class="section-eyebrow">Siguiente salida</span>
                    <h2><?= htmlspecialchars($senderoDestacado['nombre']) ?></h2>
                    <p class="featured-desc">
                        <?= htmlspecialchars($senderoDestacado['descripcion_corta'] ?: 'Una ruta preparada para conectar con la naturaleza y compartir una experiencia segura en comunidad.') ?>
                    </p>

                    <div class="featured-meta">
                        <span><i data-feather="calendar"></i><?= fecha_larga_es($senderoDestacado['fecha_sendero']) ?></span>
                        <span><i data-feather="map-pin"></i><?= htmlspecialchars($senderoDestacado['lugar']) ?><?= !empty($senderoDestacado['provincia']) ? ', ' . htmlspecialchars($senderoDestacado['provincia']) : '' ?></span>
                        <span><i data-feather="trending-up"></i><?= htmlspecialchars($senderoDestacado['nivel_dificultad']) ?></span>
                    </div>

                    <div class="featured-actions">
                        <a href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $senderoDestacado['id'] ?>" class="btn-primary-sendero">
                            Ver detalle
                        </a>
                        <span class="days-chip"><?= $dias === 0 ? 'Salida hoy' : 'Faltan ' . $dias . ' dias' ?></span>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="senderos-section">
            <div class="section-heading-row">
                <div>
                    <span class="section-eyebrow">Agenda abierta</span>
                    <h2 class="section-title">Proximos senderos</h2>
                    <p class="section-description">
                        Cada tarjeta se alimenta desde la base de datos. Al crear un sendero pendiente y activo desde el mantenimiento, aparecera aqui automaticamente.
                    </p>
                </div>
                <a href="<?= BASE_URL ?>pantallas/senderos_visitados.php" class="section-link">Ver visitados</a>
            </div>

            <?php if (!empty($proximosSenderos)): ?>
                <div class="senderos-grid">
                    <?php foreach ($proximosSenderos as $sendero): ?>
                        <?php
                        $imagenSrc = sendero_img_src($sendero['imagen_flyer']);
                        $terrenos = trim((string) ($sendero['tipos_terreno'] ?? ''));
                        ?>
                        <article class="sendero-card">
                            <a href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $sendero['id'] ?>" class="sendero-card-link">
                                <div class="sendero-image-wrap">
                                    <?php if ($imagenSrc !== ''): ?>
                                        <img src="<?= $imagenSrc ?>" alt="<?= htmlspecialchars($sendero['nombre']) ?>" class="sendero-card-image">
                                    <?php else: ?>
                                        <div class="sendero-no-image">
                                            <i data-feather="image"></i>
                                            <span>Sin imagen cargada</span>
                                        </div>
                                    <?php endif; ?>
                                    <span class="sendero-level"><?= htmlspecialchars($sendero['nivel_dificultad']) ?></span>
                                </div>

                                <div class="sendero-card-body">
                                    <div class="sendero-card-top">
                                        <p class="sendero-fecha"><?= fecha_larga_es($sendero['fecha_sendero']) ?></p>
                                        <span class="sendero-days"><?= dias_restantes($sendero['fecha_sendero']) ?> dias</span>
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
                                            <span><i data-feather="clock"></i><?= tiempo_publico((int) $sendero['tiempo_sendero_min']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($sendero['hora_salida'])): ?>
                                            <span><i data-feather="clock"></i><?= date('h:i A', strtotime($sendero['hora_salida'])) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($terrenos !== ''): ?>
                                        <div class="terrain-row">
                                            <?php foreach (array_slice(explode(', ', $terrenos), 0, 3) as $terreno): ?>
                                                <span><?= htmlspecialchars($terreno) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-senderos">
                    <i data-feather="map"></i>
                    <h3>Aun no hay senderos proximos</h3>
                    <p>Cuando el administrador publique un sendero pendiente y activo, aparecera en esta seccion.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="calendar-section">
            <div class="calendar-copy">
                <span class="section-eyebrow">Calendario</span>
                <h2 class="section-title"><?= $nombresMeses[$mesActual] . ' ' . $anioActual ?></h2>
                <p class="section-description">
                    Visualiza rapidamente los dias con rutas programadas y entra al detalle de cada sendero.
                </p>
            </div>

            <div class="calendar-card">
                <div class="calendar-weekdays">
                    <div>Lun</div>
                    <div>Mar</div>
                    <div>Mie</div>
                    <div>Jue</div>
                    <div>Vie</div>
                    <div>Sab</div>
                    <div>Dom</div>
                </div>

                <div class="calendar-grid" id="calendarGrid">
                    <?php
                    for ($i = 1; $i < $inicioSemana; $i++) {
                        echo '<div class="calendar-day muted"></div>';
                    }

                    for ($dia = 1; $dia <= $diasMes; $dia++):
                        $fechaActual = sprintf('%04d-%02d-%02d', $anioActual, $mesActual, $dia);
                        $tieneEventos = isset($eventosCalendario[$fechaActual]);
                        ?>
                        <div class="calendar-day <?= $tieneEventos ? 'has-event' : '' ?>">
                            <span><?= $dia ?></span>

                            <?php if ($tieneEventos): ?>
                                <?php foreach ($eventosCalendario[$fechaActual] as $evento): ?>
                                    <a href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $evento['id'] ?>" class="calendar-event-name" title="<?= htmlspecialchars($evento['nombre']) ?>">
                                        <?= htmlspecialchars($evento['nombre']) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>
    </main>
</div>

<?php
mysqli_close($conn);
include_once __DIR__ . "/../componentes/pie_pagina.php";
?>
