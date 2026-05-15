<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "pantallas/contacto.php");
    exit;
}

$_SESSION['contact_error'] = "El envio del formulario de contacto aun esta pendiente de configurar.";
header("Location: " . BASE_URL . "pantallas/contacto.php#contacto-form");
exit;
