<?php
// Cobro de una inscripcion: parte (o todo) lo puede asumir otro usuario como cuenta por cobrar.
// La persona queda anotada como pagada y el monto se le carga al otro usuario en Por cobrar.
return [
    'id' => '20261103_023_cobro_cargado_a_usuario',
    'descripcion' => 'Permite cargar el cobro de una inscripcion a otro usuario como cuenta por cobrar.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudo preparar el cargo a otro usuario: ' . $conn->error);
            }
        };
        $tabla = $conn->query("SHOW TABLES LIKE 'contabilidad_registro_pagos'");
        if (!$tabla || $tabla->num_rows !== 1) {
            return;
        }
        $tiene = static function (string $columna) use ($conn): bool {
            $res = $conn->query("SHOW COLUMNS FROM contabilidad_registro_pagos LIKE '$columna'");
            return $res && $res->num_rows === 1;
        };
        if (!$tiene('cargado_usuario_id')) {
            $run('ALTER TABLE contabilidad_registro_pagos ADD COLUMN cargado_usuario_id INT NULL DEFAULT NULL');
        }
        if (!$tiene('monto_cargado')) {
            $run('ALTER TABLE contabilidad_registro_pagos ADD COLUMN monto_cargado DECIMAL(12,2) NOT NULL DEFAULT 0');
        }
        // Cuenta por cobrar (fin_obligaciones) que se creo para ese cargo.
        if (!$tiene('obligacion_id')) {
            $run('ALTER TABLE contabilidad_registro_pagos ADD COLUMN obligacion_id INT NULL DEFAULT NULL');
        }
    },
];
