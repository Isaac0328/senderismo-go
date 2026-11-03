<?php
require_once __DIR__ . '/../configuracion.php';
$PERMISO_REQUERIDO = 'finanzas.balances';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/balances_servicio.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Metodo no permitido.']); exit; }
$token = (string)($_POST['csrf_token'] ?? '');
if (!$token || !hash_equals(csrf_token(),$token)) { http_response_code(419); echo json_encode(['error'=>'La sesion del formulario expiro. Recarga la pagina.']); exit; }
$action = (string)($_POST['action'] ?? '');
$permissionAction = in_array($action,['reversar','reversar_sendero','reversar_masivo','anular_obligacion','cancelar_movimiento'],true) ? 'eliminar' :
    ((in_array($action,['catalogo','programacion','obligacion'],true) && !empty($_POST['id'])) || in_array($action,['pausar','editar_movimiento'],true) ? 'editar' : 'agregar');
if (!sg_has_permission_action($conn,'finanzas.balances',$permissionAction) ||
    ($action === 'cuadre' && isset($_POST['cerrado']) && !sg_has_permission($conn,'finanzas.cierres'))) {
    http_response_code(403); echo json_encode(['error'=>'No tienes permiso para esta operacion.']); exit;
}
try {
    $conn->begin_transaction();
    bal_one($conn,'SELECT id FROM fin_control WHERE id=1 FOR UPDATE');
    $requestToken = (string)($_POST['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/D',$requestToken)) { throw new InvalidArgumentException('Recarga el formulario e intenta de nuevo.'); }
    $existing = bal_one($conn,'SELECT resultado FROM fin_solicitudes WHERE token=? AND usuario_id=?',[$requestToken,$_SESSION['usuario_id']]);
    if ($existing) { $message=$existing['resultado']; }
    else {
        $message = bal_execute($conn,$action,$_POST,(int)$_SESSION['usuario_id']);
        bal_query($conn,'INSERT INTO fin_solicitudes (token,usuario_id,resultado) VALUES (?,?,?)',[$requestToken,$_SESSION['usuario_id'],$message]);
    }
    $conn->commit();
    echo json_encode(['ok'=>true,'message'=>$message]);
} catch (Throwable $e) {
    $conn->rollback();
    $known = $e instanceof InvalidArgumentException;
    if (!$known) { error_log('Balances: ' . $e->getMessage()); }
    http_response_code($known ? 422 : 500);
    echo json_encode(['error'=>$known ? $e->getMessage() : 'No se pudo guardar. Revisa que el nombre no este repetido y que la actualizacion de balances este instalada.']);
}
