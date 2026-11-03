<?php
// Registros que "amarran" a un usuario. Mientras existan, el usuario solo se puede inactivar:
// eliminarlo borraria historial (inscripciones, creditos, respuestas) o dejaria huerfanos
// movimientos de tesoreria. Lo usan la lista de Usuarios (para mostrar Eliminar) y el proceso (que decide).

if (!function_exists('usuarios_vinculos_etiquetas')) {
    function usuarios_vinculos_etiquetas(): array
    {
        return [
            'senderos' => ['inscripcion en senderos', 'inscripciones en senderos'],
            'creditos' => ['credito', 'creditos'],
            'encuestas' => ['encuesta respondida', 'encuestas respondidas'],
            'tesoreria' => ['registro de tesoreria', 'registros de tesoreria'],
            'personas' => ['persona sin cuenta reconciliada', 'personas sin cuenta reconciliadas'],
        ];
    }
}

if (!function_exists('usuarios_vinculos')) {
    // [usuario_id => ['senderos' => n, ...]] con los conteos mayores que cero. Con $usuarioId solo consulta a ese usuario.
    function usuarios_vinculos(mysqli $conn, ?int $usuarioId = null): array
    {
        // clave => lista de [tabla, columna, condicion extra]
        $fuentes = [
            'senderos' => [['registros_senderos', 'usuario_id', '']],
            'creditos' => [['usuario_creditos', 'usuario_id', '']],
            'encuestas' => [['encuesta_envios', 'usuario_id', "estado = 'respondida'"]],
            'tesoreria' => [
                ['fin_movimientos', 'creado_por', ''], ['fin_cuentas', 'creado_por', ''], ['fin_obligaciones', 'creado_por', ''],
                ['fin_programaciones', 'creado_por', ''], ['fin_cuadres', 'creado_por', ''], ['fin_auditoria', 'usuario_id', ''],
            ],
            'personas' => [['personas_sin_cuenta', 'usuario_id', '']],
        ];

        $existentes = [];
        $res = mysqli_query($conn, 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        while ($res && $fila = mysqli_fetch_row($res)) {
            $existentes[$fila[0]] = true;
        }

        $resultado = [];
        foreach ($fuentes as $clave => $tablas) {
            foreach ($tablas as [$tabla, $columna, $condicion]) {
                if (!isset($existentes[$tabla])) {
                    continue;
                }
                $where = "{$columna} IS NOT NULL" . ($condicion !== '' ? " AND {$condicion}" : '')
                    . ($usuarioId !== null ? ' AND ' . $columna . ' = ' . (int) $usuarioId : '');
                $consulta = mysqli_query($conn, "SELECT {$columna} AS usuario_id, COUNT(*) AS total FROM {$tabla} WHERE {$where} GROUP BY {$columna}");
                while ($consulta && $fila = mysqli_fetch_assoc($consulta)) {
                    $id = (int) $fila['usuario_id'];
                    $resultado[$id][$clave] = ($resultado[$id][$clave] ?? 0) + (int) $fila['total'];
                }
            }
        }
        return $resultado;
    }
}

if (!function_exists('usuarios_vinculos_texto')) {
    // "3 inscripciones en senderos, 1 credito"
    function usuarios_vinculos_texto(array $vinculos): string
    {
        $etiquetas = usuarios_vinculos_etiquetas();
        $partes = [];
        foreach ($vinculos as $clave => $total) {
            $partes[] = $total . ' ' . ($etiquetas[$clave][$total === 1 ? 0 : 1] ?? $clave);
        }
        return implode(', ', $partes);
    }
}
