<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta herramienta solo se ejecuta desde la linea de comandos.');
}

require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/migraciones.php';

try {
    $pendientes = sg_migraciones_pendientes($conn);
    if (!$pendientes) {
        echo "No hay migraciones pendientes para " . DB_NAME . ".\n";
        exit(0);
    }

    foreach ($pendientes as $migracion) {
        echo 'Aplicando ' . $migracion['id'] . ' - ' . $migracion['descripcion'] . "\n";
    }

    $aplicadas = sg_migraciones_aplicar($conn);
    echo 'Migraciones aplicadas: ' . count($aplicadas) . ".\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    mysqli_close($conn);
}
