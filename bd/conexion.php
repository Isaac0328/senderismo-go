<?php
require_once __DIR__ . '/../configuracion.php';

// Crear conexión
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión (en producción NO mostramos detalles)
if ($conn === false) {
    if (APP_DEBUG) {
        die("Error de conexión MySQL: " . mysqli_connect_error());
    }
    die("Error de conexión. Intente más tarde.");
}

// Forzar UTF-8
mysqli_set_charset($conn, "utf8mb4");
