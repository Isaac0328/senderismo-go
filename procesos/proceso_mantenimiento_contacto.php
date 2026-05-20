<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in']) || (int) ($_SESSION['usuario_rol_id'] ?? 0) !== 1) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

function volver_mantenimiento_contacto(): void
{
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_contacto.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['contacto_admin_error'] = "Metodo no permitido.";
    volver_mantenimiento_contacto();
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    $_SESSION['contacto_admin_error'] = "No se pudo conectar con la base de datos.";
    volver_mantenimiento_contacto();
}
mysqli_set_charset($conn, "utf8mb4");

$sqlTabla = "
    CREATE TABLE IF NOT EXISTS configuracion_contacto (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
        titulo VARCHAR(160) NOT NULL,
        subtitulo VARCHAR(255) NOT NULL,
        horario VARCHAR(160) NOT NULL,
        ubicacion VARCHAR(160) NOT NULL,
        telefono VARCHAR(40) NOT NULL,
        whatsapp VARCHAR(40) NOT NULL,
        email VARCHAR(160) NOT NULL,
        instagram VARCHAR(80) NOT NULL,
        instagram_url VARCHAR(255) NOT NULL,
        texto_formulario TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
mysqli_query($conn, $sqlTabla);

$actual = [
    'hero_imagen' => 'imagenes/paisajes/hero.jpg',
];
$resActual = mysqli_query($conn, "SELECT hero_imagen FROM configuracion_contacto WHERE id = 1 LIMIT 1");
if ($resActual && ($row = mysqli_fetch_assoc($resActual))) {
    $actual['hero_imagen'] = $row['hero_imagen'] ?: $actual['hero_imagen'];
}

$campos = [
    'titulo' => 160,
    'subtitulo' => 255,
    'horario' => 160,
    'ubicacion' => 160,
    'telefono' => 40,
    'whatsapp' => 40,
    'email' => 160,
    'instagram' => 80,
    'instagram_url' => 255,
    'texto_formulario' => 1200,
];

$datos = [];
foreach ($campos as $campo => $max) {
    $valor = trim((string) ($_POST[$campo] ?? ''));
    if ($valor === '') {
        $_SESSION['contacto_admin_error'] = "Completa todos los campos obligatorios.";
        volver_mantenimiento_contacto();
    }
    if (strlen($valor) > $max) {
        $_SESSION['contacto_admin_error'] = "El campo {$campo} supera el limite permitido.";
        volver_mantenimiento_contacto();
    }
    $datos[$campo] = $valor;
}

if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['contacto_admin_error'] = "El correo no tiene un formato valido.";
    volver_mantenimiento_contacto();
}

if (!filter_var($datos['instagram_url'], FILTER_VALIDATE_URL)) {
    $_SESSION['contacto_admin_error'] = "La URL de Instagram no tiene un formato valido.";
    volver_mantenimiento_contacto();
}

$heroImagen = $actual['hero_imagen'];
if (!empty($_FILES['hero_imagen']['name']) && is_uploaded_file($_FILES['hero_imagen']['tmp_name'])) {
    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($_FILES['hero_imagen']['tmp_name']);
    if (!isset($permitidos[$mime])) {
        $_SESSION['contacto_admin_error'] = "La imagen debe ser JPG, PNG o WEBP.";
        volver_mantenimiento_contacto();
    }
    if ((int) ($_FILES['hero_imagen']['size'] ?? 0) > 4 * 1024 * 1024) {
        $_SESSION['contacto_admin_error'] = "La imagen no debe superar 4 MB.";
        volver_mantenimiento_contacto();
    }

    $directorio = __DIR__ . '/../imagenes/contacto';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0775, true);
    }

    $nombreArchivo = 'contacto_hero_' . date('Ymd_His') . '.' . $permitidos[$mime];
    $destino = $directorio . '/' . $nombreArchivo;
    if (!move_uploaded_file($_FILES['hero_imagen']['tmp_name'], $destino)) {
        $_SESSION['contacto_admin_error'] = "No se pudo guardar la imagen.";
        volver_mantenimiento_contacto();
    }
    $heroImagen = 'imagenes/contacto/' . $nombreArchivo;
}

$sql = "
    INSERT INTO configuracion_contacto
        (id, hero_imagen, titulo, subtitulo, horario, ubicacion, telefono, whatsapp, email, instagram, instagram_url, texto_formulario)
    VALUES
        (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        hero_imagen = VALUES(hero_imagen),
        titulo = VALUES(titulo),
        subtitulo = VALUES(subtitulo),
        horario = VALUES(horario),
        ubicacion = VALUES(ubicacion),
        telefono = VALUES(telefono),
        whatsapp = VALUES(whatsapp),
        email = VALUES(email),
        instagram = VALUES(instagram),
        instagram_url = VALUES(instagram_url),
        texto_formulario = VALUES(texto_formulario)
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    'sssssssssss',
    $heroImagen,
    $datos['titulo'],
    $datos['subtitulo'],
    $datos['horario'],
    $datos['ubicacion'],
    $datos['telefono'],
    $datos['whatsapp'],
    $datos['email'],
    $datos['instagram'],
    $datos['instagram_url'],
    $datos['texto_formulario']
);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

$_SESSION['contacto_admin_success'] = "Datos de contacto actualizados correctamente.";
volver_mantenimiento_contacto();
