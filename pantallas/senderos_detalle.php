<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bd/conexion.php';

$pageTitle = "Detalle del Sendero | Senderismo Go!";

$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/senderos_detalle.css"
];

$jsFiles = [
    "js/barra_navegacion.js",
    "js/senderos_detalle.js"
];

$idSendero = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idSendero <= 0) {
    header("Location: " . BASE_URL . "pantallas/senderos.php");
    exit;
}

function detalle_img_src(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    if ($ruta !== '' && file_exists(__DIR__ . '/../' . $ruta)) {
        return BASE_URL . htmlspecialchars($ruta);
    }
    return '';
}

function tiempo_detalle(?int $minutos): string
{
    if ($minutos === null) {
        return 'Por definir';
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

function fecha_larga_detalle(?string $fecha): string
{
    if (empty($fecha)) {
        return 'Fecha por coordinar';
    }

    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];

    $ts = strtotime($fecha);
    return date('d', $ts) . ' de ' . $meses[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
}

function dinero_detalle($monto): string
{
    if ($monto === null || $monto === '') {
        return 'Por definir';
    }

    return 'RD$ ' . number_format((float) $monto, 2) . ' pesos';
}

function dias_restantes_detalle(?string $fecha): int
{
    if (empty($fecha)) {
        return 0;
    }

    $hoy = new DateTime('today');
    $evento = new DateTime($fecha);
    return max(0, (int) $hoy->diff($evento)->format('%r%a'));
}

$sqlSendero = "
    SELECT
        s.id,
        s.nombre,
        s.slug,
        s.fecha_sendero,
        s.lugar,
        s.provincia,
        s.descripcion_corta,
        s.descripcion,
        s.imagen_principal,
        s.tiempo_ida_vehiculo_min,
        s.tiempo_regreso_vehiculo_min,
        s.tiempo_sendero_min,
        s.distancia_km,
        s.desnivel_mts,
        s.cobertura_senal_pct,
        s.inversion_total,
        s.fecha_limite_pago,
        s.estado,
        nd.nombre AS nivel_dificultad,
        tc.nombre AS tipo_camino_vehiculo
    FROM senderos s
    INNER JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
    LEFT JOIN tipos_camino_vehiculo tc ON tc.id = s.tipo_camino_vehiculo_id
    WHERE s.id = ?
      AND s.activo = 1
    LIMIT 1
";

$stmtSendero = mysqli_prepare($conn, $sqlSendero);

if (!$stmtSendero) {
    die("Error al preparar la consulta del sendero.");
}

mysqli_stmt_bind_param($stmtSendero, "i", $idSendero);
mysqli_stmt_execute($stmtSendero);
$resSendero = mysqli_stmt_get_result($stmtSendero);
$sendero = mysqli_fetch_assoc($resSendero);
mysqli_stmt_close($stmtSendero);

if (!$sendero) {
    header("Location: " . BASE_URL . "pantallas/senderos.php");
    exit;
}

$tiposTerreno = [];
$stmtTerrenos = mysqli_prepare(
    $conn,
    "SELECT tt.nombre
     FROM sendero_tipos_terreno stt
     INNER JOIN tipos_terreno tt ON tt.id = stt.tipo_terreno_id
     WHERE stt.sendero_id = ?
     ORDER BY tt.nombre ASC"
);
if ($stmtTerrenos) {
    mysqli_stmt_bind_param($stmtTerrenos, "i", $idSendero);
    mysqli_stmt_execute($stmtTerrenos);
    $resTerrenos = mysqli_stmt_get_result($stmtTerrenos);
    while ($row = mysqli_fetch_assoc($resTerrenos)) {
        $tiposTerreno[] = $row['nombre'];
    }
    mysqli_stmt_close($stmtTerrenos);
}

$galeria = [];
$stmtGaleria = mysqli_prepare(
    $conn,
    "SELECT ruta_imagen, titulo, orden
     FROM sendero_imagenes
     WHERE sendero_id = ?
       AND activo = 1
     ORDER BY orden ASC, id ASC"
);
if ($stmtGaleria) {
    mysqli_stmt_bind_param($stmtGaleria, "i", $idSendero);
    mysqli_stmt_execute($stmtGaleria);
    $resGaleria = mysqli_stmt_get_result($stmtGaleria);
    while ($row = mysqli_fetch_assoc($resGaleria)) {
        $galeria[] = $row;
    }
    mysqli_stmt_close($stmtGaleria);
}

$puntosEncuentro = [];
$stmtPuntos = mysqli_prepare(
    $conn,
    "SELECT nombre_punto, direccion_referencia, hora_encuentro, hora_salida, url_mapa, orden
     FROM sendero_puntos_encuentro
     WHERE sendero_id = ?
       AND activo = 1
     ORDER BY orden ASC, id ASC"
);
if ($stmtPuntos) {
    mysqli_stmt_bind_param($stmtPuntos, "i", $idSendero);
    mysqli_stmt_execute($stmtPuntos);
    $resPuntos = mysqli_stmt_get_result($stmtPuntos);
    while ($row = mysqli_fetch_assoc($resPuntos)) {
        $puntosEncuentro[] = $row;
    }
    mysqli_stmt_close($stmtPuntos);
}

$anotaciones = [];
$stmtAnotaciones = mysqli_prepare(
    $conn,
    "SELECT ai.nombre, ai.descripcion
     FROM sendero_anotaciones sa
     INNER JOIN anotaciones_importantes ai ON ai.id = sa.anotacion_id
     WHERE sa.sendero_id = ?
       AND ai.activo = 1
     ORDER BY ai.nombre ASC"
);
if ($stmtAnotaciones) {
    mysqli_stmt_bind_param($stmtAnotaciones, "i", $idSendero);
    mysqli_stmt_execute($stmtAnotaciones);
    $resAnotaciones = mysqli_stmt_get_result($stmtAnotaciones);
    while ($row = mysqli_fetch_assoc($resAnotaciones)) {
        $anotaciones[] = $row;
    }
    mysqli_stmt_close($stmtAnotaciones);
}

$incluye = [];
$stmtIncluye = mysqli_prepare(
    $conn,
    "SELECT i.nombre, i.descripcion
     FROM sendero_elementos_incluidos si
     INNER JOIN elementos_incluidos i ON i.id = si.incluye_id
     WHERE si.sendero_id = ?
       AND i.activo = 1
     ORDER BY i.nombre ASC"
);
if ($stmtIncluye) {
    mysqli_stmt_bind_param($stmtIncluye, "i", $idSendero);
    mysqli_stmt_execute($stmtIncluye);
    $resIncluye = mysqli_stmt_get_result($stmtIncluye);
    while ($row = mysqli_fetch_assoc($resIncluye)) {
        $incluye[] = $row;
    }
    mysqli_stmt_close($stmtIncluye);
}

$tarjetaPago = null;
$resPago = mysqli_query($conn, "SELECT * FROM tarjeta_pago WHERE id = 1 AND activo = 1 LIMIT 1");
if ($resPago && ($rowPago = mysqli_fetch_assoc($resPago))) {
    $tarjetaPago = $rowPago;
}

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";

$imagenPrincipalSrc = detalle_img_src($sendero['imagen_principal']);
$esVisitado = ($sendero['estado'] ?? '') === 'visitado';
$diasRestantes = dias_restantes_detalle($sendero['fecha_sendero']);
$horaSalidaPrincipal = $puntosEncuentro[0]['hora_salida'] ?? null;
$tieneFecha = !$esVisitado && !empty($sendero['fecha_sendero']);
?>

<div class="sendero-detalle-page">
    <section class="detalle-hero">
        <?php if ($imagenPrincipalSrc !== ''): ?>
            <img src="<?= $imagenPrincipalSrc ?>" alt="<?= htmlspecialchars($sendero['nombre']) ?>" class="detalle-hero-img">
        <?php else: ?>
            <div class="detalle-hero-img detalle-no-image">
                <i data-feather="image"></i>
                <span>Sin imagen cargada</span>
            </div>
        <?php endif; ?>
        <div class="detalle-hero-overlay"></div>

        <div class="detalle-hero-content container-detalle">
            <a href="<?= BASE_URL ?>pantallas/<?= $esVisitado ? 'senderos_visitados.php' : 'senderos.php' ?>" class="back-link">
                <i data-feather="arrow-left"></i>
                Volver a senderos
            </a>

            <span class="status-badge"><?= htmlspecialchars($sendero['estado']) ?></span>
            <h1><?= htmlspecialchars($sendero['nombre']) ?></h1>

            <?php if (!empty($sendero['descripcion_corta'])): ?>
                <p class="hero-desc"><?= htmlspecialchars($sendero['descripcion_corta']) ?></p>
            <?php endif; ?>

            <div class="hero-meta">
                <?php if ($tieneFecha): ?>
                    <span><i data-feather="calendar"></i><?= fecha_larga_detalle($sendero['fecha_sendero']) ?></span>
                <?php endif; ?>
                <span><i data-feather="map-pin"></i><?= htmlspecialchars($sendero['lugar']) ?><?= !empty($sendero['provincia']) ? ', ' . htmlspecialchars($sendero['provincia']) : '' ?></span>
                <span><i data-feather="trending-up"></i><?= htmlspecialchars($sendero['nivel_dificultad']) ?></span>
            </div>
        </div>
    </section>

    <main class="detalle-main container-detalle">
        <?php if (!empty($_SESSION['registro_sendero_success'])): ?>
            <div class="detalle-alert success"><?= htmlspecialchars($_SESSION['registro_sendero_success']) ?></div>
            <?php unset($_SESSION['registro_sendero_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['registro_sendero_error'])): ?>
            <div class="detalle-alert error"><?= htmlspecialchars($_SESSION['registro_sendero_error']) ?></div>
            <?php unset($_SESSION['registro_sendero_error']); ?>
        <?php endif; ?>

        <section class="summary-panel">
            <?php if (!$esVisitado): ?>
                <?php if ($tieneFecha): ?>
                    <div class="summary-card date-card">
                        <span><?= date('d', strtotime($sendero['fecha_sendero'])) ?></span>
                        <strong><?= strtoupper(date('M', strtotime($sendero['fecha_sendero']))) ?></strong>
                    </div>
                <?php else: ?>
                    <div class="summary-card date-card">
                        <i data-feather="map"></i>
                        <strong>Catalogo</strong>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!$esVisitado): ?>
                <div class="summary-card">
                    <i data-feather="clock"></i>
                    <span>Salida</span>
                    <strong><?= $horaSalidaPrincipal ? date('h:i A', strtotime($horaSalidaPrincipal)) : 'Por definir' ?></strong>
                </div>
            <?php endif; ?>
            <div class="summary-card">
                <i data-feather="trending-up"></i>
                <span>Dificultad</span>
                <strong><?= htmlspecialchars($sendero['nivel_dificultad']) ?></strong>
            </div>
            <div class="summary-card">
                <i data-feather="activity"></i>
                <span>Recorrido</span>
                <strong><?= tiempo_detalle($sendero['tiempo_sendero_min'] !== null ? (int) $sendero['tiempo_sendero_min'] : null) ?></strong>
            </div>
            <div class="summary-card">
                <i data-feather="flag"></i>
                <span>Distancia</span>
                <strong><?= $sendero['distancia_km'] !== null ? number_format((float) $sendero['distancia_km'], 2) . ' km' : 'Por definir' ?></strong>
            </div>
        </section>

        <section class="content-grid">
            <article class="detail-section main-copy">
                <span class="section-kicker">Informacion general</span>
                <h2>Descripcion del sendero</h2>
                <p>
                    <?= nl2br(htmlspecialchars($sendero['descripcion'] ?: $sendero['descripcion_corta'] ?: 'La informacion completa de este sendero sera publicada proximamente.')) ?>
                </p>
            </article>

            <aside class="detail-section terrain-card">
                <span class="section-kicker">Caracteristicas</span>
                <h2>Terreno y dificultad</h2>
                <div class="feature-list">
                    <span><strong>Trayecto vehiculo:</strong> <?= htmlspecialchars($sendero['tipo_camino_vehiculo'] ?: 'Por definir') ?></span>
                    <span><strong>Ida vehiculo:</strong> <?= tiempo_detalle($sendero['tiempo_ida_vehiculo_min'] !== null ? (int) $sendero['tiempo_ida_vehiculo_min'] : null) ?></span>
                    <span><strong>Regreso vehiculo:</strong> <?= tiempo_detalle($sendero['tiempo_regreso_vehiculo_min'] !== null ? (int) $sendero['tiempo_regreso_vehiculo_min'] : null) ?></span>
                    <span><strong>Desnivel (+ -):</strong> <?= $sendero['desnivel_mts'] !== null ? '+ ' . (int) $sendero['desnivel_mts'] . ' mts aprox.' : 'Por definir' ?></span>
                    <span><strong>Cobertura senal:</strong> <?= $sendero['cobertura_senal_pct'] !== null ? (int) $sendero['cobertura_senal_pct'] . '%' : 'Por definir' ?></span>
                </div>
                <div class="terrain-tags">
                    <span><?= htmlspecialchars($sendero['nivel_dificultad']) ?></span>
                    <?php foreach ($tiposTerreno as $terreno): ?>
                        <span><?= htmlspecialchars($terreno) ?></span>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="detail-section">
            <div class="section-head-row">
                <div>
                    <span class="section-kicker">Galeria</span>
                    <h2>Imagenes de referencia</h2>
                </div>
            </div>

            <div class="gallery-grid">
                <?php if (!empty($galeria)): ?>
                    <?php foreach ($galeria as $index => $imagen): ?>
                        <?php $src = detalle_img_src($imagen['ruta_imagen']); ?>
                        <?php if ($src !== ''): ?>
                            <button type="button" class="gallery-item" data-gallery-src="<?= $src ?>" data-gallery-index="<?= $index ?>" aria-label="Ver imagen">
                                <img src="<?= $src ?>" alt="<?= htmlspecialchars($imagen['titulo'] ?: $sendero['nombre']) ?>">
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gallery-empty">
                        <i data-feather="image"></i>
                        <span>Este sendero aun no tiene galeria cargada.</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!$esVisitado): ?>
            <section class="detail-section">
                <span class="section-kicker">Encuentro</span>
                <h2>Puntos y horarios</h2>

                <div class="meeting-grid">
                    <?php if (!empty($puntosEncuentro)): ?>
                        <?php foreach ($puntosEncuentro as $punto): ?>
                            <article class="meeting-card">
                                <div class="meeting-icon"><i data-feather="map-pin"></i></div>
                                <div>
                                    <span class="meeting-point-label">Punto <?= (int) ($punto['orden'] ?? 0) ?></span>
                                    <h3><?= htmlspecialchars($punto['nombre_punto']) ?></h3>
                                    <?php if (!empty($punto['direccion_referencia'])): ?>
                                        <p><?= htmlspecialchars($punto['direccion_referencia']) ?></p>
                                    <?php endif; ?>
                                    <div class="time-grid">
                                        <span><strong>Encuentro</strong><?= date('h:i A', strtotime($punto['hora_encuentro'])) ?></span>
                                        <span><strong>Salida</strong><?= date('h:i A', strtotime($punto['hora_salida'])) ?></span>
                                    </div>
                                    <?php if (!empty($punto['url_mapa'])): ?>
                                        <a href="<?= htmlspecialchars($punto['url_mapa']) ?>" target="_blank" rel="noopener noreferrer" class="map-link">
                                            Abrir ubicacion
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-block">Los puntos de encuentro se publicaran proximamente.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="detail-lists-grid">
                <article class="detail-section list-panel">
                    <span class="section-kicker">Preparacion</span>
                    <h2>Anotaciones importantes</h2>

                    <?php if (!empty($anotaciones)): ?>
                        <div class="detail-list">
                            <?php foreach ($anotaciones as $item): ?>
                                <div class="detail-list-item">
                                    <i data-feather="check-circle"></i>
                                    <div>
                                        <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                        <?php if (!empty($item['descripcion'])): ?>
                                            <p><?= htmlspecialchars($item['descripcion']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-block">No hay anotaciones registradas.</div>
                    <?php endif; ?>
                </article>

                <article class="detail-section list-panel">
                    <span class="section-kicker">Incluido</span>
                    <h2>Este sendero incluye</h2>

                    <?php if (!empty($incluye)): ?>
                        <div class="detail-list">
                            <?php foreach ($incluye as $item): ?>
                                <div class="detail-list-item">
                                    <i data-feather="plus-circle"></i>
                                    <div>
                                        <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                        <?php if (!empty($item['descripcion'])): ?>
                                            <p><?= htmlspecialchars($item['descripcion']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-block">No hay elementos incluidos registrados.</div>
                    <?php endif; ?>
                </article>
            </section>

            <section class="payment-section">
                <article class="detail-section investments-panel">
                    <span class="section-kicker">Inversiones</span>
                    <h2>Inversiones</h2>
                    <div class="payment-lines">
                        <div class="payment-line payment-amount">
                            <i data-feather="credit-card"></i>
                            <span>
                                <small>Inversion total</small>
                                <strong><?= dinero_detalle($sendero['inversion_total']) ?></strong>
                            </span>
                        </div>
                        <div class="payment-line">
                            <i data-feather="info"></i>
                            <span>
                                Solo reservas tu cupo con el pago total del sendero<?= !empty($sendero['fecha_limite_pago']) ? ', a mas tardar el ' . fecha_larga_detalle($sendero['fecha_limite_pago']) . '.' : '.' ?>
                            </span>
                        </div>
                        <div class="payment-line">
                            <i data-feather="info"></i>
                            <span>El transporte, para las personas que no van en su vehiculo, deben coordinarlo con un companero. <strong>Deben compartir el gasto de combustible.</strong></span>
                        </div>
                    </div>
                </article>

                <?php if ($tarjetaPago): ?>
                    <article class="detail-section payment-card-section">
                        <span class="section-kicker">Pago</span>
                        <h2>Informacion para el pago</h2>
                        <div class="bank-card">
                            <h3><?= htmlspecialchars($tarjetaPago['banco']) ?></h3>
                            <dl>
                                <div><dt>Cuenta No.:</dt><dd><?= htmlspecialchars($tarjetaPago['cuenta']) ?></dd></div>
                                <div><dt>Tipo de cuenta:</dt><dd><?= htmlspecialchars($tarjetaPago['tipo_cuenta']) ?></dd></div>
                                <div><dt>Cedula:</dt><dd><?= htmlspecialchars($tarjetaPago['cedula']) ?></dd></div>
                                <div><dt>Correo:</dt><dd><?= htmlspecialchars($tarjetaPago['correo']) ?></dd></div>
                                <div><dt>Nombre:</dt><dd><?= htmlspecialchars($tarjetaPago['nombre']) ?></dd></div>
                                <div><dt>Comprobante:</dt><dd><?= htmlspecialchars($tarjetaPago['telefono_comprobante']) ?></dd></div>
                            </dl>
                            <div class="payment-note">
                                <strong><i data-feather="alert-triangle"></i> Nota importante</strong>
                                <ul>
                                    <?php foreach (preg_split('/\r\n|\r|\n|\.\s+/', (string) $tarjetaPago['nota_importante']) as $linea): ?>
                                        <?php $linea = trim($linea); ?>
                                        <?php if ($linea !== ''): ?>
                                            <li><?= htmlspecialchars(rtrim($linea, '.')) ?>.</li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </article>
                <?php endif; ?>
            </section>

            <section class="detail-register-cta">
                <div>
                    <span class="section-kicker">Registro</span>
                    <h2>Reserva tu cupo para este sendero</h2>
                    <p>Completa tus datos de participante, salud y contacto de emergencia en una pantalla separada.</p>
                </div>
                <a class="register-sendero-btn" href="<?= BASE_URL ?>pantallas/registro_sendero.php?id=<?= (int) $sendero['id'] ?>">
                    Registrarme
                    <i data-feather="arrow-right"></i>
                </a>
            </section>
        <?php endif; ?>
    </main>

    <div class="gallery-modal" id="galleryModal" aria-hidden="true">
        <button type="button" class="gallery-modal-close" id="galleryModalClose" aria-label="Cerrar">&times;</button>
        <button type="button" class="gallery-modal-nav prev" id="galleryPrev" aria-label="Imagen anterior">
            <i data-feather="chevron-left"></i>
        </button>
        <img src="" alt="Imagen del sendero" id="galleryModalImage">
        <button type="button" class="gallery-modal-nav next" id="galleryNext" aria-label="Imagen siguiente">
            <i data-feather="chevron-right"></i>
        </button>
        <div class="gallery-modal-counter" id="galleryCounter">1 / 1</div>
    </div>
</div>

<?php
mysqli_close($conn);
include_once __DIR__ . "/../componentes/pie_pagina.php";
?>
