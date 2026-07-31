<?php

require_once __DIR__ . '/helpers.php';

if (!function_exists('sg_admin_fetch_all')) {
    function sg_admin_fetch_all(mysqli_result|false $result): array
    {
        $rows = [];
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('sg_admin_find_row_by_id')) {
    function sg_admin_find_row_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('sg_admin_metodos_pago')) {
    function sg_admin_metodos_pago(mysqli $conn): array
    {
        return sg_admin_fetch_all(mysqli_query(
            $conn,
            "SELECT id, nombre FROM contabilidad_metodo_pago WHERE activo = 1 ORDER BY nombre ASC"
        ));
    }
}

if (!function_exists('sg_senderos_catalogos_mantenimiento')) {
    function sg_senderos_catalogos_mantenimiento(mysqli $conn): array
    {
        return [
            'niveles' => sg_admin_fetch_all(mysqli_query(
                $conn,
                "SELECT id, nombre, nivel_numero FROM niveles_dificultad WHERE activo = 1 ORDER BY nivel_numero ASC, id ASC"
            )),
            'terrenos' => sg_admin_fetch_all(mysqli_query(
                $conn,
                "SELECT id, nombre FROM tipos_terreno WHERE activo = 1 ORDER BY nombre ASC"
            )),
            'caminosVehiculo' => sg_admin_fetch_all(mysqli_query(
                $conn,
                "SELECT id, nombre FROM tipos_camino_vehiculo WHERE activo = 1 ORDER BY nombre ASC"
            )),
            'anotaciones' => sg_admin_fetch_all(mysqli_query(
                $conn,
                "SELECT id, nombre, descripcion FROM anotaciones_importantes WHERE activo = 1 ORDER BY nombre ASC"
            )),
            'incluyeItems' => sg_admin_fetch_all(mysqli_query(
                $conn,
                "SELECT id, nombre, descripcion FROM elementos_incluidos WHERE activo = 1 ORDER BY nombre ASC"
            )),
            'puntosCatalogo' => sg_admin_fetch_all(mysqli_query(
                $conn,
                "SELECT id, nombre, direccion_referencia, url_mapa FROM puntos_encuentro WHERE activo = 1 ORDER BY nombre ASC"
            )),
        ];
    }
}

if (!function_exists('sg_senderos_listado_mantenimiento')) {
    function sg_senderos_listado_mantenimiento(mysqli $conn): array
    {
        return sg_admin_fetch_all(mysqli_query($conn, "
            SELECT s.*, nd.nombre AS nivel_nombre,
                   tc.nombre AS camino_nombre,
                   (SELECT COUNT(*) FROM sendero_imagenes si WHERE si.sendero_id = s.id AND si.activo = 1) AS total_imagenes,
                   (SELECT COUNT(*) FROM sendero_puntos_encuentro sp WHERE sp.sendero_id = s.id AND sp.activo = 1) AS total_puntos
            FROM senderos s
            INNER JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
            LEFT JOIN tipos_camino_vehiculo tc ON tc.id = s.tipo_camino_vehiculo_id
            ORDER BY s.fecha_sendero IS NULL ASC, s.fecha_sendero DESC, s.id DESC
        "));
    }
}

if (!function_exists('sg_admin_senderos_para_ingresos')) {
    function sg_admin_senderos_para_ingresos(mysqli $conn, string $where, string $types, array $values): array
    {
        $res = sgf_execute_query($conn, "
            SELECT
                s.id,
                s.nombre,
                s.fecha_sendero,
                s.estado,
                s.distancia_km,
                nd.nombre AS dificultad_nombre,
                COALESCE(SUM(1 + COALESCE(m.total_menores, 0)), 0) AS inscritos,
                COALESCE(SUM(CASE WHEN crp.pagado = 1 THEN 1 + COALESCE(m.total_menores, 0) ELSE 0 END), 0) AS pagados,
                COALESCE(SUM(CASE WHEN crp.pagado = 1 THEN crp.monto_pagado ELSE 0 END), 0) AS ingresos
            FROM senderos s
            LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
            LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id AND rs.estado = 'registrado'
            LEFT JOIN (
                SELECT registro_id, COUNT(*) AS total_menores
                FROM registro_sendero_menores
                GROUP BY registro_id
            ) m ON m.registro_id = rs.id
            LEFT JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
            {$where}
            GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, nd.nombre
            ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
        ", $types, $values);

        return sg_admin_fetch_all($res);
    }
}

if (!function_exists('sg_admin_senderos_para_asistencia')) {
    function sg_admin_senderos_para_asistencia(mysqli $conn, string $where, string $types, array $values): array
    {
        $res = sgf_execute_query($conn, "
            SELECT
                s.id,
                s.nombre,
                s.fecha_sendero,
                s.estado,
                s.distancia_km,
                nd.nombre AS dificultad_nombre,
                COALESCE(SUM(CASE WHEN rs.estado = 'registrado' THEN 1 + COALESCE(m.menores, 0) ELSE 0 END), 0) AS registrados,
                COALESCE(SUM(CASE WHEN rs.asistio = 1 AND rs.estado = 'registrado' THEN 1 + COALESCE(m.menores, 0) ELSE 0 END), 0) AS asistieron
            FROM senderos s
            LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
            LEFT JOIN registros_senderos rs ON rs.sendero_id = s.id AND rs.estado = 'registrado'
            LEFT JOIN (
                SELECT registro_id, COUNT(*) AS menores
                FROM registro_sendero_menores
                GROUP BY registro_id
            ) m ON m.registro_id = rs.id
            {$where}
            GROUP BY s.id, s.nombre, s.fecha_sendero, s.estado, s.distancia_km, nd.nombre
            ORDER BY COALESCE(s.fecha_sendero, '1900-01-01') DESC, s.nombre ASC
        ", $types, $values);

        return sg_admin_fetch_all($res);
    }
}
