<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in']) || (int) ($_SESSION['usuario_rol_id'] ?? 0) !== 1) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

function volver_mantenimiento_contacto(string $extra = ''): void
{
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_contacto.php" . $extra);
    exit;
}

function crear_tablas_contacto(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS configuracion_contacto (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            hero_imagen VARCHAR(255) NOT NULL DEFAULT 'imagenes/paisajes/hero.jpg',
            titulo VARCHAR(160) NOT NULL,
            subtitulo VARCHAR(255) NOT NULL,
            hero_boton_texto VARCHAR(80) NOT NULL DEFAULT 'Escribir mensaje',
            hero_whatsapp_texto VARCHAR(80) NOT NULL DEFAULT 'WhatsApp',
            horario VARCHAR(160) NOT NULL,
            ubicacion VARCHAR(160) NOT NULL,
            telefono VARCHAR(40) NOT NULL,
            whatsapp VARCHAR(40) NOT NULL,
            email VARCHAR(160) NOT NULL,
            instagram VARCHAR(80) NOT NULL,
            instagram_url VARCHAR(255) NOT NULL,
            seccion_kicker VARCHAR(80) NOT NULL DEFAULT 'Atencion personalizada',
            seccion_titulo VARCHAR(160) NOT NULL DEFAULT 'Estamos listos para orientarte.',
            texto_formulario TEXT NOT NULL,
            nota_contacto TEXT NULL,
            form_kicker VARCHAR(80) NOT NULL DEFAULT 'Mensaje rapido',
            form_titulo VARCHAR(120) NOT NULL DEFAULT 'Escribenos',
            form_subtitulo VARCHAR(255) NOT NULL DEFAULT 'Completa estos datos y nos pondremos en contacto contigo.',
            form_privacidad VARCHAR(255) NOT NULL DEFAULT 'Usaremos tu informacion solo para responder esta solicitud.',
            boton_formulario VARCHAR(80) NOT NULL DEFAULT 'Enviar mensaje',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $columnas = [
        "hero_boton_texto VARCHAR(80) NOT NULL DEFAULT 'Escribir mensaje'",
        "hero_whatsapp_texto VARCHAR(80) NOT NULL DEFAULT 'WhatsApp'",
        "seccion_kicker VARCHAR(80) NOT NULL DEFAULT 'Atencion personalizada'",
        "seccion_titulo VARCHAR(160) NOT NULL DEFAULT 'Estamos listos para orientarte.'",
        "nota_contacto TEXT NULL",
        "form_kicker VARCHAR(80) NOT NULL DEFAULT 'Mensaje rapido'",
        "form_titulo VARCHAR(120) NOT NULL DEFAULT 'Escribenos'",
        "form_subtitulo VARCHAR(255) NOT NULL DEFAULT 'Completa estos datos y nos pondremos en contacto contigo.'",
        "form_privacidad VARCHAR(255) NOT NULL DEFAULT 'Usaremos tu informacion solo para responder esta solicitud.'",
        "boton_formulario VARCHAR(80) NOT NULL DEFAULT 'Enviar mensaje'",
    ];
    foreach ($columnas as $definicion) {
        $nombre = strtok($definicion, ' ');
        $existe = mysqli_query($conn, "SHOW COLUMNS FROM configuracion_contacto LIKE '" . mysqli_real_escape_string($conn, $nombre) . "'");
        if ($existe && mysqli_num_rows($existe) === 0) {
            mysqli_query($conn, "ALTER TABLE configuracion_contacto ADD COLUMN {$definicion}");
        }
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS contacto_bloques (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            grupo VARCHAR(30) NOT NULL,
            icono VARCHAR(60) NOT NULL DEFAULT 'circle',
            titulo VARCHAR(120) NOT NULL,
            texto VARCHAR(255) NOT NULL,
            url VARCHAR(255) DEFAULT NULL,
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_contacto_bloques_grupo (grupo, activo, orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function limpiar_texto(string $campo, int $maximo, bool $requerido = true): string
{
    $valor = trim((string) ($_POST[$campo] ?? ''));
    if ($requerido && $valor === '') {
        $_SESSION['contacto_admin_error'] = "Completa todos los campos obligatorios.";
        volver_mantenimiento_contacto();
    }
    if (mb_strlen($valor, 'UTF-8') > $maximo) {
        $_SESSION['contacto_admin_error'] = "Uno de los campos supera el limite permitido.";
        volver_mantenimiento_contacto();
    }
    return $valor;
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
crear_tablas_contacto($conn);

$accion = (string) ($_POST['accion'] ?? 'guardar_config');

if ($accion === 'guardar_bloque') {
    $id = max(0, (int) ($_POST['bloque_id'] ?? 0));
    $grupo = limpiar_texto('grupo', 30);
    if (!in_array($grupo, ['resumen', 'canal'], true)) {
        $_SESSION['contacto_admin_error'] = "Selecciona un grupo valido.";
        volver_mantenimiento_contacto();
    }

    $icono = preg_replace('/[^a-z0-9-]/i', '', limpiar_texto('icono', 60));
    $titulo = limpiar_texto('titulo_bloque', 120);
    $texto = limpiar_texto('texto_bloque', 255);
    $url = limpiar_texto('url', 255, false);
    $orden = (int) ($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, 'mailto:')) {
        $_SESSION['contacto_admin_error'] = "El enlace debe ser una URL valida o iniciar con mailto:.";
        volver_mantenimiento_contacto();
    }

    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE contacto_bloques SET grupo = ?, icono = ?, titulo = ?, texto = ?, url = ?, orden = ?, activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sssssiii', $grupo, $icono, $titulo, $texto, $url, $orden, $activo, $id);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO contacto_bloques (grupo, icono, titulo, texto, url, orden, activo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssssii', $grupo, $icono, $titulo, $texto, $url, $orden, $activo);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    $_SESSION['contacto_admin_success'] = $id > 0 ? "Bloque actualizado correctamente." : "Bloque agregado correctamente.";
    volver_mantenimiento_contacto();
}

if ($accion === 'toggle_bloque') {
    $id = max(0, (int) ($_POST['bloque_id'] ?? 0));
    $activo = isset($_POST['activo']) ? 1 : 0;
    if ($id <= 0) {
        $_SESSION['contacto_admin_error'] = "Bloque invalido.";
        volver_mantenimiento_contacto();
    }
    $stmt = mysqli_prepare($conn, "UPDATE contacto_bloques SET activo = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $activo, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    $_SESSION['contacto_admin_success'] = $activo ? "Bloque activado." : "Bloque inactivado.";
    volver_mantenimiento_contacto();
}

if ($accion === 'eliminar_bloque') {
    $id = max(0, (int) ($_POST['bloque_id'] ?? 0));
    if ($id <= 0) {
        $_SESSION['contacto_admin_error'] = "Bloque invalido.";
        volver_mantenimiento_contacto();
    }
    $stmt = mysqli_prepare($conn, "DELETE FROM contacto_bloques WHERE id = ? AND activo = 0");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $afectados = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    $_SESSION['contacto_admin_success'] = $afectados > 0 ? "Bloque eliminado." : "Solo puedes eliminar bloques inactivos.";
    volver_mantenimiento_contacto();
}

$actual = ['hero_imagen' => 'imagenes/paisajes/hero.jpg'];
$resActual = mysqli_query($conn, "SELECT hero_imagen FROM configuracion_contacto WHERE id = 1 LIMIT 1");
if ($resActual && ($row = mysqli_fetch_assoc($resActual))) {
    $actual['hero_imagen'] = $row['hero_imagen'] ?: $actual['hero_imagen'];
}

$campos = [
    'titulo' => 160,
    'subtitulo' => 255,
    'hero_boton_texto' => 80,
    'hero_whatsapp_texto' => 80,
    'horario' => 160,
    'ubicacion' => 160,
    'telefono' => 40,
    'whatsapp' => 40,
    'email' => 160,
    'instagram' => 80,
    'instagram_url' => 255,
    'seccion_kicker' => 80,
    'seccion_titulo' => 160,
    'texto_formulario' => 1200,
    'nota_contacto' => 1200,
    'form_kicker' => 80,
    'form_titulo' => 120,
    'form_subtitulo' => 255,
    'form_privacidad' => 255,
    'boton_formulario' => 80,
];

$datos = [];
foreach ($campos as $campo => $max) {
    $datos[$campo] = limpiar_texto($campo, $max);
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
        (id, hero_imagen, titulo, subtitulo, hero_boton_texto, hero_whatsapp_texto, horario, ubicacion, telefono, whatsapp, email, instagram, instagram_url, seccion_kicker, seccion_titulo, texto_formulario, nota_contacto, form_kicker, form_titulo, form_subtitulo, form_privacidad, boton_formulario)
    VALUES
        (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        hero_imagen = VALUES(hero_imagen),
        titulo = VALUES(titulo),
        subtitulo = VALUES(subtitulo),
        hero_boton_texto = VALUES(hero_boton_texto),
        hero_whatsapp_texto = VALUES(hero_whatsapp_texto),
        horario = VALUES(horario),
        ubicacion = VALUES(ubicacion),
        telefono = VALUES(telefono),
        whatsapp = VALUES(whatsapp),
        email = VALUES(email),
        instagram = VALUES(instagram),
        instagram_url = VALUES(instagram_url),
        seccion_kicker = VALUES(seccion_kicker),
        seccion_titulo = VALUES(seccion_titulo),
        texto_formulario = VALUES(texto_formulario),
        nota_contacto = VALUES(nota_contacto),
        form_kicker = VALUES(form_kicker),
        form_titulo = VALUES(form_titulo),
        form_subtitulo = VALUES(form_subtitulo),
        form_privacidad = VALUES(form_privacidad),
        boton_formulario = VALUES(boton_formulario)
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    str_repeat('s', 21),
    $heroImagen,
    $datos['titulo'],
    $datos['subtitulo'],
    $datos['hero_boton_texto'],
    $datos['hero_whatsapp_texto'],
    $datos['horario'],
    $datos['ubicacion'],
    $datos['telefono'],
    $datos['whatsapp'],
    $datos['email'],
    $datos['instagram'],
    $datos['instagram_url'],
    $datos['seccion_kicker'],
    $datos['seccion_titulo'],
    $datos['texto_formulario'],
    $datos['nota_contacto'],
    $datos['form_kicker'],
    $datos['form_titulo'],
    $datos['form_subtitulo'],
    $datos['form_privacidad'],
    $datos['boton_formulario']
);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

$_SESSION['contacto_admin_success'] = "Datos de contacto actualizados correctamente.";
volver_mantenimiento_contacto();
