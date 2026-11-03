<?php

return [
    'id' => '20261014_009_gastos_detallados',
    'descripcion' => 'Permite gastos libres agrupados (otros gastos) y varias lineas del mismo gasto en un sendero.',
    'up' => static function (mysqli $conn): void {
        $ejecutar = static function (string $sql, string $error) use ($conn): void {
            if (!mysqli_query($conn, $sql)) {
                throw new RuntimeException($error . ': ' . mysqli_error($conn));
            }
        };

        // Una linea puede no venir del catalogo: entonces lleva grupo y descripcion propios.
        $ejecutar(
            "ALTER TABLE contabilidad_sendero_gastos
             MODIFY COLUMN gasto_id INT NULL,
             ADD COLUMN IF NOT EXISTS grupo VARCHAR(120) DEFAULT NULL AFTER gasto_id,
             ADD COLUMN IF NOT EXISTS descripcion VARCHAR(160) DEFAULT NULL AFTER grupo,
             ADD COLUMN IF NOT EXISTS orden INT NOT NULL DEFAULT 0 AFTER nota",
            'No se pudieron agregar las columnas de gastos detallados'
        );

        // Ya no es una sola linea por gasto del catalogo (ej.: combustible de ida y de vuelta).
        $ejecutar(
            "ALTER TABLE contabilidad_sendero_gastos DROP INDEX IF EXISTS uq_cont_sendero_gasto",
            'No se pudo quitar la restriccion de una linea por gasto'
        );
        $ejecutar(
            "ALTER TABLE contabilidad_sendero_gastos ADD INDEX IF NOT EXISTS idx_cont_gastos_sendero_orden (sendero_id, orden)",
            'No se pudo crear el indice de orden'
        );

        $ejecutar(
            "UPDATE contabilidad_sendero_gastos SET orden = id WHERE orden = 0",
            'No se pudo ordenar los gastos existentes'
        );
    },
];
