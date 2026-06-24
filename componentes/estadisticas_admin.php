<?php

if (!isset($conn) || !($conn instanceof mysqli)) {
    throw new Exception("estadisticas_admin.php requiere que exista \$conn (mysqli) antes de incluirlo.");
}

if (!function_exists('admin_table_exists')) {
    function admin_table_exists(mysqli $conn, string $table): bool
    {
        $table = mysqli_real_escape_string($conn, $table);
        $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('admin_column_exists')) {
    function admin_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $db = mysqli_real_escape_string($conn, DB_NAME);
        $table = mysqli_real_escape_string($conn, $table);
        $column = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '{$db}'
              AND TABLE_NAME = '{$table}'
              AND COLUMN_NAME = '{$column}'
        ");
        if (!$res) {
            return false;
        }
        $row = mysqli_fetch_assoc($res);
        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('admin_scalar_int')) {
    function admin_scalar_int(mysqli $conn, string $sql): int
    {
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return 0;
        }
        $row = mysqli_fetch_row($res);
        return (int) ($row[0] ?? 0);
    }
}

if (!function_exists('admin_scalar_float')) {
    function admin_scalar_float(mysqli $conn, string $sql): float
    {
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return 0.0;
        }
        $row = mysqli_fetch_row($res);
        return (float) ($row[0] ?? 0);
    }
}

if (!function_exists('admin_fetch_all')) {
    function admin_fetch_all(mysqli $conn, string $sql): array
    {
        $rows = [];
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return $rows;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

$hasSenderos = admin_table_exists($conn, 'senderos');
$hasRegistros = admin_table_exists($conn, 'registros_senderos');
$hasPagos = admin_table_exists($conn, 'contabilidad_registro_pagos');
$hasGastosSendero = admin_table_exists($conn, 'contabilidad_sendero_gastos');
$hasMensajes = admin_table_exists($conn, 'mensajes_contacto');
$hasUsuarios = admin_table_exists($conn, 'usuarios');
$hasImagenes = admin_table_exists($conn, 'sendero_imagenes');
$hasAsistio = $hasRegistros && admin_column_exists($conn, 'registros_senderos', 'asistio');
$hasUsuarioEstado = $hasUsuarios && admin_column_exists($conn, 'usuarios', 'estado');
$hasUsuarioCreatedAt = $hasUsuarios && admin_column_exists($conn, 'usuarios', 'created_at');
$hasUsuarioLastLogin = $hasUsuarios && admin_column_exists($conn, 'usuarios', 'last_login');
$hasUsuarioRolId = $hasUsuarios && admin_column_exists($conn, 'usuarios', 'rol_id');
$hasRegistroManualNombre = $hasRegistros && admin_column_exists($conn, 'registros_senderos', 'manual_nombre');
$hasRegistroManualApellido = $hasRegistros && admin_column_exists($conn, 'registros_senderos', 'manual_apellido');
$hasRegistroFecha = $hasRegistros && admin_column_exists($conn, 'registros_senderos', 'fecha_registro');
$hasRegistroUpdated = $hasRegistros && admin_column_exists($conn, 'registros_senderos', 'updated_at');
$hasRegistroEstado = $hasRegistros && admin_column_exists($conn, 'registros_senderos', 'estado');

$stats = [
    'totalUsuarios' => $hasUsuarios ? admin_scalar_int($conn, "SELECT COUNT(*) FROM usuarios") : 0,
    'usuariosActivos' => $hasUsuarioEstado ? admin_scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE estado = 1") : 0,
    'usuariosInact' => $hasUsuarioEstado ? admin_scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE estado = 0") : 0,
    'nuevos30d' => $hasUsuarioCreatedAt ? admin_scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE created_at >= (NOW() - INTERVAL 30 DAY)") : 0,
    'logins7d' => $hasUsuarioLastLogin ? admin_scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE last_login IS NOT NULL AND last_login >= (NOW() - INTERVAL 7 DAY)") : 0,
    'admins' => $hasUsuarioRolId ? admin_scalar_int($conn, "SELECT COUNT(*) FROM usuarios WHERE rol_id = 1") : 0,
    'senderos' => $hasSenderos ? admin_scalar_int($conn, "SELECT COUNT(*) FROM senderos") : 0,
    'senderosProximos' => $hasSenderos ? admin_scalar_int($conn, "SELECT COUNT(*) FROM senderos WHERE estado = 'pendiente' AND activo = 1 AND fecha_sendero >= CURDATE()") : 0,
    'senderosVisitados' => $hasSenderos ? admin_scalar_int($conn, "SELECT COUNT(*) FROM senderos WHERE estado = 'visitado' AND activo = 1") : 0,
    'registrosActivos' => $hasRegistroEstado ? admin_scalar_int($conn, "SELECT COUNT(*) FROM registros_senderos WHERE estado = 'registrado'") : 0,
    'registros30d' => ($hasRegistroEstado && $hasRegistroFecha) ? admin_scalar_int($conn, "SELECT COUNT(*) FROM registros_senderos WHERE estado = 'registrado' AND fecha_registro >= (NOW() - INTERVAL 30 DAY)") : 0,
    'asistencias' => ($hasAsistio && $hasRegistroEstado) ? admin_scalar_int($conn, "SELECT COUNT(*) FROM registros_senderos WHERE estado = 'registrado' AND asistio = 1") : 0,
    'mensajesNuevos' => $hasMensajes ? admin_scalar_int($conn, "SELECT COUNT(*) FROM mensajes_contacto WHERE estado = 'nuevo'") : 0,
    'mensajes7d' => $hasMensajes ? admin_scalar_int($conn, "SELECT COUNT(*) FROM mensajes_contacto WHERE fecha_creacion >= (NOW() - INTERVAL 7 DAY)") : 0,
    'galeria' => $hasImagenes ? admin_scalar_int($conn, "SELECT COUNT(*) FROM sendero_imagenes WHERE activo = 1") : 0,
    'ingresosMes' => $hasPagos ? admin_scalar_float($conn, "SELECT COALESCE(SUM(monto_pagado + credito_aplicado), 0) FROM contabilidad_registro_pagos WHERE estado_financiero <> 'cortesia' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')") : 0.0,
    'ingresosTotales' => $hasPagos ? admin_scalar_float($conn, "SELECT COALESCE(SUM(monto_pagado + credito_aplicado), 0) FROM contabilidad_registro_pagos WHERE estado_financiero <> 'cortesia'") : 0.0,
    'porCobrar' => $hasPagos ? admin_scalar_float($conn, "SELECT COALESCE(SUM(saldo_pendiente), 0) FROM contabilidad_registro_pagos WHERE estado_financiero IN ('deuda','parcial','pendiente')") : 0.0,
    'gastosTotales' => $hasGastosSendero ? admin_scalar_float($conn, "SELECT COALESCE(SUM(total), 0) FROM contabilidad_sendero_gastos") : 0.0,
    'creditosActivos' => admin_table_exists($conn, 'usuario_creditos') ? admin_scalar_float($conn, "SELECT COALESCE(SUM(saldo_disponible), 0) FROM usuario_creditos WHERE estado = 'activo'") : 0.0,
    'proximosSenderos' => [],
    'actividadReciente' => [],
];

if ($hasSenderos) {
    $stats['proximosSenderos'] = admin_fetch_all($conn, "
        SELECT id, nombre, fecha_sendero, lugar, provincia
        FROM senderos
        WHERE estado = 'pendiente'
          AND activo = 1
          AND fecha_sendero >= CURDATE()
        ORDER BY fecha_sendero ASC, nombre ASC
        LIMIT 4
    ");
}

$manualNombre = $hasRegistroManualNombre ? 'rs.manual_nombre' : 'NULL';
$manualApellido = $hasRegistroManualApellido ? 'rs.manual_apellido' : 'NULL';
$registroFecha = $hasRegistroFecha ? 'rs.fecha_registro' : ($hasRegistroUpdated ? 'rs.updated_at' : 'NOW()');
$registroEstado = $hasRegistroEstado ? 'rs.estado' : "'registrado'";

if ($hasRegistros && $hasUsuarios && $hasSenderos) {
    $stats['actividadReciente'] = admin_fetch_all($conn, "
        SELECT
            'registro' AS tipo,
            COALESCE(NULLIF(TRIM(CONCAT(u.nombre, ' ', u.apellido)), ''), NULLIF(TRIM(CONCAT({$manualNombre}, ' ', {$manualApellido})), ''), 'Participante') AS principal,
            s.nombre AS secundario,
            {$registroFecha} AS fecha,
            {$registroEstado} AS estado
        FROM registros_senderos rs
        LEFT JOIN usuarios u ON u.id = rs.usuario_id
        LEFT JOIN senderos s ON s.id = rs.sendero_id
        ORDER BY {$registroFecha} DESC
        LIMIT 5
    ");
}

return $stats;
