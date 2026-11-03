<?php

return [
    'id' => '20261005_001_registrar_esquema_base',
    'descripcion' => 'Registra el esquema inicial de la aplicacion.',
    'up' => static function (mysqli $conn): void {
        $tablasRequeridas = ['roles', 'usuarios', 'senderos', 'registros_senderos', 'configuracion_sitio'];
        $base = mysqli_real_escape_string($conn, DB_NAME);

        foreach ($tablasRequeridas as $tabla) {
            $tablaSegura = mysqli_real_escape_string($conn, $tabla);
            $resultado = mysqli_query($conn, "
                SELECT COUNT(*) AS total
                FROM information_schema.tables
                WHERE table_schema = '{$base}'
                  AND table_name = '{$tablaSegura}'
            ");
            $fila = $resultado ? mysqli_fetch_assoc($resultado) : null;
            if ($resultado) {
                mysqli_free_result($resultado);
            }

            if ((int) ($fila['total'] ?? 0) !== 1) {
                throw new RuntimeException('Falta la tabla base ' . $tabla . '. Primero importa migraciones/estructura/001_estructura_base.sql en una base vacia.');
            }
        }
    },
];
