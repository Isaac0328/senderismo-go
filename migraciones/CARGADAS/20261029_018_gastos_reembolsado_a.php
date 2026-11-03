<?php
// Gastos: campo opcional "Reembolsado a" (texto libre) para saber a quien se le devolvio un dinero adelantado.
return [
    'id' => '20261029_018_gastos_reembolsado_a',
    'descripcion' => 'Agrega el campo opcional "Reembolsado a" a los movimientos de gastos.',
    'up' => static function (mysqli $conn): void {
        if (!$conn->query("SHOW TABLES LIKE 'fin_movimientos'")->num_rows) {
            throw new RuntimeException('Instala primero el modulo de balances y cuentas.');
        }
        $columna = $conn->query("SHOW COLUMNS FROM fin_movimientos LIKE 'reembolsado_a'");
        if (!$columna) { throw new RuntimeException($conn->error); }
        if (!$columna->num_rows) {
            if (!$conn->query("ALTER TABLE fin_movimientos ADD COLUMN reembolsado_a VARCHAR(160) NULL AFTER referencia")) {
                throw new RuntimeException('No se pudo agregar "Reembolsado a": ' . $conn->error);
            }
        }
    },
];
