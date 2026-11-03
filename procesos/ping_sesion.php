<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$loggedIn = !empty($_SESSION['usuario_id'])
    && !empty($_SESSION['logged_in'])
    && $_SESSION['logged_in'] === true;

if (!$loggedIn) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'not_authenticated']);
    exit;
}

$roleId = (int) ($_SESSION['usuario_rol_id'] ?? 0);
$limit = ($roleId === 1) ? (10 * 60) : (20 * 60);
$now = time();
$last = (int) ($_SESSION['last_activity'] ?? 0);

if ($last > 0 && ($now - $last) > $limit) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'reason' => 'expired']);
    exit;
}

$_SESSION['last_activity'] = $now;

echo json_encode([
    'ok' => true,
    'server_time' => $now,
]);
