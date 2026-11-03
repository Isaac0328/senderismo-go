<?php
return [
    'id' => '20261021_012_cuentas_responsable_unicidad',
    'descripcion' => 'Relaciona la custodia con personal o entidades y evita duplicar cuentas en un mismo banco.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) { throw new RuntimeException('No se pudo actualizar las cuentas: ' . $conn->error); }
        };
        $column = $conn->query("SHOW COLUMNS FROM fin_cuentas LIKE 'responsable_tercero_id'");
        if (!$column) { throw new RuntimeException($conn->error); }
        if (!$column->num_rows) { $run('ALTER TABLE fin_cuentas ADD COLUMN responsable_tercero_id INT NULL AFTER responsable'); }
        $fk = $conn->query("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_cuentas' AND COLUMN_NAME='responsable_tercero_id' AND REFERENCED_TABLE_NAME='fin_terceros'");
        if (!$fk) { throw new RuntimeException($conn->error); }
        if (!$fk->num_rows) { $run('ALTER TABLE fin_cuentas ADD CONSTRAINT fk_fin_cuentas_responsable_tercero FOREIGN KEY (responsable_tercero_id) REFERENCES fin_terceros(id)'); }
        $index = $conn->query("SHOW INDEX FROM fin_cuentas WHERE Key_name='uq_fin_cuentas_banco_numero'");
        if (!$index) { throw new RuntimeException($conn->error); }
        if (!$index->num_rows) { $run('ALTER TABLE fin_cuentas ADD UNIQUE KEY uq_fin_cuentas_banco_numero (banco_id, numero_cuenta)'); }
    },
];
