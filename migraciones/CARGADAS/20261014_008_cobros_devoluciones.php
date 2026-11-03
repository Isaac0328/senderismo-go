<?php

return [
    'id' => '20261014_008_cobros_devoluciones',
    'descripcion' => 'Permite registrar devoluciones en los cobros de senderos y guarda de que credito sale cada pago con credito.',
    'up' => static function (mysqli $conn): void {
        $ejecutar = static function (string $sql, string $error) use ($conn): void {
            if (!mysqli_query($conn, $sql)) {
                throw new RuntimeException($error . ': ' . mysqli_error($conn));
            }
        };

        $ejecutar(
            "ALTER TABLE contabilidad_registro_pagos
             ADD COLUMN IF NOT EXISTS monto_devuelto DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER monto_retenido",
            'No se pudo agregar el monto devuelto'
        );

        $ejecutar(
            "CREATE TABLE IF NOT EXISTS contabilidad_credito_aplicaciones (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                registro_id INT NOT NULL,
                credito_id INT NOT NULL,
                monto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_credito_aplicacion (registro_id, credito_id),
                INDEX idx_credito_aplicacion_credito (credito_id),
                CONSTRAINT fk_credito_aplicacion_registro FOREIGN KEY (registro_id) REFERENCES registros_senderos(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_credito_aplicacion_credito FOREIGN KEY (credito_id) REFERENCES usuario_creditos(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            'No se pudo crear el registro de creditos aplicados'
        );

        // Hasta hoy cada cobro guardaba un solo credito: se copia tal cual.
        $ejecutar(
            "INSERT IGNORE INTO contabilidad_credito_aplicaciones (registro_id, credito_id, monto)
             SELECT p.registro_id, p.credito_id, p.credito_aplicado
             FROM contabilidad_registro_pagos p
             INNER JOIN usuario_creditos uc ON uc.id = p.credito_id
             WHERE p.credito_aplicado > 0",
            'No se pudieron copiar los creditos aplicados'
        );
    },
];
