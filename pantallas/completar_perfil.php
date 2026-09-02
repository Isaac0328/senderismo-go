<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $senderoIdRetorno = isset($_GET['sendero_id']) ? (int) $_GET['sendero_id'] : 0;
    if ($senderoIdRetorno > 0) {
        $_SESSION['redirect_after_login'] = BASE_URL . "pantallas/completar_perfil.php?sendero_id=" . $senderoIdRetorno;
    }
    $_SESSION['error_message'] = "Inicia sesion para completar tus datos.";
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/pasaporte_bootstrap.php';
require_once __DIR__ . '/../componentes/encuestas_usuario.php';

pasaporte_bootstrap($conn);

$usuarioId = (int) $_SESSION['usuario_id'];
$senderoId = isset($_GET['sendero_id']) ? (int) $_GET['sendero_id'] : 0;
$esMiPerfil = ($perfilModo ?? '') === 'mi_perfil';

function perfil_h($value): string
{
    return sg_h($value);
}

function perfil_selected(array $data, string $key, string $value): string
{
    return (string) ($data[$key] ?? '') === $value ? 'selected' : '';
}

function perfil_checked(array $data, string $key, string $value): string
{
    return (string) ($data[$key] ?? '') === $value ? 'checked' : '';
}

function perfil_media_url(?string $ruta, string $fallback): string
{
    $ruta = trim((string) $ruta);
    if ($ruta === '') {
        $ruta = $fallback;
    }
    if (preg_match('/^https?:\/\//i', $ruta)) {
        return $ruta;
    }
    return BASE_URL . ltrim($ruta, '/');
}

function perfil_table_exists(mysqli $conn, string $table): bool
{
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $table);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int) ($row['total'] ?? 0) > 0;
}

function perfil_nivel_senderista(mysqli $conn, int $asistidas, float $kmAcumulados): array
{
    $niveles = [];
    $res = mysqli_query($conn, "
        SELECT *
        FROM pasaporte_niveles
        WHERE activo = 1
        ORDER BY min_senderos ASC, min_km ASC, orden ASC, id ASC
    ");
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $niveles[] = $row;
    }

    if (empty($niveles)) {
        return [
            'nombre' => 'Explorador inicial',
            'texto' => 'Tu aventura apenas esta tomando forma.',
            'icono' => 'compass',
            'color' => '#0f7a3f',
            'siguiente' => null,
            'faltan_senderos' => 0,
            'faltan_km' => 0,
            'progreso' => 100,
        ];
    }

    $actual = $niveles[0];
    foreach ($niveles as $nivel) {
        if ($asistidas >= (int) $nivel['min_senderos'] && $kmAcumulados >= (float) $nivel['min_km']) {
            $actual = $nivel;
        }
    }

    $siguiente = null;
    foreach ($niveles as $nivel) {
        $superaActual = (int) $nivel['min_senderos'] > (int) $actual['min_senderos']
            || (float) $nivel['min_km'] > (float) $actual['min_km'];
        $cumplido = $asistidas >= (int) $nivel['min_senderos'] && $kmAcumulados >= (float) $nivel['min_km'];
        if ($superaActual && !$cumplido) {
            $siguiente = $nivel;
            break;
        }
    }

    $faltanSenderos = $siguiente ? max(0, (int) $siguiente['min_senderos'] - $asistidas) : 0;
    $faltanKm = $siguiente ? max(0, (float) $siguiente['min_km'] - $kmAcumulados) : 0;
    $progreso = 100;
    if ($siguiente) {
        $baseSenderos = (int) $actual['min_senderos'];
        $baseKm = (float) $actual['min_km'];
        $senderosObjetivo = max(1, (int) $siguiente['min_senderos'] - $baseSenderos);
        $kmObjetivo = max(1, (float) $siguiente['min_km'] - $baseKm);
        $progresoSenderos = (($asistidas - $baseSenderos) / $senderosObjetivo) * 100;
        $progresoKm = (($kmAcumulados - $baseKm) / $kmObjetivo) * 100;
        $progreso = (int) min(99, max(0, min($progresoSenderos, $progresoKm)));
    }

    return [
        'nombre' => $actual['nombre'],
        'texto' => $actual['descripcion'] ?: 'Sigue sumando rutas y kilometros para avanzar de nivel.',
        'icono' => $actual['icono'] ?: 'map',
        'color' => $actual['color'] ?: '#0f7a3f',
        'siguiente' => $siguiente,
        'faltan_senderos' => $faltanSenderos,
        'faltan_km' => $faltanKm,
        'progreso' => $progreso,
    ];
}

$stmt = mysqli_prepare($conn, "SELECT nombre, apellido, user, email, created_at, last_login FROM usuarios WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM registros_senderos WHERE usuario_id = ? AND estado = 'registrado'");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$registroStats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['total' => 0];
mysqli_stmt_close($stmt);

$pasaporteStats = [
    'reservas_total' => 0,
    'reservas_proximas' => 0,
    'rutas_asistidas' => 0,
    'km_asistidos' => 0,
    'nivel_maximo' => 0,
    'menores' => 0,
    'creditos_activos' => 0.0,
];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS reservas_total,
        COALESCE(SUM(CASE WHEN rs.asistio = 1 THEN 1 ELSE 0 END), 0) AS rutas_asistidas,
        COALESCE(SUM(CASE WHEN rs.asistio = 1 THEN s.distancia_km ELSE 0 END), 0) AS km_asistidos,
        COALESCE(MAX(CASE WHEN rs.asistio = 1 THEN nd.nivel_numero ELSE 0 END), 0) AS nivel_maximo,
        COALESCE(SUM(CASE WHEN s.estado = 'pendiente' AND s.activo = 1 AND s.fecha_sendero >= CURDATE() THEN 1 ELSE 0 END), 0) AS reservas_proximas
     FROM registros_senderos rs
     INNER JOIN senderos s ON s.id = rs.sendero_id
     LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
     WHERE rs.usuario_id = ? AND rs.estado = 'registrado'"
);
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$pasaporteStats = array_merge($pasaporteStats, mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: []);
mysqli_stmt_close($stmt);

if (perfil_table_exists($conn, 'registro_sendero_menores')) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(DISTINCT rm.id) AS total
         FROM registro_sendero_menores rm
         INNER JOIN registros_senderos rs ON rs.id = rm.registro_id
         WHERE rs.usuario_id = ? AND rs.estado = 'registrado'"
    );
    mysqli_stmt_bind_param($stmt, "i", $usuarioId);
    mysqli_stmt_execute($stmt);
    $rowMenores = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $pasaporteStats['menores'] = (int) ($rowMenores['total'] ?? 0);
}

if (perfil_table_exists($conn, 'usuario_creditos')) {
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(saldo_disponible), 0) AS total FROM usuario_creditos WHERE usuario_id = ? AND estado = 'activo'");
    mysqli_stmt_bind_param($stmt, "i", $usuarioId);
    mysqli_stmt_execute($stmt);
    $rowCreditos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
    $pasaporteStats['creditos_activos'] = (float) ($rowCreditos['total'] ?? 0);
}

$proximaReserva = null;
$stmt = mysqli_prepare(
    $conn,
    "SELECT s.id, s.nombre, s.fecha_sendero, s.lugar, s.provincia
     FROM registros_senderos rs
     INNER JOIN senderos s ON s.id = rs.sendero_id
     WHERE rs.usuario_id = ? AND rs.estado = 'registrado' AND s.estado = 'pendiente' AND s.activo = 1 AND s.fecha_sendero >= CURDATE()
     ORDER BY s.fecha_sendero ASC, s.nombre ASC
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$proximaReserva = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
mysqli_stmt_close($stmt);

$detalle = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$detalle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
mysqli_stmt_close($stmt);

$historialAsistencia = [];
$encuestasPendientes = [];
$encuestasPendientesTotal = 0;
$sgEncuestasUsuarioResumen = ['total' => 0, 'items' => []];
$sgEncuestasUsuarioResumenCargado = false;

if ($esMiPerfil) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            s.id,
            s.nombre,
            s.lugar,
            s.provincia,
            s.fecha_sendero,
            s.estado,
            rs.fecha_asistencia,
            rs.asistencia_notas
         FROM registros_senderos rs
         INNER JOIN senderos s ON s.id = rs.sendero_id
         WHERE rs.usuario_id = ? AND rs.estado = 'registrado' AND rs.asistio = 1
         ORDER BY COALESCE(rs.fecha_asistencia, s.fecha_sendero) DESC, s.nombre ASC
         LIMIT 8"
    );
    mysqli_stmt_bind_param($stmt, "i", $usuarioId);
    mysqli_stmt_execute($stmt);
    $resHistorial = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($resHistorial)) {
        $historialAsistencia[] = $row;
    }
    mysqli_stmt_close($stmt);

    $sgEncuestasUsuarioResumen = sg_encuestas_usuario_resumen($conn, $usuarioId, 100);
    $sgEncuestasUsuarioResumenCargado = true;
    $encuestasPendientes = $sgEncuestasUsuarioResumen['items'];
    $encuestasPendientesTotal = (int) $sgEncuestasUsuarioResumen['total'];
}

$old = $_SESSION['perfil_senderista_old'] ?? [];
if (is_array($old)) {
    $detalle = array_merge($detalle, $old);
}
unset($_SESSION['perfil_senderista_old']);

function perfil_completo_vista(array $detalle): bool
{
    $requeridos = [
        'telefono',
        'rango_edad',
        'identificacion',
        'grupo_sanguineo',
        'enfermedad',
        'seguro_medico',
        'experiencia_senderismo',
        'via_entero',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono',
    ];

    foreach ($requeridos as $campo) {
        if (trim((string) ($detalle[$campo] ?? '')) === '') {
            return false;
        }
    }

    return (int) ($detalle['es_alergico'] ?? 0) !== 1 || trim((string) ($detalle['alergias_detalle'] ?? '')) !== '';
}

function perfil_fecha_historial(?string $fecha): string
{
    if (!$fecha) {
        return 'Sin fecha';
    }
    $time = strtotime($fecha);
    return $time ? date('d/m/Y', $time) : 'Sin fecha';
}

$perfilCompleto = perfil_completo_vista($detalle);
$camposPerfil = [
    'telefono',
    'rango_edad',
    'identificacion',
    'grupo_sanguineo',
    'enfermedad',
    'seguro_medico',
    'experiencia_senderismo',
    'via_entero',
    'emergencia_nombre',
    'emergencia_parentesco',
    'emergencia_telefono',
];
$camposCompletos = 0;
foreach ($camposPerfil as $campoPerfil) {
    if (trim((string) ($detalle[$campoPerfil] ?? '')) !== '') {
        $camposCompletos++;
    }
}
if ((int) ($detalle['es_alergico'] ?? 0) !== 1 || trim((string) ($detalle['alergias_detalle'] ?? '')) !== '') {
    $camposCompletos++;
}
$perfilProgreso = (int) min(100, round(($camposCompletos / (count($camposPerfil) + 1)) * 100));
$nombreCompleto = trim((string) (($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')));
$iniciales = strtoupper(substr((string) ($usuario['nombre'] ?? 'U'), 0, 1) . substr((string) ($usuario['apellido'] ?? ''), 0, 1));
$imagenCabeceraRuta = trim((string) ($detalle['imagen_cabecera'] ?? ''));
$tieneImagenCabecera = $imagenCabeceraRuta !== '';
$imagenCabecera = perfil_media_url($imagenCabeceraRuta, 'imagenes/paisajes/hero.jpg');
$imagenPerfil = trim((string) ($detalle['imagen_perfil'] ?? ''));
$nivelSenderista = perfil_nivel_senderista($conn, (int) ($pasaporteStats['rutas_asistidas'] ?? 0), (float) ($pasaporteStats['km_asistidos'] ?? 0));
$faltantesNivel = [];
if ((int) ($nivelSenderista['faltan_senderos'] ?? 0) > 0) {
    $faltantesNivel[] = (int) $nivelSenderista['faltan_senderos'] . ' ruta(s)';
}
if ((float) ($nivelSenderista['faltan_km'] ?? 0) > 0) {
    $faltantesNivel[] = number_format((float) $nivelSenderista['faltan_km'], 1) . ' km';
}
$textoProximoNivel = $nivelSenderista['siguiente']
    ? 'Faltan ' . ($faltantesNivel ? implode(' y ', $faltantesNivel) : 'un poco mas') . ' para ' . ($nivelSenderista['siguiente']['nombre'] ?? 'el proximo nivel')
    : 'Nivel maximo alcanzado';

$rangosEdad = ['0-18', '19-30', '31-40', '41-50', '51-60', '61+'];
$gruposSanguineos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
$experiencias = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
$vias = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'Google', 'Amigos', 'Otro'];

$pageTitle = ($esMiPerfil ? "Mi perfil" : "Completar perfil") . " | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/perfil_senderista.css"
];
$jsFiles = [
    "js/barra_navegacion.js"
];

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<main class="perfil-page">
    <section class="perfil-shell">
        <div class="perfil-hero" id="perfil_hero" style="--perfil-cover: url('<?= perfil_h($imagenCabecera) ?>');">
            <details class="perfil-cover-menu">
                <summary class="perfil-cover-edit" aria-label="<?= $tieneImagenCabecera ? 'Editar imagen de cabecera' : 'Cargar imagen de cabecera' ?>">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                    </svg>
                </summary>
                <div class="perfil-cover-actions">
                    <label class="perfil-photo-action primary" for="imagen_cabecera"><?= $tieneImagenCabecera ? 'Cambiar cabecera' : 'Cargar cabecera' ?></label>
                    <input class="perfil-file-hidden" form="perfil-form" type="file" name="imagen_cabecera" id="imagen_cabecera" accept="image/jpeg,image/png,image/webp">
                    <?php if ($tieneImagenCabecera): ?>
                        <button class="perfil-photo-action neutral" type="button" data-adjust-cover>Ajustar cabecera</button>
                        <label class="perfil-photo-action danger">
                            <input form="perfil-form" type="checkbox" name="quitar_imagen_cabecera" value="1">
                            Quitar cabecera
                        </label>
                    <?php endif; ?>
                    <small class="perfil-cover-help" id="cabecera_estado">Imagen horizontal JPG, PNG o WEBP. Maximo 4 MB.</small>
                </div>
            </details>
            <span class="perfil-kicker"><?= $esMiPerfil ? 'Mi cuenta' : 'Datos del senderista' ?></span>
            <h1><?= $esMiPerfil ? 'Mi perfil' : 'Completa tu perfil' ?></h1>
            <p><?= $esMiPerfil ? 'Consulta y actualiza tus datos personales, de salud y contacto de emergencia.' : 'Estos datos se guardan una sola vez y se usaran para tus proximas reservas.' ?></p>
        </div>

        <?php if (!empty($_SESSION['perfil_senderista_success'])): ?>
            <div class="perfil-alert success"><?= perfil_h($_SESSION['perfil_senderista_success']) ?></div>
            <?php unset($_SESSION['perfil_senderista_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['perfil_senderista_info'])): ?>
            <div class="perfil-alert info"><?= perfil_h($_SESSION['perfil_senderista_info']) ?></div>
            <?php unset($_SESSION['perfil_senderista_info']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['perfil_senderista_error'])): ?>
            <div class="perfil-alert error"><?= perfil_h($_SESSION['perfil_senderista_error']) ?></div>
            <?php unset($_SESSION['perfil_senderista_error']); ?>
        <?php endif; ?>

        <?php if ($esMiPerfil && !empty($encuestasPendientes)): ?>
            <section class="perfil-survey-panel" id="encuestas-pendientes" aria-label="Encuestas pendientes">
                <div class="perfil-survey-head">
                    <div>
                        <span>Encuestas pendientes</span>
                        <h2>Queremos conocer tu experiencia</h2>
                    </div>
                    <strong><?= $encuestasPendientesTotal ?></strong>
                </div>
                <div class="perfil-survey-list">
                    <?php foreach ($encuestasPendientes as $encuestaPendiente): ?>
                        <article class="perfil-survey-item">
                            <div>
                                <strong><?= perfil_h($encuestaPendiente['titulo']) ?></strong>
                                <?php if (!empty($encuestaPendiente['sendero_nombre'])): ?>
                                    <p><?= perfil_h($encuestaPendiente['sendero_nombre']) ?></p>
                                <?php elseif (trim((string) $encuestaPendiente['descripcion']) !== ''): ?>
                                    <p><?= perfil_h(sg_clean_text((string) $encuestaPendiente['descripcion'], 120)) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($encuestaPendiente['fecha_cierre'])): ?>
                                    <small>Cierra el <?= perfil_h(sg_fecha($encuestaPendiente['fecha_cierre'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="<?= BASE_URL ?>pantallas/encuesta.php?envio_id=<?= (int) $encuestaPendiente['envio_id'] ?>">
                                Responder
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <form id="perfil-form" class="perfil-card" method="POST" action="<?= BASE_URL ?>procesos/proceso_completar_perfil.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="sendero_id" value="<?= (int) $senderoId ?>">
            <input type="hidden" name="origen" value="<?= $esMiPerfil ? 'mi_perfil' : 'completar_perfil' ?>">

            <details class="perfil-media-panel">
                <summary class="perfil-media-summary">
                    <span class="perfil-avatar-preview" id="perfil_avatar_preview" aria-hidden="true">
                        <?php if ($imagenPerfil !== ''): ?>
                            <img src="<?= perfil_h(perfil_media_url($imagenPerfil, '')) ?>" alt="">
                        <?php else: ?>
                            <span id="perfil_avatar_initials"><?= perfil_h($iniciales ?: 'U') ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="perfil-media-copy">
                        <span>Imagenes de tu perfil</span>
                        <strong><?= perfil_h($nombreCompleto ?: ($usuario['user'] ?? 'Usuario')) ?></strong>
                        <p>Toca tu foto para <?= $imagenPerfil !== '' ? 'cambiarla o quitarla' : 'cargar una foto de perfil' ?>.</p>
                    </span>
                </summary>
                <div class="perfil-photo-actions">
                    <label class="perfil-photo-action primary" for="imagen_perfil"><?= $imagenPerfil !== '' ? 'Cambiar foto de perfil' : 'Cargar foto de perfil' ?></label>
                    <input class="perfil-file-hidden" type="file" name="imagen_perfil" id="imagen_perfil" accept="image/jpeg,image/png,image/webp">
                    <?php if ($imagenPerfil !== ''): ?>
                        <button class="perfil-photo-action neutral" type="button" data-adjust-profile>Ajustar foto de perfil</button>
                        <label class="perfil-photo-action danger">
                            <input type="checkbox" name="quitar_imagen_perfil" value="1">
                            Quitar foto de perfil
                        </label>
                    <?php endif; ?>
                    <small class="perfil-help" id="perfil_foto_estado">Formato JPG, PNG o WEBP. Maximo 4 MB.</small>
                </div>
            </details>

            <?php if ($esMiPerfil): ?>
                <section class="perfil-passport-panel" aria-label="Pasaporte del senderista">
                    <div class="perfil-level-card" style="--passport-level-color: <?= perfil_h($nivelSenderista['color'] ?? '#0f7a3f') ?>;">
                        <div class="perfil-level-top">
                            <span class="perfil-level-kicker">Pasaporte senderista</span>
                            <span class="perfil-level-icon"><i data-feather="<?= perfil_h($nivelSenderista['icono'] ?? 'map') ?>"></i></span>
                        </div>
                        <h2><?= perfil_h($nivelSenderista['nombre']) ?></h2>
                        <p><?= perfil_h($nivelSenderista['texto']) ?></p>
                        <div class="perfil-progress-block">
                            <div class="perfil-progress-head">
                                <strong><?= (int) $nivelSenderista['progreso'] ?>%</strong>
                                <span><?= perfil_h($textoProximoNivel) ?></span>
                            </div>
                            <div class="perfil-progress-track" aria-hidden="true">
                                <span style="width: <?= (int) $nivelSenderista['progreso'] ?>%;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="perfil-metrics-grid">
                        <article class="perfil-metric-card">
                            <span><i data-feather="map"></i> Rutas asistidas</span>
                            <strong><?= (int) ($pasaporteStats['rutas_asistidas'] ?? 0) ?></strong>
                            <small>Confirmadas por administracion</small>
                        </article>
                        <article class="perfil-metric-card">
                            <span><i data-feather="navigation"></i> Kilometros</span>
                            <strong><?= number_format((float) ($pasaporteStats['km_asistidos'] ?? 0), 1) ?> km</strong>
                            <small>Acumulados en rutas asistidas</small>
                        </article>
                        <article class="perfil-metric-card">
                            <span><i data-feather="activity"></i> Dificultad max.</span>
                            <strong><?= (int) ($pasaporteStats['nivel_maximo'] ?? 0) ?>/100</strong>
                            <small>Mayor nivel completado</small>
                        </article>
                        <article class="perfil-metric-card">
                            <span><i data-feather="calendar"></i> Proximas</span>
                            <strong><?= (int) ($pasaporteStats['reservas_proximas'] ?? 0) ?></strong>
                            <small>Reservas activas futuras</small>
                        </article>
                        <article class="perfil-metric-card">
                            <span><i data-feather="users"></i> Menores</span>
                            <strong><?= (int) ($pasaporteStats['menores'] ?? 0) ?></strong>
                            <small>Registrados bajo tu perfil</small>
                        </article>
                        <article class="perfil-metric-card">
                            <span><i data-feather="credit-card"></i> Saldo a favor</span>
                            <strong><?= sg_money($pasaporteStats['creditos_activos'] ?? 0) ?></strong>
                            <small>Credito disponible registrado</small>
                        </article>
                    </div>

                    <div class="perfil-next-step">
                        <div>
                            <span>Proximo paso</span>
                            <?php if ($proximaReserva): ?>
                                <strong><?= perfil_h($proximaReserva['nombre']) ?></strong>
                                <p><?= perfil_h(sg_fecha($proximaReserva['fecha_sendero'])) ?> · <?= perfil_h(trim(($proximaReserva['lugar'] ?? '') . ', ' . ($proximaReserva['provincia'] ?? ''), ', ')) ?></p>
                            <?php elseif (!$perfilCompleto): ?>
                                <strong>Completa tus datos</strong>
                                <p>Tu perfil necesita informacion de salud y emergencia para reservar con mas agilidad.</p>
                            <?php else: ?>
                                <strong>Explora tu proxima ruta</strong>
                                <p>No tienes reservas futuras. Revisa los proximos senderos disponibles.</p>
                            <?php endif; ?>
                        </div>
                        <a href="<?= $proximaReserva ? BASE_URL . 'pantallas/senderos_detalle.php?id=' . (int) $proximaReserva['id'] : BASE_URL . 'pantallas/senderos.php' ?>">
                            <?= $proximaReserva ? 'Ver reserva' : 'Ver senderos' ?>
                        </a>
                    </div>
                </section>
            <?php endif; ?>

            <div class="perfil-user-summary">
                <div>
                    <span>Nombre</span>
                    <strong><?= perfil_h($nombreCompleto) ?></strong>
                </div>
                <div>
                    <span>Usuario</span>
                    <strong>@<?= perfil_h($usuario['user'] ?? '') ?></strong>
                </div>
                <div>
                    <span>Correo</span>
                    <strong><?= perfil_h($usuario['email'] ?? '') ?></strong>
                </div>
                <div>
                    <span>Estado del perfil</span>
                    <strong class="<?= $perfilCompleto ? 'perfil-status-ok' : 'perfil-status-pending' ?>"><?= $perfilCompleto ? 'Completo' : 'Pendiente' ?> · <?= $perfilProgreso ?>%</strong>
                </div>
                <div>
                    <span>Reservas activas</span>
                    <strong><?= (int) ($registroStats['total'] ?? 0) ?></strong>
                </div>
                <div>
                    <span>Miembro desde</span>
                    <strong><?= !empty($usuario['created_at']) ? date('d/m/Y', strtotime($usuario['created_at'])) : 'No disponible' ?></strong>
                </div>
            </div>

            <?php if ($esMiPerfil): ?>
                <section class="perfil-history-panel">
                    <div class="perfil-history-head">
                        <div>
                            <span>Historial</span>
                            <h2>Rutas asistidas</h2>
                        </div>
                        <strong><?= count($historialAsistencia) ?></strong>
                    </div>

                    <?php if (empty($historialAsistencia)): ?>
                        <p class="perfil-history-empty">Aun no tienes senderos marcados como asistidos. Cuando el administrador confirme tu asistencia, apareceran aqui.</p>
                    <?php else: ?>
                        <div class="perfil-history-list">
                            <?php foreach ($historialAsistencia as $ruta): ?>
                                <a class="perfil-history-card" href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $ruta['id'] ?>&desde=perfil">
                                    <span><?= perfil_h(perfil_fecha_historial($ruta['fecha_asistencia'] ?: $ruta['fecha_sendero'])) ?></span>
                                    <strong><?= perfil_h($ruta['nombre']) ?></strong>
                                    <p><?= perfil_h(trim(($ruta['lugar'] ?? '') . ', ' . ($ruta['provincia'] ?? ''), ', ')) ?></p>
                                    <?php if (!empty($ruta['asistencia_notas'])): ?>
                                        <small><?= perfil_h($ruta['asistencia_notas']) ?></small>
                                    <?php endif; ?>
                                    <em>Ver sendero</em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <details class="perfil-collapsible-panel">
                <summary class="perfil-collapsible-summary">
                    <span>
                        <strong>Informacion personal y de salud</strong>
                        <small>Los campos con * son obligatorios.</small>
                    </span>
                    <i data-feather="chevron-down"></i>
                </summary>

                <div class="perfil-collapsible-body">
                    <div class="perfil-grid">
                        <div class="perfil-field">
                            <label for="telefono">Telefono *</label>
                            <input type="tel" name="telefono" id="telefono" required inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" placeholder="8090000000" value="<?= perfil_h($detalle['telefono'] ?? '') ?>" oninput="this.value=this.value.replace(/\D/g,'')">
                        </div>
                        <div class="perfil-field">
                            <label for="rango_edad">Edad *</label>
                            <select name="rango_edad" id="rango_edad" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($rangosEdad as $rango): ?>
                                    <option value="<?= perfil_h($rango) ?>" <?= perfil_selected($detalle, 'rango_edad', $rango) ?>><?= perfil_h($rango) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="perfil-field">
                            <label for="identificacion">Identificacion *</label>
                            <input type="text" name="identificacion" id="identificacion" required maxlength="50" value="<?= perfil_h($detalle['identificacion'] ?? '') ?>">
                        </div>
                        <div class="perfil-field">
                            <label for="grupo_sanguineo">Grupo sanguineo *</label>
                            <select name="grupo_sanguineo" id="grupo_sanguineo" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($gruposSanguineos as $grupo): ?>
                                    <option value="<?= perfil_h($grupo) ?>" <?= perfil_selected($detalle, 'grupo_sanguineo', $grupo) ?>><?= perfil_h($grupo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="perfil-grid">
                        <div class="perfil-field">
                            <label>Es alergico? *</label>
                            <div class="perfil-radio-row">
                                <label><input type="radio" name="es_alergico" value="1" <?= perfil_checked($detalle, 'es_alergico', '1') ?> required> Si</label>
                                <label><input type="radio" name="es_alergico" value="0" <?= $detalle ? perfil_checked($detalle, 'es_alergico', '0') : 'checked' ?> required> No</label>
                            </div>
                        </div>
                        <div class="perfil-field">
                            <label for="alergias_detalle">Detalle de alergia</label>
                            <input type="text" name="alergias_detalle" id="alergias_detalle" maxlength="255" placeholder="Solo si aplica" value="<?= perfil_h($detalle['alergias_detalle'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="perfil-field">
                        <label for="enfermedad">Padece alguna enfermedad? *</label>
                        <input type="text" name="enfermedad" id="enfermedad" required maxlength="255" placeholder="Si no aplica, escribe No" value="<?= perfil_h($detalle['enfermedad'] ?? '') ?>">
                    </div>

                    <div class="perfil-field">
                        <label for="seguro_medico">Tiene seguro medico? *</label>
                        <input type="text" name="seguro_medico" id="seguro_medico" required maxlength="255" placeholder="Si no aplica, escribe No" value="<?= perfil_h($detalle['seguro_medico'] ?? '') ?>">
                    </div>

                    <div class="perfil-grid">
                        <div class="perfil-field">
                            <label for="experiencia_senderismo">Experiencia haciendo senderismo *</label>
                            <select name="experiencia_senderismo" id="experiencia_senderismo" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($experiencias as $experiencia): ?>
                                    <option value="<?= perfil_h($experiencia) ?>" <?= perfil_selected($detalle, 'experiencia_senderismo', $experiencia) ?>><?= perfil_h($experiencia) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="perfil-field">
                            <label for="via_entero">Por cual via te enteraste? *</label>
                            <select name="via_entero" id="via_entero" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($vias as $via): ?>
                                    <option value="<?= perfil_h($via) ?>" <?= perfil_selected($detalle, 'via_entero', $via) ?>><?= perfil_h($via) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="perfil-field">
                        <label for="referido_nombre">Si fue por amigos, escribe su nombre</label>
                        <input type="text" name="referido_nombre" id="referido_nombre" maxlength="150" pattern="[^0-9]*" value="<?= perfil_h($detalle['referido_nombre'] ?? '') ?>" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                    </div>
                </div>
            </details>

            <details class="perfil-collapsible-panel">
                <summary class="perfil-collapsible-summary">
                    <span>
                        <strong>Contacto de emergencia</strong>
                        <small>Persona a contactar ante cualquier eventualidad.</small>
                    </span>
                    <i data-feather="chevron-down"></i>
                </summary>

                <div class="perfil-collapsible-body">
                    <div class="perfil-grid">
                        <div class="perfil-field">
                            <label for="emergencia_nombre">Nombre *</label>
                            <input type="text" name="emergencia_nombre" id="emergencia_nombre" required maxlength="150" pattern="[^0-9]*" value="<?= perfil_h($detalle['emergencia_nombre'] ?? '') ?>" oninput="this.value=this.value.replace(/[0-9]/g,'')">
                        </div>
                        <div class="perfil-field">
                            <label for="emergencia_parentesco">Parentesco *</label>
                            <input type="text" name="emergencia_parentesco" id="emergencia_parentesco" required maxlength="80" value="<?= perfil_h($detalle['emergencia_parentesco'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="perfil-field">
                        <label for="emergencia_telefono">Telefono de emergencia *</label>
                        <input type="tel" name="emergencia_telefono" id="emergencia_telefono" required inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" placeholder="8090000000" value="<?= perfil_h($detalle['emergencia_telefono'] ?? '') ?>" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>
                </div>
            </details>

            <div class="perfil-actions">
                <a class="perfil-btn secondary" href="<?= $senderoId > 0 ? BASE_URL . 'pantallas/senderos_detalle.php?id=' . (int) $senderoId : BASE_URL . 'pantallas/inicio.php' ?>">Cancelar</a>
                <button class="perfil-btn primary" type="submit"><?= $esMiPerfil ? 'Guardar cambios' : 'Guardar y continuar' ?></button>
            </div>
        </form>
    </section>
</main>

<div class="perfil-crop-modal" id="perfil_crop_modal" aria-hidden="true">
    <div class="perfil-crop-backdrop" data-crop-cancel></div>
    <section class="perfil-crop-panel" role="dialog" aria-modal="true" aria-labelledby="perfil_crop_title">
        <div class="perfil-crop-head">
            <div>
                <span>Ajustar imagen</span>
                <h2 id="perfil_crop_title">Acomoda tu imagen</h2>
            </div>
            <button type="button" class="perfil-crop-close" data-crop-cancel aria-label="Cerrar">x</button>
        </div>
        <div class="perfil-crop-stage">
            <div class="perfil-crop-frame" id="perfil_crop_frame">
                <img id="perfil_crop_image" alt="">
            </div>
        </div>
        <div class="perfil-crop-controls">
            <label for="perfil_crop_zoom">Zoom</label>
            <input type="range" id="perfil_crop_zoom" min="1" max="3" step="0.01" value="1">
        </div>
        <div class="perfil-crop-actions">
            <button type="button" class="perfil-crop-btn secondary" data-crop-cancel>Cancelar</button>
            <button type="button" class="perfil-crop-btn primary" id="perfil_crop_accept">Usar imagen</button>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const perfilInput = document.getElementById('imagen_perfil');
    const cabeceraInput = document.getElementById('imagen_cabecera');
    const avatarPreview = document.getElementById('perfil_avatar_preview');
    const hero = document.getElementById('perfil_hero');
    const perfilEstado = document.getElementById('perfil_foto_estado');
    const cabeceraEstado = document.getElementById('cabecera_estado');
    const cropModal = document.getElementById('perfil_crop_modal');
    const cropTitle = document.getElementById('perfil_crop_title');
    const cropFrame = document.getElementById('perfil_crop_frame');
    const cropImage = document.getElementById('perfil_crop_image');
    const cropZoom = document.getElementById('perfil_crop_zoom');
    const cropAccept = document.getElementById('perfil_crop_accept');
    const cropCancelButtons = document.querySelectorAll('[data-crop-cancel]');
    const adjustProfileButton = document.querySelector('[data-adjust-profile]');
    const adjustCoverButton = document.querySelector('[data-adjust-cover]');
    const currentProfileUrl = <?= json_encode($imagenPerfil !== '' ? perfil_media_url($imagenPerfil, '') : '') ?>;
    const currentCoverUrl = <?= json_encode($tieneImagenCabecera ? $imagenCabecera : '') ?>;
    let cropState = null;

    function setStatus(element, file) {
        if (!element || !file) {
            return;
        }
        element.textContent = 'Imagen ajustada: "' + file.name + '". Presiona Guardar cambios para aplicar.';
        element.classList.add('perfil-file-ready');
    }

    function setInputFile(input, file) {
        if (!input || !window.DataTransfer) {
            return;
        }
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    }

    function clampCrop() {
        if (!cropState) {
            return;
        }
        const frameW = cropFrame.clientWidth;
        const frameH = cropFrame.clientHeight;
        const drawW = cropState.imageW * cropState.scale;
        const drawH = cropState.imageH * cropState.scale;
        const minX = Math.min(0, frameW - drawW);
        const minY = Math.min(0, frameH - drawH);
        cropState.x = Math.min(0, Math.max(minX, cropState.x));
        cropState.y = Math.min(0, Math.max(minY, cropState.y));
    }

    function renderCrop() {
        if (!cropState) {
            return;
        }
        clampCrop();
        cropImage.style.width = (cropState.imageW * cropState.scale) + 'px';
        cropImage.style.height = (cropState.imageH * cropState.scale) + 'px';
        cropImage.style.transform = 'translate(' + cropState.x + 'px, ' + cropState.y + 'px)';
    }

    function fitCropImage() {
        if (!cropState) {
            return;
        }
        const frameW = cropFrame.clientWidth;
        const frameH = cropFrame.clientHeight;
        cropState.baseScale = Math.max(frameW / cropState.imageW, frameH / cropState.imageH);
        cropState.scale = cropState.baseScale * Number(cropZoom.value || 1);
        cropState.x = (frameW - cropState.imageW * cropState.scale) / 2;
        cropState.y = cropState.options.kind === 'cover'
            ? 0
            : (frameH - cropState.imageH * cropState.scale) / 2;
        renderCrop();
    }

    function openCropper(input, file, options) {
        if (!cropModal || !cropImage || !cropFrame) {
            return;
        }
        const src = URL.createObjectURL(file);
        cropState = {
            input: input,
            file: file,
            options: options,
            imageW: 0,
            imageH: 0,
            baseScale: 1,
            scale: 1,
            x: 0,
            y: 0,
            dragging: false,
            dragStartX: 0,
            dragStartY: 0,
            startX: 0,
            startY: 0
        };
        cropTitle.textContent = options.title;
        cropFrame.classList.toggle('is-profile', options.kind === 'profile');
        cropFrame.classList.toggle('is-cover', options.kind === 'cover');
        cropZoom.value = '1';
        cropImage.onload = function () {
            cropState.imageW = cropImage.naturalWidth;
            cropState.imageH = cropImage.naturalHeight;
            cropModal.classList.add('is-open');
            cropModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('perfil-crop-open');
            requestAnimationFrame(fitCropImage);
        };
        cropImage.src = src;
    }

    function openCropperFromUrl(input, imageUrl, options) {
        if (!imageUrl) {
            return;
        }
        fetch(imageUrl, { cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('No se pudo cargar la imagen actual.');
                }
                return response.blob();
            })
            .then(function (blob) {
                const file = new File([blob], options.prefix + '-actual.jpg', { type: blob.type || 'image/jpeg' });
                openCropper(input, file, options);
            })
            .catch(function () {
                if (options.status) {
                    options.status.textContent = 'No se pudo abrir la imagen actual para ajustarla.';
                    options.status.classList.add('perfil-file-ready');
                }
            });
    }

    function closeCropper(clearInput) {
        if (clearInput && cropState && cropState.input) {
            cropState.input.value = '';
        }
        if (cropImage) {
            cropImage.removeAttribute('src');
        }
        if (cropModal) {
            cropModal.classList.remove('is-open');
            cropModal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('perfil-crop-open');
        cropState = null;
    }

    function acceptCrop() {
        if (!cropState) {
            return;
        }
        const frameW = cropFrame.clientWidth;
        const frameH = cropFrame.clientHeight;
        const canvas = document.createElement('canvas');
        canvas.width = cropState.options.outputW;
        canvas.height = cropState.options.outputH;
        const ctx = canvas.getContext('2d');
        const ratioX = canvas.width / frameW;
        const ratioY = canvas.height / frameH;
        ctx.drawImage(
            cropImage,
            cropState.x * ratioX,
            cropState.y * ratioY,
            cropState.imageW * cropState.scale * ratioX,
            cropState.imageH * cropState.scale * ratioY
        );
        canvas.toBlob(function (blob) {
            if (!blob || !cropState) {
                return;
            }
            const name = cropState.options.prefix + '-' + Date.now() + '.jpg';
            const croppedFile = new File([blob], name, { type: 'image/jpeg' });
            setInputFile(cropState.input, croppedFile);
            cropState.options.after(URL.createObjectURL(blob), croppedFile);
            setStatus(cropState.options.status, croppedFile);
            closeCropper(false);
        }, 'image/jpeg', 0.9);
    }

    function bindCropInput(input, options) {
        if (!input) {
            return;
        }
        input.addEventListener('change', function () {
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }
            if (!file.type || !file.type.startsWith('image/')) {
                input.value = '';
                return;
            }
            openCropper(input, file, options);
        });
    }

    cropZoom && cropZoom.addEventListener('input', function () {
        if (!cropState) {
            return;
        }
        const frameW = cropFrame.clientWidth;
        const frameH = cropFrame.clientHeight;
        const centerX = frameW / 2 - cropState.x;
        const centerY = frameH / 2 - cropState.y;
        const oldScale = cropState.scale;
        cropState.scale = cropState.baseScale * Number(cropZoom.value || 1);
        cropState.x = frameW / 2 - centerX * (cropState.scale / oldScale);
        cropState.y = frameH / 2 - centerY * (cropState.scale / oldScale);
        renderCrop();
    });

    cropFrame && cropFrame.addEventListener('pointerdown', function (event) {
        if (!cropState) {
            return;
        }
        cropState.dragging = true;
        cropState.dragStartX = event.clientX;
        cropState.dragStartY = event.clientY;
        cropState.startX = cropState.x;
        cropState.startY = cropState.y;
        cropFrame.setPointerCapture(event.pointerId);
    });

    cropFrame && cropFrame.addEventListener('pointermove', function (event) {
        if (!cropState || !cropState.dragging) {
            return;
        }
        cropState.x = cropState.startX + event.clientX - cropState.dragStartX;
        cropState.y = cropState.startY + event.clientY - cropState.dragStartY;
        renderCrop();
    });

    cropFrame && cropFrame.addEventListener('pointerup', function () {
        if (cropState) {
            cropState.dragging = false;
        }
    });

    cropCancelButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            closeCropper(true);
        });
    });

    cropAccept && cropAccept.addEventListener('click', acceptCrop);

    const profileCropOptions = {
        kind: 'profile',
        title: 'Ajusta tu foto de perfil',
        prefix: 'perfil',
        outputW: 700,
        outputH: 700,
        status: perfilEstado,
        after: function (imageUrl) {
            const removeProfile = document.querySelector('input[name="quitar_imagen_perfil"]');
            if (removeProfile) {
                removeProfile.checked = false;
            }
            if (avatarPreview) {
                avatarPreview.innerHTML = '<img src="' + imageUrl + '" alt="">';
            }
        }
    };

    const coverCropOptions = {
        kind: 'cover',
        title: 'Ajusta tu imagen de cabecera',
        prefix: 'cabecera',
        outputW: 1800,
        outputH: 430,
        status: cabeceraEstado,
        after: function (imageUrl) {
            const removeCover = document.querySelector('input[name="quitar_imagen_cabecera"]');
            if (removeCover) {
                removeCover.checked = false;
            }
            if (hero) {
                hero.style.setProperty('--perfil-cover', "url('" + imageUrl + "')");
            }
        }
    };

    bindCropInput(perfilInput, profileCropOptions);
    bindCropInput(cabeceraInput, coverCropOptions);

    adjustProfileButton && adjustProfileButton.addEventListener('click', function () {
        openCropperFromUrl(perfilInput, currentProfileUrl, profileCropOptions);
    });

    adjustCoverButton && adjustCoverButton.addEventListener('click', function () {
        openCropperFromUrl(cabeceraInput, currentCoverUrl, coverCropOptions);
    });
});
</script>

<?php
mysqli_close($conn);
include_once __DIR__ . "/../componentes/pie_pagina.php";
?>
