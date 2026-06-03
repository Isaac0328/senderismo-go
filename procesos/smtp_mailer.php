<?php

function smtp_mailer_is_configured(): bool
{
    return defined('SMTP_HOST')
        && defined('SMTP_PORT')
        && defined('SMTP_USER')
        && defined('SMTP_PASS')
        && SMTP_HOST !== ''
        && (int) SMTP_PORT > 0
        && SMTP_USER !== ''
        && SMTP_PASS !== ''
        && SMTP_PASS !== 'CAMBIAR_CLAVE_DEL_CORREO';
}

function smtp_mailer_send(string $toEmail, string $toName, string $subject, string $body, ?string &$error = null): bool
{
    $error = null;

    if (!smtp_mailer_is_configured()) {
        $error = 'SMTP no configurado.';
        return false;
    }

    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $secure = strtolower((string) (defined('SMTP_SECURE') ? SMTP_SECURE : 'ssl'));
    $remote = $secure === 'ssl' ? "ssl://{$host}" : $host;
    $timeout = 20;

    $socket = @fsockopen($remote, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        $error = "No se pudo conectar al SMTP: {$errstr}";
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $read = static function () use ($socket): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };

    $expect = static function (array $codes, string $step) use ($read, &$error): bool {
        $response = $read();
        $code = substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            $error = "{$step}: {$response}";
            return false;
        }
        return true;
    };

    $write = static function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    if (!$expect(['220'], 'Conexion SMTP')) {
        fclose($socket);
        return false;
    }

    $serverName = $_SERVER['SERVER_NAME'] ?? 'senderismogopro.com';
    $write("EHLO {$serverName}");
    if (!$expect(['250'], 'EHLO')) {
        fclose($socket);
        return false;
    }

    if ($secure === 'tls') {
        $write('STARTTLS');
        if (!$expect(['220'], 'STARTTLS')) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $error = 'No se pudo activar TLS.';
            fclose($socket);
            return false;
        }
        $write("EHLO {$serverName}");
        if (!$expect(['250'], 'EHLO TLS')) {
            fclose($socket);
            return false;
        }
    }

    $write('AUTH LOGIN');
    if (!$expect(['334'], 'AUTH LOGIN')) {
        fclose($socket);
        return false;
    }

    $write(base64_encode(SMTP_USER));
    if (!$expect(['334'], 'SMTP usuario')) {
        fclose($socket);
        return false;
    }

    $write(base64_encode(SMTP_PASS));
    if (!$expect(['235'], 'SMTP clave')) {
        fclose($socket);
        return false;
    }

    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USER;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Senderismo Go';
    $replyTo = defined('SMTP_REPLY_TO') ? SMTP_REPLY_TO : $fromEmail;

    $write('MAIL FROM:<' . $fromEmail . '>');
    if (!$expect(['250'], 'MAIL FROM')) {
        fclose($socket);
        return false;
    }

    $write('RCPT TO:<' . $toEmail . '>');
    if (!$expect(['250', '251'], 'RCPT TO')) {
        fclose($socket);
        return false;
    }

    $write('DATA');
    if (!$expect(['354'], 'DATA')) {
        fclose($socket);
        return false;
    }

    $headers = [
        'From: ' . smtp_mailer_format_address($fromEmail, $fromName),
        'Reply-To: ' . smtp_mailer_format_address($replyTo, $fromName),
        'To: ' . smtp_mailer_format_address($toEmail, $toName),
        'Subject: ' . smtp_mailer_header_text($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $data = implode("\r\n", $headers) . "\r\n\r\n" . smtp_mailer_dot_stuff($body) . "\r\n.";
    $write($data);
    if (!$expect(['250'], 'Enviar correo')) {
        fclose($socket);
        return false;
    }

    $write('QUIT');
    fclose($socket);
    return true;
}

function smtp_mailer_format_address(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = trim($name);
    if ($name === '') {
        return "<{$email}>";
    }
    return '"' . str_replace('"', "'", $name) . "\" <{$email}>";
}

function smtp_mailer_header_text(string $text): string
{
    return mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
}

function smtp_mailer_dot_stuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);
    return implode("\r\n", $lines);
}
