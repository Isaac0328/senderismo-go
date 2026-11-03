<?php
// Personas que asisten inscritas a mano, sin cuenta en la plataforma. Cada inscripcion
// manual pasa a apuntar a una ficha, y la ficha se puede reconciliar despues con un usuario.
return [
    'id' => '20261022_014_usuarios_sin_cuenta',
    'descripcion' => 'Crea la ficha de usuarios sin cuenta, la enlaza a sus inscripciones manuales y agrupa las repetidas.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudo preparar usuarios sin cuenta: ' . $conn->error);
            }
        };
        $existe = static function (string $sql) use ($conn): bool {
            $res = $conn->query($sql);
            if (!$res) {
                throw new RuntimeException($conn->error);
            }
            return $res->num_rows > 0;
        };

        $run("CREATE TABLE IF NOT EXISTS personas_sin_cuenta (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL DEFAULT '',
            telefono VARCHAR(20) NOT NULL DEFAULT '',
            email VARCHAR(100) NOT NULL DEFAULT '',
            notas VARCHAR(255) NOT NULL DEFAULT '',
            activo TINYINT NOT NULL DEFAULT 1,
            usuario_id INT NULL,
            reconciliado_at DATETIME NULL,
            reconciliado_por INT NULL,
            creado_por INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_psc_usuario (usuario_id),
            CONSTRAINT fk_psc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        if (!$existe("SHOW COLUMNS FROM registros_senderos LIKE 'persona_sin_cuenta_id'")) {
            $run('ALTER TABLE registros_senderos ADD COLUMN persona_sin_cuenta_id INT NULL AFTER detalle_usuario_id, ADD KEY idx_registros_persona_sin_cuenta (persona_sin_cuenta_id)');
        }
        if (!$existe("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='registros_senderos' AND COLUMN_NAME='persona_sin_cuenta_id' AND REFERENCED_TABLE_NAME='personas_sin_cuenta'")) {
            $run('ALTER TABLE registros_senderos ADD CONSTRAINT fk_registros_persona_sin_cuenta FOREIGN KEY (persona_sin_cuenta_id) REFERENCES personas_sin_cuenta(id)');
        }

        // Nombre comparable: minusculas, sin tildes y sin espacios repetidos.
        $normalizar = static function (?string $texto): string {
            $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
            $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
            return preg_replace('/\s+/', ' ', $texto) ?? $texto;
        };
        $bonito = static fn(?string $texto): string => mb_convert_case(trim((string) $texto), MB_CASE_TITLE, 'UTF-8');

        // 1. Una ficha por persona: mismo nombre y apellido (sin importar mayusculas ni tildes)
        //    y telefonos compatibles. Si ambos registros tienen telefonos distintos, son personas distintas.
        $pendientes = $conn->query("SELECT id, manual_nombre, manual_apellido, manual_telefono, manual_email
            FROM registros_senderos
            WHERE (usuario_id IS NULL OR usuario_id = 0) AND persona_sin_cuenta_id IS NULL
              AND TRIM(COALESCE(manual_nombre, '')) <> ''
            ORDER BY id");
        if (!$pendientes) {
            throw new RuntimeException($conn->error);
        }
        $fichas = [];
        $insertar = $conn->prepare('INSERT INTO personas_sin_cuenta (nombre, apellido, telefono, email) VALUES (?, ?, ?, ?)');
        $enlazar = $conn->prepare('UPDATE registros_senderos SET persona_sin_cuenta_id = ? WHERE id = ?');
        $completar = $conn->prepare("UPDATE personas_sin_cuenta SET telefono = IF(telefono = '', ?, telefono), email = IF(email = '', ?, email) WHERE id = ?");
        while ($fila = $pendientes->fetch_assoc()) {
            $clave = $normalizar($fila['manual_nombre']) . '|' . $normalizar($fila['manual_apellido']);
            $telefono = preg_replace('/\D+/', '', (string) $fila['manual_telefono']) ?? '';
            $email = trim((string) $fila['manual_email']);
            $personaId = 0;
            foreach ($fichas[$clave] ?? [] as $candidata) {
                if ($telefono === '' || $candidata['telefono'] === '' || $candidata['telefono'] === $telefono) {
                    $personaId = $candidata['id'];
                    break;
                }
            }
            if ($personaId === 0) {
                $nombre = $bonito($fila['manual_nombre']);
                $apellido = $bonito($fila['manual_apellido']);
                $insertar->bind_param('ssss', $nombre, $apellido, $telefono, $email);
                $insertar->execute();
                $personaId = (int) $conn->insert_id;
                $fichas[$clave][] = ['id' => $personaId, 'telefono' => $telefono];
            } elseif ($telefono !== '' || $email !== '') {
                $completar->bind_param('ssi', $telefono, $email, $personaId);
                $completar->execute();
                foreach ($fichas[$clave] as &$candidata) {
                    if ($candidata['id'] === $personaId && $candidata['telefono'] === '') {
                        $candidata['telefono'] = $telefono;
                    }
                }
                unset($candidata);
            }
            $registroId = (int) $fila['id'];
            $enlazar->bind_param('ii', $personaId, $registroId);
            $enlazar->execute();
        }

        // 2. Uniones confirmadas por la administracion el 21/10/2026 aunque el nombre no coincida:
        //    Danny Rodriguez = Dhanny Rodriguez, y Mariana Mariana = Mariana Estevez.
        //    Solo se aplican si los registros siguen siendo esas personas sin cuenta.
        $uniones = [
            [141, 107, 'danny', 'dhanny'],
            [77, 35, 'mariana', 'mariana'],
        ];
        foreach ($uniones as [$registroDestino, $registroOrigen, $nombreDestino, $nombreOrigen]) {
            $res = $conn->query('SELECT id, persona_sin_cuenta_id, manual_nombre FROM registros_senderos WHERE id IN (' . (int) $registroDestino . ',' . (int) $registroOrigen . ') AND (usuario_id IS NULL OR usuario_id = 0)');
            $porId = [];
            while ($res && $fila = $res->fetch_assoc()) {
                $porId[(int) $fila['id']] = $fila;
            }
            if (!isset($porId[$registroDestino], $porId[$registroOrigen])
                || $normalizar($porId[$registroDestino]['manual_nombre']) !== $nombreDestino
                || $normalizar($porId[$registroOrigen]['manual_nombre']) !== $nombreOrigen) {
                continue;
            }
            $destino = (int) $porId[$registroDestino]['persona_sin_cuenta_id'];
            $origen = (int) $porId[$registroOrigen]['persona_sin_cuenta_id'];
            if ($destino === 0 || $origen === 0 || $destino === $origen) {
                continue;
            }
            $run("UPDATE personas_sin_cuenta d JOIN personas_sin_cuenta o ON o.id = $origen
                SET d.telefono = IF(d.telefono = '', o.telefono, d.telefono), d.email = IF(d.email = '', o.email, d.email)
                WHERE d.id = $destino");
            $run("UPDATE registros_senderos SET persona_sin_cuenta_id = $destino WHERE persona_sin_cuenta_id = $origen");
            $run("DELETE FROM personas_sin_cuenta WHERE id = $origen");
        }

        // 3. Cada inscripcion muestra el nombre de su ficha (por ejemplo, "Dhanny" pasa a "Danny").
        $run("UPDATE registros_senderos r JOIN personas_sin_cuenta p ON p.id = r.persona_sin_cuenta_id
            SET r.manual_nombre = p.nombre, r.manual_apellido = p.apellido,
                r.manual_telefono = NULLIF(p.telefono, ''), r.manual_email = NULLIF(p.email, '')
            WHERE r.usuario_id IS NULL OR r.usuario_id = 0");

        // 4. Acceso al modulo, junto a Usuarios en el panel.
        require_once dirname(sg_migraciones_directorio()) . '/componentes/permisos.php';
        sg_seed_permission_catalog($conn);
        $run("INSERT IGNORE INTO admin_menu_modulos (permiso_codigo, seccion_id, subseccion_id, orden, activo)
            SELECT 'usuarios.sin_cuenta', seccion_id, subseccion_id, orden + 1, 1
            FROM admin_menu_modulos WHERE permiso_codigo = 'usuarios.usuarios' LIMIT 1");
    },
];
