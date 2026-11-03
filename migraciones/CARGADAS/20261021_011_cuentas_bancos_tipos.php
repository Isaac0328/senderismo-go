<?php
return [
    'id' => '20261021_011_cuentas_bancos_tipos',
    'descripcion' => 'Catalogos de bancos y tipos de cuenta; datos bancarios y custodia de efectivo sin alterar saldos.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) { throw new RuntimeException('No se pudo actualizar las cuentas: ' . $conn->error); }
        };
        if (!$conn->query("SHOW TABLES LIKE 'fin_cuentas'")->num_rows) {
            throw new RuntimeException('Instala primero el modulo de balances y cuentas.');
        }
        $run("CREATE TABLE IF NOT EXISTS fin_bancos (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
            activo TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $run("CREATE TABLE IF NOT EXISTS fin_cuenta_tipos (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL UNIQUE,
            clase ENUM('banco','efectivo','otra') NOT NULL, activo TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $run("INSERT INTO fin_cuenta_tipos (nombre,clase) VALUES ('Banco','banco'),('Efectivo','efectivo'),('Otra','otra') ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)");
        foreach (['tipo_id'=>'INT NULL', 'banco_id'=>'INT NULL', 'titular'=>"VARCHAR(160) NOT NULL DEFAULT ''",
            'numero_cuenta'=>"VARCHAR(50) NOT NULL DEFAULT ''", 'responsable'=>"VARCHAR(160) NOT NULL DEFAULT ''",
            'ubicacion'=>"VARCHAR(200) NOT NULL DEFAULT ''"] as $column => $definition) {
            $result = $conn->query("SHOW COLUMNS FROM fin_cuentas LIKE '$column'");
            if (!$result) { throw new RuntimeException($conn->error); }
            if (!$result->num_rows) { $run("ALTER TABLE fin_cuentas ADD COLUMN $column $definition"); }
        }
        $run("UPDATE fin_cuentas c JOIN fin_cuenta_tipos t ON t.nombre=CASE c.tipo WHEN 'banco' THEN 'Banco' WHEN 'efectivo' THEN 'Efectivo' ELSE 'Otra' END SET c.tipo_id=t.id WHERE c.tipo_id IS NULL");
        $run('ALTER TABLE fin_cuentas MODIFY tipo_id INT NOT NULL');
        foreach (['tipo_id'=>'fin_cuenta_tipos','banco_id'=>'fin_bancos'] as $column => $table) {
            $result = $conn->query("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_cuentas' AND COLUMN_NAME='$column' AND REFERENCED_TABLE_NAME='$table'");
            if (!$result) { throw new RuntimeException($conn->error); }
            if (!$result->num_rows) { $run("ALTER TABLE fin_cuentas ADD CONSTRAINT fk_fin_cuentas_$column FOREIGN KEY ($column) REFERENCES $table(id)"); }
        }
    },
];
