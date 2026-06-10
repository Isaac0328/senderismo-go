<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $_SESSION['error_message'] = "Inicia sesion para registrarte en este sendero.";
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "pantallas/senderos.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$inversionId = (int) ($_POST['inversion_id'] ?? 0);

function registro_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "pantallas/registro_sendero.php?id=" . $senderoId);
    exit;
}

function clean_text(string $value, int $max = 255): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return substr((string) $value, 0, $max);
}

function only_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function guardar_formulario_anterior(int $senderoId, array $data): void
{
    $_SESSION['registro_sendero_old'] = array_merge(['sendero_id' => $senderoId], $data);
}

if ($senderoId <= 0) {
    $_SESSION['registro_sendero_error'] = "Sendero no valido.";
    registro_redirect($conn, 0);
}

$stmt = mysqli_prepare($conn, "SELECT id FROM senderos WHERE id = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $senderoId);
mysqli_stmt_execute($stmt);
$senderoExiste = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$senderoExiste) {
    $_SESSION['registro_sendero_error'] = "El sendero seleccionado no esta disponible.";
    registro_redirect($conn, $senderoId);
}

$stmt = mysqli_prepare($conn, "SELECT id FROM sendero_inversiones WHERE id = ? AND sendero_id = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $inversionId, $senderoId);
mysqli_stmt_execute($stmt);
$inversionExiste = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$consentimiento = isset($_POST['consentimiento']);
$rgpd = isset($_POST['rgpd']);

$stmt = mysqli_prepare($conn, "SELECT id FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$detalle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$detalleId = (int) ($detalle['id'] ?? 0);

$errores = [];
if (!$inversionExiste) {
    $errores[] = "Selecciona un tipo de inversion valido.";
}
if ($detalleId <= 0) {
    $errores[] = "Debes completar tus datos de senderista antes de reservar.";
}
if (!$consentimiento || !$rgpd) {
    $errores[] = "Debes aceptar el consentimiento y el acuerdo RGPD.";
}

if (!empty($errores)) {
    guardar_formulario_anterior($senderoId, [
        'inversion_id' => (string) $inversionId,
        'consentimiento' => $consentimiento ? '1' : '',
        'rgpd' => $rgpd ? '1' : '',
    ]);
    $_SESSION['registro_sendero_error'] = implode(' ', $errores);
    registro_redirect($conn, $senderoId);
}

$consentimientoTexto = "Estoy de acuerdo que: Yo, siendo mayor de edad, en pleno uso de mis facultades y con total capacidad para comprender el contenido de este documento, declaro que he leido y entiendo completamente la informacion proporcionada en esta pagina sobre la actividad en la que participare. Reconozco que se trata de una actividad fisicamente exigente y que conlleva riesgos inherentes a su naturaleza. Estoy consciente de los posibles desafios y riesgos involucrados, incluyendo aquellos relacionados con el esfuerzo fisico, las condiciones del terreno y cualquier otro factor mencionado en la informacion suministrada. Asimismo, acepto que, en caso de emergencia medica, el acceso a asistencia puede estar sujeto a condiciones y tiempos de respuesta variables. Acepto y doy mi consentimiento a recibir asistencia de primeros auxilios por el personal de la directiva de ser necesario. Comprendo que la organizacion y su personal no son responsables de los riesgos que pudiera enfrentar debido a mi participacion o al incumplimiento de las recomendaciones y medidas de seguridad indicadas. Declaro que participo de manera voluntaria, asumiendo plena responsabilidad por mi bienestar y cualquier consecuencia derivada de mi participacion. Finalmente, autorizo el uso y publicacion de imagenes en las que pueda aparecer durante la actividad, siempre que se respete mi integridad y dignidad.";
$rgpdTexto = "Doy mi consentimiento para que esta web almacene la informacion que envio para que puedan responder a mi peticion. Politica de Privacidad.";

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO registros_senderos (
            sendero_id, usuario_id, detalle_usuario_id, estado, consentimiento_aceptado,
            rgpd_aceptado, consentimiento_texto, rgpd_texto, inversion_id
        ) VALUES (?, ?, ?, 'registrado', 1, 1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            detalle_usuario_id = VALUES(detalle_usuario_id),
            inversion_id = VALUES(inversion_id),
            estado = 'registrado',
            consentimiento_aceptado = 1,
            rgpd_aceptado = 1,
            consentimiento_texto = VALUES(consentimiento_texto),
            rgpd_texto = VALUES(rgpd_texto)"
    );
    mysqli_stmt_bind_param($stmt, "iiissi", $senderoId, $usuarioId, $detalleId, $consentimientoTexto, $rgpdTexto, $inversionId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    guardar_formulario_anterior($senderoId, [
        'inversion_id' => (string) $inversionId,
        'consentimiento' => $consentimiento ? '1' : '',
        'rgpd' => $rgpd ? '1' : '',
    ]);
    $_SESSION['registro_sendero_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo completar el registro.";
    registro_redirect($conn, $senderoId);
}

unset($_SESSION['registro_sendero_old']);
$_SESSION['registro_sendero_success'] = "Registro enviado correctamente.";
mysqli_close($conn);

header("Location: " . BASE_URL . "pantallas/senderos_detalle.php?id=" . $senderoId);
exit;
