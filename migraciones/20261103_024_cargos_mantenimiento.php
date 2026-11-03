<?php
// Cargos con su propio mantenimiento (menu "Cargos") y reglas del rol de acceso:
// el Invitado es el cliente de afuera y no tiene cargos; los cargos los llevan el Administrador y el Normal.
return [
    'id' => '20261103_024_cargos_mantenimiento',
    'descripcion' => 'Agrega el mantenimiento de Cargos y deja sin cargos a los usuarios con rol Invitado (pasan a Normal si tenian alguno).',
    'up' => static function (mysqli $conn): void {
        $run = static function (string $sql) use ($conn): void {
            if (!$conn->query($sql)) {
                throw new RuntimeException('No se pudo preparar el mantenimiento de cargos: ' . $conn->error);
            }
        };
        $uno = static function (string $sql) use ($conn): ?array {
            $res = $conn->query($sql);
            return $res ? ($res->fetch_assoc() ?: null) : null;
        };

        // Acceso a la pantalla Cargos, junto a Roles.
        require_once dirname(sg_migraciones_directorio()) . '/componentes/permisos.php';
        sg_seed_permission_catalog($conn);
        $run("INSERT IGNORE INTO admin_menu_modulos (permiso_codigo, seccion_id, subseccion_id, orden, activo)
            SELECT 'usuarios.cargos', seccion_id, subseccion_id, orden + 1, 1
            FROM admin_menu_modulos WHERE permiso_codigo = 'usuarios.roles' LIMIT 1");

        // Quien tenia un cargo y el rol Invitado pasa a Normal (el Invitado no lleva cargos).
        $invitado = $uno("SELECT id FROM roles WHERE nombre = 'Invitado' LIMIT 1");
        $normal = $uno("SELECT id FROM roles WHERE nombre = 'Normal' LIMIT 1");
        $res = $conn->query("SHOW TABLES LIKE 'usuario_cargos'");
        if ($invitado && $normal && $res && $res->num_rows === 1) {
            $invitadoId = (int) $invitado['id'];
            $normalId = (int) $normal['id'];
            $run("UPDATE usuarios SET rol_id = {$normalId} WHERE rol_id = {$invitadoId} AND id IN (SELECT usuario_id FROM usuario_cargos)");
        }
    },
];
