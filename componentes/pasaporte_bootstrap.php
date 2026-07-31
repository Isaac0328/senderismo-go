<?php

if (!function_exists('pasaporte_bootstrap')) {
    function pasaporte_bootstrap(mysqli $conn): void
    {
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS pasaporte_niveles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                descripcion VARCHAR(255) DEFAULT '',
                icono VARCHAR(30) DEFAULT 'map',
                color VARCHAR(20) DEFAULT '#0f7a3f',
                min_senderos INT NOT NULL DEFAULT 0,
                min_km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                orden INT NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pasaporte_activo (activo, min_senderos, min_km),
                INDEX idx_pasaporte_orden (orden)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pasaporte_niveles");
        $row = $res ? mysqli_fetch_assoc($res) : ['total' => 0];
        if ((int) ($row['total'] ?? 0) > 0) {
            return;
        }

        $niveles = [
            ['Explorador inicial', 'Completa tu primera ruta y empieza a construir tu pasaporte senderista.', 'compass', '#0f7a3f', 0, 0, 1],
            ['Caminante activo', 'Ya tienes huellas reales en el camino y experiencia confirmada.', 'map', '#15803d', 1, 3, 2],
            ['Senderista constante', 'Tu constancia empieza a notarse en rutas y kilometros acumulados.', 'trending-up', '#0f766e', 3, 15, 3],
            ['Aventurero avanzado', 'Has completado varias rutas y manejas mejor los retos del terreno.', 'activity', '#b45309', 6, 35, 4],
            ['Explorador elite', 'Un perfil fuerte, con historial amplio y recorrido comprobado.', 'award', '#b91c1c', 12, 80, 5],
        ];

        $stmt = mysqli_prepare($conn, "
            INSERT INTO pasaporte_niveles (nombre, descripcion, icono, color, min_senderos, min_km, orden, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        foreach ($niveles as $nivel) {
            [$nombre, $descripcion, $icono, $color, $minSenderos, $minKm, $orden] = $nivel;
            mysqli_stmt_bind_param($stmt, "ssssidi", $nombre, $descripcion, $icono, $color, $minSenderos, $minKm, $orden);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}
