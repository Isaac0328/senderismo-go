<?php
// "Directiva" deja de ser un cargo con permisos y pasa a ser una casilla propia del usuario (usuarios.es_directiva).
// Solo marca la pertenencia: activa la exencion en senderos, pero no da permisos.
return [
    'id' => '20261103_022_directiva_casilla',
    'descripcion' => 'La directiva pasa a ser una casilla aparte del usuario, separada de los cargos y sin permisos propios.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudo preparar la casilla de directiva: ' . $conn->error);
            }
        };
        $uno = static function (string $sql) use ($conn): ?array {
            $res = $conn->query($sql);
            return $res ? ($res->fetch_assoc() ?: null) : null;
        };

        $res = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'es_directiva'");
        if (!$res || $res->num_rows === 0) {
            $run('ALTER TABLE usuarios ADD COLUMN es_directiva TINYINT(1) NOT NULL DEFAULT 0');
        }

        $directiva = $uno("SELECT id FROM roles WHERE nombre = 'Directiva' LIMIT 1");
        if (!$directiva) {
            return;
        }
        $id = (int) $directiva['id'];

        // Quien tenia el cargo Directiva (o, antes de los cargos, ese rol) queda marcado como directiva.
        $res = $conn->query("SHOW TABLES LIKE 'usuario_cargos'");
        if ($res && $res->num_rows === 1) {
            $run("UPDATE usuarios SET es_directiva = 1 WHERE id IN (SELECT usuario_id FROM usuario_cargos WHERE cargo_id = {$id})");
        }
        $run("UPDATE usuarios SET es_directiva = 1 WHERE rol_id = {$id}");

        // El cargo ya no existe como tal: se retira (sus permisos se van con el) si nadie lo usa como rol.
        // Si alguien aun lo tiene como rol de acceso, se deja para no romper su acceso.
        $enUso = $uno("SELECT COUNT(*) AS total FROM usuarios WHERE rol_id = {$id}");
        if ((int) ($enUso['total'] ?? 0) === 0) {
            $run("DELETE FROM roles WHERE id = {$id}");
        }
    },
];
