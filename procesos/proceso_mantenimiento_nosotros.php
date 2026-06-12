<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in']) || (int) ($_SESSION['usuario_rol_id'] ?? 0) !== 1) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

function volver_nosotros(string $extra = ''): void
{
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_nosotros.php" . $extra);
    exit;
}

function crear_tablas_nosotros(mysqli $conn): void
{
    require_once __DIR__ . '/../pantallas/nosotros.php';
}

function texto_post(string $campo, int $max = 1200, bool $requerido = true): string
{
    $valor = trim((string) ($_POST[$campo] ?? ''));
    if ($requerido && $valor === '') {
        $_SESSION['nosotros_admin_error'] = 'Completa todos los campos obligatorios.';
        volver_nosotros();
    }
    if (mb_strlen($valor, 'UTF-8') > $max) {
        $_SESSION['nosotros_admin_error'] = 'Uno de los campos supera el limite permitido.';
        volver_nosotros();
    }
    return $valor;
}

function subir_imagen_nosotros(string $campo, string $actual): string
{
    if (empty($_FILES[$campo]['name']) || !is_uploaded_file($_FILES[$campo]['tmp_name'])) {
        return $actual;
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($_FILES[$campo]['tmp_name']);
    if (!isset($permitidos[$mime])) {
        $_SESSION['nosotros_admin_error'] = 'La imagen debe ser JPG, PNG o WEBP.';
        volver_nosotros();
    }
    if ((int) ($_FILES[$campo]['size'] ?? 0) > 4 * 1024 * 1024) {
        $_SESSION['nosotros_admin_error'] = 'La imagen no debe superar 4 MB.';
        volver_nosotros();
    }

    $dir = __DIR__ . '/../imagenes/nosotros';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $nombre = $campo . '_' . date('Ymd_His') . '.' . $permitidos[$mime];
    $destino = $dir . '/' . $nombre;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
        $_SESSION['nosotros_admin_error'] = 'No se pudo guardar la imagen.';
        volver_nosotros();
    }
    return 'imagenes/nosotros/' . $nombre;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['nosotros_admin_error'] = 'Metodo no permitido.';
    volver_nosotros();
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    $_SESSION['nosotros_admin_error'] = 'No se pudo conectar con la base de datos.';
    volver_nosotros();
}
mysqli_set_charset($conn, 'utf8mb4');

// Cargar la pagina crea tablas y semillas; se corta la salida para usar sus helpers sin imprimir HTML.
ob_start();
require __DIR__ . '/../pantallas/nosotros.php';
ob_end_clean();

$accion = (string) ($_POST['accion'] ?? 'guardar_config');

if ($accion === 'guardar_config') {
    $actual = [
        'hero_imagen' => 'imagenes/paisajes/hero.jpg',
        'historia_imagen' => 'imagenes/paisajes/img4.jpg',
    ];
    $res = mysqli_query($conn, "SELECT hero_imagen, historia_imagen FROM configuracion_nosotros WHERE id = 1 LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $actual['hero_imagen'] = $row['hero_imagen'] ?: $actual['hero_imagen'];
        $actual['historia_imagen'] = $row['historia_imagen'] ?: $actual['historia_imagen'];
    }

    $heroImagen = subir_imagen_nosotros('hero_imagen', $actual['hero_imagen']);
    $historiaImagen = subir_imagen_nosotros('historia_imagen', $actual['historia_imagen']);

    $campos = [
        'hero_kicker' => 80,
        'hero_titulo' => 180,
        'hero_subtitulo' => 1200,
        'boton_principal_texto' => 80,
        'boton_principal_url' => 255,
        'boton_secundario_texto' => 80,
        'boton_secundario_url' => 255,
        'historia_badge_titulo' => 80,
        'historia_badge_texto' => 120,
        'historia_kicker' => 80,
        'historia_titulo' => 180,
        'historia_texto_1' => 1600,
        'historia_texto_2' => 1600,
        'valores_kicker' => 80,
        'valores_titulo' => 180,
        'valores_texto' => 1200,
        'proceso_kicker' => 80,
        'proceso_titulo' => 180,
        'proceso_texto' => 1200,
        'equipo_kicker' => 80,
        'equipo_titulo' => 180,
        'equipo_texto' => 1200,
        'cta_kicker' => 80,
        'cta_titulo' => 180,
        'cta_texto' => 1200,
        'cta_boton_principal_texto' => 80,
        'cta_boton_principal_url' => 255,
        'cta_boton_secundario_texto' => 80,
        'cta_boton_secundario_url' => 255,
    ];
    $datos = [];
    foreach ($campos as $campo => $max) {
        $datos[$campo] = texto_post($campo, $max);
    }

    $sql = "UPDATE configuracion_nosotros SET
        hero_imagen=?, hero_kicker=?, hero_titulo=?, hero_subtitulo=?, boton_principal_texto=?, boton_principal_url=?,
        boton_secundario_texto=?, boton_secundario_url=?, historia_imagen=?, historia_badge_titulo=?, historia_badge_texto=?,
        historia_kicker=?, historia_titulo=?, historia_texto_1=?, historia_texto_2=?, valores_kicker=?, valores_titulo=?,
        valores_texto=?, proceso_kicker=?, proceso_titulo=?, proceso_texto=?, equipo_kicker=?, equipo_titulo=?, equipo_texto=?,
        cta_kicker=?, cta_titulo=?, cta_texto=?, cta_boton_principal_texto=?, cta_boton_principal_url=?,
        cta_boton_secundario_texto=?, cta_boton_secundario_url=? WHERE id=1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        str_repeat('s', 31),
        $heroImagen,
        $datos['hero_kicker'],
        $datos['hero_titulo'],
        $datos['hero_subtitulo'],
        $datos['boton_principal_texto'],
        $datos['boton_principal_url'],
        $datos['boton_secundario_texto'],
        $datos['boton_secundario_url'],
        $historiaImagen,
        $datos['historia_badge_titulo'],
        $datos['historia_badge_texto'],
        $datos['historia_kicker'],
        $datos['historia_titulo'],
        $datos['historia_texto_1'],
        $datos['historia_texto_2'],
        $datos['valores_kicker'],
        $datos['valores_titulo'],
        $datos['valores_texto'],
        $datos['proceso_kicker'],
        $datos['proceso_titulo'],
        $datos['proceso_texto'],
        $datos['equipo_kicker'],
        $datos['equipo_titulo'],
        $datos['equipo_texto'],
        $datos['cta_kicker'],
        $datos['cta_titulo'],
        $datos['cta_texto'],
        $datos['cta_boton_principal_texto'],
        $datos['cta_boton_principal_url'],
        $datos['cta_boton_secundario_texto'],
        $datos['cta_boton_secundario_url']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    $_SESSION['nosotros_admin_success'] = 'Contenido de Nosotros actualizado.';
    volver_nosotros();
}

$tipo = (string) ($_POST['tipo'] ?? '');
$id = max(0, (int) ($_POST['item_id'] ?? 0));
$activo = isset($_POST['activo']) ? 1 : 0;

$tablas = [
    'indicador' => 'nosotros_indicadores',
    'valor' => 'nosotros_valores',
    'paso' => 'nosotros_pasos',
    'equipo' => 'nosotros_equipo',
];
if (!isset($tablas[$tipo])) {
    $_SESSION['nosotros_admin_error'] = 'Tipo de registro invalido.';
    volver_nosotros();
}
$tabla = $tablas[$tipo];

if ($accion === 'toggle_item') {
    $stmt = mysqli_prepare($conn, "UPDATE {$tabla} SET activo=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $activo, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    $_SESSION['nosotros_admin_success'] = $activo ? 'Registro activado.' : 'Registro inactivado.';
    volver_nosotros();
}

if ($accion === 'eliminar_item') {
    $stmt = mysqli_prepare($conn, "DELETE FROM {$tabla} WHERE id=? AND activo=0");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    $_SESSION['nosotros_admin_success'] = $ok ? 'Registro eliminado.' : 'Solo puedes eliminar registros inactivos.';
    volver_nosotros();
}

$orden = (int) ($_POST['orden'] ?? 0);
if ($tipo === 'indicador') {
    $valor = texto_post('valor', 40);
    $etiqueta = texto_post('etiqueta', 120);
    $stmt = $id > 0
        ? mysqli_prepare($conn, "UPDATE {$tabla} SET valor=?, etiqueta=?, orden=?, activo=? WHERE id=?")
        : mysqli_prepare($conn, "INSERT INTO {$tabla} (valor, etiqueta, orden, activo) VALUES (?, ?, ?, ?)");
    $id > 0 ? mysqli_stmt_bind_param($stmt, 'ssiii', $valor, $etiqueta, $orden, $activo, $id) : mysqli_stmt_bind_param($stmt, 'ssii', $valor, $etiqueta, $orden, $activo);
} elseif ($tipo === 'valor') {
    $icono = preg_replace('/[^a-z0-9-]/i', '', texto_post('icono', 60));
    $titulo = texto_post('titulo', 120);
    $texto = texto_post('texto', 1600);
    $stmt = $id > 0
        ? mysqli_prepare($conn, "UPDATE {$tabla} SET icono=?, titulo=?, texto=?, orden=?, activo=? WHERE id=?")
        : mysqli_prepare($conn, "INSERT INTO {$tabla} (icono, titulo, texto, orden, activo) VALUES (?, ?, ?, ?, ?)");
    $id > 0 ? mysqli_stmt_bind_param($stmt, 'sssiii', $icono, $titulo, $texto, $orden, $activo, $id) : mysqli_stmt_bind_param($stmt, 'sssii', $icono, $titulo, $texto, $orden, $activo);
} elseif ($tipo === 'paso') {
    $numero = texto_post('numero', 10);
    $titulo = texto_post('titulo', 140);
    $texto = texto_post('texto', 1600);
    $stmt = $id > 0
        ? mysqli_prepare($conn, "UPDATE {$tabla} SET numero=?, titulo=?, texto=?, orden=?, activo=? WHERE id=?")
        : mysqli_prepare($conn, "INSERT INTO {$tabla} (numero, titulo, texto, orden, activo) VALUES (?, ?, ?, ?, ?)");
    $id > 0 ? mysqli_stmt_bind_param($stmt, 'sssiii', $numero, $titulo, $texto, $orden, $activo, $id) : mysqli_stmt_bind_param($stmt, 'sssii', $numero, $titulo, $texto, $orden, $activo);
} else {
    $nombre = texto_post('nombre', 120);
    $rol = texto_post('rol', 160);
    $actualImagen = '';
    if ($id > 0) {
        $stmtImg = mysqli_prepare($conn, "SELECT imagen FROM {$tabla} WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($stmtImg, 'i', $id);
        mysqli_stmt_execute($stmtImg);
        $rowImg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtImg));
        $actualImagen = $rowImg['imagen'] ?? '';
        mysqli_stmt_close($stmtImg);
    }
    $imagen = subir_imagen_nosotros('imagen_equipo', $actualImagen ?: 'imagenes/paisajes/img1.jpg');
    $stmt = $id > 0
        ? mysqli_prepare($conn, "UPDATE {$tabla} SET nombre=?, rol=?, imagen=?, orden=?, activo=? WHERE id=?")
        : mysqli_prepare($conn, "INSERT INTO {$tabla} (nombre, rol, imagen, orden, activo) VALUES (?, ?, ?, ?, ?)");
    $id > 0 ? mysqli_stmt_bind_param($stmt, 'sssiii', $nombre, $rol, $imagen, $orden, $activo, $id) : mysqli_stmt_bind_param($stmt, 'sssii', $nombre, $rol, $imagen, $orden, $activo);
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);
$_SESSION['nosotros_admin_success'] = $id > 0 ? 'Registro actualizado.' : 'Registro agregado.';
volver_nosotros();
