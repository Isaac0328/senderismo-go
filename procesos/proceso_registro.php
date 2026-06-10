<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../bd/conexion.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Método no permitido";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

// Capturar y limpiar
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$user = trim($_POST['user'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';
$terms = (string) ($_POST['terms'] ?? '');
$telefono = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? ''));
$rangoEdad = trim((string) ($_POST['rango_edad'] ?? ''));
$identificacion = trim((string) ($_POST['identificacion'] ?? ''));
$esAlergico = (int) ($_POST['es_alergico'] ?? 0);
$alergiasDetalle = trim((string) ($_POST['alergias_detalle'] ?? ''));
$grupoSanguineo = trim((string) ($_POST['grupo_sanguineo'] ?? ''));
$enfermedad = trim((string) ($_POST['enfermedad'] ?? ''));
$seguroMedico = trim((string) ($_POST['seguro_medico'] ?? ''));
$experiencia = trim((string) ($_POST['experiencia_senderismo'] ?? ''));
$viaEntero = trim((string) ($_POST['via_entero'] ?? ''));
$referidoNombre = trim((string) ($_POST['referido_nombre'] ?? ''));
$emergenciaNombre = trim((string) ($_POST['emergencia_nombre'] ?? ''));
$emergenciaParentesco = trim((string) ($_POST['emergencia_parentesco'] ?? ''));
$emergenciaTelefono = preg_replace('/\D+/', '', (string) ($_POST['emergencia_telefono'] ?? ''));

// Para repoblar el form si falla
$_SESSION['reg_old'] = [
    'nombre' => $nombre,
    'apellido' => $apellido,
    'user' => $user,
    'email' => $email,
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

// Validaciones server-side
if ($nombre === '' || $apellido === '' || $user === '' || $email === '' || $password === '' || $confirm === '') {
    $_SESSION['error_message'] = "Por favor completa todos los campos.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if ($terms !== '1') {
    $_SESSION['error_message'] = "Debes aceptar los terminos y condiciones para registrarte.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

$rangosPermitidos = ['0-18', '19-30', '31-40', '41-50', '51-60', '61+'];
$gruposPermitidos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
$experienciasPermitidas = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
$viasPermitidas = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'Google', 'Amigos', 'Otro'];

$erroresDetalle = [];
if (strlen($telefono) < 10 || strlen($telefono) > 15) {
    $erroresDetalle[] = "El telefono debe contener entre 10 y 15 digitos.";
}
if (!in_array($rangoEdad, $rangosPermitidos, true)) {
    $erroresDetalle[] = "Selecciona un rango de edad valido.";
}
if ($identificacion === '') {
    $erroresDetalle[] = "La identificacion es obligatoria.";
}
if ($esAlergico === 1 && $alergiasDetalle === '') {
    $erroresDetalle[] = "Especifica a que eres alergico.";
}
if (!in_array($grupoSanguineo, $gruposPermitidos, true)) {
    $erroresDetalle[] = "Selecciona un grupo sanguineo valido.";
}
if ($enfermedad === '' || $seguroMedico === '') {
    $erroresDetalle[] = "Completa enfermedad y seguro medico. Si no aplica, escribe No.";
}
if (!in_array($experiencia, $experienciasPermitidas, true)) {
    $erroresDetalle[] = "Selecciona tu experiencia haciendo senderismo.";
}
if (!in_array($viaEntero, $viasPermitidas, true)) {
    $erroresDetalle[] = "Selecciona por cual via te enteraste.";
}
if ($emergenciaNombre === '' || $emergenciaParentesco === '' || strlen($emergenciaTelefono) < 10 || strlen($emergenciaTelefono) > 15) {
    $erroresDetalle[] = "Completa correctamente el contacto de emergencia.";
}
if (!empty($erroresDetalle)) {
    $_SESSION['error_message'] = implode(' ', $erroresDetalle);
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if (preg_match('/\s/', $user)) {
    $_SESSION['error_message'] = "El usuario no puede contener espacios.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "Correo inválido.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['error_message'] = "La contraseña debe tener al menos 6 caracteres.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

if ($password !== $confirm) {
    $_SESSION['error_message'] = "Las contraseñas no coinciden.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

// Hash seguro (compatible con password_verify en Login)
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Llamar SP
mysqli_query($conn, "SET @p_mensaje = ''");
mysqli_query($conn, "SET @p_codigo = 0");

$stmt = mysqli_prepare($conn, "CALL sp_registrar_usuario(?, ?, ?, ?, ?, @p_mensaje, @p_codigo)");
if (!$stmt) {
    $_SESSION['error_message'] = "Error preparando el registro.";
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "sssss", $nombre, $apellido, $user, $email, $passwordHash);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Limpiar resultados restantes (por seguridad)
while (mysqli_more_results($conn)) {
    mysqli_next_result($conn);
}

// Obtener OUT params
$res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
$data = $res ? mysqli_fetch_assoc($res) : null;

$codigo = (int) ($data['codigo'] ?? 99);
$mensaje = $data['mensaje'] ?? 'No se pudo completar el registro.';

// Respuesta
if ($codigo !== 0) {
    $_SESSION['error_message'] = $mensaje;
    header("Location: " . BASE_URL . "pantallas/registro.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE user = ? OR email = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "ss", $user, $email);
mysqli_stmt_execute($stmt);
$usuarioCreado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$usuarioId = (int) ($usuarioCreado['id'] ?? 0);

if ($usuarioId > 0) {
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
}

// OK
mysqli_close($conn);
unset($_SESSION['reg_old']);
$_SESSION['success_message'] = "✅ Cuenta creada. Ahora inicia sesión.";
header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
exit;
