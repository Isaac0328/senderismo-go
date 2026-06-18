<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/contabilidad_bootstrap.php';

contabilidad_bootstrap($conn);

function gastos_sendero_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    $url = BASE_URL . "mantenimientos/mantenimiento_gastos_sendero.php";
    if ($senderoId > 0) {
        $url .= "?sendero_id=" . $senderoId;
    }
    header("Location: " . $url);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['gastos_sendero_error'] = "Metodo no permitido.";
    gastos_sendero_redirect($conn, 0);
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_gastos_sendero.php", 'gastos_sendero_error');

$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$gastos = $_POST['gastos'] ?? [];

if ($senderoId <= 0 || !is_array($gastos)) {
    $_SESSION['gastos_sendero_error'] = "Selecciona un sendero valido.";
    gastos_sendero_redirect($conn, $senderoId);
}

$stmt = mysqli_prepare($conn, "SELECT id FROM senderos WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $senderoId);
mysqli_stmt_execute($stmt);
$existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$existe) {
    $_SESSION['gastos_sendero_error'] = "El sendero seleccionado no existe.";
    gastos_sendero_redirect($conn, 0);
}

mysqli_begin_transaction($conn);

try {
    $stmtDelete = mysqli_prepare($conn, "DELETE FROM contabilidad_sendero_gastos WHERE sendero_id = ?");
    mysqli_stmt_bind_param($stmtDelete, 'i', $senderoId);
    mysqli_stmt_execute($stmtDelete);
    mysqli_stmt_close($stmtDelete);

    $stmtInsert = mysqli_prepare(
        $conn,
        "INSERT INTO contabilidad_sendero_gastos (sendero_id, gasto_id, cantidad, costo_unitario, total, nota)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $totalGeneral = 0;
    foreach ($gastos as $gastoId => $data) {
        if (!is_array($data)) {
            continue;
        }

        $gastoId = (int) $gastoId;
        $cantidad = max(0, (float) ($data['cantidad'] ?? 0));
        $costo = max(0, (float) ($data['costo_unitario'] ?? 0));
        $usar = !empty($data['usar']);
        $nota = trim((string) ($data['nota'] ?? ''));
        $nota = substr(preg_replace('/\s+/', ' ', $nota), 0, 255);
        $total = round($cantidad * $costo, 2);

        if (!$usar || $gastoId <= 0 || $cantidad <= 0 || $total <= 0) {
            continue;
        }

        mysqli_stmt_bind_param($stmtInsert, 'iiddds', $senderoId, $gastoId, $cantidad, $costo, $total, $nota);
        mysqli_stmt_execute($stmtInsert);
        $totalGeneral += $total;
    }

    mysqli_stmt_close($stmtInsert);
    mysqli_commit($conn);

    $_SESSION['gastos_sendero_success'] = "Gastos guardados. Total del sendero: RD$ " . number_format($totalGeneral, 2) . ".";
    gastos_sendero_redirect($conn, $senderoId);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['gastos_sendero_error'] = APP_DEBUG ? $e->getMessage() : "No se pudieron guardar los gastos del sendero.";
    gastos_sendero_redirect($conn, $senderoId);
}
