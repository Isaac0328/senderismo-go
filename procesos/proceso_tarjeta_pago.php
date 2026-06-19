<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in']) || (int) ($_SESSION['usuario_rol_id'] ?? 0) !== 1) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_tarjeta_pago.php");
    exit;
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_tarjeta_pago.php", 'pago_error');

require_once __DIR__ . '/../bd/conexion.php';

$banco = trim($_POST['banco'] ?? '');
$cuenta = trim($_POST['cuenta'] ?? '');
$tipoCuenta = trim($_POST['tipo_cuenta'] ?? '');
$cedula = trim($_POST['cedula'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono_comprobante'] ?? '');
$nota = trim($_POST['nota_importante'] ?? '');
$activo = isset($_POST['activo']) ? 1 : 0;

if ($banco === '' || $cuenta === '' || $tipoCuenta === '' || $cedula === '' || $correo === '' || $nombre === '' || $telefono === '' || $nota === '') {
    $_SESSION['pago_error'] = "Completa todos los datos de pago.";
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_tarjeta_pago.php");
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO tarjeta_pago
     (id, banco, cuenta, tipo_cuenta, cedula, correo, nombre, telefono_comprobante, nota_importante, activo)
     VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        banco = VALUES(banco),
        cuenta = VALUES(cuenta),
        tipo_cuenta = VALUES(tipo_cuenta),
        cedula = VALUES(cedula),
        correo = VALUES(correo),
        nombre = VALUES(nombre),
        telefono_comprobante = VALUES(telefono_comprobante),
        nota_importante = VALUES(nota_importante),
        activo = VALUES(activo)"
);
mysqli_stmt_bind_param($stmt, "ssssssssi", $banco, $cuenta, $tipoCuenta, $cedula, $correo, $nombre, $telefono, $nota, $activo);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['pago_success'] = "Datos de pago actualizados correctamente.";
mysqli_close($conn);

header("Location: " . BASE_URL . "mantenimientos/mantenimiento_tarjeta_pago.php");
exit;
