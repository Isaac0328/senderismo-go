<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ROLES_PERMITIDOS = [1];
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/ingresos_cobro.php';

function asistencia_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    $url = BASE_URL . "mantenimientos/mantenimiento_asistencia_senderos.php";
    if ($senderoId > 0) {
        $url .= "?sendero_id=" . $senderoId;
    }
    header("Location: " . $url);
    exit;
}

function asistencia_clean_note(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return mb_substr($value, 0, 255);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_asistencia_senderos.php");
    exit;
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_asistencia_senderos.php", 'asistencia_error');

$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$registroIds = $_POST['registro_ids'] ?? [];
$asistieron = $_POST['asistio'] ?? [];
$notas = $_POST['notas'] ?? [];
// Como estaba cada fila al abrir la pantalla: solo se guarda lo que cambio.
$asistioOriginal = is_array($_POST['asistio_original'] ?? null) ? $_POST['asistio_original'] : [];
$notaOriginal = is_array($_POST['nota_original'] ?? null) ? $_POST['nota_original'] : [];
$adminId = (int) ($_SESSION['usuario_id'] ?? 0);

if ($senderoId <= 0 || !is_array($registroIds)) {
    $_SESSION['asistencia_error'] = "Selecciona un sendero valido.";
    asistencia_redirect($conn, $senderoId);
}

$registroIds = array_values(array_unique(array_filter(array_map('intval', $registroIds))));
$asistieronMap = array_flip(is_array($asistieron) ? array_map('intval', $asistieron) : []);
$notas = is_array($notas) ? $notas : [];

if (empty($registroIds)) {
    $_SESSION['asistencia_error'] = "No hay registros activos para actualizar.";
    asistencia_redirect($conn, $senderoId);
}

$stmt = mysqli_prepare($conn, "SELECT fecha_sendero FROM senderos WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $senderoId);
mysqli_stmt_execute($stmt);
$senderoRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$fechaSendero = (string) ($senderoRow['fecha_sendero'] ?? '');
$fechaAsistencia = $fechaSendero !== '' ? $fechaSendero . ' 00:00:00' : date('Y-m-d H:i:s');

// Estado actual en la base de cada inscripcion enviada.
$idsSql = implode(',', $registroIds);
$res = mysqli_query(
    $conn,
    "SELECT rs.id, rs.asistio, COALESCE(rs.asistencia_notas, '') AS notas,
            TRIM(CONCAT(COALESCE(u.nombre, rs.manual_nombre, ''), ' ', COALESCE(u.apellido, rs.manual_apellido, ''))) AS nombre
     FROM registros_senderos rs
     LEFT JOIN usuarios u ON u.id = rs.usuario_id
     WHERE rs.sendero_id = " . (int) $senderoId . "
       AND rs.estado = 'registrado'
       AND rs.id IN ($idsSql)"
);
$actuales = [];
while ($res && $row = mysqli_fetch_assoc($res)) {
    $actuales[(int) $row['id']] = $row;
}

if (empty($actuales)) {
    $_SESSION['asistencia_error'] = "No se encontraron registros activos para este sendero.";
    asistencia_redirect($conn, $senderoId);
}

$conCobros = ic_esquema_listo($conn);
$cambiosAsistencia = 0;
$cambiosNota = 0;
$presentes = 0;
$omitidos = [];

foreach ($actuales as $registroId => $actual) {
    $nombre = $actual['nombre'] !== '' ? $actual['nombre'] : 'Inscripcion #' . $registroId;
    $asistio = isset($asistieronMap[$registroId]) ? 1 : 0;
    $nota = asistencia_clean_note((string) ($notas[$registroId] ?? ''));
    $asistioAntes = array_key_exists($registroId, $asistioOriginal) ? (int) $asistioOriginal[$registroId] : (int) $actual['asistio'];
    $notaAntes = array_key_exists($registroId, $notaOriginal) ? asistencia_clean_note((string) $notaOriginal[$registroId]) : (string) $actual['notas'];
    $presentes += $asistio;

    $cambiaAsistencia = $asistio !== $asistioAntes;
    $cambiaNota = $nota !== $notaAntes;
    if (!$cambiaAsistencia && !$cambiaNota) {
        continue;
    }

    // Otra persona (aqui o en Ingresos) lo cambio despues de abrir esta pantalla: no se pisa.
    if ($cambiaAsistencia && (int) $actual['asistio'] !== $asistioAntes) {
        $omitidos[] = $nombre . ': otra persona cambio su asistencia mientras editabas; recarga para ver lo ultimo.';
        continue;
    }

    mysqli_begin_transaction($conn);
    try {
        if ($cambiaAsistencia) {
            if ($conCobros) {
                // Mismas reglas que Ingresos por sendero: el cobro se recalcula y debe cuadrar.
                ic_cambiar_asistencia($conn, $senderoId, $registroId, $asistio === 1, $adminId);
            }
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE registros_senderos
                 SET asistio = ?,
                     fecha_asistencia = CASE WHEN ? = 1 THEN ? ELSE NULL END,
                     asistencia_marcada_por = CASE WHEN ? = 1 THEN ? ELSE NULL END
                 WHERE id = ? AND sendero_id = ? AND estado = 'registrado'"
            );
            mysqli_stmt_bind_param($stmt, 'iisiiii', $asistio, $asistio, $fechaAsistencia, $asistio, $adminId, $registroId, $senderoId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $cambiosAsistencia++;
        }
        if ($cambiaNota) {
            $stmt = mysqli_prepare($conn, "UPDATE registros_senderos SET asistencia_notas = ? WHERE id = ? AND sendero_id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $nota, $registroId, $senderoId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $cambiosNota++;
        }
        mysqli_commit($conn);
    } catch (IngresoValidacionException $e) {
        mysqli_rollback($conn);
        $mensaje = preg_replace('/^' . preg_quote($nombre, '/') . ':\s*/', '', $e->getMessage());
        $omitidos[] = $nombre . ': no se cambio su asistencia porque su cobro no cuadraria (' . rtrim($mensaje, '.') . '). Revisalo en Ingresos por sendero.';
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('Asistencia: ' . $e->getMessage());
        $omitidos[] = $nombre . ': ' . (APP_DEBUG ? $e->getMessage() : 'no se pudo guardar.');
    }
}

if ($cambiosAsistencia === 0 && $cambiosNota === 0 && !$omitidos) {
    $_SESSION['asistencia_success'] = "No habia cambios que guardar.";
} else {
    $partes = [];
    if ($cambiosAsistencia > 0) {
        $partes[] = $cambiosAsistencia . ' cambio(s) de asistencia';
    }
    if ($cambiosNota > 0) {
        $partes[] = $cambiosNota . ' nota(s)';
    }
    if ($partes) {
        $_SESSION['asistencia_success'] = 'Asistencia guardada: ' . implode(' y ', $partes) . '.';
    }
    if ($omitidos) {
        $_SESSION['asistencia_error'] = 'No se aplico a ' . count($omitidos) . ' persona(s). ' . implode(' ', $omitidos);
    }
}
asistencia_redirect($conn, $senderoId);
