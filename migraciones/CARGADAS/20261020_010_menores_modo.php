<?php

return [
    'id' => '20261020_010_menores_modo',
    'descripcion' => 'Agrega como participan los menores en cada sendero: no admite, igual al adulto, precio propio, gratis o inversiones propias.',
    'up' => static function (mysqli $conn): void {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM senderos LIKE 'menores_modo'");
        $yaExiste = $res && mysqli_num_rows($res) === 1;
        if ($yaExiste) {
            return;
        }

        if (!mysqli_query(
            $conn,
            "ALTER TABLE senderos
             ADD COLUMN menores_modo VARCHAR(20) NOT NULL DEFAULT 'igual' AFTER menores_misma_inversion"
        )) {
            throw new RuntimeException('No se pudo agregar el modo de menores: ' . mysqli_error($conn));
        }

        // Cada sendero existente toma el modo que corresponde a como esta configurado hoy.
        if (!mysqli_query(
            $conn,
            "UPDATE senderos s
             SET s.menores_modo = CASE
                 WHEN s.menores_misma_inversion = 0 THEN 'propias'
                 WHEN NOT EXISTS (
                     SELECT 1 FROM sendero_inversiones i
                     WHERE i.sendero_id = s.id AND i.es_menor = 0
                       AND i.monto_menor IS NOT NULL AND i.monto_menor <> i.monto
                 ) THEN 'igual'
                 WHEN NOT EXISTS (
                     SELECT 1 FROM sendero_inversiones i
                     WHERE i.sendero_id = s.id AND i.es_menor = 0 AND COALESCE(i.monto_menor, -1) <> 0
                 ) THEN 'gratis'
                 ELSE 'precio'
             END"
        )) {
            throw new RuntimeException('No se pudo clasificar los senderos existentes: ' . mysqli_error($conn));
        }
    },
];
