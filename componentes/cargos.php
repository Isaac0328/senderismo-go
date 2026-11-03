<?php
// Cargos de los usuarios (Contable, Directiva...): grupos a los que pertenecen, aparte del rol de acceso.
// Un usuario puede tener varios cargos; sus permisos se suman a los de su rol.

if (!function_exists('sg_cargos_listo')) {
    function sg_cargos_listo(mysqli $conn): bool
    {
        static $listo = null;
        if ($listo === null) {
            $res = mysqli_query($conn, "SHOW TABLES LIKE 'usuario_cargos'");
            $listo = $res && mysqli_num_rows($res) === 1;
        }
        return $listo;
    }
}

// La directiva es una casilla propia del usuario (usuarios.es_directiva), aparte de los cargos y sin permisos.
if (!function_exists('sg_directiva_columna_lista')) {
    function sg_directiva_columna_lista(mysqli $conn): bool
    {
        static $listo = null;
        if ($listo === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'es_directiva'");
            $listo = $res && mysqli_num_rows($res) === 1;
        }
        return $listo;
    }
}

if (!function_exists('sg_cargos_de_usuario')) {
    function sg_cargos_de_usuario(mysqli $conn, int $usuarioId): array
    {
        static $cache = [];
        if ($usuarioId <= 0 || !sg_cargos_listo($conn)) {
            return [];
        }
        if (!isset($cache[$usuarioId])) {
            $ids = [];
            $stmt = mysqli_prepare($conn, 'SELECT cargo_id FROM usuario_cargos WHERE usuario_id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $usuarioId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($res && $row = mysqli_fetch_row($res)) {
                    $ids[] = (int) $row[0];
                }
                mysqli_stmt_close($stmt);
            }
            $cache[$usuarioId] = $ids;
        }
        return $cache[$usuarioId];
    }
}

if (!function_exists('sg_cargo_id_por_nombre')) {
    function sg_cargo_id_por_nombre(mysqli $conn, string $nombre): int
    {
        static $cache = [];
        if (!isset($cache[$nombre])) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM roles WHERE nombre = ? LIMIT 1");
            $id = 0;
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $nombre);
                mysqli_stmt_execute($stmt);
                $fila = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
                $id = (int) ($fila[0] ?? 0);
                mysqli_stmt_close($stmt);
            }
            $cache[$nombre] = $id;
        }
        return $cache[$nombre];
    }
}

// Expresion SQL (1 o 0) que dice si el usuario es de la directiva: tiene el cargo Directiva o, antes de la
// actualizacion de cargos, ese rol. $usuarioCol es la columna con el id del usuario y $rolNombreCol el nombre del rol.
if (!function_exists('sg_directiva_sql')) {
    function sg_directiva_sql(mysqli $conn, string $usuarioCol = 'u.id', string $rolNombreCol = 'ro.nombre'): string
    {
        $alias = strstr($usuarioCol, '.', true);
        $casilla = sg_directiva_columna_lista($conn) && $alias !== false ? "{$alias}.es_directiva = 1 OR " : '';
        $cargo = sg_cargos_listo($conn)
            ? " OR EXISTS (SELECT 1 FROM usuario_cargos dc JOIN roles dr ON dr.id = dc.cargo_id WHERE dc.usuario_id = {$usuarioCol} AND dr.nombre = 'Directiva')"
            : '';
        return "(CASE WHEN {$casilla}{$rolNombreCol} = 'Directiva'{$cargo} THEN 1 ELSE 0 END)";
    }
}
