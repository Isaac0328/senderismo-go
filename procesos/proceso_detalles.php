<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in']) || (int) ($_SESSION['usuario_rol_id'] ?? 0) !== 1) {
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['detalles_error'] = "Metodo no permitido.";
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_detalles.php");
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';

$catalogs = [
    'terreno' => [
        'table' => 'tipos_terreno',
        'label' => 'tipo de terreno',
    ],
    'dificultad' => [
        'table' => 'niveles_dificultad',
        'label' => 'nivel de dificultad',
    ],
    'camino' => [
        'table' => 'tipos_camino_vehiculo',
        'label' => 'tipo de camino vehicular',
    ],
    'anotacion' => [
        'table' => 'anotaciones_importantes',
        'label' => 'anotacion importante',
    ],
    'incluye' => [
        'table' => 'elementos_incluidos',
        'label' => 'elemento incluido',
    ],
];

$catalog = $_POST['catalog'] ?? '';
$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

function redirect_detalles(mysqli $conn, string $catalog = ''): void
{
    mysqli_close($conn);
    $url = BASE_URL . "mantenimientos/mantenimiento_detalles.php";
    if ($catalog !== '') {
        $url .= "#" . $catalog;
    }
    header("Location: " . $url);
    exit;
}

try {
    global $catalogs, $catalog, $action, $id, $conn;

    if (!isset($catalogs[$catalog])) {
        $_SESSION['detalles_error'] = "Catalogo no valido.";
        redirect_detalles($conn);
    }

    $table = $catalogs[$catalog]['table'];
    $label = $catalogs[$catalog]['label'];

    if ($action === 'save') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $_SESSION['detalles_error'] = "El nombre del {$label} es obligatorio.";
            redirect_detalles($conn, $catalog);
        }

        if ($id > 0) {
            $sql = "UPDATE {$table} SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssii", $nombre, $descripcion, $activo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['detalles_success'] = ucfirst($label) . " actualizado correctamente.";
        } else {
            $sql = "INSERT INTO {$table} (nombre, descripcion, activo) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $nombre, $descripcion, $activo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['detalles_success'] = ucfirst($label) . " creado correctamente.";
        }

        redirect_detalles($conn, $catalog);
    }

    if ($action === 'toggle') {
        $activo = (int) ($_POST['activo'] ?? 0);
        $sql = "UPDATE {$table} SET activo = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['detalles_success'] = $activo === 1 ? "Registro activado." : "Registro inactivado.";
        redirect_detalles($conn, $catalog);
    }

    $_SESSION['detalles_error'] = "Accion no valida.";
    redirect_detalles($conn, $catalog);
} catch (Throwable $e) {
    $_SESSION['detalles_error'] = "Error: " . $e->getMessage();
    redirect_detalles($conn, $catalog);
}
