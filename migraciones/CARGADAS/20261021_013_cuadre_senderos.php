<?php
return [
    'id' => '20261021_013_cuadre_senderos',
    'descripcion' => 'Marca por sendero si sus cobros y sus gastos ya estan cuadrados, para pasarlos a Balances solo cuando esten revisados.',
    'up' => static function (mysqli $conn): void {
        $sql = "CREATE TABLE IF NOT EXISTS fin_sendero_cuadres (
            sendero_id INT NOT NULL PRIMARY KEY,
            cobros_cuadrado_at DATETIME NULL,
            cobros_cuadrado_por INT NULL,
            gastos_cuadrado_at DATETIME NULL,
            gastos_cuadrado_por INT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        if (!$conn->query($sql)) {
            throw new RuntimeException('No se pudo crear el registro de cuadre por sendero: ' . $conn->error);
        }
    },
];
