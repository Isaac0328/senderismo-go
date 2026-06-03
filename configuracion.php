<?php
// configuracion.php (NO debe exponer credenciales en produccion)

// Detectar entorno: local vs hosting
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);

// Base URL (la ajustaremos luego si SG queda dentro de una carpeta en el hosting)
define('BASE_URL', $isLocal ? '/SG/' : '/');

// DB Config
define('DB_HOST', $isLocal ? 'localhost' : 'localhost'); // en hostinger también suele ser localhost
define('DB_NAME', $isLocal ? 'sgbd' : 'TU_DB_NAME_HOSTINGER');
define('DB_USER', $isLocal ? 'root' : 'TU_DB_USER_HOSTINGER');
define('DB_PASS', $isLocal ? '' : 'TU_DB_PASS_HOSTINGER');

// Mostrar errores solo en local
define('APP_DEBUG', $isLocal);

// SMTP para envio de correos del sistema.
// En produccion completa SMTP_PASS con la clave del correo creado en Hostinger.
define('SMTP_HOST', $isLocal ? '' : 'smtp.hostinger.com');
define('SMTP_PORT', $isLocal ? 0 : 465);
define('SMTP_SECURE', $isLocal ? '' : 'ssl'); // ssl para 465, tls para 587
define('SMTP_USER', $isLocal ? '' : 'no-reply@senderismogopro.com');
define('SMTP_PASS', $isLocal ? '' : 'CAMBIAR_CLAVE_DEL_CORREO');
define('SMTP_FROM_EMAIL', $isLocal ? 'no-reply@senderismogopro.com' : 'no-reply@senderismogopro.com');
define('SMTP_FROM_NAME', 'Senderismo Go');
define('SMTP_REPLY_TO', 'senderismogopro@gmail.com');
