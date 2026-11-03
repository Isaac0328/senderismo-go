<?php
require_once __DIR__.'/../configuracion.php';
require_once __DIR__.'/../componentes/csrf.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$PERMISO_REQUERIDO = 'panel.menu_panel';
require_once __DIR__.'/../componentes/proteccion_autenticacion.php';
require_once __DIR__.'/../bd/conexion.php';
require_once __DIR__.'/../componentes/menu_panel.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function menu_panel_redirect(string $anchor = ''): void
{
    header('Location: '.BASE_URL.'admin/menu-panel'.($anchor !== '' ? '#'.$anchor : ''));
    exit;
}
function menu_panel_is_async(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}
function menu_panel_json(bool $ok, string $message, array $extra = []): void
{
    http_response_code($ok ? 200 : 422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'message' => $message] + $extra);
    exit;
}
function menu_panel_text(string $key, int $max, bool $required = true): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    if ($required && $value === '') { throw new RuntimeException('Completa los campos obligatorios.'); }
    return mb_substr($value, 0, $max);
}
function menu_panel_icon(): string
{
    $icon = menu_panel_text('icono', 50);
    if (!isset(sg_admin_menu_icon_choices()[$icon])) { throw new RuntimeException('Selecciona un icono valido.'); }
    return $icon;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { $_SESSION['menu_panel_error'] = 'Metodo no permitido.'; menu_panel_redirect(); }
csrf_validate_post(BASE_URL.'admin/menu-panel', 'menu_panel_error');

$anchor = '';
try {
    if (!sg_admin_menu_tables_ready($conn)) { throw new RuntimeException('Instala primero la actualizacion de configuracion del menu.'); }
    $action = (string) ($_POST['action'] ?? '');
    $id = max(0, (int) ($_POST['id'] ?? 0));
    $permissionAction = match ($action) {
        'create_section' => 'agregar',
        'delete_section' => 'eliminar',
        'save_layout', 'reorder_sections', 'assign_modules' => 'editar',
        default => null,
    };
    if ($permissionAction === null || !sg_has_permission_action($conn, 'panel.menu_panel', $permissionAction)) {
        throw new RuntimeException('No tienes permiso para realizar esta accion.');
    }

    if ($action === 'save_layout') {
        $anchor = 'seccion-'.$id;
        $created = sg_admin_menu_save_layout($conn, $id, [
            'etiqueta' => menu_panel_text('etiqueta', 80),
            'nombre' => menu_panel_text('nombre', 120),
            'icono' => menu_panel_icon(),
            'activo' => isset($_POST['activo']),
            'subs' => (array) ($_POST['subs'] ?? []),
            'modulos' => (array) ($_POST['modulos'] ?? []),
        ], sg_has_permission_action($conn, 'panel.menu_panel', 'agregar'), sg_has_permission_action($conn, 'panel.menu_panel', 'eliminar'));
        if (menu_panel_is_async()) { menu_panel_json(true, 'Seccion guardada.', ['subs' => $created]); }
        $_SESSION['menu_panel_success'] = 'Seccion guardada.';
    } elseif ($action === 'reorder_sections') {
        sg_admin_menu_reorder_sections($conn, (array) ($_POST['orden'] ?? []));
        if (menu_panel_is_async()) { menu_panel_json(true, 'Orden de secciones guardado.'); }
        $_SESSION['menu_panel_success'] = 'Orden de secciones guardado.';
    } elseif ($action === 'assign_modules') {
        $assigned = sg_admin_menu_assign_modules($conn, (array) ($_POST['modulos'] ?? []));
        if ($assigned === 0) { throw new RuntimeException('Elige una seccion para al menos un modulo.'); }
        $_SESSION['menu_panel_success'] = $assigned === 1 ? 'Modulo ubicado en el panel.' : $assigned.' modulos ubicados en el panel.';
    } elseif ($action === 'create_section') {
        $label = menu_panel_text('etiqueta', 80); $name = menu_panel_text('nombre', 120); $icon = menu_panel_icon();
        $next = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COALESCE(MAX(orden),0)+10 siguiente FROM admin_menu_secciones'));
        $order = (int) ($next['siguiente'] ?? 10);
        $stmt = mysqli_prepare($conn, 'INSERT INTO admin_menu_secciones (etiqueta,nombre,icono,orden,activo) VALUES (?,?,?,?,1)');
        mysqli_stmt_bind_param($stmt, 'sssi', $label, $name, $icon, $order);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $anchor = 'seccion-'.mysqli_insert_id($conn);
        $_SESSION['menu_panel_success'] = 'Seccion creada. Ahora puedes agregarle modulos.';
    } elseif ($action === 'delete_section' && $id > 0) {
        if (!sg_admin_menu_section_exists($conn, $id)) { throw new RuntimeException('La seccion no existe.'); }
        $sectionCount = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) total FROM admin_menu_secciones'));
        if ((int) $sectionCount['total'] <= 1) { throw new RuntimeException('Conserva al menos una seccion en el panel.'); }
        if (!sg_admin_menu_can_delete_section($conn, $id)) { throw new RuntimeException('No puedes eliminar esta seccion porque tiene subsecciones o modulos.'); }
        $stmt = mysqli_prepare($conn, 'DELETE FROM admin_menu_secciones WHERE id=?'); mysqli_stmt_bind_param($stmt, 'i', $id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); $_SESSION['menu_panel_success'] = 'Seccion eliminada.';
    } else { throw new RuntimeException('Accion no valida.'); }
} catch (InvalidArgumentException|RuntimeException $e) {
    if (menu_panel_is_async()) { menu_panel_json(false, $e->getMessage()); }
    $_SESSION['menu_panel_error'] = $e->getMessage();
} catch (Throwable $e) {
    if (menu_panel_is_async()) { menu_panel_json(false, APP_DEBUG ? $e->getMessage() : 'No se pudo actualizar el panel.'); }
    $_SESSION['menu_panel_error'] = APP_DEBUG ? $e->getMessage() : 'No se pudo actualizar el panel.';
}
menu_panel_redirect($anchor);
