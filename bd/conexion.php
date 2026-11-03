<?php
require_once __DIR__ . '/../configuracion.php';

if (DB_NAME === '' || DB_USER === '') {
    error_log('Configuracion de base de datos incompleta para el entorno ' . APP_ENV . '.');
    die(APP_DEBUG ? 'Faltan DB_NAME o DB_USER en la configuracion privada.' : 'Error de conexión. Intente más tarde.');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión (en producción NO mostramos detalles)
if ($conn === false) {
    if (APP_DEBUG) {
        die("Error de conexión MySQL: " . mysqli_connect_error());
    }
    die("Error de conexión. Intente más tarde.");
}

// Forzar UTF-8
mysqli_set_charset($conn, "utf8mb4");

// MySQL puede usar UTC por defecto en el hosting; la aplicacion opera en UTC-4.
mysqli_query($conn, "SET time_zone = '" . APP_DB_TIMEZONE . "'");
