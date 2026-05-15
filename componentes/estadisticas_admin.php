<?php

if (!isset($conn) || !($conn instanceof mysqli)) {
    throw new Exception("admin_stats.php requiere que exista \$conn (mysqli) antes de incluirlo.");
}

function table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    return $res && mysqli_num_rows($res) > 0;
}

function scalar_int(mysqli $conn, string $sql): int
{
    $res = mysqli_query($conn, $sql);
    if (!$res)
        return 0;
    $row = mysqli_fetch_row($res);
    return (int) ($row[0] ?? 0);
}

$stats = [
    // Usuarios
    'totalUsuarios' => scalar_int($conn, "SELECT COUNT(*) FROM usuarios"),
    'usuariosActivos' => scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE estado = 1"),
    'usuariosInact' => scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE estado = 0"),
    'nuevos30d' => scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE created_at >= (NOW() - INTERVAL 30 DAY)"),
    'logins7d' => scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE last_login IS NOT NULL AND last_login >= (NOW() - INTERVAL 7 DAY)"),
    'admins' => scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE rol_id = 1"),

    // Opcionales
    'senderos' => 0,
    'galeria' => 0,
];

if (table_exists($conn, "senderos")) {
    $stats['senderos'] = scalar_int($conn, "SELECT COUNT(*) FROM senderos");
}

if (table_exists($conn, "sendero_imagenes")) {
    $stats['galeria'] = scalar_int($conn, "SELECT COUNT(*) FROM sendero_imagenes WHERE activo = 1");
} elseif (table_exists($conn, "galeria")) {
    $stats['galeria'] = scalar_int($conn, "SELECT COUNT(*) FROM galeria");
}

return $stats;
