<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$PERMISO_REQUERIDO = 'operaciones.provincias';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
$redirect = sg_clean_url('mantenimientos/mantenimiento_provincias.php');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metodo no permitido.');
}
csrf_validate_post($redirect, 'provincias_error');
$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
sg_require_permission_action($conn, $PERMISO_REQUERIDO, $action === 'save' && $id === 0 ? 'agregar' : 'editar', $redirect);

try {
    if (!in_array($action, ['save', 'toggle'], true) || $id < 0 || ($action === 'toggle' && $id === 0)) {
        throw new InvalidArgumentException('Accion no valida.');
    }
    if ($id > 0) {
        $stmt = $conn->prepare('SELECT id FROM provincias WHERE id = ?');
        if (!$stmt) { throw new RuntimeException('No se pudo consultar la provincia.'); }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) { throw new RuntimeException('No se pudo consultar la provincia.'); }
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) { throw new InvalidArgumentException('La provincia ya no existe.'); }
    }
    if ($action === 'save') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $tipo = (string) ($_POST['tipo'] ?? 'provincia');
        $activo = isset($_POST['activo']) ? 1 : 0;
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 120) {
            throw new InvalidArgumentException('El nombre es obligatorio y debe tener hasta 120 caracteres.');
        }
        if (!in_array($tipo, ['provincia', 'distrito_nacional'], true)) {
            throw new InvalidArgumentException('El tipo de provincia no es valido.');
        }
        $stmt = $conn->prepare($id > 0
            ? 'UPDATE provincias SET nombre = ?, tipo = ?, activo = ? WHERE id = ?'
            : 'INSERT INTO provincias (nombre, tipo, activo) VALUES (?, ?, ?)');
        if (!$stmt) { throw new RuntimeException('No se pudo preparar el registro.'); }
        if ($id > 0) { $stmt->bind_param('ssii', $nombre, $tipo, $activo, $id); }
        else { $stmt->bind_param('ssi', $nombre, $tipo, $activo); }
    } else {
        $rawActive = (string) ($_POST['activo'] ?? '');
        if (!in_array($rawActive, ['0', '1'], true)) {
            throw new InvalidArgumentException('El estado no es valido.');
        }
        $activo = (int) $rawActive;
        $stmt = $conn->prepare('UPDATE provincias SET activo = ? WHERE id = ?');
        if (!$stmt) { throw new RuntimeException('No se pudo preparar el cambio de estado.'); }
        $stmt->bind_param('ii', $activo, $id);
    }
    if (!$stmt->execute()) {
        $duplicate = $stmt->errno === 1062;
        $stmt->close();
        if ($duplicate) { throw new InvalidArgumentException('Ya existe una provincia con ese nombre.'); }
        throw new RuntimeException('No se pudo guardar la provincia.');
    }
    $stmt->close();
    $_SESSION['provincias_success'] = $action === 'save' ? 'Provincia guardada correctamente.' : ($activo === 1 ? 'Provincia activada.' : 'Provincia inactivada.');
} catch (Throwable $error) {
    error_log('Provincias: ' . $error->getMessage());
    $_SESSION['provincias_error'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'No se pudo guardar la provincia. Revisa las actualizaciones del sistema.';
}
mysqli_close($conn);
header('Location: ' . $redirect);
exit;
