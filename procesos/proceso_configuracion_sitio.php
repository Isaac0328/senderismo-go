<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$PERMISO_REQUERIDO = 'interfaz.configuracion_general';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

$seccion = (string) ($_POST['seccion'] ?? '');
$secciones = ['identidad', 'seo', 'menu', 'pie', 'privacidad'];
function site_config_redirect(string $seccion): void
{
    $query = in_array($seccion, ['identidad', 'seo', 'menu', 'pie', 'privacidad'], true)
        ? '?seccion=' . rawurlencode($seccion) : '';
    header('Location: ' . BASE_URL . 'admin/configuracion' . $query);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') site_config_redirect('');
csrf_validate_post(BASE_URL . 'admin/configuracion?seccion=' . rawurlencode($seccion), 'site_config_error');

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/configuracion_sitio.php';

function site_config_text(string $key, int $max, bool $required = false): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    if ($required && $value === '') throw new RuntimeException('Completa todos los campos obligatorios.');
    return mb_substr($value, 0, $max);
}

function site_config_upload(string $field, string $current): string
{
    if (empty($_FILES[$field]['name'])) return $current;
    $saved = sg_save_uploaded_image($_FILES[$field], 'imagenes/configuracion', $field, 4 * 1024 * 1024);
    if (!$saved) throw new RuntimeException('No se pudo guardar ' . str_replace('_', ' ', $field) . '. Usa JPG, PNG o WEBP de hasta 4 MB.');
    return $saved;
}

function site_config_long_text(string $key, int $max): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') throw new RuntimeException('Completa todos los textos de consentimiento y privacidad.');
    return mb_substr($value, 0, $max);
}

try {
    if (!in_array($seccion, $secciones, true)) throw new RuntimeException('Selecciona una seccion valida.');
    if (!sg_has_permission_action($conn, 'interfaz.configuracion_general', 'editar')) {
        throw new RuntimeException('No tienes permiso para editar esta configuracion.');
    }
    if (!sg_site_table_exists($conn, 'configuracion_sitio') || ($seccion === 'menu' && !sg_site_table_exists($conn, 'configuracion_menu'))) {
        throw new RuntimeException('Falta instalar la configuracion general de la base de datos.');
    }

    $current = sg_site_config($conn);
    $data = [];
    if ($seccion === 'identidad') {
        $data = [
            'nombre_sitio' => site_config_text('nombre_sitio', 120, true),
            'nombre_corto' => site_config_text('nombre_corto', 80, true),
            'eslogan' => site_config_text('eslogan', 180),
            'logo_header' => site_config_upload('logo_header', $current['logo_header']),
            'logo_footer' => site_config_upload('logo_footer', $current['logo_footer']),
            'favicon' => site_config_upload('favicon', $current['favicon']),
            'imagen_compartir' => site_config_upload('imagen_compartir', $current['imagen_compartir']),
        ];
    } elseif ($seccion === 'seo') {
        $data = ['meta_descripcion' => site_config_text('meta_descripcion', 320, true)];
    } elseif ($seccion === 'menu') {
        $data = ['login_texto' => site_config_text('login_texto', 60, true)];
    } elseif ($seccion === 'pie') {
        $data = [
            'footer_descripcion' => trim(mb_substr((string) ($_POST['footer_descripcion'] ?? ''), 0, 800)),
            'footer_enlaces_titulo' => site_config_text('footer_enlaces_titulo', 80, true),
            'footer_contacto_titulo' => site_config_text('footer_contacto_titulo', 80, true),
            'footer_copyright' => site_config_text('footer_copyright', 255, true),
        ];
        if ($data['footer_descripcion'] === '') throw new RuntimeException('Completa la descripcion del pie de pagina.');
        foreach ([
            'footer_mostrar_identidad', 'footer_mostrar_logo', 'footer_mostrar_descripcion',
            'footer_mostrar_enlaces', 'footer_mostrar_contacto', 'footer_mostrar_ubicacion',
            'footer_mostrar_telefono', 'footer_mostrar_email', 'footer_mostrar_whatsapp',
            'footer_mostrar_instagram', 'footer_mostrar_copyright',
        ] as $key) {
            $data[$key] = isset($_POST[$key]) ? 1 : 0;
        }
    } else {
        $data = [
            'consentimiento_informado_texto' => site_config_long_text('consentimiento_informado_texto', 10000),
            'rgpd_texto' => site_config_long_text('rgpd_texto', 2000),
            'politica_privacidad_texto' => site_config_long_text('politica_privacidad_texto', 10000),
        ];
    }

    $postedMenu = [];
    $allowedCodes = [];
    if ($seccion === 'menu') {
        $postedMenu = is_array($_POST['menu'] ?? null) ? $_POST['menu'] : [];
        $allowedCodes = array_column(sg_site_menu($conn, false), 'codigo');
        foreach ($allowedCodes as $code) {
            $item = is_array($postedMenu[$code] ?? null) ? $postedMenu[$code] : [];
            if (trim((string) ($item['etiqueta'] ?? '')) === '') throw new RuntimeException('Cada opcion del menu debe tener una etiqueta.');
        }
    }

    mysqli_begin_transaction($conn);
    $conn->query('INSERT IGNORE INTO configuracion_sitio (id) VALUES (1)');
    $columns = array_keys($data);
    $set = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', $columns));
    $stmt = $conn->prepare('UPDATE configuracion_sitio SET ' . $set . ' WHERE id = 1');
    $stmt->execute(array_values($data));
    $stmt->close();

    if ($seccion === 'menu') {
        $stmt = $conn->prepare('UPDATE configuracion_menu SET etiqueta = ?, orden = ?, activo = ? WHERE codigo = ?');
        foreach ($allowedCodes as $code) {
            $item = $postedMenu[$code];
            $label = trim(mb_substr((string) $item['etiqueta'], 0, 80));
            $order = min(999, max(0, (int) ($item['orden'] ?? 0)));
            $active = isset($item['activo']) ? 1 : 0;
            $stmt->bind_param('siis', $label, $order, $active, $code);
            $stmt->execute();
        }
        $stmt->close();
    }
    mysqli_commit($conn);
    $_SESSION['site_config_success'] = 'Cambios guardados correctamente.';
} catch (Throwable $e) {
    if ($conn instanceof mysqli) @mysqli_rollback($conn);
    $_SESSION['site_config_error'] = APP_DEBUG ? $e->getMessage() : 'No se pudo actualizar la configuracion.';
}
mysqli_close($conn);
site_config_redirect($seccion);
