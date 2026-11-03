<?php

return [
    'id' => '20261005_002_semillas_neutras',
    'descripcion' => 'Agrega niveles de pasaporte y estados financieros iniciales.',
    'up' => static function (mysqli $conn): void {
        $resultado = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM pasaporte_niveles');
        $fila = $resultado ? mysqli_fetch_assoc($resultado) : null;
        if ($resultado) {
            mysqli_free_result($resultado);
        }

        if ((int) ($fila['total'] ?? 0) === 0) {
            $niveles = [
                ['Explorador inicial', 'Completa tu primera ruta y empieza a construir tu pasaporte.', 'compass', '#0f7a3f', 0, 0, 1],
                ['Caminante activo', 'Ya tienes experiencias confirmadas y huellas reales en el camino.', 'map', '#15803d', 1, 3, 2],
                ['Participante constante', 'Tu constancia empieza a notarse en actividades y kilometros acumulados.', 'trending-up', '#0f766e', 3, 15, 3],
                ['Aventurero avanzado', 'Has completado varias actividades y manejas mejor los retos.', 'activity', '#b45309', 6, 35, 4],
                ['Explorador elite', 'Un perfil fuerte, con historial amplio y recorrido comprobado.', 'award', '#b91c1c', 12, 80, 5],
            ];

            $stmt = mysqli_prepare($conn, '
                INSERT INTO pasaporte_niveles (nombre, descripcion, icono, color, min_senderos, min_km, orden, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ');
            if (!$stmt) {
                throw new RuntimeException('No se pudieron preparar los niveles iniciales de pasaporte.');
            }

            foreach ($niveles as $nivel) {
                [$nombre, $descripcion, $icono, $color, $minSenderos, $minKm, $orden] = $nivel;
                mysqli_stmt_bind_param($stmt, 'ssssidi', $nombre, $descripcion, $icono, $color, $minSenderos, $minKm, $orden);
                if (!mysqli_stmt_execute($stmt)) {
                    $error = mysqli_stmt_error($stmt);
                    mysqli_stmt_close($stmt);
                    throw new RuntimeException('No se pudo crear un nivel inicial de pasaporte: ' . $error);
                }
            }
            mysqli_stmt_close($stmt);
        }

        $estados = "
            INSERT IGNORE INTO contabilidad_estado_financiero (codigo, nombre, descripcion, color, orden, activo) VALUES
            ('pendiente', 'Pendiente', 'La actividad aun no ocurre y no hay cobertura registrada.', '#B7791F', 10, 1),
            ('pagado', 'Pagado', 'El monto pagado cubre el valor esperado.', '#167447', 20, 1),
            ('parcial', 'Parcial', 'Existe cobertura, pero aun queda balance.', '#B7791F', 30, 1),
            ('credito_aplicado', 'Credito aplicado', 'El credito a favor cubre el valor esperado.', '#2563EB', 40, 1),
            ('pagado_con_credito', 'Pagado con credito', 'El pago combina efectivo y credito a favor.', '#2563EB', 50, 1),
            ('descuento', 'Pagado con descuento', 'El valor queda cubierto usando un descuento.', '#7C3AED', 60, 1),
            ('deuda', 'Debe pagar', 'La persona asistio y aun tiene balance pendiente.', '#B42318', 70, 1),
            ('cortesia', 'Cortesia', 'Excepcion manual sin cobro.', '#0F766E', 80, 1),
            ('exento', 'Exento', 'Excepcion manual sin valor esperado.', '#475569', 90, 1),
            ('no_asistio_sin_pago', 'No asistio / sin pago', 'La actividad paso sin asistencia ni cobertura.', '#64748B', 100, 1)
        ";

        if (!mysqli_query($conn, $estados)) {
            throw new RuntimeException('No se pudieron crear los estados financieros iniciales: ' . mysqli_error($conn));
        }
    },
];
