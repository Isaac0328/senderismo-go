<?php
// Personal y entidades: "Identificacion" y "Contacto" pasan a ser dos campos separados.
// La identificacion sigue en la columna `referencia`; el contacto (telefono o correo) va en `contacto`.
return [
    'id' => '20261029_017_terceros_contacto',
    'descripcion' => 'Separa la identificacion y el contacto de Personal y entidades en dos campos.',
    'up' => static function (mysqli $conn): void {
        if (!$conn->query("SHOW TABLES LIKE 'fin_terceros'")->num_rows) {
            throw new RuntimeException('Instala primero el modulo de balances y cuentas.');
        }
        $columna = $conn->query("SHOW COLUMNS FROM fin_terceros LIKE 'contacto'");
        if (!$columna) { throw new RuntimeException($conn->error); }
        if (!$columna->num_rows) {
            if (!$conn->query("ALTER TABLE fin_terceros ADD COLUMN contacto VARCHAR(160) NOT NULL DEFAULT '' AFTER referencia")) {
                throw new RuntimeException('No se pudo agregar el contacto: ' . $conn->error);
            }
        }
    },
];
