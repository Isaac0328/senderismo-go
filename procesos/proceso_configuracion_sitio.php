<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'interfaz.configuracion_general';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

function site_config_redirect(): void
{
    header('Location: ' . BASE_URL . 'mantenimientos/mantenimiento_configuracion_sitio.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['site_config_error'] = 'Metodo no permitido.';
    site_config_redirect();
}
csrf_validate_post(BASE_URL . 'mantenimientos/mantenimiento_configuracion_sitio.php', 'site_config_error');

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/configuracion_sitio.php';

function site_config_text(string $key, int $max, bool $required = false): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    if ($required && $value === '') {
        throw new RuntimeException('Completa todos los campos obligatorios.');
    }
    return mb_substr($value, 0, $max);
}

function site_config_upload(string $field, string $current): string
{
    if (empty($_FILES[$field]['name'])) {
        return $current;
    }
    $saved = sg_save_uploaded_image($_FILES[$field], 'imagenes/configuracion', $field, 4 * 1024 * 1024);
    if (!$saved) {
        throw new RuntimeException('No se pudo guardar ' . str_replace('_', ' ', $field) . '. Usa JPG, PNG o WEBP de hasta 4 MB.');
    }
    return $saved;
}

try {
    if (!sg_site_table_exists($conn, 'configuracion_sitio') || !sg_site_table_exists($conn, 'configuracion_menu')) {
        throw new RuntimeException('Primero ejecuta el script 2026-07-01_configuracion_general_sitio.sql.');
    }

    $current = sg_site_config($conn);
    $data = [
        'nombre_sitio' => site_config_text('nombre_sitio', 120, true),
        'nombre_corto' => site_config_text('nombre_corto', 80, true),
        'eslogan' => site_config_text('eslogan', 180),
        'logo_header' => site_config_upload('logo_header', $current['logo_header']),
        'logo_footer' => site_config_upload('logo_footer', $current['logo_footer']),
        'favicon' => site_config_upload('favicon', $current['favicon']),
        'imagen_compartir' => site_config_upload('imagen_compartir', $current['imagen_compartir']),
        'meta_descripcion' => site_config_text('meta_descripcion', 320, true),
        'login_texto' => site_config_text('login_texto', 60, true),
        'footer_descripcion' => trim(mb_substr((string) ($_POST['footer_descripcion'] ?? ''), 0, 800)),
        'footer_enlaces_titulo' => site_config_text('footer_enlaces_titulo', 80, true),
        'footer_contacto_titulo' => site_config_text('footer_contacto_titulo', 80, true),
        'footer_copyright' => site_config_text('footer_copyright', 255, true),
    ];
    if ($data['footer_descripcion'] === '') {
        throw new RuntimeException('Completa la descripcion del pie de pagina.');
    }

    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "
        INSERT INTO configuracion_sitio
            (id, nombre_sitio, nombre_corto, eslogan, logo_header, logo_footer, favicon, imagen_compartir, meta_descripcion, login_texto, footer_descripcion, footer_enlaces_titulo, footer_contacto_titulo, footer_copyright)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nombre_sitio = VALUES(nombre_sitio), nombre_corto = VALUES(nombre_corto), eslogan = VALUES(eslogan),
            logo_header = VALUES(logo_header), logo_footer = VALUES(logo_footer), favicon = VALUES(favicon),
            imagen_compartir = VALUES(imagen_compartir), meta_descripcion = VALUES(meta_descripcion),
            login_texto = VALUES(login_texto), footer_descripcion = VALUES(footer_descripcion),
            footer_enlaces_titulo = VALUES(footer_enlaces_titulo), footer_contacto_titulo = VALUES(footer_contacto_titulo),
            footer_copyright = VALUES(footer_copyright)
    ");
    mysqli_stmt_bind_param($stmt, 'sssssssssssss', $data['nombre_sitio'], $data['nombre_corto'], $data['eslogan'], $data['logo_header'], $data['logo_footer'], $data['favicon'], $data['imagen_compartir'], $data['meta_descripcion'], $data['login_texto'], $data['footer_descripcion'], $data['footer_enlaces_titulo'], $data['footer_contacto_titulo'], $data['footer_copyright']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $postedMenu = is_array($_POST['menu'] ?? null) ? $_POST['menu'] : [];
    $allowedCodes = array_column(sg_site_menu($conn, false), 'codigo');
    $stmt = mysqli_prepare($conn, 'UPDATE configuracion_menu SET etiqueta = ?, orden = ?, activo = ? WHERE codigo = ?');
    foreach ($allowedCodes as $code) {
        $item = is_array($postedMenu[$code] ?? null) ? $postedMenu[$code] : [];
        $label = trim(mb_substr((string) ($item['etiqueta'] ?? ''), 0, 80));
        if ($label === '') {
            throw new RuntimeException('Cada opcion del menu debe tener una etiqueta.');
        }
        $order = min(999, max(0, (int) ($item['orden'] ?? 0)));
        $active = isset($item['activo']) ? 1 : 0;
        mysqli_stmt_bind_param($stmt, 'siis', $label, $order, $active, $code);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($conn);
    $_SESSION['site_config_success'] = 'Configuracion general actualizada correctamente.';
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        @mysqli_rollback($conn);
    }
    $_SESSION['site_config_error'] = APP_DEBUG ? $e->getMessage() : 'No se pudo actualizar la configuracion.';
}

mysqli_close($conn);
site_config_redirect();
