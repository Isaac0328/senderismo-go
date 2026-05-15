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

$telefono = only_digits($_POST['telefono'] ?? '');
$rangoEdad = clean_text($_POST['rango_edad'] ?? '', 20);
$identificacion = clean_text($_POST['identificacion'] ?? '', 50);
$esAlergico = (int) ($_POST['es_alergico'] ?? 0);
$alergiasDetalle = clean_text($_POST['alergias_detalle'] ?? '');
$grupoSanguineo = clean_text($_POST['grupo_sanguineo'] ?? '', 5);
$enfermedad = clean_text($_POST['enfermedad'] ?? '');
$seguroMedico = clean_text($_POST['seguro_medico'] ?? '');
$experiencia = clean_text($_POST['experiencia_senderismo'] ?? '', 80);
$viaEntero = clean_text($_POST['via_entero'] ?? '', 80);
$referidoNombre = clean_text($_POST['referido_nombre'] ?? '', 150);
$emergenciaNombre = clean_text($_POST['emergencia_nombre'] ?? '', 150);
$emergenciaParentesco = clean_text($_POST['emergencia_parentesco'] ?? '', 80);
$emergenciaTelefono = only_digits($_POST['emergencia_telefono'] ?? '');
$consentimiento = isset($_POST['consentimiento']);
$rgpd = isset($_POST['rgpd']);

$rangosPermitidos = ['0-18', '19-30', '31-40', '41-50', '51-60', '61+'];
$gruposPermitidos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
$experienciasPermitidas = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
$viasPermitidas = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'Google', 'Amigos', 'Otro'];

$errores = [];
if (strlen($telefono) < 10 || strlen($telefono) > 15) {
    $errores[] = "El telefono debe contener entre 10 y 15 digitos.";
}
if (!in_array($rangoEdad, $rangosPermitidos, true)) {
    $errores[] = "Selecciona un rango de edad valido.";
}
if ($identificacion === '') {
    $errores[] = "La identificacion es obligatoria.";
}
if ($esAlergico === 1 && $alergiasDetalle === '') {
    $errores[] = "Especifica a que eres alergico.";
}
if (!in_array($grupoSanguineo, $gruposPermitidos, true)) {
    $errores[] = "Selecciona un grupo sanguineo valido.";
}
if ($enfermedad === '' || $seguroMedico === '') {
    $errores[] = "Completa enfermedad y seguro medico. Si no aplica, escribe No.";
}
if (!in_array($experiencia, $experienciasPermitidas, true)) {
    $errores[] = "Selecciona tu experiencia haciendo senderismo.";
}
if (!in_array($viaEntero, $viasPermitidas, true)) {
    $errores[] = "Selecciona por cual via te enteraste.";
}
if ($emergenciaNombre === '' || $emergenciaParentesco === '' || strlen($emergenciaTelefono) < 10 || strlen($emergenciaTelefono) > 15) {
    $errores[] = "Completa correctamente el contacto de emergencia.";
}
if (!$consentimiento || !$rgpd) {
    $errores[] = "Debes aceptar el consentimiento y el acuerdo RGPD.";
}

if (!empty($errores)) {
    guardar_formulario_anterior($senderoId, [
        'telefono' => $telefono,
        'rango_edad' => $rangoEdad,
        'identificacion' => $identificacion,
        'es_alergico' => (string) $esAlergico,
        'alergias_detalle' => $alergiasDetalle,
        'grupo_sanguineo' => $grupoSanguineo,
        'enfermedad' => $enfermedad,
        'seguro_medico' => $seguroMedico,
        'experiencia_senderismo' => $experiencia,
        'via_entero' => $viaEntero,
        'referido_nombre' => $referidoNombre,
        'emergencia_nombre' => $emergenciaNombre,
        'emergencia_parentesco' => $emergenciaParentesco,
        'emergencia_telefono' => $emergenciaTelefono,
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
        "INSERT INTO detalles_usuarios (
            usuario_id, telefono, rango_edad, identificacion, es_alergico, alergias_detalle,
            grupo_sanguineo, enfermedad, seguro_medico, experiencia_senderismo, via_entero,
            referido_nombre, emergencia_nombre, emergencia_parentesco, emergencia_telefono
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            telefono = VALUES(telefono),
            rango_edad = VALUES(rango_edad),
            identificacion = VALUES(identificacion),
            es_alergico = VALUES(es_alergico),
            alergias_detalle = VALUES(alergias_detalle),
            grupo_sanguineo = VALUES(grupo_sanguineo),
            enfermedad = VALUES(enfermedad),
            seguro_medico = VALUES(seguro_medico),
            experiencia_senderismo = VALUES(experiencia_senderismo),
            via_entero = VALUES(via_entero),
            referido_nombre = VALUES(referido_nombre),
            emergencia_nombre = VALUES(emergencia_nombre),
            emergencia_parentesco = VALUES(emergencia_parentesco),
            emergencia_telefono = VALUES(emergencia_telefono)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "isssissssssssss",
        $usuarioId,
        $telefono,
        $rangoEdad,
        $identificacion,
        $esAlergico,
        $alergiasDetalle,
        $grupoSanguineo,
        $enfermedad,
        $seguroMedico,
        $experiencia,
        $viaEntero,
        $referidoNombre,
        $emergenciaNombre,
        $emergenciaParentesco,
        $emergenciaTelefono
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT id FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $usuarioId);
    mysqli_stmt_execute($stmt);
    $detalle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $detalleId = (int) ($detalle['id'] ?? 0);

    if ($detalleId <= 0) {
        throw new RuntimeException("No se pudo guardar el detalle del usuario.");
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO registros_senderos (
            sendero_id, usuario_id, detalle_usuario_id, estado, consentimiento_aceptado,
            rgpd_aceptado, consentimiento_texto, rgpd_texto
        ) VALUES (?, ?, ?, 'registrado', 1, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
            detalle_usuario_id = VALUES(detalle_usuario_id),
            estado = 'registrado',
            consentimiento_aceptado = 1,
            rgpd_aceptado = 1,
            consentimiento_texto = VALUES(consentimiento_texto),
            rgpd_texto = VALUES(rgpd_texto)"
    );
    mysqli_stmt_bind_param($stmt, "iiiss", $senderoId, $usuarioId, $detalleId, $consentimientoTexto, $rgpdTexto);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    guardar_formulario_anterior($senderoId, [
        'telefono' => $telefono,
        'rango_edad' => $rangoEdad,
        'identificacion' => $identificacion,
        'es_alergico' => (string) $esAlergico,
        'alergias_detalle' => $alergiasDetalle,
        'grupo_sanguineo' => $grupoSanguineo,
        'enfermedad' => $enfermedad,
        'seguro_medico' => $seguroMedico,
        'experiencia_senderismo' => $experiencia,
        'via_entero' => $viaEntero,
        'referido_nombre' => $referidoNombre,
        'emergencia_nombre' => $emergenciaNombre,
        'emergencia_parentesco' => $emergenciaParentesco,
        'emergencia_telefono' => $emergenciaTelefono,
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
