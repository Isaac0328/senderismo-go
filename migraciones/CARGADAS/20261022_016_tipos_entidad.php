<?php
// Tipos de entidad (personal y entidades) como catalogo editable. Cada tipo tiene una
// "clase" fija (empleado, directivo, proveedor, cliente, otro) que conserva las reglas:
// solo empleados y directivos pueden custodiar una cuenta de efectivo.
return [
    'id' => '20261022_016_tipos_entidad',
    'descripcion' => 'Catalogo de tipos de entidad para Personal y entidades.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) { throw new RuntimeException('No se pudo preparar los tipos de entidad: ' . $conn->error); }
        };
        if (!$conn->query("SHOW TABLES LIKE 'fin_terceros'")->num_rows) {
            throw new RuntimeException('Instala primero el modulo de balances y cuentas.');
        }
        $run("CREATE TABLE IF NOT EXISTS fin_tercero_tipos (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL UNIQUE,
            clase ENUM('empleado','directivo','proveedor','cliente','otro') NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $run("INSERT INTO fin_tercero_tipos (nombre, clase) VALUES
            ('Empleado','empleado'),('Directivo','directivo'),('Proveedor','proveedor'),('Cliente','cliente'),('Otro','otro')
            ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");

        $columna = $conn->query("SHOW COLUMNS FROM fin_terceros LIKE 'tipo_id'");
        if (!$columna) { throw new RuntimeException($conn->error); }
        if (!$columna->num_rows) {
            $run('ALTER TABLE fin_terceros ADD COLUMN tipo_id INT NULL AFTER tipo');
        }
        // Cada entidad existente toma el tipo que corresponde a su clase actual.
        $run("UPDATE fin_terceros t JOIN fin_tercero_tipos tt ON tt.nombre = CASE t.tipo
                WHEN 'empleado' THEN 'Empleado' WHEN 'directivo' THEN 'Directivo' WHEN 'proveedor' THEN 'Proveedor'
                WHEN 'cliente' THEN 'Cliente' ELSE 'Otro' END
            SET t.tipo_id = tt.id WHERE t.tipo_id IS NULL");
        $fk = $conn->query("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'fin_terceros' AND COLUMN_NAME = 'tipo_id' AND REFERENCED_TABLE_NAME = 'fin_tercero_tipos'");
        if (!$fk) { throw new RuntimeException($conn->error); }
        if (!$fk->num_rows) {
            $run('ALTER TABLE fin_terceros ADD CONSTRAINT fk_fin_terceros_tipo_id FOREIGN KEY (tipo_id) REFERENCES fin_tercero_tipos(id)');
        }
    },
];
