<?php
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/componentes/rutas.php';

$legacyMarkerCount = (int) isset($_GET['__legacy_screen'])
    + (int) isset($_GET['__legacy_admin'])
    + (int) isset($_GET['__legacy_logout']);
if ($legacyMarkerCount > 1) {
    http_response_code(400);
    exit;
}

$legacyRoute = null;
if (isset($_GET['__legacy_screen'])) {
    $legacyScreen = strtolower((string) $_GET['__legacy_screen']);
    if (preg_match('/^[a-z0-9_]+$/', $legacyScreen)) {
        $legacyRoute = $legacyScreen === 'index' ? '' : sg_clean_route_for_screen($legacyScreen);
    }
    unset($_GET['__legacy_screen']);
} elseif (isset($_GET['__legacy_admin'])) {
    $legacyAdmin = strtolower((string) $_GET['__legacy_admin']);
    if (preg_match('/^[a-z0-9_]+$/', $legacyAdmin)) {
        $legacyRoute = 'admin/' . str_replace('_', '-', $legacyAdmin);
    }
    unset($_GET['__legacy_admin']);
} elseif (isset($_GET['__legacy_logout'])) {
    $legacyRoute = 'salir';
    unset($_GET['__legacy_logout']);
}

if ($legacyRoute !== null) {
    $destination = BASE_URL . ltrim($legacyRoute, '/');
    $query = http_build_query($_GET);
    header('Location: ' . $destination . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

$route = sg_current_clean_route();
$target = sg_resolve_clean_route($route);

if ($target === null) {
    http_response_code(404);
    $pageTitle = 'Pagina no encontrada';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>body{margin:0;display:grid;min-height:100vh;place-items:center;background:#f2f5f1;font-family:Arial,sans-serif;color:#111}.box{max-width:520px;padding:32px;text-align:center}.code{font-size:4rem;font-weight:900;color:#c40000}.box h1{margin:8px 0}.box p{color:#5f6863;line-height:1.5}.box a{display:inline-block;margin-top:12px;padding:12px 18px;border-radius:7px;background:#111;color:#fff;text-decoration:none;font-weight:700}</style>';
    echo '</head><body><main class="box"><div class="code">404</div><h1>Pagina no encontrada</h1><p>La direccion solicitada no existe o fue movida.</p><a href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '">Volver al inicio</a></main></body></html>';
    exit;
}

$_SERVER['SG_CLEAN_ROUTE_DISPATCH'] = '1';
$_SERVER['SG_ROUTE_TARGET'] = $target;
$originalWorkingDirectory = getcwd();
chdir(dirname($target));
require $target;
if ($originalWorkingDirectory !== false) {
    chdir($originalWorkingDirectory);
}
