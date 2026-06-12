<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/tema_colores.php';
require_once __DIR__ . '/../bd/conexion.php';

function redirigir_tema(string $tipo, string $mensaje): void
{
    $_SESSION[$tipo === 'success' ? 'tema_admin_success' : 'tema_admin_error'] = $mensaje;
    header('Location: ' . BASE_URL . 'mantenimientos/mantenimiento_tema.php');
    exit;
}

function color_post(string $key, string $fallback): string
{
    return sg_hex_color($_POST[$key] ?? null, $fallback);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_tema('error', 'Solicitud invalida.');
}

$paletas = sg_paletas_colores();
$tema = trim((string) ($_POST['tema'] ?? 'senderismo'));
$permitidos = array_merge(array_keys($paletas), ['personalizado']);

if (!in_array($tema, $permitidos, true)) {
    redirigir_tema('error', 'Selecciona una paleta valida.');
}

$base = $paletas['senderismo'];
if ($tema !== 'personalizado') {
    $colores = $paletas[$tema];
} else {
    $colores = [
        'primary' => color_post('primary_color', $base['primary']),
        'primary_dark' => color_post('primary_dark_color', $base['primary_dark']),
        'accent' => color_post('accent_color', $base['accent']),
        'accent_dark' => color_post('accent_dark_color', $base['accent_dark']),
        'background' => color_post('background_color', $base['background']),
        'surface' => color_post('surface_color', $base['surface']),
        'text' => color_post('text_color', $base['text']),
        'muted' => color_post('muted_color', $base['muted']),
    ];
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS configuracion_tema (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        tema VARCHAR(40) NOT NULL DEFAULT 'senderismo',
        primary_color VARCHAR(7) NOT NULL DEFAULT '#255f38',
        primary_dark_color VARCHAR(7) NOT NULL DEFAULT '#102617',
        accent_color VARCHAR(7) NOT NULL DEFAULT '#e10600',
        accent_dark_color VARCHAR(7) NOT NULL DEFAULT '#b90000',
        background_color VARCHAR(7) NOT NULL DEFAULT '#f3f6ef',
        surface_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
        text_color VARCHAR(7) NOT NULL DEFAULT '#111111',
        muted_color VARCHAR(7) NOT NULL DEFAULT '#5f6d64',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$sql = "
    INSERT INTO configuracion_tema (
        id, tema, primary_color, primary_dark_color, accent_color, accent_dark_color,
        background_color, surface_color, text_color, muted_color
    ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        tema = VALUES(tema),
        primary_color = VALUES(primary_color),
        primary_dark_color = VALUES(primary_dark_color),
        accent_color = VALUES(accent_color),
        accent_dark_color = VALUES(accent_dark_color),
        background_color = VALUES(background_color),
        surface_color = VALUES(surface_color),
        text_color = VALUES(text_color),
        muted_color = VALUES(muted_color)
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    redirigir_tema('error', 'No se pudo preparar el guardado del tema.');
}

mysqli_stmt_bind_param(
    $stmt,
    'sssssssss',
    $tema,
    $colores['primary'],
    $colores['primary_dark'],
    $colores['accent'],
    $colores['accent_dark'],
    $colores['background'],
    $colores['surface'],
    $colores['text'],
    $colores['muted']
);

if (!mysqli_stmt_execute($stmt)) {
    redirigir_tema('error', 'No se pudo guardar la paleta seleccionada.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
redirigir_tema('success', 'Paleta visual actualizada correctamente.');
