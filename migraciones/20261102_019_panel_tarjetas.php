<?php
// Tarjetas del panel administrativo configurables: mostrar u ocultar, ordenar y limitar a ciertos roles.
return [
    'id' => '20261102_019_panel_tarjetas',
    'descripcion' => 'Permite elegir que tarjetas del panel se muestran, su orden y para que roles.',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudo preparar las tarjetas del panel: ' . $conn->error);
            }
        };

        $run("CREATE TABLE IF NOT EXISTS panel_tarjetas (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(60) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            grupo VARCHAR(30) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1,
            orden INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_panel_tarjetas_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Si una tarjeta tiene roles aqui, solo esos roles la ven; sin filas la ven todos los roles.
        $run("CREATE TABLE IF NOT EXISTS panel_tarjeta_roles (
            tarjeta_id INT NOT NULL,
            rol_id INT NOT NULL,
            PRIMARY KEY (tarjeta_id, rol_id),
            CONSTRAINT fk_panel_tarjeta_roles_tarjeta FOREIGN KEY (tarjeta_id) REFERENCES panel_tarjetas(id) ON DELETE CASCADE,
            CONSTRAINT fk_panel_tarjeta_roles_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Todo queda visible y en su orden de siempre.
        require_once dirname(sg_migraciones_directorio()) . '/componentes/panel_tarjetas.php';
        foreach (sg_panel_tarjetas_catalogo() as $posicion => [$codigo, $nombre, $grupo]) {
            $orden = ($posicion + 1) * 10;
            $stmt = $conn->prepare('INSERT INTO panel_tarjetas (codigo, nombre, grupo, activo, orden) VALUES (?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), grupo = VALUES(grupo)');
            $stmt->bind_param('sssi', $codigo, $nombre, $grupo, $orden);
            $stmt->execute();
            $stmt->close();
        }

        // Acceso a la pantalla de configuracion, junto a "Configuracion de avisos".
        require_once dirname(sg_migraciones_directorio()) . '/componentes/permisos.php';
        sg_seed_permission_catalog($conn);
        $run("INSERT IGNORE INTO admin_menu_modulos (permiso_codigo, seccion_id, subseccion_id, orden, activo)
            SELECT 'panel.tarjetas', seccion_id, subseccion_id, orden + 1, 1
            FROM admin_menu_modulos WHERE permiso_codigo = 'panel.notificaciones' LIMIT 1");
    },
];
