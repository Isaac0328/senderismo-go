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

function dificultad_color_detalle(int $nivel): string
{
    if ($nivel <= 35) {
        return 'easy';
    }
    if ($nivel <= 70) {
        return 'medium';
    }
    return 'hard';
}

function dificultad_face_detalle(int $nivel): string
{
    if ($nivel <= 35) {
        return ':)';
    }
    if ($nivel <= 70) {
        return ':|';
    }
    return ':O';
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
        nd.nivel_numero,
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
     ORDER BY sa.orden ASC, sa.id ASC"
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

$inversiones = [];
$stmtInversiones = mysqli_prepare(
    $conn,
    "SELECT si.id, si.nombre, si.descripcion, si.monto, si.fecha_limite_pago, si.orden,
            i.nombre AS incluye_nombre, i.descripcion AS incluye_descripcion
     FROM sendero_inversiones si
     LEFT JOIN sendero_inversion_incluye sii ON sii.inversion_id = si.id
     LEFT JOIN elementos_incluidos i ON i.id = sii.incluye_id AND i.activo = 1
     WHERE si.sendero_id = ?
       AND si.activo = 1
     ORDER BY si.orden ASC, si.id ASC, sii.orden ASC, i.nombre ASC"
);
if ($stmtInversiones) {
    mysqli_stmt_bind_param($stmtInversiones, "i", $idSendero);
    mysqli_stmt_execute($stmtInversiones);
    $resInversiones = mysqli_stmt_get_result($stmtInversiones);
    while ($row = mysqli_fetch_assoc($resInversiones)) {
        $idInversion = (int) $row['id'];
        if (!isset($inversiones[$idInversion])) {
            $inversiones[$idInversion] = [
                'id' => $idInversion,
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'monto' => $row['monto'],
                'fecha_limite_pago' => $row['fecha_limite_pago'],
                'orden' => (int) $row['orden'],
                'incluye' => [],
            ];
        }
        if (!empty($row['incluye_nombre'])) {
            $inversiones[$idInversion]['incluye'][] = [
                'nombre' => $row['incluye_nombre'],
                'descripcion' => $row['incluye_descripcion'],
            ];
        }
    }
    mysqli_stmt_close($stmtInversiones);
}
$inversiones = array_values($inversiones);

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
$horaSalidaPrincipal = null;
for ($i = count($puntosEncuentro) - 1; $i >= 0; $i--) {
    if (!empty($puntosEncuentro[$i]['hora_salida'])) {
        $horaSalidaPrincipal = $puntosEncuentro[$i]['hora_salida'];
        break;
    }
}
$tieneFecha = !$esVisitado && !empty($sendero['fecha_sendero']);
$nivelNumero = min(100, max(0, (int) ($sendero['nivel_numero'] ?? 50)));
$dificultadClase = dificultad_color_detalle($nivelNumero);
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
                <div class="difficulty-meter <?= $dificultadClase ?>" style="--difficulty-level: <?= $nivelNumero ?>%;">
                    <div class="difficulty-meter-head">
                        <span>Nivel de dificultad</span>
                        <strong><?= htmlspecialchars($sendero['nivel_dificultad']) ?> · <?= $nivelNumero ?>/100</strong>
                    </div>
                    <div class="difficulty-bar" aria-label="Nivel de dificultad <?= $nivelNumero ?> de 100">
                        <span class="difficulty-marker"></span>
                    </div>
                    <div class="difficulty-scale">
                        <span>Bajo</span>
                        <span>Alto</span>
                    </div>
                </div>
                <div class="terrain-tags">
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
                            <button type="button" class="gallery-item <?= $index >= 4 ? 'is-gallery-mobile-extra' : '' ?> <?= $index >= 6 ? 'is-gallery-extra' : '' ?>" data-gallery-src="<?= $src ?>" data-gallery-index="<?= $index ?>" aria-label="Ver imagen">
                                <img src="<?= $src ?>" alt="<?= htmlspecialchars($imagen['titulo'] ?: $sendero['nombre']) ?>">
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($galeria) > 4): ?>
                        <button type="button" class="gallery-more-btn" data-gallery-more data-more-text="Ver mas imagenes" data-less-text="Ver menos imagenes">
                            Ver mas imagenes
                        </button>
                    <?php endif; ?>
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

            <section class="detail-section list-panel">
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
            </section>

            <section class="payment-section">
                <article class="detail-section list-panel">
                    <span class="section-kicker">Inversiones</span>
                    <h2>Elige tu opcion</h2>
                    <?php if (!empty($inversiones)): ?>
                        <div class="investment-public-grid">
                            <?php foreach ($inversiones as $idx => $inversion): ?>
                                <?php
                                $numeroInversion = (int) ($inversion['orden'] ?? ($idx + 1));
                                $tituloInversion = 'Inversion ' . $numeroInversion;
                                $nombreOpcional = trim((string) ($inversion['nombre'] ?? ''));
                                $mostrarNombreOpcional = $nombreOpcional !== '' && strcasecmp($nombreOpcional, $tituloInversion) !== 0;
                                ?>
                                <article class="investment-public-card">
                                    <div class="investment-public-top">
                                        <span><?= htmlspecialchars($tituloInversion) ?></span>
                                        <strong><?= dinero_detalle($inversion['monto']) ?></strong>
                                    </div>
                                    <?php if ($mostrarNombreOpcional): ?>
                                        <p class="investment-alias"><?= htmlspecialchars($nombreOpcional) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($inversion['descripcion'])): ?>
                                        <p><?= htmlspecialchars($inversion['descripcion']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($inversion['fecha_limite_pago'])): ?>
                                        <div class="investment-limit">
                                            <i data-feather="calendar"></i>
                                            Pago hasta <?= fecha_larga_detalle($inversion['fecha_limite_pago']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="investment-included-list">
                                        <b>Incluye</b>
                                        <?php if (!empty($inversion['incluye'])): ?>
                                            <?php foreach ($inversion['incluye'] as $item): ?>
                                                <span><i data-feather="check"></i><?= htmlspecialchars($item['nombre']) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span><i data-feather="minus"></i>No tiene suplementos asignados.</span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-block">Las inversiones se publicaran proximamente.</div>
                    <?php endif; ?>
                    <div class="payment-line fuel-note">
                        <i data-feather="info"></i>
                        <span>El transporte, para las personas que no van en su vehiculo, deben coordinarlo con un compañero. <strong>Deben compartir el gasto de combustible. </strong><strong>Su punto de encuentro es el punto 2.</strong></span>
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
                                <div><dt>Enviar a:</dt><dd><?= htmlspecialchars($tarjetaPago['telefono_comprobante']) ?></dd></div>
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
