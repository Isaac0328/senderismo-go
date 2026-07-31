<?php
// configuracion.php (NO debe exponer credenciales en produccion)

// Detectar entorno: local vs hosting
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);

// Base URL (la ajustaremos luego si SG queda dentro de una carpeta en el hosting)
define('BASE_URL', $isLocal ? '/SG/' : '/');

// DB Config
define('DB_HOST', $isLocal ? 'localhost' : (getenv('DB_HOST') ?: 'localhost'));
define('DB_NAME', $isLocal ? 'sgbd' : (getenv('DB_NAME') ?: 'TU_DB_NAME_HOSTINGER'));
define('DB_USER', $isLocal ? 'root' : (getenv('DB_USER') ?: 'TU_DB_USER_HOSTINGER'));
define('DB_PASS', $isLocal ? '' : (getenv('DB_PASS') ?: 'TU_DB_PASS_HOSTINGER'));

// Mostrar errores solo en local
define('APP_DEBUG', $isLocal);
ini_set('display_errors', APP_DEBUG ? 1 : 0);
error_reporting(APP_DEBUG ? E_ALL : 0);


define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 465));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl');
define('SMTP_USER', getenv('SMTP_USER') ?: 'no-reply@senderismogopro.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'CAMBIAR_CLAVE_DEL_CORREO');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'no-reply@senderismogopro.com');
define('SMTP_FROM_NAME', 'Senderismo Go');
define('SMTP_REPLY_TO', getenv('SMTP_REPLY_TO') ?: 'senderismogopro@gmail.com');
