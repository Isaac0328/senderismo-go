<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $_SESSION['error_message'] = "Inicia sesion para completar tus datos.";
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "pantallas/inicio.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$origen = trim((string) ($_POST['origen'] ?? ''));

function perfil_clean_text(string $value, int $max = 255): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return substr((string) $value, 0, $max);
}

function perfil_only_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function perfil_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    $origen = trim((string) ($_POST['origen'] ?? ''));
    $url = $origen === 'mi_perfil'
        ? BASE_URL . "pantallas/mi_perfil.php"
        : BASE_URL . "pantallas/completar_perfil.php";
    if ($senderoId > 0) {
        $url .= "?sendero_id=" . $senderoId;
    }
    header("Location: " . $url);
    exit;
}

$telefono = perfil_only_digits((string) ($_POST['telefono'] ?? ''));
$rangoEdad = perfil_clean_text((string) ($_POST['rango_edad'] ?? ''), 20);
$identificacion = perfil_clean_text((string) ($_POST['identificacion'] ?? ''), 50);
$esAlergico = (int) ($_POST['es_alergico'] ?? 0);
$alergiasDetalle = perfil_clean_text((string) ($_POST['alergias_detalle'] ?? ''));
$grupoSanguineo = perfil_clean_text((string) ($_POST['grupo_sanguineo'] ?? ''), 10);
$enfermedad = perfil_clean_text((string) ($_POST['enfermedad'] ?? ''));
$seguroMedico = perfil_clean_text((string) ($_POST['seguro_medico'] ?? ''));
$experiencia = perfil_clean_text((string) ($_POST['experiencia_senderismo'] ?? ''), 80);
$viaEntero = perfil_clean_text((string) ($_POST['via_entero'] ?? ''), 80);
$referidoNombre = perfil_clean_text((string) ($_POST['referido_nombre'] ?? ''), 150);
$emergenciaNombre = perfil_clean_text((string) ($_POST['emergencia_nombre'] ?? ''), 150);
$emergenciaParentesco = perfil_clean_text((string) ($_POST['emergencia_parentesco'] ?? ''), 80);
$emergenciaTelefono = perfil_only_digits((string) ($_POST['emergencia_telefono'] ?? ''));

$_SESSION['perfil_senderista_old'] = [
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
];

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
if ($esAlergico !== 0 && $esAlergico !== 1) {
    $errores[] = "Selecciona si eres alergico.";
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

if (!empty($errores)) {
    $_SESSION['perfil_senderista_error'] = implode(' ', $errores);
    perfil_redirect($conn, $senderoId);
}

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

if (!$stmt) {
    $_SESSION['perfil_senderista_error'] = "No se pudo preparar el guardado de tus datos.";
    perfil_redirect($conn, $senderoId);
}

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

unset($_SESSION['perfil_senderista_old']);
mysqli_close($conn);

if ($senderoId > 0 && $origen !== 'mi_perfil') {
    $_SESSION['registro_sendero_info'] = "Datos actualizados. Ya puedes completar tu reserva.";
    header("Location: " . BASE_URL . "pantallas/registro_sendero.php?id=" . $senderoId);
    exit;
}

$_SESSION['perfil_senderista_success'] = "Datos de senderista actualizados correctamente.";
header("Location: " . BASE_URL . "pantallas/mi_perfil.php");
exit;
