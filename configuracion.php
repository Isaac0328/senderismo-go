<?php
// configuracion.php (NO debe exponer credenciales en produccion)

// Zona horaria oficial de la aplicacion: Republica Dominicana (UTC-4).
date_default_timezone_set('America/Santo_Domingo');
define('APP_TIMEZONE', 'America/Santo_Domingo');
define('APP_DB_TIMEZONE', '-04:00');

// Detectar entorno: local vs hosting
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);

// Base URL (la ajustaremos luego si SG queda dentro de una carpeta en el hosting)
define('BASE_URL', $isLocal ? '/SG/' : '/');

// DB Config
define('DB_HOST', $isLocal ? 'localhost' : 'localhost');
define('DB_NAME', $isLocal ? 'sgbd' : 'u591234109_senderismo');
define('DB_USER', $isLocal ? 'root' : 'u591234109_adminsg');
define('DB_PASS', $isLocal ? '' : 'S3nderismo_G0');

// Mostrar errores solo en local
define('APP_DEBUG', true);
ini_set('display_errors', 1);
error_reporting(E_ALL);


define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USER', 'no-reply@senderismogopro.com');
define('SMTP_PASS', 'S3nderismo_G0');
define('SMTP_FROM_EMAIL', 'no-reply@senderismogopro.com');
define('SMTP_FROM_NAME', 'Senderismo Go');
define('SMTP_REPLY_TO', 'senderismogopro@gmail.com');
