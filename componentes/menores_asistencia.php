<?php
// Menores acompanantes de un sendero: lista por inscripcion, con su inversion y su asistencia propia.

if (!function_exists('sg_menores_asistencia_listo')) {
    function sg_menores_asistencia_listo(mysqli $conn): bool
    {
        static $listo = null;
        if ($listo === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM registro_sendero_menores LIKE 'asistio'");
            $listo = $res && mysqli_num_rows($res) === 1;
        }
        return $listo;
    }
}

if (!function_exists('sg_menores_de_sendero')) {
    /**
     * Devuelve [registro_id => [menor, ...]] con id, nombre, rango_edad, asistio, inversion y monto (pesos).
     * Si $registroIds se indica, solo esas inscripciones.
     */
    function sg_menores_de_sendero(mysqli $conn, int $senderoId, array $registroIds = []): array
    {
        $asistio = sg_menores_asistencia_listo($conn) ? 'm.asistio' : '0';
        $filtro = $registroIds ? ' AND rs.id IN (' . implode(',', array_map('intval', $registroIds)) . ')' : '';
        $stmt = mysqli_prepare(
            $conn,
            "SELECT m.id, m.registro_id, m.nombre, m.apellido, COALESCE(m.rango_edad, '') AS rango_edad,
                    {$asistio} AS asistio, COALESCE(si.nombre, 'Sin inversion') AS inversion,
                    COALESCE(CASE WHEN COALESCE(si.es_menor, 0) = 1 THEN si.monto ELSE COALESCE(si.monto_menor, si.monto) END, 0) AS monto
             FROM registro_sendero_menores m
             INNER JOIN registros_senderos rs ON rs.id = m.registro_id
             LEFT JOIN sendero_inversiones si ON si.id = m.inversion_id
             WHERE rs.sendero_id = ? AND rs.estado = 'registrado'{$filtro}
             ORDER BY m.registro_id, m.id"
        );
        $porRegistro = [];
        if (!$stmt) {
            return $porRegistro;
        }
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && $fila = mysqli_fetch_assoc($res)) {
            $porRegistro[(int) $fila['registro_id']][] = [
                'id' => (int) $fila['id'],
                'nombre' => trim($fila['nombre'] . ' ' . $fila['apellido']),
                'rango_edad' => (string) $fila['rango_edad'],
                'asistio' => (int) $fila['asistio'] === 1,
                'inversion' => (string) $fila['inversion'],
                'monto' => (float) $fila['monto'],
            ];
        }
        mysqli_stmt_close($stmt);
        return $porRegistro;
    }
}
