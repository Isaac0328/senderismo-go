<?php

return [
    'id' => '20261005_003_panel_actualizaciones',
    'descripcion' => 'Agrega el acceso administrativo para actualizaciones controladas.',
    'up' => static function (mysqli $conn): void {
        require_once __DIR__ . '/../componentes/permisos.php';
        sg_seed_permission_catalog($conn);

        $tablas = ['admin_menu_secciones', 'admin_menu_modulos'];
        foreach ($tablas as $tabla) {
            $resultado = mysqli_query($conn, "SHOW TABLES LIKE '{$tabla}'");
            $existe = $resultado && mysqli_num_rows($resultado) === 1;
            if ($resultado) {
                mysqli_free_result($resultado);
            }
            if (!$existe) {
                return;
            }
        }

        $seccion = mysqli_query($conn, "SELECT id FROM admin_menu_secciones WHERE etiqueta = 'Usuarios' ORDER BY orden, id LIMIT 1");
        $fila = $seccion ? mysqli_fetch_assoc($seccion) : null;
        if ($seccion) {
            mysqli_free_result($seccion);
        }
        if (!$fila) {
            return;
        }

        $seccionId = (int) $fila['id'];
        $orden = 9990;
        $activo = 1;
        $codigo = 'sistema.actualizaciones';
        $stmt = mysqli_prepare($conn, '
            INSERT IGNORE INTO admin_menu_modulos (permiso_codigo, seccion_id, subseccion_id, orden, activo)
            VALUES (?, ?, NULL, ?, ?)
        ');
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar el acceso a actualizaciones.');
        }
        mysqli_stmt_bind_param($stmt, 'siii', $codigo, $seccionId, $orden, $activo);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException('No se pudo agregar el acceso a actualizaciones: ' . $error);
        }
        mysqli_stmt_close($stmt);
    },
];
