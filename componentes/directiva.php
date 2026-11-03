<?php
require_once __DIR__ . '/cargos.php';

// Los miembros de la directiva (casilla "Miembro de la directiva" del usuario) participan en los senderos sin cobro por ellos mismos:
// no eligen inversion ni adjuntan comprobante. Sus menores acompanantes si pagan su inversion.

if (!function_exists('sg_usuario_es_directiva')) {
    function sg_usuario_es_directiva(mysqli $conn, int $usuarioId): bool
    {
        static $cache = [];
        if (!isset($cache[$usuarioId])) {
            $columna = sg_directiva_columna_lista($conn) ? 'u.es_directiva' : '0';
            $stmt = mysqli_prepare($conn, "SELECT r.nombre, {$columna} AS casilla FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $usuarioId);
            mysqli_stmt_execute($stmt);
            $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            // Es de la directiva con el cargo Directiva (o, antes de la actualizacion de cargos, con ese rol).
            $cache[$usuarioId] = ($fila && ((int) $fila['casilla'] === 1 || strcasecmp(trim((string) $fila['nombre']), 'Directiva') === 0))
                || in_array(sg_cargo_id_por_nombre($conn, 'Directiva'), sg_cargos_de_usuario($conn, $usuarioId), true);
        }
        return $cache[$usuarioId];
    }
}