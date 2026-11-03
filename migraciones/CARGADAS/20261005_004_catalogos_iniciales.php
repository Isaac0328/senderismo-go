<?php

return [
    'id' => '20261005_004_catalogos_iniciales',
    'descripcion' => 'Agrega el rol publico y catalogos iniciales para nuevas instalaciones.',
    'up' => static function (mysqli $conn): void {
        $tablaExiste = static function (string $tabla) use ($conn): bool {
            $segura = mysqli_real_escape_string($conn, $tabla);
            $resultado = mysqli_query($conn, "SHOW TABLES LIKE '{$segura}'");
            $existe = $resultado && mysqli_num_rows($resultado) === 1;
            if ($resultado) {
                mysqli_free_result($resultado);
            }
            return $existe;
        };
        $sembrarSiVacio = static function (string $tabla, string $sql) use ($conn, $tablaExiste): void {
            if (!$tablaExiste($tabla)) {
                return;
            }
            $resultado = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `{$tabla}`");
            $fila = $resultado ? mysqli_fetch_assoc($resultado) : null;
            if ($resultado) {
                mysqli_free_result($resultado);
            }
            if ((int) ($fila['total'] ?? 0) === 0 && !mysqli_query($conn, $sql)) {
                throw new RuntimeException('No se pudo preparar el catalogo ' . $tabla . ': ' . mysqli_error($conn));
            }
        };

        if ($tablaExiste('roles')) {
            $administrador = mysqli_query($conn, "SELECT id FROM roles WHERE id = 1 AND nombre = 'Administrador' LIMIT 1");
            $existeAdministrador = $administrador && mysqli_num_rows($administrador) === 1;
            if ($administrador) {
                mysqli_free_result($administrador);
            }
            if ($existeAdministrador) {
                mysqli_query($conn, "
                    INSERT INTO roles (nombre, descripcion)
                    SELECT 'Invitado', 'Acceso publico para personas registradas.'
                    WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'Invitado')
                ");
            }
        }

        $sembrarSiVacio('niveles_dificultad', "
            INSERT INTO niveles_dificultad (nombre, descripcion, nivel_numero, activo) VALUES
            ('Bajo', 'Recorrido adecuado para comenzar.', 0, 1),
            ('Bajo - Intermedio', 'Exige algo mas de resistencia.', 25, 1),
            ('Intermedio', 'Requiere buena condicion fisica.', 50, 1),
            ('Intermedio - Alto', 'Presenta retos tecnicos o de duracion.', 75, 1),
            ('Alto', 'Recomendado para participantes experimentados.', 100, 1)
        ");
        $sembrarSiVacio('tipos_terreno', "
            INSERT INTO tipos_terreno (nombre, descripcion, activo) VALUES
            ('Rocoso', 'Superficie con piedras o desniveles.', 1),
            ('Tierra compacta', 'Camino firme de tierra.', 1),
            ('Boscoso', 'Tramos con vegetacion y sombra.', 1),
            ('Humedo', 'Zonas con agua, barro o humedad.', 1),
            ('Mixto', 'Combinacion de distintos terrenos.', 1)
        ");
        $sembrarSiVacio('tipos_camino_vehiculo', "
            INSERT INTO tipos_camino_vehiculo (nombre, descripcion, activo) VALUES
            ('Pavimentado', 'Acceso por carretera pavimentada.', 1),
            ('No pavimentado', 'Acceso por camino de tierra o grava.', 1),
            ('Mixto', 'Combina tramos pavimentados y no pavimentados.', 1)
        ");
        $sembrarSiVacio('anotaciones_importantes', "
            INSERT INTO anotaciones_importantes (nombre, descripcion, activo) VALUES
            ('Llevar agua suficiente', 'Recomendacion general de hidratacion.', 1),
            ('Usar calzado adecuado', 'Utiliza zapatos comodos con buen agarre.', 1),
            ('Proteccion solar', 'Considera bloqueador, gorra y ropa apropiada.', 1),
            ('Respetar las indicaciones del equipo', 'Sigue las instrucciones durante toda la actividad.', 1)
        ");
        $sembrarSiVacio('elementos_incluidos', "
            INSERT INTO elementos_incluidos (nombre, descripcion, activo) VALUES
            ('Acompanamiento del equipo', 'Personal de apoyo durante la actividad.', 1),
            ('Botiquin basico', 'Material de primeros auxilios disponible.', 1),
            ('Hidratacion', 'Agua o bebida de apoyo segun la actividad.', 1)
        ");
        $sembrarSiVacio('contabilidad_metodo_pago', "
            INSERT INTO contabilidad_metodo_pago (nombre, descripcion, activo) VALUES
            ('Transferencia bancaria', 'Pago realizado mediante transferencia.', 1),
            ('Efectivo', 'Pago recibido en efectivo.', 1),
            ('Tarjeta', 'Pago con tarjeta de credito o debito.', 1),
            ('Pago digital', 'Pago por billetera o enlace digital.', 1)
        ");
        $sembrarSiVacio('contabilidad_categoria_gasto', "
            INSERT INTO contabilidad_categoria_gasto (nombre, descripcion, activo) VALUES
            ('Alimentacion', 'Comidas, bebidas y refrigerios.', 1),
            ('Transporte', 'Traslados y combustible.', 1),
            ('Seguridad', 'Botiquin, guias y medidas de proteccion.', 1),
            ('Equipamiento', 'Materiales y equipos operativos.', 1),
            ('Logistica', 'Costos generales de coordinacion.', 1)
        ");
        $sembrarSiVacio('fin_tipos', "
            INSERT INTO fin_tipos (nombre, naturaleza, activo) VALUES
            ('Cobros de actividades', 'ingreso', 1),
            ('Otros ingresos', 'ingreso', 1),
            ('Gastos de actividades', 'salida', 1),
            ('Pagos al personal', 'salida', 1),
            ('Otros gastos', 'salida', 1)
        ");
        $sembrarSiVacio('notificacion_tipos', "
            INSERT INTO notificacion_tipos (codigo, activo, importante) VALUES
            ('sendero', 1, 1), ('pago', 1, 1), ('encuesta', 1, 1), ('pasaporte', 1, 0),
            ('admin:usuario', 1, 0), ('admin:asignaciones', 1, 1), ('admin:asistencia', 1, 1),
            ('admin:encuesta', 1, 0), ('admin:obligacion', 1, 1)
        ");
    },
];
