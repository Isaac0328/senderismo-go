<?php

require_once __DIR__ . '/helpers.php';

if (!function_exists('sg_reporte_sendero_basico')) {
    function sg_reporte_sendero_basico(mysqli $conn, int $senderoId): ?array
    {
        $stmt = mysqli_prepare($conn, "
            SELECT id, nombre, fecha_sendero, estado
            FROM senderos
            WHERE id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        mysqli_stmt_execute($stmt);
        $sendero = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $sendero ?: null;
    }
}

if (!function_exists('sg_reporte_participantes_sendero')) {
    function sg_reporte_participantes_sendero(mysqli $conn, int $senderoId): array
    {
        $stmt = mysqli_prepare($conn, "
            SELECT
                rs.id AS registro_id,
                rs.estado AS estado_registro,
                rs.fecha_registro,
                si.nombre AS inversion_nombre,
                si.monto AS inversion_monto,
                COALESCE(u.id, 0) AS usuario_id,
                COALESCE(u.nombre, rs.manual_nombre, 'Asistente') AS nombre,
                COALESCE(u.apellido, rs.manual_apellido, 'manual') AS apellido,
                COALESCE(u.user, CONCAT('manual-', rs.id)) AS user,
                COALESCE(u.email, rs.manual_email, '') AS email,
                COALESCE(u.estado, 1) AS usuario_estado,
                COALESCE(du.telefono, rs.manual_telefono, '') AS telefono,
                COALESCE(du.rango_edad, '') AS rango_edad,
                COALESCE(du.identificacion, '') AS identificacion,
                COALESCE(du.es_alergico, 0) AS es_alergico,
                COALESCE(du.alergias_detalle, '') AS alergias_detalle,
                COALESCE(du.grupo_sanguineo, '') AS grupo_sanguineo,
                COALESCE(du.enfermedad, '') AS enfermedad,
                COALESCE(du.seguro_medico, '') AS seguro_medico,
                COALESCE(du.experiencia_senderismo, '') AS experiencia_senderismo,
                COALESCE(du.via_entero, '') AS via_entero,
                COALESCE(du.referido_nombre, '') AS referido_nombre,
                COALESCE(du.emergencia_nombre, '') AS emergencia_nombre,
                COALESCE(du.emergencia_parentesco, '') AS emergencia_parentesco,
                COALESCE(du.emergencia_telefono, '') AS emergencia_telefono
            FROM registros_senderos rs
            LEFT JOIN usuarios u ON u.id = rs.usuario_id
            LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
            LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
            WHERE rs.sendero_id = ? AND rs.estado = 'registrado'
            ORDER BY rs.fecha_registro DESC, COALESCE(u.nombre, rs.manual_nombre) ASC, COALESCE(u.apellido, rs.manual_apellido) ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $participantes = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $participantes[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $participantes;
    }
}

if (!function_exists('sg_reporte_menores_por_registro')) {
    function sg_reporte_menores_por_registro(mysqli $conn, array $participantes): array
    {
        $registroIds = array_map(static fn($row) => (int) ($row['registro_id'] ?? 0), $participantes);
        $idsSql = implode(',', array_unique(array_filter($registroIds)));

        $menoresPorRegistro = [];
        $menoresExport = [];
        if ($idsSql === '') {
            return [$menoresPorRegistro, $menoresExport];
        }

        $resMenores = mysqli_query($conn, "
            SELECT
                rm.*,
                si.nombre AS inversion_nombre,
                si.monto AS inversion_monto
            FROM registro_sendero_menores rm
            LEFT JOIN sendero_inversiones si ON si.id = rm.inversion_id
            WHERE rm.registro_id IN ($idsSql)
            ORDER BY rm.registro_id ASC, rm.id ASC
        ");

        while ($resMenores && $menor = mysqli_fetch_assoc($resMenores)) {
            $registroId = (int) $menor['registro_id'];
            $menoresPorRegistro[$registroId][] = $menor;
            $menoresExport[] = $menor;
        }

        return [$menoresPorRegistro, $menoresExport];
    }
}
