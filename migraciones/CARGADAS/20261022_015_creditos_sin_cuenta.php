<?php
// Permite que un credito pertenezca a una persona sin cuenta (su ficha). Al reconciliar
// la ficha con un usuario, el credito pasa a ese usuario.
return [
    'id' => '20261022_015_creditos_sin_cuenta',
    'descripcion' => 'Permite dejar credito a personas sin cuenta; pasa al usuario al reconciliarlas.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql, string $error) use ($conn): void {
            if (!mysqli_query($conn, $sql)) {
                throw new RuntimeException($error . ': ' . mysqli_error($conn));
            }
        };
        $existe = static function (string $sql) use ($conn): bool {
            $res = mysqli_query($conn, $sql);
            if (!$res) {
                throw new RuntimeException(mysqli_error($conn));
            }
            return mysqli_num_rows($res) > 0;
        };

        $run('ALTER TABLE usuario_creditos MODIFY COLUMN usuario_id INT NULL', 'No se pudo permitir creditos sin usuario');
        if (!$existe("SHOW COLUMNS FROM usuario_creditos LIKE 'persona_sin_cuenta_id'")) {
            $run(
                'ALTER TABLE usuario_creditos
                 ADD COLUMN persona_sin_cuenta_id INT NULL AFTER usuario_id,
                 ADD KEY idx_usuario_creditos_persona (persona_sin_cuenta_id, estado)',
                'No se pudo agregar la persona sin cuenta a los creditos'
            );
        }
        if (!$existe("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'usuario_creditos' AND COLUMN_NAME = 'persona_sin_cuenta_id' AND REFERENCED_TABLE_NAME = 'personas_sin_cuenta'")) {
            $run(
                'ALTER TABLE usuario_creditos ADD CONSTRAINT fk_usuario_creditos_persona
                 FOREIGN KEY (persona_sin_cuenta_id) REFERENCES personas_sin_cuenta(id) ON UPDATE CASCADE',
                'No se pudo enlazar los creditos con las personas sin cuenta'
            );
        }
    },
];
