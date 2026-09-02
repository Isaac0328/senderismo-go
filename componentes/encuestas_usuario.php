<?php

if (!function_exists('sg_encuestas_usuario_disponibles')) {
    function sg_encuestas_usuario_disponibles(mysqli $conn): bool
    {
        static $cache = [];
        $key = spl_object_id($conn);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $res = @mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('encuestas', 'encuesta_envios')
        ");
        $row = $res ? mysqli_fetch_assoc($res) : [];
        $cache[$key] = (int) ($row['total'] ?? 0) === 2;

        return $cache[$key];
    }
}

if (!function_exists('sg_encuestas_usuario_resumen')) {
    function sg_encuestas_usuario_resumen(mysqli $conn, int $usuarioId, int $limite = 5): array
    {
        $vacio = ['total' => 0, 'items' => []];
        if ($usuarioId <= 0 || !sg_encuestas_usuario_disponibles($conn)) {
            return $vacio;
        }

        $limite = max(1, min(100, $limite));
        $total = 0;
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM encuesta_envios ee
            INNER JOIN encuestas e ON e.id = ee.encuesta_id
            WHERE ee.usuario_id = ?
              AND ee.estado = 'pendiente'
              AND e.estado = 'enviada'
              AND e.activo = 1
              AND (e.fecha_cierre IS NULL OR e.fecha_cierre >= CURDATE())
        ");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $usuarioId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
            $total = (int) ($row['total'] ?? 0);
            mysqli_stmt_close($stmt);
        }

        if ($total === 0) {
            return $vacio;
        }

        $items = [];
        $stmt = mysqli_prepare($conn, "
            SELECT
                ee.id AS envio_id,
                e.titulo,
                e.descripcion,
                e.fecha_cierre,
                s.nombre AS sendero_nombre
            FROM encuesta_envios ee
            INNER JOIN encuestas e ON e.id = ee.encuesta_id
            LEFT JOIN senderos s ON s.id = COALESCE(ee.sendero_id, e.sendero_id)
            WHERE ee.usuario_id = ?
              AND ee.estado = 'pendiente'
              AND e.estado = 'enviada'
              AND e.activo = 1
              AND (e.fecha_cierre IS NULL OR e.fecha_cierre >= CURDATE())
            ORDER BY ee.enviado_at DESC, ee.id DESC
            LIMIT {$limite}
        ");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $usuarioId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && $row = mysqli_fetch_assoc($res)) {
                $items[] = $row;
            }
            mysqli_stmt_close($stmt);
        }

        return ['total' => $total, 'items' => $items];
    }
}
