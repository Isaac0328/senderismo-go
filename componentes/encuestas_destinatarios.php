<?php
// Quien debe recibir cada encuesta. Un solo criterio sirve para enviar y para contar los pendientes.

if (!function_exists('encuesta_destinatarios_select')) {
    // SELECT de los usuarios que cumplen el criterio de la encuesta: columnas encuesta_id, usuario_id, sendero_id.
    function encuesta_destinatarios_select(int $encuestaId, int $senderoId, string $destinatarios): string
    {
        $encuestaId = (int) $encuestaId;
        $senderoId = (int) $senderoId;

        if ($destinatarios === 'todos_usuarios') {
            return "SELECT {$encuestaId} AS encuesta_id, u.id AS usuario_id, " . ($senderoId > 0 ? $senderoId : 'NULL') . " AS sendero_id
                FROM usuarios u
                WHERE u.estado = 1";
        }

        // Solo los que marcaron asistencia, o todos los inscritos activos, de un sendero.
        $soloAsistentes = $destinatarios !== 'sendero_registrados' ? 'AND rs.asistio = 1' : '';

        return "SELECT DISTINCT {$encuestaId} AS encuesta_id, rs.usuario_id AS usuario_id, {$senderoId} AS sendero_id
            FROM registros_senderos rs
            INNER JOIN usuarios u ON u.id = rs.usuario_id
            WHERE rs.sendero_id = {$senderoId}
              AND rs.estado = 'registrado'
              {$soloAsistentes}
              AND rs.usuario_id IS NOT NULL
              AND u.estado = 1";
    }
}

if (!function_exists('encuesta_destinatarios_pendientes')) {
    // Cuantos usuarios cumplen el criterio pero todavia no tienen la invitacion.
    function encuesta_destinatarios_pendientes(mysqli $conn, array $encuesta): int
    {
        $senderoId = (int) ($encuesta['sendero_id'] ?? 0);
        $destinatarios = (string) ($encuesta['destinatarios'] ?? '');
        if ($destinatarios !== 'todos_usuarios' && $senderoId <= 0) {
            return 0;
        }

        $select = encuesta_destinatarios_select((int) $encuesta['id'], $senderoId, $destinatarios);
        $resultado = mysqli_query($conn, "SELECT COUNT(*) AS total FROM ({$select}) d
            WHERE NOT EXISTS (SELECT 1 FROM encuesta_envios ee WHERE ee.encuesta_id = d.encuesta_id AND ee.usuario_id = d.usuario_id)");

        return $resultado ? (int) (mysqli_fetch_assoc($resultado)['total'] ?? 0) : 0;
    }
}
