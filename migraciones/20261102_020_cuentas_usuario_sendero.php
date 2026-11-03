<?php
// Cuentas por cobrar y por pagar: cargar a un usuario registrado y referenciar un sendero.
return [
    'id' => '20261102_020_cuentas_usuario_sendero',
    'descripcion' => 'Permite cargar una cuenta a un usuario registrado y referenciar un sendero en las cuentas por cobrar y por pagar.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudo preparar las cuentas por usuario y sendero: ' . $conn->error);
            }
        };
        $tieneColumna = static function (string $tabla, string $columna) use ($conn): bool {
            $res = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE '$columna'");
            return $res && $res->num_rows === 1;
        };

        // Un usuario registrado se representa con una ficha de "Personal y entidades" ligada a su cuenta.
        if (!$tieneColumna('fin_terceros', 'usuario_id')) {
            $run('ALTER TABLE fin_terceros ADD COLUMN usuario_id INT NULL DEFAULT NULL, ADD UNIQUE KEY uq_fin_terceros_usuario (usuario_id)');
        }
        // Sendero al que se refiere una cuenta (solo referencia, no cambia los reportes del sendero).
        if (!$tieneColumna('fin_obligaciones', 'sendero_id')) {
            $run('ALTER TABLE fin_obligaciones ADD COLUMN sendero_id INT NULL DEFAULT NULL, ADD KEY idx_fin_obligaciones_sendero (sendero_id)');
        }
    },
];
