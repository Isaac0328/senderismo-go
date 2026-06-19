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
    $_SESSION['senderos_error'] = "Metodo no permitido.";
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_senderos.php");
    exit;
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_senderos.php", 'senderos_error');

require_once __DIR__ . '/../bd/conexion.php';

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

function redirect_senderos(mysqli $conn, ?int $editId = null): void
{
    mysqli_close($conn);
    $url = BASE_URL . "mantenimientos/mantenimiento_senderos.php";
    if ($editId && $editId > 0) {
        $url .= "?edit=" . $editId;
    }
    header("Location: " . $url);
    exit;
}

function slugify_sendero(string $text): string
{
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n'
    ];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text !== '' ? $text : 'sendero';
}

function unique_sendero_slug(mysqli $conn, string $nombre, int $id): string
{
    $base = slugify_sendero($nombre);
    $slug = $base;
    $i = 2;

    while (true) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM senderos WHERE slug = ? AND id <> ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "si", $slug, $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = $res && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$exists) {
            return $slug;
        }

        $slug = $base . '-' . $i;
        $i++;
    }
}

function save_uploaded_image(array $file, string $slug, string $prefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("No se pudo cargar una imagen.");
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp) || getimagesize($tmp) === false) {
        throw new RuntimeException("El archivo cargado no es una imagen valida.");
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException("Solo se permiten imagenes JPG, PNG o WEBP.");
    }

    $folder = __DIR__ . '/../imagenes/senderos/' . $slug;
    if (!is_dir($folder) && !mkdir($folder, 0775, true)) {
        throw new RuntimeException("No se pudo crear la carpeta del sendero.");
    }

    $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $folder . '/' . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException("No se pudo guardar la imagen cargada.");
    }

    return 'imagenes/senderos/' . $slug . '/' . $filename;
}

function uploaded_files_array(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }

    $files = [];
    $count = count($_FILES[$field]['name']);

    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name' => $_FILES[$field]['name'][$i],
            'type' => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error' => $_FILES[$field]['error'][$i],
            'size' => $_FILES[$field]['size'][$i],
        ];
    }

    return $files;
}

function normalizar_fecha_sendero(string $fechaIso, string $fechaVisual = ''): string
{
    $fechaVisual = trim($fechaVisual);

    if ($fechaVisual !== '') {
        $dt = DateTime::createFromFormat('d/m/Y', $fechaVisual);
        $errores = DateTime::getLastErrors();
        $sinErrores = $errores === false || ((int) ($errores['warning_count'] ?? 0) === 0 && (int) ($errores['error_count'] ?? 0) === 0);
        if ($dt && $sinErrores) {
            return $dt->format('Y-m-d');
        }
        return '';
    }

    $fechaIso = trim($fechaIso);
    $dt = DateTime::createFromFormat('Y-m-d', $fechaIso);
    $errores = DateTime::getLastErrors();
    $sinErrores = $errores === false || ((int) ($errores['warning_count'] ?? 0) === 0 && (int) ($errores['error_count'] ?? 0) === 0);
    if ($dt && $sinErrores) {
        return $dt->format('Y-m-d');
    }

    return '';
}

try {
    if ($action === 'delete_image') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        if ($imageId <= 0) {
            $_SESSION['senderos_error'] = "Imagen invalida.";
            redirect_senderos($conn, $id);
        }

        $stmt = mysqli_prepare($conn, "UPDATE sendero_imagenes SET activo = 0 WHERE id = ? AND sendero_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $imageId, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['senderos_success'] = "Imagen removida de la galeria.";
        redirect_senderos($conn, $id);
    }

    if ($action === 'toggle_activo') {
        $activo = (int) ($_POST['activo'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE senderos SET activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['senderos_success'] = $activo === 1 ? "Sendero activado." : "Sendero inactivado.";
        redirect_senderos($conn);
    }

    if ($action !== 'save') {
        $_SESSION['senderos_error'] = "Accion no valida.";
        redirect_senderos($conn);
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $fecha = normalizar_fecha_sendero((string) ($_POST['fecha_sendero'] ?? ''), (string) ($_POST['fecha_sendero_visual'] ?? ''));
    $lugar = trim($_POST['lugar'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $descripcionCorta = trim($_POST['descripcion_corta'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nivelId = (int) ($_POST['nivel_dificultad_id'] ?? 0);
    $tiempoIdaVehiculo = ($_POST['tiempo_ida_vehiculo_min'] ?? '') !== '' ? max(0, (int) $_POST['tiempo_ida_vehiculo_min']) : null;
    $tiempoRegresoVehiculo = ($_POST['tiempo_regreso_vehiculo_min'] ?? '') !== '' ? max(0, (int) $_POST['tiempo_regreso_vehiculo_min']) : null;
    $tipoCaminoVehiculoId = ($_POST['tipo_camino_vehiculo_id'] ?? '') !== '' ? (int) $_POST['tipo_camino_vehiculo_id'] : null;
    $tiempoSendero = ($_POST['tiempo_sendero_min'] ?? '') !== '' ? max(0, (int) $_POST['tiempo_sendero_min']) : null;
    $distanciaKm = ($_POST['distancia_km'] ?? '') !== '' ? max(0, (float) $_POST['distancia_km']) : null;
    $desnivelMts = ($_POST['desnivel_mts'] ?? '') !== '' ? max(0, (int) $_POST['desnivel_mts']) : null;
    $coberturaSenal = ($_POST['cobertura_senal_pct'] ?? '') !== '' ? min(100, max(0, (int) $_POST['cobertura_senal_pct'])) : null;
    $inversionesInput = is_array($_POST['inversiones'] ?? null) ? $_POST['inversiones'] : [];
    $estado = $_POST['estado'] ?? 'pendiente';
    $activo = isset($_POST['activo']) ? 1 : 0;
    $terrenos = array_map('intval', $_POST['tipos_terreno'] ?? []);
    $anotaciones = array_map('intval', $_POST['anotaciones'] ?? []);
    $inversiones = [];
    foreach ($inversionesInput as $idx => $inversion) {
        if (!is_array($inversion)) {
            continue;
        }

        $ordenInversion = max(1, (int) ($inversion['orden'] ?? ((int) $idx + 1)));
        $nombreInversion = trim((string) ($inversion['nombre'] ?? ''));
        $montoInversion = ($inversion['monto'] ?? '') !== '' ? max(0, (float) $inversion['monto']) : null;

        if ($nombreInversion === '' && $montoInversion === null) {
            continue;
        }

        if ($nombreInversion === '') {
            $nombreInversion = 'Inversion ' . $ordenInversion;
        }

        $inversiones[] = [
            'id' => (int) ($inversion['id'] ?? 0),
            'nombre' => $nombreInversion,
            'descripcion' => trim((string) ($inversion['descripcion'] ?? '')),
            'monto' => $montoInversion ?? 0,
            'fecha_limite_pago' => normalizar_fecha_sendero((string) ($inversion['fecha_limite_pago'] ?? '')),
            'orden' => $ordenInversion,
            'activo' => isset($inversion['activo']) ? 1 : 0,
            'incluye' => array_values(array_unique(array_filter(array_map('intval', $inversion['incluye'] ?? [])))),
        ];
    }

    if ($nombre === '' || $lugar === '' || $nivelId <= 0) {
        $_SESSION['senderos_error'] = "Completa nombre, fecha en formato dia/mes/año, lugar y dificultad.";
        redirect_senderos($conn, $id);
    }

    $inversionesActivas = array_values(array_filter($inversiones, static fn($item) => (int) $item['activo'] === 1));
    if ($estado === 'pendiente' && empty($inversionesActivas)) {
        $_SESSION['senderos_error'] = "Agrega al menos una inversion activa para este sendero.";
        redirect_senderos($conn, $id);
    }

    foreach ($inversionesActivas as $inversion) {
        if ((float) $inversion['monto'] <= 0) {
            $_SESSION['senderos_error'] = "Cada inversion activa debe tener monto mayor a cero.";
            redirect_senderos($conn, $id);
        }
    }

    $inversionBase = $inversionesActivas[0] ?? ($inversiones[0] ?? null);
    $inversionTotal = $inversionBase ? (float) $inversionBase['monto'] : null;
    $fechaLimitePago = $inversionBase['fecha_limite_pago'] ?? '';

    if (!in_array($estado, ['pendiente', 'visitado'], true)) {
        $estado = 'pendiente';
    }

    if ($estado === 'pendiente') {
        if ($fecha === '') {
            $_SESSION['senderos_error'] = "Un sendero pendiente debe tener fecha.";
            redirect_senderos($conn, $id);
        }

        if ($fecha < date('Y-m-d')) {
            $_SESSION['senderos_error'] = "Un sendero pendiente debe tener una fecha de hoy o futura. Si ya se realizo, cambialo a visitado.";
            redirect_senderos($conn, $id);
        }
    }

    mysqli_begin_transaction($conn);

    $slug = unique_sendero_slug($conn, $nombre, $id);
    $imagenPrincipal = null;
    $imagenFlyer = null;
    $imagenCatalogo = null;

    if (!empty($_FILES['imagen_principal'])) {
        $imagenPrincipal = save_uploaded_image($_FILES['imagen_principal'], $slug, 'principal');
    }

    if (!empty($_FILES['imagen_flyer'])) {
        $imagenFlyer = save_uploaded_image($_FILES['imagen_flyer'], $slug, 'flyer');
    }

    if (!empty($_FILES['imagen_catalogo'])) {
        $imagenCatalogo = save_uploaded_image($_FILES['imagen_catalogo'], $slug, 'catalogo');
    }

    if ($id > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE senderos
             SET nombre = ?, slug = ?, fecha_sendero = ?, lugar = ?, provincia = ?, descripcion_corta = ?,
                 descripcion = ?, imagen_principal = COALESCE(?, imagen_principal),
                 imagen_flyer = COALESCE(?, imagen_flyer),
                 imagen_catalogo = COALESCE(?, imagen_catalogo), nivel_dificultad_id = ?,
                 tiempo_ida_vehiculo_min = ?, tiempo_regreso_vehiculo_min = ?, tipo_camino_vehiculo_id = ?,
                 tiempo_sendero_min = ?, distancia_km = ?, desnivel_mts = ?, cobertura_senal_pct = ?,
                 inversion_total = ?, fecha_limite_pago = ?,
                 estado = ?, activo = ?
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "ssssssssssiiiiidiidssii", $nombre, $slug, $fecha, $lugar, $provincia, $descripcionCorta, $descripcion, $imagenPrincipal, $imagenFlyer, $imagenCatalogo, $nivelId, $tiempoIdaVehiculo, $tiempoRegresoVehiculo, $tipoCaminoVehiculoId, $tiempoSendero, $distanciaKm, $desnivelMts, $coberturaSenal, $inversionTotal, $fechaLimitePago, $estado, $activo, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $senderoId = $id;
    } else {
        if (!$imagenPrincipal) {
            $imagenPrincipal = '';
        }
        if (!$imagenFlyer) {
            $imagenFlyer = '';
        }
        if (!$imagenCatalogo) {
            $imagenCatalogo = '';
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO senderos
             (nombre, slug, fecha_sendero, lugar, provincia, descripcion_corta, descripcion, imagen_principal, imagen_flyer, imagen_catalogo,
              nivel_dificultad_id, tiempo_ida_vehiculo_min, tiempo_regreso_vehiculo_min, tipo_camino_vehiculo_id,
              tiempo_sendero_min, distancia_km, desnivel_mts, cobertura_senal_pct, inversion_total, fecha_limite_pago, estado, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ssssssssssiiiiidiidssi", $nombre, $slug, $fecha, $lugar, $provincia, $descripcionCorta, $descripcion, $imagenPrincipal, $imagenFlyer, $imagenCatalogo, $nivelId, $tiempoIdaVehiculo, $tiempoRegresoVehiculo, $tipoCaminoVehiculoId, $tiempoSendero, $distanciaKm, $desnivelMts, $coberturaSenal, $inversionTotal, $fechaLimitePago, $estado, $activo);
        mysqli_stmt_execute($stmt);
        $senderoId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    mysqli_query($conn, "DELETE FROM sendero_tipos_terreno WHERE sendero_id = " . (int) $senderoId);
    foreach (array_unique($terrenos) as $terrenoId) {
        if ($terrenoId <= 0) {
            continue;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO sendero_tipos_terreno (sendero_id, tipo_terreno_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $senderoId, $terrenoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_query($conn, "DELETE FROM sendero_anotaciones WHERE sendero_id = " . (int) $senderoId);
    foreach (array_values(array_unique($anotaciones)) as $ordenAnotacion => $anotacionId) {
        if ($anotacionId <= 0) {
            continue;
        }
        $ordenItem = $ordenAnotacion + 1;
        $stmt = mysqli_prepare($conn, "INSERT INTO sendero_anotaciones (sendero_id, anotacion_id, orden) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iii", $senderoId, $anotacionId, $ordenItem);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $existingInvestmentIds = [];
    if ($id > 0) {
        $resExisting = mysqli_query($conn, "SELECT id FROM sendero_inversiones WHERE sendero_id = " . (int) $senderoId);
        if ($resExisting) {
            while ($row = mysqli_fetch_assoc($resExisting)) {
                $existingInvestmentIds[] = (int) $row['id'];
            }
        }
    }

    $savedInvestmentIds = [];
    $allIncluye = [];
    foreach ($inversiones as $inversion) {
        $investmentId = (int) $inversion['id'];
        if ($investmentId > 0 && in_array($investmentId, $existingInvestmentIds, true)) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE sendero_inversiones
                 SET nombre = ?, descripcion = ?, monto = ?, fecha_limite_pago = ?, orden = ?, activo = ?
                 WHERE id = ? AND sendero_id = ?"
            );
            mysqli_stmt_bind_param($stmt, "ssdsiiii", $inversion['nombre'], $inversion['descripcion'], $inversion['monto'], $inversion['fecha_limite_pago'], $inversion['orden'], $inversion['activo'], $investmentId, $senderoId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO sendero_inversiones (sendero_id, nombre, descripcion, monto, fecha_limite_pago, orden, activo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "issdsii", $senderoId, $inversion['nombre'], $inversion['descripcion'], $inversion['monto'], $inversion['fecha_limite_pago'], $inversion['orden'], $inversion['activo']);
            mysqli_stmt_execute($stmt);
            $investmentId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }

        $savedInvestmentIds[] = $investmentId;
        mysqli_query($conn, "DELETE FROM sendero_inversion_incluye WHERE inversion_id = " . (int) $investmentId);
        foreach ($inversion['incluye'] as $ordenIncluye => $incluyeId) {
            if ($incluyeId <= 0) {
                continue;
            }
            $allIncluye[] = $incluyeId;
            $ordenItem = $ordenIncluye + 1;
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO sendero_inversion_incluye (inversion_id, incluye_id, orden) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iii", $investmentId, $incluyeId, $ordenItem);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    foreach (array_diff($existingInvestmentIds, $savedInvestmentIds) as $disabledId) {
        mysqli_query($conn, "UPDATE sendero_inversiones SET activo = 0 WHERE id = " . (int) $disabledId . " AND sendero_id = " . (int) $senderoId);
    }

    mysqli_query($conn, "DELETE FROM sendero_elementos_incluidos WHERE sendero_id = " . (int) $senderoId);
    foreach (array_unique($allIncluye) as $incluyeId) {
        if ($incluyeId <= 0) {
            continue;
        }
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO sendero_elementos_incluidos (sendero_id, incluye_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $senderoId, $incluyeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_query($conn, "DELETE FROM sendero_puntos_encuentro WHERE sendero_id = " . (int) $senderoId);
    $puntos = $_POST['puntos'] ?? [];
    $orden = 1;
    foreach ($puntos as $punto) {
        $puntoEncuentroId = (int) ($punto['punto_encuentro_id'] ?? 0);
        $nombrePunto = trim($punto['nombre_punto'] ?? '');
        $direccion = trim($punto['direccion_referencia'] ?? '');
        $horaEncuentro = trim($punto['hora_encuentro'] ?? '');
        $horaSalida = trim($punto['hora_salida'] ?? '');
        $urlMapa = trim($punto['url_mapa'] ?? '');

        if ($puntoEncuentroId > 0) {
            $stmt = mysqli_prepare($conn, "SELECT nombre, direccion_referencia, url_mapa FROM puntos_encuentro WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $puntoEncuentroId);
            mysqli_stmt_execute($stmt);
            $resPuntoCatalogo = mysqli_stmt_get_result($stmt);
            $catalogoPunto = mysqli_fetch_assoc($resPuntoCatalogo);
            mysqli_stmt_close($stmt);

            if ($catalogoPunto) {
                $nombrePunto = trim((string) $catalogoPunto['nombre']);
                $direccion = trim((string) ($catalogoPunto['direccion_referencia'] ?? ''));
                $urlMapa = trim((string) ($catalogoPunto['url_mapa'] ?? ''));
            } else {
                $puntoEncuentroId = 0;
            }
        }

        if ($nombrePunto === '' || $horaEncuentro === '' || $horaSalida === '') {
            continue;
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO sendero_puntos_encuentro
             (sendero_id, punto_encuentro_id, nombre_punto, direccion_referencia, hora_encuentro, hora_salida, url_mapa, orden, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $puntoEncuentroId = $puntoEncuentroId > 0 ? $puntoEncuentroId : null;
        mysqli_stmt_bind_param($stmt, "iisssssi", $senderoId, $puntoEncuentroId, $nombrePunto, $direccion, $horaEncuentro, $horaSalida, $urlMapa, $orden);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $orden++;
    }

    $galleryFiles = uploaded_files_array('galeria');
    $galleryOrder = 1;
    $resOrder = mysqli_query($conn, "SELECT COALESCE(MAX(orden), 0) + 1 FROM sendero_imagenes WHERE sendero_id = " . (int) $senderoId);
    if ($resOrder) {
        $galleryOrder = (int) mysqli_fetch_row($resOrder)[0];
    }

    foreach ($galleryFiles as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $rutaGaleria = save_uploaded_image($file, $slug, 'galeria');
        if (!$rutaGaleria) {
            continue;
        }

        $titulo = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO sendero_imagenes (sendero_id, ruta_imagen, titulo, orden, activo)
             VALUES (?, ?, ?, ?, 1)"
        );
        mysqli_stmt_bind_param($stmt, "issi", $senderoId, $rutaGaleria, $titulo, $galleryOrder);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $galleryOrder++;
    }

    mysqli_commit($conn);

    $_SESSION['senderos_success'] = $id > 0 ? "Sendero actualizado correctamente." : "Sendero creado correctamente.";
    redirect_senderos($conn, $senderoId);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['senderos_error'] = "Error: " . $e->getMessage();
    redirect_senderos($conn, $id);
}
