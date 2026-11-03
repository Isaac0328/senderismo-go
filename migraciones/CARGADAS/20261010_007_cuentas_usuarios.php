<?php
return [
    'id' => '20261010_007_cuentas_usuarios',
    'descripcion' => 'Agrega el estado de cuenta financiero por usuario al panel.',
    'up' => static function (mysqli $conn): void {
        require_once dirname(sg_migraciones_directorio()) . '/componentes/permisos.php';
        sg_seed_permission_catalog($conn);
        if (!mysqli_query($conn, "INSERT IGNORE INTO admin_menu_modulos (permiso_codigo, seccion_id, subseccion_id, orden, activo)
            SELECT 'finanzas.cuentas_usuarios', id, NULL, 9991, 1
            FROM admin_menu_secciones WHERE etiqueta = 'Finanzas' ORDER BY orden, id LIMIT 1")) {
            throw new RuntimeException('No se pudo agregar Estado de cuenta por usuario al panel.');
        }
    },
];
