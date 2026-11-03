<?php

$privateConfigPath = __DIR__ . '/.private/config.php';
$privateConfig = is_file($privateConfigPath) ? require $privateConfigPath : [];
if (!is_array($privateConfig)) {
    $privateConfig = [];
}

$configValue = static function (string $key, $default = null) use ($privateConfig) {
    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    return array_key_exists($key, $privateConfig) ? $privateConfig[$key] : $default;
};

$serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$configuredEnvironment = strtolower(trim((string) $configValue('APP_ENV', '')));
$isLocal = $configuredEnvironment === 'local'
    || ($configuredEnvironment === '' && (PHP_SAPI === 'cli' || in_array($serverName, ['localhost', '127.0.0.1'], true)));

define('APP_ENV', $isLocal ? 'local' : 'production');
define('APP_TIMEZONE', (string) $configValue('APP_TIMEZONE', 'America/Santo_Domingo'));
define('APP_DB_TIMEZONE', (string) $configValue('APP_DB_TIMEZONE', '-04:00'));
date_default_timezone_set(APP_TIMEZONE);

// En local la URL base sigue el nombre real de la carpeta dentro de htdocs.
define('BASE_URL', (string) $configValue('BASE_URL', $isLocal ? '/' . basename(__DIR__) . '/' : '/'));

$databasePrefix = $isLocal ? 'DB_LOCAL_' : 'DB_PRODUCTION_';
define('DB_HOST', (string) $configValue('DB_HOST', $configValue($databasePrefix . 'HOST', 'localhost')));
define('DB_NAME', (string) $configValue('DB_NAME', $configValue($databasePrefix . 'NAME', $isLocal ? 'sgbd' : '')));
define('DB_USER', (string) $configValue('DB_USER', $configValue($databasePrefix . 'USER', $isLocal ? 'root' : '')));
define('DB_PASS', (string) $configValue('DB_PASS', $configValue($databasePrefix . 'PASS', '')));

$debugValue = $configValue('APP_DEBUG', $isLocal);
$debugEnabled = filter_var($debugValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
define('APP_DEBUG', $debugEnabled ?? $isLocal);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    $requestIsHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $requestIsHttps ? '1' : '0');
}

define('SMTP_HOST', (string) $configValue('SMTP_HOST', 'smtp.hostinger.com'));
define('SMTP_PORT', (int) $configValue('SMTP_PORT', 465));
define('SMTP_SECURE', (string) $configValue('SMTP_SECURE', 'ssl'));
define('SMTP_USER', (string) $configValue('SMTP_USER', ''));
define('SMTP_PASS', (string) $configValue('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', (string) $configValue('SMTP_FROM_EMAIL', SMTP_USER));
define('SMTP_FROM_NAME', (string) $configValue('SMTP_FROM_NAME', 'Plataforma'));
define('SMTP_REPLY_TO', (string) $configValue('SMTP_REPLY_TO', SMTP_FROM_EMAIL));
