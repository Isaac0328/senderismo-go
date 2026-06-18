<?php

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_validate_post')) {
    function csrf_validate_post(?string $redirectUrl = null, string $sessionKey = 'error_message'): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        $postedToken = (string) ($_POST['csrf_token'] ?? '');

        if ($sessionToken !== '' && $postedToken !== '' && hash_equals($sessionToken, $postedToken)) {
            return;
        }

        $_SESSION[$sessionKey] = 'La solicitud expiro o no es valida. Intenta nuevamente.';

        if ($redirectUrl !== null && $redirectUrl !== '') {
            header('Location: ' . $redirectUrl);
            exit;
        }

        http_response_code(419);
        exit('Solicitud no valida.');
    }
}
