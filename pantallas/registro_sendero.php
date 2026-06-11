<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $idParaRetorno = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($idParaRetorno > 0) {
        $_SESSION['redirect_after_login'] = BASE_URL . "pantallas/registro_sendero.php?id=" . $idParaRetorno;
    }
    $_SESSION['error_message'] = "Inicia sesion para registrarte en este sendero.";
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

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

$stmt = mysqli_prepare($conn, "SELECT id, nombre, fecha_sendero, lugar, provincia, estado FROM senderos WHERE id = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $idSendero);
mysqli_stmt_execute($stmt);
$sendero = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$sendero) {
    header("Location: " . BASE_URL . "pantallas/senderos.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, nombre, apellido, email FROM usuarios WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$usuario) {
    $_SESSION['error_message'] = "No se pudo cargar tu usuario. Inicia sesion nuevamente.";
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
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
if (is_array($oldData) && (int) ($oldData['sendero_id'] ?? 0) === $idSendero) {
    $formData = array_merge($formData, $oldData);
    unset($_SESSION['registro_sendero_old']);
}

$registroExistente = false;
$registroInversionId = 0;
$stmt = mysqli_prepare($conn, "SELECT id, inversion_id FROM registros_senderos WHERE usuario_id = ? AND sendero_id = ? AND estado = 'registrado' LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $usuarioId, $idSendero);
mysqli_stmt_execute($stmt);
$registroRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$registroExistente = (bool) $registroRow;
$registroInversionId = (int) ($registroRow['inversion_id'] ?? 0);
mysqli_stmt_close($stmt);

$inversiones = [];
$stmt = mysqli_prepare($conn, "SELECT id, nombre, descripcion, monto, fecha_limite_pago, orden FROM sendero_inversiones WHERE sendero_id = ? AND activo = 1 ORDER BY orden ASC, id ASC");
mysqli_stmt_bind_param($stmt, "i", $idSendero);
mysqli_stmt_execute($stmt);
$resInversiones = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($resInversiones)) {
    $inversiones[] = $row;
}
mysqli_stmt_close($stmt);

$pageTitle = "Registro de Sendero | Senderismo Go!";
$cssFiles = [
    "css/global.css",
    "css/barra_navegacion.css",
    "css/registro_sendero.css"
];
$jsFiles = [
    "js/barra_navegacion.js",
    "js/registro_sendero.js"
];

$consentimientoTexto = "Estoy de acuerdo que: Yo, siendo mayor de edad, en pleno uso de mis facultades y con total capacidad para comprender el contenido de este documento, declaro que he leido y entiendo completamente la informacion proporcionada en esta pagina sobre la actividad en la que participare. Reconozco que se trata de una actividad fisicamente exigente y que conlleva riesgos inherentes a su naturaleza. Estoy consciente de los posibles desafios y riesgos involucrados, incluyendo aquellos relacionados con el esfuerzo fisico, las condiciones del terreno y cualquier otro factor mencionado en la informacion suministrada. Asimismo, acepto que, en caso de emergencia medica, el acceso a asistencia puede estar sujeto a condiciones y tiempos de respuesta variables. Acepto y doy mi consentimiento a recibir asistencia de primeros auxilios por el personal de la directiva de ser necesario. Comprendo que la organizacion y su personal no son responsables de los riesgos que pudiera enfrentar debido a mi participacion o al incumplimiento de las recomendaciones y medidas de seguridad indicadas. Declaro que participo de manera voluntaria, asumiendo plena responsabilidad por mi bienestar y cualquier consecuencia derivada de mi participacion. Finalmente, autorizo el uso y publicacion de imagenes en las que pueda aparecer durante la actividad, siempre que se respete mi integridad y dignidad.";
$rgpdTexto = "Doy mi consentimiento para que esta web almacene la informacion que envio para que puedan responder a mi peticion. Politica de Privacidad.";

$rangosEdad = ['0-18', '19-30', '31-40', '41-50', '51-60', '61+'];
$gruposSanguineos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
$experiencias = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
$vias = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'Google', 'Amigos', 'Otro'];

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
            <p><?= h($sendero['lugar']) ?><?= !empty($sendero['provincia']) ? ', ' . h($sendero['provincia']) : '' ?> · <?= date('d/m/Y', strtotime($sendero['fecha_sendero'])) ?></p>
        </div>

        <?php if (!empty($_SESSION['registro_sendero_error'])): ?>
            <div class="registro-alert error"><?= h($_SESSION['registro_sendero_error']) ?></div>
            <?php unset($_SESSION['registro_sendero_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['registro_sendero_info'])): ?>
            <div class="registro-alert success"><?= h($_SESSION['registro_sendero_info']) ?></div>
            <?php unset($_SESSION['registro_sendero_info']); ?>
        <?php endif; ?>

        <?php if ($registroExistente): ?>
            <div class="registro-alert success">Ya tienes un registro activo para este sendero. Puedes actualizar tus datos y enviarlos nuevamente.</div>
        <?php endif; ?>

        <form class="registro-card" method="POST" action="<?= BASE_URL ?>procesos/proceso_registro_sendero.php" novalidate>
            <input type="hidden" name="sendero_id" value="<?= (int) $sendero['id'] ?>">

            <section class="registro-section">
                <div class="section-title-row">
                    <span>1</span>
                    <div>
                        <h2>Datos del senderista</h2>
                        <p>Estos datos estan guardados en tu cuenta. Si necesitas cambiarlos, contacta al administrador o actualizalos desde tu perfil cuando este disponible.</p>
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
                        <h2>Tipo de inversion</h2>
                        <p>Elige la opcion con la que deseas reservar tu cupo.</p>
                    </div>
                </div>

                <div class="investment-choice-grid">
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
                        <label class="investment-choice-card">
                            <input type="radio" name="inversion_id" value="<?= (int) $inversion['id'] ?>" <?= $selectedInvestment ? 'checked' : '' ?> required>
                            <span>
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
            </section>

            <section class="registro-section">
                <div class="section-title-row">
                    <span>3</span>
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
                    <button class="consent-toggle" type="button" aria-expanded="false" aria-controls="consentimientoTexto">
                        <span>Leer consentimiento completo</span>
                        <i data-feather="chevron-down"></i>
                    </button>
                    <div class="consent-readable" id="consentimientoTexto" hidden><?= h($consentimientoTexto) ?></div>
                </div>

                <label class="consent-box">
                    <input type="checkbox" name="rgpd" value="1" <?= !empty($formData['rgpd']) ? 'checked' : '' ?> required>
                    <span class="consent-check" aria-hidden="true"></span>
                    <span class="consent-copy">
                        <strong>Acuerdo RGPD *</strong>
                        <span>Doy mi consentimiento para que esta web almacene la informacion que envio para que puedan responder a mi peticion. <span class="privacy-link">Politica de Privacidad</span>.</span>
                    </span>
                </label>
            </section>

            <div class="registro-actions">
                <a class="btn-secondary" href="<?= BASE_URL ?>pantallas/senderos_detalle.php?id=<?= (int) $sendero['id'] ?>">Cancelar</a>
                <button class="btn-primary" type="submit"><?= $registroExistente ? 'Actualizar registro' : 'Enviar registro' ?></button>
            </div>
        </form>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . "/../componentes/pie_pagina.php";
?>
