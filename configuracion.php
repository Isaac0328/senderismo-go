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
