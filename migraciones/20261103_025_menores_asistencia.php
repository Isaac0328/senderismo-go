<?php
// Asistencia de cada menor acompanante (aparte de la del adulto responsable).
return [
    'id' => '20261103_025_menores_asistencia',
    'descripcion' => 'Permite marcar la asistencia de cada menor acompanante por separado.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudo preparar la asistencia de menores: ' . $conn->error);
            }
        };
        $tabla = $conn->query("SHOW TABLES LIKE 'registro_sendero_menores'");
        if (!$tabla || $tabla->num_rows !== 1) {
            return;
        }
        $res = $conn->query("SHOW COLUMNS FROM registro_sendero_menores LIKE 'asistio'");
        if (!$res || $res->num_rows === 0) {
            $run('ALTER TABLE registro_sendero_menores ADD COLUMN asistio TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN fecha_asistencia DATETIME NULL DEFAULT NULL');
            // Hasta hoy los menores asistian si asistia su adulto: se conserva ese resultado.
            $run('UPDATE registro_sendero_menores m JOIN registros_senderos rs ON rs.id = m.registro_id
                  SET m.asistio = 1, m.fecha_asistencia = rs.fecha_asistencia WHERE rs.asistio = 1');
        }
    },
];
