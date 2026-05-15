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

$formData = $detalle;
$oldData = $_SESSION['registro_sendero_old'] ?? null;
if (is_array($oldData) && (int) ($oldData['sendero_id'] ?? 0) === $idSendero) {
    $formData = array_merge($formData, $oldData);
    unset($_SESSION['registro_sendero_old']);
}

$registroExistente = false;
$stmt = mysqli_prepare($conn, "SELECT id FROM registros_senderos WHERE usuario_id = ? AND sendero_id = ? AND estado = 'registrado' LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $usuarioId, $idSendero);
mysqli_stmt_execute($stmt);
$registroExistente = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
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
                        <p>Estos datos se toman de tu cuenta y se completan con informacion de contacto.</p>
                    </div>
                </div>

                <div class="registro-grid">
                    <label class="field">
                        <span>Nombre *</span>
                        <input type="text" value="<?= h($usuario['nombre']) ?>" readonly>
                    </label>
                    <label class="field">
                        <span>Apellidos *</span>
                        <input type="text" value="<?= h($usuario['apellido']) ?>" readonly>
                    </label>
                    <label class="field">
                        <span>Correo electronico *</span>
                        <input type="email" value="<?= h($usuario['email']) ?>" readonly>
                    </label>
                    <label class="field">
                        <span>Numero telefonico *</span>
                        <input name="telefono" type="tel" inputmode="numeric" pattern="[0-9]{10,15}" placeholder="# Sin guiones" value="<?= h($formData['telefono'] ?? '') ?>" required>
                    </label>
                    <label class="field">
                        <span>Edad *</span>
                        <select name="rango_edad" required>
                            <option value="">Elije una respuesta</option>
                            <?php foreach ($rangosEdad as $rango): ?>
                                <option value="<?= h($rango) ?>" <?= selected_value($formData['rango_edad'] ?? '', $rango) ?>><?= h($rango) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Identificacion *</span>
                        <input name="identificacion" type="text" value="<?= h($formData['identificacion'] ?? '') ?>" required>
                    </label>
                </div>
            </section>

            <section class="registro-section">
                <div class="section-title-row">
                    <span>2</span>
                    <div>
                        <h2>Salud y experiencia</h2>
                        <p>Esta informacion ayuda a la organizacion a prepararse mejor para la ruta.</p>
                    </div>
                </div>

                <div class="registro-grid">
                    <div class="field">
                        <span>Es alergico? *</span>
                        <div class="radio-row">
                            <label><input type="radio" name="es_alergico" value="1" <?= checked_value($formData['es_alergico'] ?? '0', '1') ?> required> Si</label>
                            <label><input type="radio" name="es_alergico" value="0" <?= checked_value($formData['es_alergico'] ?? '0', '0') ?> required> No</label>
                        </div>
                    </div>
                    <label class="field">
                        <span>Especifique a que es alergico</span>
                        <input name="alergias_detalle" type="text" value="<?= h($formData['alergias_detalle'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Grupo sanguineo *</span>
                        <select name="grupo_sanguineo" required>
                            <option value="">Elije una respuesta</option>
                            <?php foreach ($gruposSanguineos as $grupo): ?>
                                <option value="<?= h($grupo) ?>" <?= selected_value($formData['grupo_sanguineo'] ?? '', $grupo) ?>><?= h($grupo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field span-2">
                        <span>Padece alguna enfermedad? Cual? *</span>
                        <input name="enfermedad" type="text" placeholder="Si no aplica, escribe No" value="<?= h($formData['enfermedad'] ?? '') ?>" required>
                    </label>
                    <label class="field span-2">
                        <span>Tiene seguro medico? Cual? *</span>
                        <input name="seguro_medico" type="text" placeholder="Si no aplica, escribe No" value="<?= h($formData['seguro_medico'] ?? '') ?>" required>
                    </label>
                    <label class="field">
                        <span>Experiencia haciendo senderismo *</span>
                        <select name="experiencia_senderismo" required>
                            <option value="">Elije una respuesta</option>
                            <?php foreach ($experiencias as $experiencia): ?>
                                <option value="<?= h($experiencia) ?>" <?= selected_value($formData['experiencia_senderismo'] ?? '', $experiencia) ?>><?= h($experiencia) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Por cual via te enteraste? *</span>
                        <select name="via_entero" required>
                            <option value="">Elije una respuesta</option>
                            <?php foreach ($vias as $via): ?>
                                <option value="<?= h($via) ?>" <?= selected_value($formData['via_entero'] ?? '', $via) ?>><?= h($via) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Si fue amigos, escriba su nombre</span>
                        <input name="referido_nombre" type="text" value="<?= h($formData['referido_nombre'] ?? '') ?>">
                    </label>
                </div>
            </section>

            <section class="registro-section">
                <div class="section-title-row">
                    <span>3</span>
                    <div>
                        <h2>Contacto de emergencia</h2>
                        <p>Usaremos este contacto solo si ocurre alguna situacion durante la actividad.</p>
                    </div>
                </div>

                <div class="registro-grid">
                    <label class="field">
                        <span>Nombre de contacto de emergencia *</span>
                        <input name="emergencia_nombre" type="text" value="<?= h($formData['emergencia_nombre'] ?? '') ?>" required>
                    </label>
                    <label class="field">
                        <span>Parentesco *</span>
                        <input name="emergencia_parentesco" type="text" value="<?= h($formData['emergencia_parentesco'] ?? '') ?>" required>
                    </label>
                    <label class="field">
                        <span>Numero de telefono *</span>
                        <input name="emergencia_telefono" type="tel" inputmode="numeric" pattern="[0-9]{10,15}" placeholder="# Sin guiones" value="<?= h($formData['emergencia_telefono'] ?? '') ?>" required>
                    </label>
                </div>
            </section>

            <section class="registro-section">
                <div class="section-title-row">
                    <span>4</span>
                    <div>
                        <h2>Consentimientos</h2>
                        <p>Debes aceptar estos terminos para completar el registro.</p>
                    </div>
                </div>

                <label class="consent-box">
                    <input type="checkbox" name="consentimiento" value="1" <?= !empty($formData['consentimiento']) ? 'checked' : '' ?> required>
                    <span><strong>Consentimiento *</strong><?= h($consentimientoTexto) ?></span>
                </label>

                <label class="consent-box">
                    <input type="checkbox" name="rgpd" value="1" <?= !empty($formData['rgpd']) ? 'checked' : '' ?> required>
                    <span><strong>Acuerdo RGPD *</strong><?= h($rgpdTexto) ?></span>
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
