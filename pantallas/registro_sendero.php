<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/rutas.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $idParaRetorno = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($idParaRetorno > 0) {
        $_SESSION['redirect_after_login'] = BASE_URL . "pantallas/registro_sendero.php?id=" . $idParaRetorno;
    }
    $_SESSION['error_message'] = "Inicia sesion para registrarte en este sendero.";
    header('Location: ' . sg_clean_url('pantallas/inicio_sesion.php'));
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/actualizar_estado_senderos.php';
require_once __DIR__ . '/../componentes/configuracion_sitio.php';
require_once __DIR__ . '/../componentes/menores_sendero.php';

sg_actualizar_senderos_vencidos($conn);

$idSendero = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idSendero <= 0) {
    header("Location: " . BASE_URL . "pantallas/senderos.php");
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function selected_value($current, string $value): string
{
    return (string) $current === $value ? 'selected' : '';
}

function checked_value($current, string $value): string
{
    return (string) $current === $value ? 'checked' : '';
}

function dinero_registro($monto): string
{
    return 'RD$ ' . number_format((float) $monto, 2);
}

function registro_crear_tabla_menores(mysqli $conn): void
{
    // La estructura se gestiona desde scripts_bd/migracion_estructura_configuracion_2026_06_17.sql.
}

function registro_sendero_disponible(array $sendero): bool
{
    if (($sendero['estado'] ?? '') !== 'pendiente' || empty($sendero['fecha_sendero'])) {
        return false;
    }

    $hoy = new DateTime('today');
    $fechaSendero = new DateTime((string) $sendero['fecha_sendero']);
    return $fechaSendero >= $hoy;
}

function perfil_senderista_completo(array $detalle): bool
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

registro_crear_tabla_menores($conn);

$stmt = mysqli_prepare($conn, "SELECT * FROM senderos WHERE id = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $idSendero);
mysqli_stmt_execute($stmt);
$sendero = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$sendero) {
    header("Location: " . BASE_URL . "pantallas/senderos.php");
    exit;
}

if (!registro_sendero_disponible($sendero)) {
    $_SESSION['registro_sendero_error'] = "Este sendero ya no esta disponible para registro.";
    header("Location: " . BASE_URL . "pantallas/senderos_detalle.php?id=" . (int) $idSendero);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, nombre, apellido, email FROM usuarios WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

require_once __DIR__ . '/../componentes/directiva.php';
$esDirectiva = sg_usuario_es_directiva($conn, (int) $usuarioId);

if (!$usuario) {
    $_SESSION['error_message'] = "No se pudo cargar tu usuario. Inicia sesion nuevamente.";
    header('Location: ' . sg_clean_url('pantallas/inicio_sesion.php'));
    exit;
}

$detalle = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$detalle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
mysqli_stmt_close($stmt);

if (!perfil_senderista_completo($detalle)) {
    $_SESSION['perfil_senderista_info'] = "Completa tus datos de senderista antes de reservar este sendero.";
    header("Location: " . BASE_URL . "pantallas/completar_perfil.php?sendero_id=" . (int) $idSendero);
    exit;
}

$formData = $detalle;
$oldData = $_SESSION['registro_sendero_old'] ?? null;

$registroExistente = false;
$registroInversionId = 0;
$registroChalecoTallaId = 0;
$registroPuntoEncuentroId = 0;
$registroComprobanteRuta = '';
$registroComprobanteNombre = '';
$registroId = 0;
$stmt = mysqli_prepare($conn, "SELECT id, inversion_id, chaleco_talla_id, sendero_punto_encuentro_id, comprobante_pago_ruta, comprobante_pago_nombre FROM registros_senderos WHERE usuario_id = ? AND sendero_id = ? AND estado = 'registrado' LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $usuarioId, $idSendero);
mysqli_stmt_execute($stmt);
$registroRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$registroExistente = (bool) $registroRow;
$registroId = (int) ($registroRow['id'] ?? 0);
$registroInversionId = (int) ($registroRow['inversion_id'] ?? 0);
$registroChalecoTallaId = (int) ($registroRow['chaleco_talla_id'] ?? 0);
$registroPuntoEncuentroId = (int) ($registroRow['sendero_punto_encuentro_id'] ?? 0);
$registroComprobanteRuta = trim((string) ($registroRow['comprobante_pago_ruta'] ?? ''));
$registroComprobanteNombre = trim((string) ($registroRow['comprobante_pago_nombre'] ?? ''));
mysqli_stmt_close($stmt);

$puntosEncuentro = [];
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nombre_punto, direccion_referencia, hora_encuentro, hora_salida, url_mapa
     FROM sendero_puntos_encuentro
     WHERE sendero_id = ? AND activo = 1
     ORDER BY orden ASC, id ASC"
);
mysqli_stmt_bind_param($stmt, "i", $idSendero);
mysqli_stmt_execute($stmt);
$resPuntos = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($resPuntos)) {
    $puntosEncuentro[] = $row;
}
mysqli_stmt_close($stmt);

$tallasChalecos = [];
if ((int) ($sendero['incluye_chaleco_salvavidas'] ?? 0) === 1) {
    $resTallas = mysqli_query(
        $conn,
        "SELECT id, nombre, descripcion
         FROM tallas_chalecos_salvavidas
         WHERE activo = 1
         ORDER BY orden ASC, nombre ASC"
    );
    while ($resTallas && $row = mysqli_fetch_assoc($resTallas)) {
        $tallasChalecos[] = $row;
    }
}

$menoresRegistrados = [];
if ($registroId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM registro_sendero_menores WHERE registro_id = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, "i", $registroId);
    mysqli_stmt_execute($stmt);
    $resMenores = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($resMenores)) {
        $menoresRegistrados[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$menoresFrecuentes = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM menores_usuarios WHERE usuario_id = ? AND activo = 1 ORDER BY nombre ASC, apellido ASC, id ASC");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$resMenoresFrecuentes = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($resMenoresFrecuentes)) {
    $row['menor_usuario_id'] = (int) $row['id'];
    $menoresFrecuentes[] = $row;
}
mysqli_stmt_close($stmt);

if (is_array($oldData) && (int) ($oldData['sendero_id'] ?? 0) === $idSendero) {
    $formData = array_merge($formData, $oldData);
    if (isset($oldData['menores']) && is_array($oldData['menores'])) {
        $menoresRegistrados = $oldData['menores'];
    }
    unset($_SESSION['registro_sendero_old']);
}

$inversiones = [];
$stmt = mysqli_prepare($conn, "SELECT id, nombre, descripcion, monto, monto_menor, fecha_limite_pago, orden FROM sendero_inversiones WHERE sendero_id = ? AND es_menor = 0 AND activo = 1 ORDER BY orden ASC, id ASC");
mysqli_stmt_bind_param($stmt, "i", $idSendero);
mysqli_stmt_execute($stmt);
$resInversiones = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($resInversiones)) {
    $inversiones[] = $row;
}
mysqli_stmt_close($stmt);

$inversionesMenores = [];
$stmt = mysqli_prepare($conn, "SELECT id, nombre, descripcion, monto, fecha_limite_pago, orden FROM sendero_inversiones WHERE sendero_id = ? AND es_menor = 1 AND activo = 1 ORDER BY orden ASC, id ASC");
mysqli_stmt_bind_param($stmt, "i", $idSendero);
mysqli_stmt_execute($stmt);
$resInversionesMenores = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($resInversionesMenores)) {
    $inversionesMenores[] = $row;
}
mysqli_stmt_close($stmt);

$menoresMismaInversion = (int) ($sendero['menores_misma_inversion'] ?? 1) === 1;
$inversionesDisponiblesMenores = $menoresMismaInversion ? $inversiones : $inversionesMenores;
foreach ($inversionesDisponiblesMenores as &$inversionMenorDisponible) {
    $inversionMenorDisponible['monto_mostrar'] = $menoresMismaInversion
        ? ($inversionMenorDisponible['monto_menor'] ?? $inversionMenorDisponible['monto'])
        : $inversionMenorDisponible['monto'];
}
unset($inversionMenorDisponible);
$menoresAdmitidos = sg_menores_admitidos($sendero);

$siteConfig = sg_site_config($conn);
$montosInversion = array_map(static fn(array $item): float => (float) ($item['monto'] ?? 0), $inversiones);
$montoMinimoInversion = $montosInversion ? min($montosInversion) : 0.0;
$montoMaximoInversion = $montosInversion ? max($montosInversion) : 0.0;
$mostrarAlertaInversion = (int) ($siteConfig['alerta_inversion_economica_activa'] ?? 1) === 1
    && count($inversiones) > 1
    && $montoMaximoInversion > $montoMinimoInversion;

$pageTitle = "Registro de Sendero";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/registro_sendero.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/registro_sendero.js"
];

$consentimientoTexto = (string) $siteConfig['consentimiento_informado_texto'];
$rgpdTexto = (string) $siteConfig['rgpd_texto'];
$politicaPrivacidadTexto = (string) $siteConfig['politica_privacidad_texto'];

$rangosEdad = ['0-18', '19-30', '31-40', '41-50', '51-60', '61+'];
$gruposSanguineos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
$experiencias = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
$vias = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'Google', 'Amigos', 'Otro'];
$menoresJson = h(json_encode(array_values($menoresRegistrados), JSON_UNESCAPED_UNICODE));
$menoresFrecuentesJson = h(json_encode(array_values($menoresFrecuentes), JSON_UNESCAPED_UNICODE));

include_once __DIR__ . "/../componentes/encabezado.php";
include_once __DIR__ . "/../componentes/barra_navegacion.php";
?>

<main class="registro-sendero-page">
    <section class="registro-shell">
        <a class="registro-back" href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $sendero['id'] ?>">
            <i data-feather="arrow-left"></i>
            Volver al detalle
        </a>

        <div class="registro-header">
            <span class="registro-kicker">Registro de participante</span>
            <h1><?= h($sendero['nombre']) ?></h1>
            <p><?= h($sendero['lugar']) ?><?= !empty($sendero['provincia']) ? ', ' . h($sendero['provincia']) : '' ?> | <?= date('d/m/Y', strtotime($sendero['fecha_sendero'])) ?></p>
        </div>

        <?php if (!empty($_SESSION['registro_sendero_error'])): ?>
            <div class="registro-alert error"><?= h($_SESSION['registro_sendero_error']) ?></div>
            <?php unset($_SESSION['registro_sendero_error']); ?>
        <?php endif; ?>

        <div class="registro-alert error client-validation-summary" data-validation-summary hidden></div>

        <?php if (!empty($_SESSION['registro_sendero_info'])): ?>
            <div class="registro-alert success"><?= h($_SESSION['registro_sendero_info']) ?></div>
            <?php unset($_SESSION['registro_sendero_info']); ?>
        <?php endif; ?>

        <?php if ($registroExistente): ?>
            <div class="registro-alert success">Ya tienes un registro activo para este sendero. Puedes actualizar tus datos y enviarlos nuevamente.</div>
        <?php endif; ?>

        <form class="registro-card" method="POST" action="<?= BASE_URL ?>procesos/proceso_registro_sendero.php" enctype="multipart/form-data" novalidate<?= $esDirectiva ? ' data-directiva' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="sendero_id" value="<?= (int) $sendero['id'] ?>">

            <section class="registro-section">
                <div class="section-title-row">
                    <span>1</span>
                    <div>
                        <h2>Datos del senderista</h2>
                        <p>Estos datos estan guardados en tu cuenta. Si necesitas cambiarlos, contacta al administrador o actualizalos desde tu perfil.</p>
                    </div>
                </div>

                <div class="registro-grid details-summary-grid">
                    <div class="detail-summary-card"><span>Nombre</span><strong><?= h($usuario['nombre'] . ' ' . $usuario['apellido']) ?></strong></div>
                    <div class="detail-summary-card"><span>Correo</span><strong><?= h($usuario['email']) ?></strong></div>
                    <div class="detail-summary-card"><span>Telefono</span><strong><?= h($detalle['telefono'] ?? 'Sin registrar') ?></strong></div>
                </div>
            </section>
            <section class="registro-section">
                <div class="section-title-row">
                    <span>2</span>
                    <div>
                        <h2><?= $esDirectiva ? 'Participacion de la directiva' : 'Tipo de inversion' ?></h2>
                        <p><?= $esDirectiva ? 'Como miembro de la directiva tu participacion no tiene cobro. Si llevas menores, ellos si pagan su inversion.' : 'Elige la opcion con la que deseas reservar tu cupo.' ?></p>
                    </div>
                </div>

                <?php if ($esDirectiva): ?>
                    <div class="directiva-aviso">
                        <i data-feather="award" aria-hidden="true"></i>
                        <div><strong>Sin cobro para ti</strong><span>Tu cupo queda registrado como exento. No necesitas elegir inversion ni adjuntar comprobante.</span></div>
                    </div>
                <?php endif; ?>

                <?php if (!$esDirectiva): ?>
                <div class="investment-choice-grid" data-validation-anchor="inversion_id">
                    <?php if (empty($inversiones)): ?>
                        <div class="registro-alert error">Este sendero aun no tiene inversiones disponibles.</div>
                    <?php endif; ?>
                    <?php foreach ($inversiones as $idx => $inversion): ?>
                        <?php
                        $selectedInvestment = (int) ($formData['inversion_id'] ?? $registroInversionId) === (int) $inversion['id'];
                        $numeroInversion = (int) ($inversion['orden'] ?? ($idx + 1));
                        $tituloInversion = 'Inversion ' . $numeroInversion;
                        $nombreOpcional = trim((string) ($inversion['nombre'] ?? ''));
                        $mostrarNombreOpcional = $nombreOpcional !== '' && strcasecmp($nombreOpcional, $tituloInversion) !== 0;
                        ?>
                        <?php $esInversionEconomica = $mostrarAlertaInversion && (float) $inversion['monto'] === $montoMinimoInversion; ?>
                        <label class="investment-choice-card">
                            <input type="radio" name="inversion_id" value="<?= (int) $inversion['id'] ?>" <?= $esInversionEconomica ? 'data-low-investment="1"' : '' ?> <?= $selectedInvestment ? 'checked' : '' ?> required>
                            <span>
                                <i class="selection-light" aria-hidden="true"></i>
                                <strong><?= h($tituloInversion) ?></strong>
                                <b><?= h(dinero_registro($inversion['monto'])) ?></b>
                                <?php if ($mostrarNombreOpcional): ?>
                                    <small><?= h($nombreOpcional) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($inversion['descripcion'])): ?>
                                    <small><?= h($inversion['descripcion']) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="field-validation-message" data-validation-message="inversion_id" role="alert" hidden></div>
                <?php endif; ?>

                <?php if ((int) ($sendero['incluye_chaleco_salvavidas'] ?? 0) === 1): ?>
                    <div class="life-jacket-choice">
                        <div class="life-jacket-icon" aria-hidden="true">
                            <i data-feather="shield"></i>
                        </div>
                        <div class="life-jacket-copy">
                            <strong>Chaleco salvavidas incluido</strong>
                            <span>Selecciona la talla que necesitas para esta actividad.</span>
                        </div>
                        <label class="field life-jacket-field" for="chaleco_talla_id">
                            <span>Elegir talla de chaleco salvavidas *</span>
                            <select id="chaleco_talla_id" name="chaleco_talla_id" required>
                                <option value="">Selecciona tu talla...</option>
                                <?php foreach ($tallasChalecos as $talla): ?>
                                    <?php $selectedTalla = (int) ($formData['chaleco_talla_id'] ?? $registroChalecoTallaId) === (int) $talla['id']; ?>
                                    <option value="<?= (int) $talla['id'] ?>" <?= $selectedTalla ? 'selected' : '' ?>>
                                        <?= h($talla['nombre']) ?><?= !empty($talla['descripcion']) ? ' - ' . h($talla['descripcion']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-validation-message" data-validation-message="chaleco_talla_id" role="alert" hidden></small>
                        </label>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($puntosEncuentro)): ?>
                <section class="registro-section meeting-point-section">
                    <div class="section-title-row">
                        <span>3</span>
                        <div>
                            <h2>Punto de encuentro</h2>
                            <p>Selecciona el lugar donde te recogeremos. Esta eleccion tambien aplica a tus menores acompanantes.</p>
                        </div>
                    </div>

                    <div class="meeting-point-grid" data-validation-anchor="sendero_punto_encuentro_id">
                        <?php foreach ($puntosEncuentro as $punto): ?>
                            <?php $puntoSeleccionado = (int) ($formData['sendero_punto_encuentro_id'] ?? $registroPuntoEncuentroId) === (int) $punto['id']; ?>
                            <label class="meeting-point-choice">
                                <input type="radio"
                                       name="sendero_punto_encuentro_id"
                                       value="<?= (int) $punto['id'] ?>"
                                       <?= $puntoSeleccionado ? 'checked' : '' ?>
                                       required>
                                <span class="meeting-point-card">
                                    <i class="selection-light" aria-hidden="true"></i>
                                    <span class="meeting-point-marker" aria-hidden="true"><i data-feather="map-pin"></i></span>
                                    <span class="meeting-point-copy">
                                        <strong><?= h($punto['nombre_punto']) ?></strong>
                                        <?php if (!empty($punto['direccion_referencia'])): ?>
                                            <small><?= h($punto['direccion_referencia']) ?></small>
                                        <?php endif; ?>
                                        <span class="meeting-point-times">
                                            <b>Encuentro <?= h(date('h:i A', strtotime($punto['hora_encuentro']))) ?></b>
                                            <b>Salida <?= h(date('h:i A', strtotime($punto['hora_salida']))) ?></b>
                                        </span>
                                        <?php if (!empty($punto['url_mapa'])): ?>
                                            <a href="<?= h($punto['url_mapa']) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();">
                                                <i data-feather="external-link"></i>
                                                Ver ubicacion
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="field-validation-message" data-validation-message="sendero_punto_encuentro_id" role="alert" hidden></div>
                </section>
            <?php endif; ?>

            <?php if (!$esDirectiva): ?>
            <section class="registro-section payment-proof-section">
                <div class="section-title-row">
                    <span><?= !empty($puntosEncuentro) ? 4 : 3 ?></span>
                    <div>
                        <h2>Comprobante de pago</h2>
                        <p>Adjunta una imagen o PDF del pago realizado. Este campo es opcional.</p>
                    </div>
                </div>

                <div class="payment-proof-box">
                    <div class="payment-proof-icon" aria-hidden="true">
                        <i data-feather="paperclip"></i>
                    </div>
                    <label class="payment-proof-field" for="comprobante_pago">
                        <strong><?= $registroComprobanteRuta !== '' ? 'Reemplazar comprobante' : 'Adjuntar comprobante' ?></strong>
                        <span data-proof-file-name>JPG, PNG, WEBP o PDF. Maximo 8 MB.</span>
                        <input id="comprobante_pago"
                               type="file"
                               name="comprobante_pago"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                               data-proof-input>
                    </label>

                    <?php if ($registroComprobanteRuta !== ''): ?>
                        <a class="payment-proof-current"
                           href="<?= BASE_URL ?>procesos/proceso_ver_comprobante_pago.php?registro_id=<?= (int) $registroId ?>"
                           target="_blank"
                           rel="noopener">
                            <i data-feather="file-text"></i>
                            <span>
                                <small>Comprobante actual</small>
                                <strong><?= h($registroComprobanteNombre !== '' ? $registroComprobanteNombre : 'Ver archivo') ?></strong>
                            </span>
                        </a>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!$menoresAdmitidos): ?>
            <section class="registro-section minors-section">
                <div class="section-title-row">
                    <span><?= (!empty($puntosEncuentro) ? 5 : 4) - ($esDirectiva ? 1 : 0) ?></span>
                    <div>
                        <h2>Menores acompanantes</h2>
                        <p>Este sendero no admite menores de edad.</p>
                    </div>
                </div>
            </section>
            <?php else: ?>
            <section class="registro-section minors-section" data-minors-root data-minors='<?= $menoresJson ?>' data-saved-minors='<?= $menoresFrecuentesJson ?>'>
                <div class="section-title-row">
                    <span><?= (!empty($puntosEncuentro) ? 5 : 4) - ($esDirectiva ? 1 : 0) ?></span>
                    <div>
                        <h2>Menores acompanantes</h2>
                        <p>Si llevaras ninos o adolescentes bajo tu responsabilidad, agregalos aqui antes de enviar el registro.</p>
                    </div>
                </div>

                <div class="minors-toolbar">
                    <div>
                        <strong data-minors-count>0 menores agregados</strong>
                        <small>Quedaran asociados a tu registro como adulto responsable.</small>
                    </div>
                    <button class="btn-secondary minors-open-btn" type="button" data-open-minors-modal>
                        <i data-feather="users"></i>
                        Agregar menores
                    </button>
                </div>

                <div class="minors-summary" data-minors-summary>
                    <div class="minors-empty">
                        <i data-feather="user-plus"></i>
                        <span>No has agregado menores para este sendero.</span>
                    </div>
                </div>

                <div data-minors-fields></div>
            </section>
            <?php endif; ?>

            <section class="registro-section">
                <div class="section-title-row">
                    <span><?= (!empty($puntosEncuentro) ? 6 : 5) - ($esDirectiva ? 1 : 0) ?></span>
                    <div>
                        <h2>Consentimientos</h2>
                        <p>Debes aceptar estos terminos para completar el registro.</p>
                    </div>
                </div>

                <div class="consent-box consent-box-large">
                    <label class="consent-accept" for="consentimiento">
                        <input id="consentimiento" type="checkbox" name="consentimiento" value="1" <?= !empty($formData['consentimiento']) ? 'checked' : '' ?> required>
                        <span class="consent-check" aria-hidden="true"></span>
                        <span class="consent-copy">
                            <strong>Consentimiento informado *</strong>
                            <small>Lee y acepta las condiciones de participacion antes de enviar tu registro.</small>
                        </span>
                    </label>
                    <button class="consent-toggle" type="button" aria-expanded="false" aria-controls="consentimientoTexto" data-collapsed-label="Leer consentimiento completo" data-expanded-label="Ocultar consentimiento">
                        <span>Leer consentimiento completo</span>
                        <i data-feather="chevron-down"></i>
                    </button>
                    <div class="consent-readable" id="consentimientoTexto" hidden><?= nl2br(h($consentimientoTexto)) ?></div>
                </div>
                <div class="field-validation-message" data-validation-message="consentimiento" role="alert" hidden></div>

                <label class="consent-box">
                    <input type="checkbox" name="rgpd" value="1" <?= !empty($formData['rgpd']) ? 'checked' : '' ?> required>
                    <span class="consent-check" aria-hidden="true"></span>
                    <span class="consent-copy">
                        <strong>Acuerdo RGPD *</strong>
                        <span><?= h($rgpdTexto) ?></span>
                    </span>
                </label>
                <button class="consent-toggle privacy-toggle" type="button" aria-expanded="false" aria-controls="politicaPrivacidadTexto" data-collapsed-label="Leer politica de privacidad" data-expanded-label="Ocultar politica de privacidad">
                    <span>Leer politica de privacidad</span>
                    <i data-feather="chevron-down"></i>
                </button>
                <div class="consent-readable privacy-readable" id="politicaPrivacidadTexto" hidden><?= nl2br(h($politicaPrivacidadTexto)) ?></div>
                <div class="field-validation-message" data-validation-message="rgpd" role="alert" hidden></div>
            </section>

            <div class="registro-actions">
                <a class="btn-secondary" href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $sendero['id'] ?>">Cancelar</a>
                <button class="btn-primary" type="submit"><?= $registroExistente ? 'Actualizar registro' : 'Enviar registro' ?></button>
            </div>
        </form>
    </section>

    <div class="minor-modal" data-minors-modal hidden>
        <div class="minor-modal-backdrop" data-close-minors-modal></div>
        <section class="minor-modal-panel" role="dialog" aria-modal="true" aria-labelledby="minorModalTitle">
            <header class="minor-modal-header">
                <div>
                    <span class="registro-kicker">Acompanantes menores</span>
                    <h2 id="minorModalTitle">Registrar menores</h2>
                    <p>Agrega los datos de cada menor que ira bajo la responsabilidad del adulto registrado.</p>
                </div>
                <button type="button" class="minor-modal-close" data-close-minors-modal aria-label="Cerrar modal">
                    <i data-feather="x"></i>
                </button>
            </header>

            <div class="minor-modal-body">
                <div class="saved-minors-panel" data-saved-minors-panel hidden>
                    <div>
                        <strong>Menores guardados en tu perfil</strong>
                        <small>Selecciona uno o varios para agregarlos a este sendero.</small>
                    </div>
                    <div class="saved-minors-list" data-saved-minors-list></div>
                </div>
                <div class="minor-editor-list" data-minors-editor></div>
            </div>

            <footer class="minor-modal-footer">
                <button type="button" class="btn-secondary" data-add-minor>
                    <i data-feather="plus"></i>
                    Agregar otro menor
                </button>
                <button type="button" class="btn-primary" data-save-minors>
                    Guardar menores
                </button>
            </footer>
        </section>
    </div>

    <?php if ($mostrarAlertaInversion): ?>
        <div class="investment-warning-modal" data-investment-warning hidden>
            <div class="investment-warning-backdrop"></div>
            <section class="investment-warning-panel" role="dialog" aria-modal="true" aria-labelledby="investmentWarningTitle">
                <span class="investment-warning-icon"><i data-feather="alert-triangle"></i></span>
                <div>
                    <span class="registro-kicker">Antes de continuar</span>
                    <h2 id="investmentWarningTitle">Revisa esta inversion</h2>
                    <p><?= h($siteConfig['alerta_inversion_economica_texto'] ?? '') ?></p>
                </div>
                <button type="button" class="btn-primary" data-accept-investment-warning>Aceptar</button>
            </section>
        </div>
    <?php endif; ?>

    <template id="minorFormTemplate">
        <article class="minor-form-card" data-minor-card>
            <div class="minor-card-head">
                <div>
                    <strong data-minor-title>Menor</strong>
                    <small data-minor-summary>Datos de salud y contacto de emergencia.</small>
                </div>
                <div class="minor-card-actions">
                    <button type="button" class="minor-collapse-btn" data-toggle-minor-card aria-expanded="true">Ocultar</button>
                    <button type="button" class="minor-remove-btn" data-remove-minor>
                        <i data-feather="trash-2"></i>
                        Quitar
                    </button>
                </div>
            </div>

            <div data-minor-card-content>
            <div class="minor-grid">
                <input type="hidden" data-field="menor_usuario_id">
                <label class="field">
                    <span>Nombre *</span>
                    <input type="text" data-field="nombre" maxlength="100" required placeholder="Nombre del menor">
                </label>
                <label class="field">
                    <span>Apellido *</span>
                    <input type="text" data-field="apellido" maxlength="100" required placeholder="Apellido del menor">
                </label>
                <label class="field">
                    <span>Telefono</span>
                    <input type="tel" data-field="telefono" maxlength="30" placeholder="Opcional">
                </label>
                <label class="field">
                    <span>Edad *</span>
                    <select data-field="rango_edad" required>
                        <option value="">Selecciona...</option>
                        <option value="8-12">8 - 12</option>
                        <option value="13-17">13 - 17</option>
                    </select>
                </label>
                <label class="field">
                    <span>Tipo de inversion del menor *</span>
                    <select data-field="inversion_id" required>
                        <option value="">Selecciona...</option>
                        <?php foreach ($inversionesDisponiblesMenores as $idx => $inversion): ?>
                            <?php
                            $numeroInversionMenor = (int) ($inversion['orden'] ?? ($idx + 1));
                            $tituloInversionMenor = 'Inversion ' . $numeroInversionMenor;
                            $nombreInversionMenor = trim((string) ($inversion['nombre'] ?? ''));
                            $labelInversionMenor = $nombreInversionMenor !== '' && strcasecmp($nombreInversionMenor, $tituloInversionMenor) !== 0
                                ? $tituloInversionMenor . ' - ' . $nombreInversionMenor
                                : $tituloInversionMenor;
                            ?>
                            <option value="<?= (int) $inversion['id'] ?>"><?= h($labelInversionMenor . ' | ' . dinero_registro($inversion['monto_mostrar'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Grupo sanguineo *</span>
                    <select data-field="grupo_sanguineo" required>
                        <option value="">Selecciona...</option>
                        <?php foreach ($gruposSanguineos as $grupo): ?>
                            <option value="<?= h($grupo) ?>"><?= h($grupo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Experiencia *</span>
                    <select data-field="experiencia_senderismo" required>
                        <option value="">Elige una respuesta</option>
                        <?php foreach ($experiencias as $experiencia): ?>
                            <option value="<?= h($experiencia) ?>"><?= h($experiencia) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="minor-grid two">
                <label class="field">
                    <span>Es alergico? *</span>
                    <select data-field="es_alergico" required>
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>
                </label>
                <label class="field minor-conditional-detail" data-minor-conditional="alergias_detalle" hidden>
                    <span>Especifique alergias</span>
                    <input type="text" data-field="alergias_detalle" maxlength="255" placeholder="Obligatorio si es alergico">
                </label>
            </div>

            <div class="minor-grid two">
                <label class="field">
                    <span>Padece alguna enfermedad? *</span>
                    <select data-field="tiene_enfermedad" required>
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>
                </label>
                <label class="field minor-conditional-detail" data-minor-conditional="enfermedad" hidden>
                    <span>Especifique la enfermedad</span>
                    <input type="text" data-field="enfermedad" maxlength="255" placeholder="Obligatorio si padece una enfermedad">
                </label>
            </div>

            <div class="minor-grid two">
                <label class="field">
                    <span>Tiene seguro medico? *</span>
                    <select data-field="tiene_seguro_medico" required>
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>
                </label>
                <label class="field minor-conditional-detail" data-minor-conditional="seguro_medico" hidden>
                    <span>Nombre o detalle del seguro</span>
                    <input type="text" data-field="seguro_medico" maxlength="255" placeholder="Obligatorio si tiene seguro medico">
                </label>
            </div>

            <div class="minor-grid">
                <label class="field">
                    <span>Contacto emergencia *</span>
                    <input type="text" data-field="emergencia_nombre" maxlength="150" required placeholder="Nombre completo">
                </label>
                <label class="field">
                    <span>Parentesco *</span>
                    <input type="text" data-field="emergencia_parentesco" maxlength="80" required placeholder="Ej: Madre, Padre, Tio">
                </label>
                <label class="field">
                    <span>Telefono emergencia *</span>
                    <input type="tel" data-field="emergencia_telefono" maxlength="30" required placeholder="Sin guiones">
                </label>
            </div>
            </div>
        </article>
    </template>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . "/../componentes/pie_pagina.php";
?>
