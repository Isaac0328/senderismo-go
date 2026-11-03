<?php

return [
    'id' => '20261010_006_provincias_rd',
    'descripcion' => 'Crea el catalogo de provincias de RD y su acceso administrativo.',
    'up' => static function (mysqli $conn): void {
        $raiz = dirname(sg_migraciones_directorio());
        $sql = file_get_contents($raiz . '/migraciones/estructura/006_provincias_rd.sql');
        if ($sql === false || !mysqli_multi_query($conn, $sql)) {
            throw new RuntimeException('No se pudo crear el catalogo de provincias: ' . mysqli_error($conn));
        }
        do {
            if ($result = mysqli_store_result($conn)) {
                mysqli_free_result($result);
            }
            if (!mysqli_more_results($conn)) {
                break;
            }
            if (!mysqli_next_result($conn)) {
                throw new RuntimeException('No se pudo completar el catalogo de provincias: ' . mysqli_error($conn));
            }
        } while (true);

        require_once $raiz . '/componentes/permisos.php';
        sg_seed_permission_catalog($conn);

        // Ubica el modulo sin cambiar la organizacion personalizada del panel.
        $sql = "INSERT IGNORE INTO admin_menu_modulos (permiso_codigo, seccion_id, subseccion_id, orden, activo)
            SELECT 'operaciones.provincias', id, NULL, 9990, 1
            FROM admin_menu_secciones WHERE etiqueta = 'Operaciones' ORDER BY orden, id LIMIT 1";
        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException('No se pudo agregar Provincias al panel: ' . mysqli_error($conn));
        }
    },
];
