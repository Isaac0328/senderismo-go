<?php

return [
    'id' => '20261006_005_finanzas_iniciales',
    'descripcion' => 'Prepara el control y las frecuencias necesarias para el modulo financiero.',
    'up' => static function (mysqli $conn): void {
        $tablaExiste = static function (string $tabla) use ($conn): bool {
            $segura = mysqli_real_escape_string($conn, $tabla);
            $resultado = mysqli_query($conn, "SHOW TABLES LIKE '{$segura}'");
            $existe = $resultado && mysqli_num_rows($resultado) === 1;
            if ($resultado) {
                mysqli_free_result($resultado);
            }
            return $existe;
        };

        if ($tablaExiste('fin_control') && !mysqli_query($conn, 'INSERT IGNORE INTO fin_control (id) VALUES (1)')) {
            throw new RuntimeException('No se pudo preparar el control financiero: ' . mysqli_error($conn));
        }

        if (!$tablaExiste('fin_frecuencias')) {
            return;
        }

        $resultado = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM fin_frecuencias');
        $fila = $resultado ? mysqli_fetch_assoc($resultado) : null;
        if ($resultado) {
            mysqli_free_result($resultado);
        }
        if ((int) ($fila['total'] ?? 0) > 0) {
            return;
        }

        $sql = "
            INSERT INTO fin_frecuencias (nombre, unidad, intervalo, activo) VALUES
            ('Semanal', 'dias', 7, 1),
            ('Cada 15 dias', 'dias', 15, 1),
            ('Mensual', 'meses', 1, 1),
            ('Trimestral', 'meses', 3, 1),
            ('Cuatrimestral', 'meses', 4, 1),
            ('Anual', 'meses', 12, 1)
        ";
        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException('No se pudieron preparar las frecuencias financieras: ' . mysqli_error($conn));
        }
    },
];
