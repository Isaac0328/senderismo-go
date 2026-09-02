<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

$PERMISO_REQUERIDO = 'usuarios.usuarios';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['usuarios_error'] = "Método no permitido.";
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_usuarios.php");
    exit;
}

csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_usuarios.php", 'usuarios_error');

require_once __DIR__ . '/../bd/conexion.php';

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

function redirect_users(mysqli $conn): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_usuarios.php");
    exit;
}

function clean_user_text(string $value, int $max = 255): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return substr($value, 0, $max);
}

function only_user_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function guardar_detalle_usuario(mysqli $conn, int $usuarioId): void
{
    if ($usuarioId <= 0) {
        return;
    }

    $telefonoRaw = trim((string) ($_POST['telefono'] ?? ''));
    $telefono = only_user_digits($telefonoRaw);
    $rangoEdad = clean_user_text((string) ($_POST['rango_edad'] ?? ''), 20);
    $identificacion = clean_user_text((string) ($_POST['identificacion'] ?? ''), 50);
    $esAlergico = (int) ($_POST['es_alergico'] ?? 0);
    $alergiasDetalle = clean_user_text((string) ($_POST['alergias_detalle'] ?? ''));
    $grupoSanguineo = clean_user_text((string) ($_POST['grupo_sanguineo'] ?? ''), 5);
    $enfermedad = clean_user_text((string) ($_POST['enfermedad'] ?? ''));
    $seguroMedico = clean_user_text((string) ($_POST['seguro_medico'] ?? ''));
    $experiencia = clean_user_text((string) ($_POST['experiencia_senderismo'] ?? ''), 80);
    $viaEntero = clean_user_text((string) ($_POST['via_entero'] ?? ''), 80);
    $referidoNombre = clean_user_text((string) ($_POST['referido_nombre'] ?? ''), 150);
    $emergenciaNombre = clean_user_text((string) ($_POST['emergencia_nombre'] ?? ''), 150);
    $emergenciaParentesco = clean_user_text((string) ($_POST['emergencia_parentesco'] ?? ''), 80);
    $emergenciaTelefonoRaw = trim((string) ($_POST['emergencia_telefono'] ?? ''));
    $emergenciaTelefono = only_user_digits($emergenciaTelefonoRaw);

    $errores = [];
    if ($telefonoRaw !== '' && !sg_is_digits_between($telefonoRaw, 10, 15)) {
        $errores[] = "El telefono debe contener solo numeros, entre 10 y 15 digitos.";
    }
    if ($referidoNombre !== '' && sg_contains_digits($referidoNombre)) {
        $errores[] = "El nombre de referido no puede contener numeros.";
    }
    if ($emergenciaNombre !== '' && sg_contains_digits($emergenciaNombre)) {
        $errores[] = "El nombre de emergencia no puede contener numeros.";
    }
    if ($emergenciaTelefonoRaw !== '' && !sg_is_digits_between($emergenciaTelefonoRaw, 10, 15)) {
        $errores[] = "El telefono de emergencia debe contener solo numeros, entre 10 y 15 digitos.";
    }
    if (!empty($errores)) {
        $_SESSION['usuarios_error'] = implode(' ', $errores);
        redirect_users($conn);
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO detalles_usuarios (
            usuario_id, telefono, rango_edad, identificacion, es_alergico, alergias_detalle,
            grupo_sanguineo, enfermedad, seguro_medico, experiencia_senderismo, via_entero,
            referido_nombre, emergencia_nombre, emergencia_parentesco, emergencia_telefono
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            telefono = VALUES(telefono),
            rango_edad = VALUES(rango_edad),
            identificacion = VALUES(identificacion),
            es_alergico = VALUES(es_alergico),
            alergias_detalle = VALUES(alergias_detalle),
            grupo_sanguineo = VALUES(grupo_sanguineo),
            enfermedad = VALUES(enfermedad),
            seguro_medico = VALUES(seguro_medico),
            experiencia_senderismo = VALUES(experiencia_senderismo),
            via_entero = VALUES(via_entero),
            referido_nombre = VALUES(referido_nombre),
            emergencia_nombre = VALUES(emergencia_nombre),
            emergencia_parentesco = VALUES(emergencia_parentesco),
            emergencia_telefono = VALUES(emergencia_telefono)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "isssissssssssss",
        $usuarioId,
        $telefono,
        $rangoEdad,
        $identificacion,
        $esAlergico,
        $alergiasDetalle,
        $grupoSanguineo,
        $enfermedad,
        $seguroMedico,
        $experiencia,
        $viaEntero,
        $referidoNombre,
        $emergenciaNombre,
        $emergenciaParentesco,
        $emergenciaTelefono
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function crear_tabla_menores_usuario_admin(mysqli $conn): void
{
        // La estructura de menores_usuarios se gestiona desde scripts_bd/migracion_estructura_configuracion_2026_06_17.sql.
}

function obtener_menores_usuario_admin(): array
{
    $items = $_POST['menores_usuario'] ?? [];
    if (!is_array($items)) {
        return [];
    }

    $menores = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $nombre = clean_user_text((string) ($item['nombre'] ?? ''), 100);
        $apellido = clean_user_text((string) ($item['apellido'] ?? ''), 100);
        if ($nombre === '' && $apellido === '') {
            continue;
        }
        $telefonoRaw = trim((string) ($item['telefono'] ?? ''));
        $emergenciaTelefonoRaw = trim((string) ($item['emergencia_telefono'] ?? ''));

        $menores[] = [
            'menor_usuario_id' => (int) ($item['menor_usuario_id'] ?? 0),
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono_raw' => $telefonoRaw,
            'telefono' => only_user_digits($telefonoRaw),
            'rango_edad' => clean_user_text((string) ($item['rango_edad'] ?? ''), 20),
            'es_alergico' => (string) ($item['es_alergico'] ?? '0') === '1' ? 1 : 0,
            'alergias_detalle' => clean_user_text((string) ($item['alergias_detalle'] ?? ''), 255),
            'grupo_sanguineo' => clean_user_text((string) ($item['grupo_sanguineo'] ?? ''), 10),
            'enfermedad' => clean_user_text((string) ($item['enfermedad'] ?? ''), 255),
            'seguro_medico' => clean_user_text((string) ($item['seguro_medico'] ?? ''), 255),
            'experiencia_senderismo' => clean_user_text((string) ($item['experiencia_senderismo'] ?? ''), 80),
            'emergencia_nombre' => clean_user_text((string) ($item['emergencia_nombre'] ?? ''), 150),
            'emergencia_parentesco' => clean_user_text((string) ($item['emergencia_parentesco'] ?? ''), 80),
            'emergencia_telefono_raw' => $emergenciaTelefonoRaw,
            'emergencia_telefono' => only_user_digits($emergenciaTelefonoRaw),
            'activo' => (string) ($item['activo'] ?? '1') === '1' ? 1 : 0,
        ];
    }

    return $menores;
}

function sincronizar_menores_usuario_admin(mysqli $conn, int $usuarioId): void
{
    if ($usuarioId <= 0 || (string) ($_POST['sync_menores'] ?? '') !== '1') {
        return;
    }

    crear_tabla_menores_usuario_admin($conn);
    $menores = obtener_menores_usuario_admin();
    $idsRecibidos = [];

    foreach ($menores as $menor) {
        $erroresMenor = [];
        if (sg_contains_digits($menor['nombre']) || sg_contains_digits($menor['apellido'])) {
            $erroresMenor[] = "Los nombres y apellidos de menores no pueden contener numeros.";
        }
        if ($menor['telefono_raw'] !== '' && !sg_is_digits_between($menor['telefono_raw'], 10, 15)) {
            $erroresMenor[] = "El telefono de menores debe contener solo numeros, entre 10 y 15 digitos.";
        }
        if ($menor['emergencia_nombre'] !== '' && sg_contains_digits($menor['emergencia_nombre'])) {
            $erroresMenor[] = "El nombre de emergencia de menores no puede contener numeros.";
        }
        if ($menor['emergencia_telefono_raw'] !== '' && !sg_is_digits_between($menor['emergencia_telefono_raw'], 10, 15)) {
            $erroresMenor[] = "El telefono de emergencia de menores debe contener solo numeros, entre 10 y 15 digitos.";
        }
        if (!empty($erroresMenor)) {
            $_SESSION['usuarios_error'] = implode(' ', $erroresMenor);
            redirect_users($conn);
        }

        $menorId = (int) $menor['menor_usuario_id'];
        $nombre = $menor['nombre'];
        $apellido = $menor['apellido'];
        $telefono = $menor['telefono'];
        $rangoEdad = $menor['rango_edad'];
        $esAlergico = (int) $menor['es_alergico'];
        $alergiasDetalle = $menor['alergias_detalle'];
        $grupoSanguineo = $menor['grupo_sanguineo'];
        $enfermedad = $menor['enfermedad'];
        $seguroMedico = $menor['seguro_medico'];
        $experiencia = $menor['experiencia_senderismo'];
        $emergenciaNombre = $menor['emergencia_nombre'];
        $emergenciaParentesco = $menor['emergencia_parentesco'];
        $emergenciaTelefono = $menor['emergencia_telefono'];
        $activo = (int) $menor['activo'];

        if ($menorId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE menores_usuarios
                 SET nombre=?, apellido=?, telefono=?, rango_edad=?, es_alergico=?, alergias_detalle=?,
                     grupo_sanguineo=?, enfermedad=?, seguro_medico=?, experiencia_senderismo=?,
                     emergencia_nombre=?, emergencia_parentesco=?, emergencia_telefono=?, activo=?
                 WHERE id=? AND usuario_id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssssissssssssiii",
                $nombre,
                $apellido,
                $telefono,
                $rangoEdad,
                $esAlergico,
                $alergiasDetalle,
                $grupoSanguineo,
                $enfermedad,
                $seguroMedico,
                $experiencia,
                $emergenciaNombre,
                $emergenciaParentesco,
                $emergenciaTelefono,
                $activo,
                $menorId,
                $usuarioId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $idsRecibidos[] = $menorId;
            continue;
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO menores_usuarios (
                usuario_id, nombre, apellido, telefono, rango_edad, es_alergico, alergias_detalle,
                grupo_sanguineo, enfermedad, seguro_medico, experiencia_senderismo,
                emergencia_nombre, emergencia_parentesco, emergencia_telefono, activo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "issssissssssssi",
            $usuarioId,
            $nombre,
            $apellido,
            $telefono,
            $rangoEdad,
            $esAlergico,
            $alergiasDetalle,
            $grupoSanguineo,
            $enfermedad,
            $seguroMedico,
            $experiencia,
            $emergenciaNombre,
            $emergenciaParentesco,
            $emergenciaTelefono,
            $activo
        );
        mysqli_stmt_execute($stmt);
        $idsRecibidos[] = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    if (!empty($idsRecibidos)) {
        $ids = implode(',', array_map('intval', $idsRecibidos));
        mysqli_query($conn, "UPDATE menores_usuarios SET activo = 0 WHERE usuario_id = {$usuarioId} AND id NOT IN ({$ids})");
    } else {
        mysqli_query($conn, "UPDATE menores_usuarios SET activo = 0 WHERE usuario_id = {$usuarioId}");
    }
}

try {

    // =========================
    // GUARDAR / ACTUALIZAR
    // =========================
    if ($action === 'save') {

        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol_id = (int) ($_POST['rol_id'] ?? 0);

        if (sg_contains_digits($nombre) || sg_contains_digits($apellido)) {
            $_SESSION['usuarios_error'] = "El nombre y apellido no pueden contener numeros.";
            redirect_users($conn);
        }

        $passwordPlain = (string) ($_POST['password'] ?? '');
        $passwordPlain = trim($passwordPlain);

        // Si es creación (id=0) password obligatoria
        if ($id === 0 && $passwordPlain === '') {
            $_SESSION['usuarios_error'] = "La contraseña es obligatoria para crear el usuario.";
            redirect_users($conn);
        }

        // Hash: solo si viene password
        $passwordHash = '';
        if ($passwordPlain !== '') {
            $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                $_SESSION['usuarios_error'] = "No se pudo generar el hash de la contraseña.";
                redirect_users($conn);
            }
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        $stmt = mysqli_prepare(
            $conn,
            "CALL sp_usuarios_guardar(?, ?, ?, ?, ?, ?, ?, @p_mensaje, @p_codigo)"
        );

        if (!$stmt) {
            $_SESSION['usuarios_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_users($conn);
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssssi",
            $id,
            $nombre,
            $apellido,
            $user,
            $email,
            $passwordHash,
            $rol_id
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }

        $res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
        $data = $res ? mysqli_fetch_assoc($res) : null;

        $codigo = (int) ($data['codigo'] ?? 99);
        $mensaje = (string) ($data['mensaje'] ?? 'Error desconocido');

        if ($codigo === 0) {
            $detalleUsuarioId = $id;
            if ($detalleUsuarioId <= 0) {
                $stmtFind = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE user = ? OR email = ? ORDER BY id DESC LIMIT 1");
                mysqli_stmt_bind_param($stmtFind, "ss", $user, $email);
                mysqli_stmt_execute($stmtFind);
                $usuarioCreado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFind));
                mysqli_stmt_close($stmtFind);
                $detalleUsuarioId = (int) ($usuarioCreado['id'] ?? 0);
            }
            guardar_detalle_usuario($conn, $detalleUsuarioId);
            sincronizar_menores_usuario_admin($conn, $detalleUsuarioId);
            $_SESSION['usuarios_success'] = $mensaje;
        } else {
            $_SESSION['usuarios_error'] = $mensaje;
        }

        redirect_users($conn);
    }

    // =========================
    // ACTIVAR / INACTIVAR
    // =========================
    if ($action === 'toggle_estado') {

        $estado = (int) ($_POST['estado'] ?? -1);

        // Evitar que el admin se inhabilite a sí mismo (opcional pero recomendado)
        if ($id === (int) ($_SESSION['usuario_id'] ?? 0) && $estado === 0) {
            $_SESSION['usuarios_error'] = "No puedes inactivarte a ti mismo.";
            redirect_users($conn);
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        $stmt = mysqli_prepare($conn, "CALL sp_usuarios_cambiar_estado(?, ?, @p_mensaje, @p_codigo)");
        if (!$stmt) {
            $_SESSION['usuarios_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_users($conn);
        }

        mysqli_stmt_bind_param($stmt, "ii", $id, $estado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }

        $res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
        $data = $res ? mysqli_fetch_assoc($res) : null;

        $codigo = (int) ($data['codigo'] ?? 99);
        $mensaje = (string) ($data['mensaje'] ?? 'Error desconocido');

        if ($codigo === 0) {
            $_SESSION['usuarios_success'] = $mensaje;
        } else {
            $_SESSION['usuarios_error'] = $mensaje;
        }

        redirect_users($conn);
    }

    // =========================
    // ELIMINAR
    // =========================
    if ($action === 'delete') {

        // Evitar borrar tu propio usuario (recomendado)
        if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
            $_SESSION['usuarios_error'] = "No puedes eliminar tu propio usuario.";
            redirect_users($conn);
        }

        mysqli_query($conn, "SET @p_mensaje = ''");
        mysqli_query($conn, "SET @p_codigo = 0");

        $stmt = mysqli_prepare($conn, "CALL sp_usuarios_eliminar(?, @p_mensaje, @p_codigo)");
        if (!$stmt) {
            $_SESSION['usuarios_error'] = "Error preparando consulta: " . mysqli_error($conn);
            redirect_users($conn);
        }

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        while (mysqli_more_results($conn)) {
            mysqli_next_result($conn);
        }

        $res = mysqli_query($conn, "SELECT @p_mensaje AS mensaje, @p_codigo AS codigo");
        $data = $res ? mysqli_fetch_assoc($res) : null;

        $codigo = (int) ($data['codigo'] ?? 99);
        $mensaje = (string) ($data['mensaje'] ?? 'Error desconocido');

        if ($codigo === 0) {
            $_SESSION['usuarios_success'] = $mensaje;
        } else {
            $_SESSION['usuarios_error'] = $mensaje;
        }

        redirect_users($conn);
    }

    // Acción desconocida
    $_SESSION['usuarios_error'] = "Acción no válida.";
    redirect_users($conn);

} catch (Throwable $e) {
    $_SESSION['usuarios_error'] = "Error: " . $e->getMessage();
    redirect_users($conn);
}

