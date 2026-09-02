<?php

if (!function_exists('sg_encuesta_resultados_cargar')) {
    function sg_encuesta_resultados_cargar(mysqli $conn, int $encuestaId): ?array
    {
        if ($encuestaId <= 0) {
            return null;
        }

        $stmt = mysqli_prepare($conn, "
            SELECT
                e.*,
                s.nombre AS sendero_nombre,
                COUNT(DISTINCT ee.id) AS total_envios,
                COUNT(DISTINCT CASE WHEN ee.estado = 'respondida' THEN ee.id END) AS total_respuestas,
                COUNT(DISTINCT CASE WHEN ee.estado = 'pendiente' THEN ee.id END) AS total_pendientes,
                COUNT(DISTINCT CASE WHEN ee.estado = 'cancelada' THEN ee.id END) AS total_cancelados
            FROM encuestas e
            LEFT JOIN senderos s ON s.id = e.sendero_id
            LEFT JOIN encuesta_envios ee ON ee.encuesta_id = e.id
            WHERE e.id = ? AND e.activo = 1
            GROUP BY e.id
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
        mysqli_stmt_execute($stmt);
        $encuesta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
        mysqli_stmt_close($stmt);

        if (!$encuesta) {
            return null;
        }

        $preguntas = [];
        $preguntasPorId = [];
        $stmt = mysqli_prepare($conn, "
            SELECT *
            FROM encuesta_preguntas
            WHERE encuesta_id = ? AND activo = 1
            ORDER BY orden ASC, id ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && $row = mysqli_fetch_assoc($res)) {
            $row['opciones'] = [];
            $preguntas[] = $row;
            $preguntasPorId[(int) $row['id']] = count($preguntas) - 1;
        }
        mysqli_stmt_close($stmt);

        if (!empty($preguntasPorId)) {
            $ids = implode(',', array_keys($preguntasPorId));
            $res = mysqli_query($conn, "
                SELECT *
                FROM encuesta_opciones
                WHERE pregunta_id IN ({$ids}) AND activo = 1
                ORDER BY pregunta_id ASC, orden ASC, id ASC
            ");
            while ($res && $row = mysqli_fetch_assoc($res)) {
                $index = $preguntasPorId[(int) $row['pregunta_id']] ?? null;
                if ($index !== null) {
                    $preguntas[$index]['opciones'][] = $row;
                }
            }
        }

        $anonima = (int) ($encuesta['anonima'] ?? 0) === 1;
        $usuarioCampos = $anonima
            ? "NULL AS nombre, NULL AS apellido, NULL AS user, NULL AS email"
            : "u.nombre, u.apellido, u.user, u.email";

        $envios = [];
        $enviosPorId = [];
        $stmt = mysqli_prepare($conn, "
            SELECT ee.id, ee.usuario_id, ee.enviado_at, ee.respondido_at, {$usuarioCampos}
            FROM encuesta_envios ee
            LEFT JOIN usuarios u ON u.id = ee.usuario_id
            WHERE ee.encuesta_id = ? AND ee.estado = 'respondida'
            ORDER BY ee.respondido_at DESC, ee.id DESC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $numeroRespuesta = 0;
        while ($res && $row = mysqli_fetch_assoc($res)) {
            $numeroRespuesta++;
            $row['numero_respuesta'] = $numeroRespuesta;
            $row['respuestas'] = [];
            $envios[] = $row;
            $enviosPorId[(int) $row['id']] = count($envios) - 1;
        }
        mysqli_stmt_close($stmt);

        $filasRespuesta = [];
        if (!empty($enviosPorId)) {
            $stmt = mysqli_prepare($conn, "
                SELECT
                    er.id,
                    er.envio_id,
                    er.pregunta_id,
                    er.opcion_id,
                    er.respuesta_texto,
                    er.respuesta_numero,
                    er.puntuacion,
                    eo.texto AS opcion_texto,
                    eo.orden AS opcion_orden
                FROM encuesta_respuestas er
                INNER JOIN encuesta_envios ee ON ee.id = er.envio_id
                LEFT JOIN encuesta_opciones eo ON eo.id = er.opcion_id
                WHERE ee.encuesta_id = ? AND ee.estado = 'respondida'
                ORDER BY ee.respondido_at DESC, er.envio_id DESC, er.pregunta_id ASC, eo.orden ASC, er.id ASC
            ");
            mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && $row = mysqli_fetch_assoc($res)) {
                $envioId = (int) $row['envio_id'];
                $preguntaId = (int) $row['pregunta_id'];
                $filasRespuesta[$preguntaId][] = $row;
                $envioIndex = $enviosPorId[$envioId] ?? null;
                if ($envioIndex !== null) {
                    $envios[$envioIndex]['respuestas'][$preguntaId][] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }

        $analisis = [];
        $puntajeMaximoEncuesta = 0.0;
        $puntajeObtenidoTotal = 0.0;
        $totalRespondidas = (int) ($encuesta['total_respuestas'] ?? 0);

        foreach ($preguntas as $pregunta) {
            $preguntaId = (int) $pregunta['id'];
            $tipo = (string) $pregunta['tipo'];
            $filas = $filasRespuesta[$preguntaId] ?? [];
            $respondentes = [];
            $puntajesPorEnvio = [];
            $numeros = [];
            $textos = [];
            $distribucion = [];

            if ($tipo !== 'escala') {
                foreach ($pregunta['opciones'] as $opcion) {
                    $distribucion[(string) $opcion['id']] = [
                        'etiqueta' => (string) $opcion['texto'],
                        'cantidad' => 0,
                        'porcentaje' => 0.0,
                        'orden' => (int) $opcion['orden'],
                    ];
                }
            }

            foreach ($filas as $fila) {
                $envioId = (int) $fila['envio_id'];
                $respondentes[$envioId] = true;
                $puntaje = (float) ($fila['puntuacion'] ?? 0);
                $puntajesPorEnvio[$envioId] = ($puntajesPorEnvio[$envioId] ?? 0) + $puntaje;
                $puntajeObtenidoTotal += $puntaje;

                if ($fila['respuesta_texto'] !== null && trim((string) $fila['respuesta_texto']) !== '') {
                    $textos[] = trim((string) $fila['respuesta_texto']);
                }

                if ($fila['respuesta_numero'] !== null && $fila['respuesta_numero'] !== '') {
                    $valor = (float) $fila['respuesta_numero'];
                    $numeros[] = $valor;
                    if ($tipo === 'escala') {
                        $key = 'numero_' . (string) $valor;
                        if (!isset($distribucion[$key])) {
                            $distribucion[$key] = [
                                'etiqueta' => trim((string) ($fila['opcion_texto'] ?? ''))
                                    ?: rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.'),
                                'cantidad' => 0,
                                'porcentaje' => 0.0,
                                'orden' => (int) round($valor),
                            ];
                        }
                        $distribucion[$key]['cantidad']++;
                    }
                }

                $opcionId = (int) ($fila['opcion_id'] ?? 0);
                if ($opcionId > 0 && $tipo !== 'escala') {
                    $key = (string) $opcionId;
                    if (!isset($distribucion[$key])) {
                        $distribucion[$key] = [
                            'etiqueta' => (string) ($fila['opcion_texto'] ?? 'Opcion'),
                            'cantidad' => 0,
                            'porcentaje' => 0.0,
                            'orden' => (int) ($fila['opcion_orden'] ?? 0),
                        ];
                    }
                    $distribucion[$key]['cantidad']++;
                }
            }

            $cantidadRespondentes = count($respondentes);
            foreach ($distribucion as &$item) {
                $item['porcentaje'] = $cantidadRespondentes > 0
                    ? round(((int) $item['cantidad'] / $cantidadRespondentes) * 100, 1)
                    : 0.0;
            }
            unset($item);
            uasort($distribucion, static fn ($a, $b) => ((int) $a['orden']) <=> ((int) $b['orden']));

            $puntajeMaximo = (float) ($pregunta['puntaje_max'] ?? 0);
            if ($puntajeMaximo > 0) {
                $puntajeMaximoEncuesta += $puntajeMaximo;
            }

            $analisis[$preguntaId] = [
                'respondidas' => $cantidadRespondentes,
                'omitidas' => max(0, $totalRespondidas - $cantidadRespondentes),
                'promedio_puntaje' => !empty($puntajesPorEnvio)
                    ? array_sum($puntajesPorEnvio) / count($puntajesPorEnvio)
                    : null,
                'promedio_numero' => !empty($numeros) ? array_sum($numeros) / count($numeros) : null,
                'minimo_numero' => !empty($numeros) ? min($numeros) : null,
                'maximo_numero' => !empty($numeros) ? max($numeros) : null,
                'distribucion' => array_values($distribucion),
                'textos' => $textos,
            ];
        }

        $totalEnvios = (int) ($encuesta['total_envios'] ?? 0);
        $tasaRespuesta = $totalEnvios > 0 ? ($totalRespondidas / $totalEnvios) * 100 : 0.0;
        $puntajePosibleTotal = $puntajeMaximoEncuesta * $totalRespondidas;
        $satisfaccion = $puntajePosibleTotal > 0
            ? min(100, max(0, ($puntajeObtenidoTotal / $puntajePosibleTotal) * 100))
            : null;

        return [
            'encuesta' => $encuesta,
            'preguntas' => $preguntas,
            'envios' => $envios,
            'analisis' => $analisis,
            'metricas' => [
                'tasa_respuesta' => $tasaRespuesta,
                'satisfaccion' => $satisfaccion,
                'puntaje_obtenido' => $puntajeObtenidoTotal,
                'puntaje_posible' => $puntajePosibleTotal,
            ],
        ];
    }
}

if (!function_exists('sg_encuesta_resultado_valor')) {
    function sg_encuesta_resultado_valor(array $filas): string
    {
        $valores = [];
        foreach ($filas as $fila) {
            $texto = trim((string) ($fila['respuesta_texto'] ?? ''));
            if ($texto !== '') {
                $valores[] = $texto;
                continue;
            }

            if (($fila['respuesta_numero'] ?? null) !== null && $fila['respuesta_numero'] !== '') {
                $numero = (float) $fila['respuesta_numero'];
                $valores[] = rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');
                continue;
            }

            $opcion = trim((string) ($fila['opcion_texto'] ?? ''));
            if ($opcion !== '') {
                $valores[] = $opcion;
            }
        }

        return !empty($valores) ? implode(' | ', $valores) : 'Sin respuesta';
    }
}
