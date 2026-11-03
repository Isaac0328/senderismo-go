<?php
// Rol (acceso) y cargos (Contable, Directiva...) pasan a ser cosas distintas: el rol dice que puede abrir
// la persona (Administrador, Invitado, Normal) y los cargos son grupos a los que pertenece, varios a la vez.
return [
    'id' => '20261103_021_cargos_usuarios',
    'descripcion' => 'Separa el acceso (rol) de los cargos (Contable, Directiva...) y permite varios cargos por usuario.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudieron preparar los cargos de usuarios: ' . $conn->error);
            }
        };
        $uno = static function (string $sql) use ($conn): ?array {
            $res = $conn->query($sql);
            return $res ? ($res->fetch_assoc() ?: null) : null;
        };

        $res = $conn->query("SHOW COLUMNS FROM roles LIKE 'tipo'");
        if (!$res || $res->num_rows === 0) {
            $run("ALTER TABLE roles ADD COLUMN tipo ENUM('acceso','cargo') NOT NULL DEFAULT 'acceso' AFTER descripcion");
        }

        // Un usuario puede tener varios cargos; sus permisos se suman a los de su rol.
        $run("CREATE TABLE IF NOT EXISTS usuario_cargos (
            usuario_id INT NOT NULL,
            cargo_id INT NOT NULL,
            PRIMARY KEY (usuario_id, cargo_id),
            KEY idx_usuario_cargos_cargo (cargo_id),
            CONSTRAINT fk_usuario_cargos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_usuario_cargos_cargo FOREIGN KEY (cargo_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Contable y Directiva son cargos, no accesos.
        $run("UPDATE roles SET tipo = 'cargo' WHERE nombre IN ('Contable', 'Directiva')");

        // Rol "Normal": acceso basico, con los mismos permisos que Invitado.
        $invitado = $uno("SELECT id FROM roles WHERE nombre = 'Invitado' LIMIT 1");
        if (!$uno("SELECT id FROM roles WHERE nombre = 'Normal' LIMIT 1")) {
            $run("INSERT INTO roles (nombre, descripcion, tipo) VALUES ('Normal', 'Usuario con acceso basico', 'acceso')");
            $normalId = (int) $conn->insert_id;
            if ($invitado) {
                $invitadoId = (int) $invitado['id'];
                $run("INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) SELECT {$normalId}, permiso_id FROM rol_permiso WHERE rol_id = {$invitadoId}");
                $accion = $conn->query("SHOW TABLES LIKE 'rol_permiso_accion'");
                if ($accion && $accion->num_rows === 1) {
                    $run("INSERT IGNORE INTO rol_permiso_accion (rol_id, permiso_id, accion, permitido)
                          SELECT {$normalId}, permiso_id, accion, permitido FROM rol_permiso_accion WHERE rol_id = {$invitadoId}");
                }
            }
        }

        // Quien hoy tiene como rol un cargo (Contable, Directiva): ese cargo pasa a ser un cargo suyo
        // y su rol de acceso queda como Invitado. Sus permisos no cambian porque los cargos se suman.
        if ($invitado) {
            $invitadoId = (int) $invitado['id'];
            $run("INSERT IGNORE INTO usuario_cargos (usuario_id, cargo_id)
                  SELECT u.id, u.rol_id FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.tipo = 'cargo'");
            $run("UPDATE usuarios u JOIN roles r ON r.id = u.rol_id SET u.rol_id = {$invitadoId} WHERE r.tipo = 'cargo'");
        }
    },
];
