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
    header("Location: " . BASE_URL . "mantenimientos/mantenimiento_usuarios_senderos.php");
    exit;
}
csrf_validate_post(BASE_URL . "mantenimientos/mantenimiento_usuarios_senderos.php", 'usuarios_senderos_error');

require_once __DIR__ . '/../bd/conexion.php';

$registroId = (int) ($_POST['registro_id'] ?? 0);
$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$accion = trim((string) ($_POST['accion'] ?? ''));

function usuarios_senderos_text(string $value, int $max = 255): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return substr($value, 0, $max);
}

function usuarios_senderos_digits(string $value): string
{
    return substr(preg_replace('/\D+/', '', $value), 0, 20);
}

function usuarios_senderos_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    $volverA = (string) ($_POST['volver_a'] ?? '');
    if ($volverA === 'asistencia') {
        if (!empty($_SESSION['usuarios_senderos_success'])) {
            $_SESSION['asistencia_success'] = $_SESSION['usuarios_senderos_success'];
            unset($_SESSION['usuarios_senderos_success']);
        }
        if (!empty($_SESSION['usuarios_senderos_error'])) {
            $_SESSION['asistencia_error'] = $_SESSION['usuarios_senderos_error'];
            unset($_SESSION['usuarios_senderos_error']);
        }
    }

    $url = $volverA === 'asistencia'
        ? BASE_URL . "mantenimientos/mantenimiento_asistencia_senderos.php"
        : BASE_URL . "mantenimientos/mantenimiento_usuarios_senderos.php";
    if ($senderoId > 0) {
        $url .= "?sendero_id=" . $senderoId;
    }
    header("Location: " . $url);
    exit;
}

function usuarios_senderos_participante_rol(mysqli $conn): int
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id
        FROM roles
        WHERE LOWER(nombre) IN ('usuario', 'invitado')
        ORDER BY CASE LOWER(nombre) WHEN 'usuario' THEN 1 WHEN 'invitado' THEN 2 ELSE 3 END
        LIMIT 1"
    );
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!empty($row['id'])) {
        return (int) $row['id'];
    }

    $res = mysqli_query($conn, "SELECT id FROM roles ORDER BY id ASC LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }

    if (!empty($row['id'])) {
        return (int) $row['id'];
    }

    throw new RuntimeException('No hay roles disponibles para crear el participante.');
}

function usuarios_senderos_unique_value(mysqli $conn, string $table, string $field, string $base, int $maxLength): string
{
    $base = strtolower(preg_replace('/[^a-z0-9._-]+/i', '', $base));
    if ($base === '') {
        $base = 'participante';
    }
    $base = substr($base, 0, max(8, $maxLength - 8));
    $value = $base;
    $counter = 1;

    while (true) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM {$table} WHERE {$field} = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $value);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$exists) {
            return substr($value, 0, $maxLength);
        }

        $suffix = (string) $counter++;
        $value = substr($base, 0, $maxLength - strlen($suffix) - 1) . '_' . $suffix;
    }
}

function usuarios_senderos_detalle_id(mysqli $conn, int $usuarioId, string $telefono = ''): int
{
    $stmt = mysqli_prepare($conn, "SELECT id FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $usuarioId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!empty($row['id'])) {
        if ($telefono !== '') {
            $stmt = mysqli_prepare($conn, "UPDATE detalles_usuarios SET telefono = ? WHERE id = ?");
            $detalleId = (int) $row['id'];
            mysqli_stmt_bind_param($stmt, 'si', $telefono, $detalleId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        return (int) $row['id'];
    }

    $vacio = '';
    $esAlergico = 0;
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO detalles_usuarios (
            usuario_id, telefono, rango_edad, identificacion, es_alergico, alergias_detalle,
            grupo_sanguineo, enfermedad, seguro_medico, experiencia_senderismo, via_entero,
            referido_nombre, emergencia_nombre, emergencia_parentesco, emergencia_telefono
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'isssissssssssss',
        $usuarioId,
        $telefono,
        $vacio,
        $vacio,
        $esAlergico,
        $vacio,
        $vacio,
        $vacio,
        $vacio,
        $vacio,
        $vacio,
        $vacio,
        $vacio,
        $vacio,
        $vacio
    );
    mysqli_stmt_execute($stmt);
    $detalleId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $detalleId;
}

function usuarios_senderos_validar_inversion(mysqli $conn, int $senderoId, int $inversionId): bool
{
    if ($senderoId <= 0 || $inversionId <= 0) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT id FROM sendero_inversiones WHERE id = ? AND sendero_id = ? AND activo = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $inversionId, $senderoId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return !empty($row['id']);
}

function usuarios_senderos_fecha_asistencia(mysqli $conn, int $senderoId): string
{
    $stmt = mysqli_prepare($conn, "SELECT fecha_sendero FROM senderos WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $senderoId);
    mysqli_stmt_execute($stmt);
    $senderoRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $fechaSendero = (string) ($senderoRow['fecha_sendero'] ?? '');
    return $fechaSendero !== '' ? $fechaSendero . ' 00:00:00' : date('Y-m-d H:i:s');
}

function usuarios_senderos_crear_usuario_basico(mysqli $conn): int
{
    $nombre = usuarios_senderos_text((string) ($_POST['nuevo_nombre'] ?? ''), 100);
    $apellido = usuarios_senderos_text((string) ($_POST['nuevo_apellido'] ?? ''), 100);
    $user = usuarios_senderos_text((string) ($_POST['nuevo_user'] ?? ''), 50);
    $email = usuarios_senderos_text((string) ($_POST['nuevo_email'] ?? ''), 100);

    if ($nombre === '' || $apellido === '') {
        throw new RuntimeException('Completa el nombre y apellido del nuevo participante.');
    }

    if ($user === '') {
        $user = usuarios_senderos_unique_value($conn, 'usuarios', 'user', $nombre . '.' . $apellido, 50);
    } else {
        $user = substr(strtolower(preg_replace('/[^a-z0-9._-]+/i', '', $user)), 0, 50);
        if ($user === '') {
            throw new RuntimeException('El nombre de usuario no es valido.');
        }

        $stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE user = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $user);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            throw new RuntimeException('Ya existe un usuario con ese nombre de usuario.');
        }
    }

    if ($email === '') {
        $emailBase = substr($user, 0, 70);
        $email = $emailBase . '@senderismogo.local';
        $counter = 1;
        while (true) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$exists) {
                break;
            }

            $email = substr($emailBase, 0, 65) . '_' . $counter++ . '@senderismogo.local';
        }
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El email del nuevo participante no es valido.');
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            throw new RuntimeException('Ya existe un usuario con ese email.');
        }
    }

    $rolId = usuarios_senderos_participante_rol($conn);
    $passwordHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO usuarios (nombre, apellido, user, email, password, rol_id, estado)
        VALUES (?, ?, ?, ?, ?, ?, 1)"
    );
    mysqli_stmt_bind_param($stmt, 'sssssi', $nombre, $apellido, $user, $email, $passwordHash, $rolId);
    mysqli_stmt_execute($stmt);
    $usuarioId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $usuarioId;
}

if ($accion === 'agregar_participante') {
    if ($senderoId <= 0) {
        $_SESSION['usuarios_senderos_error'] = "Selecciona un sendero valido.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    $tipoParticipante = (string) ($_POST['tipo_participante'] ?? 'existente');
    $inversionId = (int) ($_POST['inversion_id'] ?? 0);
    $marcarAsistio = !empty($_POST['marcar_asistio']) ? 1 : 0;
    $telefonoNuevo = usuarios_senderos_digits((string) ($_POST['nuevo_telefono'] ?? ''));

    if (!usuarios_senderos_validar_inversion($conn, $senderoId, $inversionId)) {
        $_SESSION['usuarios_senderos_error'] = "Selecciona una inversion valida para este sendero.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    $fechaAsistencia = usuarios_senderos_fecha_asistencia($conn, $senderoId);

    mysqli_begin_transaction($conn);

    try {
        $manualNombre = null;
        $manualApellido = null;
        $manualTelefono = null;
        $manualEmail = null;

        if ($tipoParticipante === 'nuevo') {
            $manualNombre = usuarios_senderos_text((string) ($_POST['nuevo_nombre'] ?? ''), 100);
            $manualApellido = usuarios_senderos_text((string) ($_POST['nuevo_apellido'] ?? ''), 100);
            $manualEmail = usuarios_senderos_text((string) ($_POST['nuevo_email'] ?? ''), 100);

            if ($manualNombre === '' || $manualApellido === '') {
                throw new RuntimeException('Completa el nombre y apellido del asistente temporal.');
            }
            if ($manualEmail !== '' && !filter_var($manualEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El email del asistente temporal no es valido.');
            }

            $usuarioId = null;
            $detalleId = null;
            $manualTelefono = $telefonoNuevo;
        } else {
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            if ($usuarioId <= 0) {
                throw new RuntimeException('Selecciona el usuario existente que asistio.');
            }

            $stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE id = ? AND estado = 1 LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $usuarioId);
            mysqli_stmt_execute($stmt);
            $usuarioExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$usuarioExiste) {
                throw new RuntimeException('El usuario seleccionado no existe o esta inactivo.');
            }

            $detalleId = usuarios_senderos_detalle_id($conn, $usuarioId);
        }

        $registroActual = null;
        if ($usuarioId !== null) {
            $stmt = mysqli_prepare($conn, "SELECT id, estado FROM registros_senderos WHERE usuario_id = ? AND sendero_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ii', $usuarioId, $senderoId);
            mysqli_stmt_execute($stmt);
            $registroActual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
        }

        $consentimientoTexto = "Registro administrativo de participante que asistio al sendero sin completar la inscripcion publica.";
        $rgpdTexto = "Registro creado por administracion para control operativo y contable.";
        $adminId = (int) ($_SESSION['usuario_id'] ?? 0);
        $notaAsistencia = "Agregado desde Usuarios por sendero";

        if ($registroActual) {
            if ($registroActual['estado'] === 'registrado') {
                throw new RuntimeException('Este usuario ya esta activo en el sendero.');
            }

            $registroActualId = (int) $registroActual['id'];
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE registros_senderos
                SET detalle_usuario_id = ?,
                    inversion_id = ?,
                    estado = 'registrado',
                    consentimiento_aceptado = 1,
                    rgpd_aceptado = 1,
                    consentimiento_texto = ?,
                    rgpd_texto = ?,
                    registro_origen = 'admin_manual',
                    manual_nombre = NULL,
                    manual_apellido = NULL,
                    manual_telefono = NULL,
                    manual_email = NULL,
                    asistio = ?,
                    fecha_asistencia = CASE WHEN ? = 1 THEN ? ELSE NULL END,
                    asistencia_marcada_por = CASE WHEN ? = 1 THEN ? ELSE NULL END,
                    asistencia_notas = CASE WHEN ? = 1 THEN ? ELSE NULL END
                WHERE id = ? AND sendero_id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'iissiisiiisii',
                $detalleId,
                $inversionId,
                $consentimientoTexto,
                $rgpdTexto,
                $marcarAsistio,
                $marcarAsistio,
                $fechaAsistencia,
                $marcarAsistio,
                $adminId,
                $marcarAsistio,
                $notaAsistencia,
                $registroActualId,
                $senderoId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO registros_senderos (
                    sendero_id, usuario_id, detalle_usuario_id, manual_nombre, manual_apellido, manual_telefono, manual_email, inversion_id, estado, registro_origen, asistio,
                    fecha_asistencia, asistencia_marcada_por, asistencia_notas,
                    consentimiento_aceptado, rgpd_aceptado, consentimiento_texto, rgpd_texto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'registrado', 'admin_manual', ?, CASE WHEN ? = 1 THEN ? ELSE NULL END, CASE WHEN ? = 1 THEN ? ELSE NULL END, CASE WHEN ? = 1 THEN ? ELSE NULL END, 1, 1, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'iiissssiiisiiisss',
                $senderoId,
                $usuarioId,
                $detalleId,
                $manualNombre,
                $manualApellido,
                $manualTelefono,
                $manualEmail,
                $inversionId,
                $marcarAsistio,
                $marcarAsistio,
                $fechaAsistencia,
                $marcarAsistio,
                $adminId,
                $marcarAsistio,
                $notaAsistencia,
                $consentimientoTexto,
                $rgpdTexto
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);
        $_SESSION['usuarios_senderos_success'] = "Participante agregado al sendero correctamente.";
        usuarios_senderos_redirect($conn, $senderoId);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $_SESSION['usuarios_senderos_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo agregar el participante.";
        usuarios_senderos_redirect($conn, $senderoId);
    }
}

if ($registroId <= 0 || $senderoId <= 0) {
    $_SESSION['usuarios_senderos_error'] = "Registro no valido.";
    usuarios_senderos_redirect($conn, $senderoId);
}

$stmt = mysqli_prepare($conn, "SELECT id, sendero_id, estado FROM registros_senderos WHERE id = ? AND sendero_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
mysqli_stmt_execute($stmt);
$registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$registro) {
    $_SESSION['usuarios_senderos_error'] = "No se encontro el registro seleccionado.";
    usuarios_senderos_redirect($conn, $senderoId);
}

try {
    if ($accion === 'editar_manual') {
        $stmt = mysqli_prepare($conn, "SELECT id, registro_origen FROM registros_senderos WHERE id = ? AND sendero_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        $manual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (($manual['registro_origen'] ?? '') !== 'admin_manual') {
            $_SESSION['usuarios_senderos_error'] = "Solo puedes editar asistentes agregados manualmente.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $inversionId = (int) ($_POST['inversion_id'] ?? 0);
        $marcarAsistio = !empty($_POST['marcar_asistio']) ? 1 : 0;
        $manualNombre = usuarios_senderos_text((string) ($_POST['manual_nombre'] ?? ''), 100);
        $manualApellido = usuarios_senderos_text((string) ($_POST['manual_apellido'] ?? ''), 100);
        $manualTelefono = usuarios_senderos_digits((string) ($_POST['manual_telefono'] ?? ''));
        $manualEmail = usuarios_senderos_text((string) ($_POST['manual_email'] ?? ''), 100);
        $nota = usuarios_senderos_text((string) ($_POST['asistencia_notas'] ?? ''), 255);
        $adminId = (int) ($_SESSION['usuario_id'] ?? 0);
        $fechaAsistencia = usuarios_senderos_fecha_asistencia($conn, $senderoId);

        if (!usuarios_senderos_validar_inversion($conn, $senderoId, $inversionId)) {
            $_SESSION['usuarios_senderos_error'] = "Selecciona una inversion valida para este sendero.";
            usuarios_senderos_redirect($conn, $senderoId);
        }
        if ($manualEmail !== '' && !filter_var($manualEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['usuarios_senderos_error'] = "El email del asistente temporal no es valido.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE registros_senderos
             SET inversion_id = ?,
                 manual_nombre = NULLIF(?, ''),
                 manual_apellido = NULLIF(?, ''),
                 manual_telefono = NULLIF(?, ''),
                 manual_email = NULLIF(?, ''),
                 asistio = ?,
                 fecha_asistencia = CASE WHEN ? = 1 THEN ? ELSE NULL END,
                 asistencia_marcada_por = CASE WHEN ? = 1 THEN ? ELSE NULL END,
                 asistencia_notas = ?
             WHERE id = ? AND sendero_id = ? AND registro_origen = 'admin_manual'"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'issssiisiisii',
            $inversionId,
            $manualNombre,
            $manualApellido,
            $manualTelefono,
            $manualEmail,
            $marcarAsistio,
            $marcarAsistio,
            $fechaAsistencia,
            $marcarAsistio,
            $adminId,
            $nota,
            $registroId,
            $senderoId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['usuarios_senderos_success'] = "Asistente manual actualizado.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    if ($accion === 'eliminar_manual') {
        $stmt = mysqli_prepare($conn, "DELETE FROM registros_senderos WHERE id = ? AND sendero_id = ? AND registro_origen = 'admin_manual'");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        $eliminados = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($eliminados <= 0) {
            $_SESSION['usuarios_senderos_error'] = "Solo puedes eliminar asistentes agregados manualmente.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $_SESSION['usuarios_senderos_success'] = "Asistente manual eliminado.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    if ($accion === 'cancelar') {
        if ($registro['estado'] === 'cancelado') {
            $_SESSION['usuarios_senderos_error'] = "Este registro ya esta inactivo.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $stmt = mysqli_prepare($conn, "UPDATE registros_senderos SET estado = 'cancelado' WHERE id = ? AND sendero_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['usuarios_senderos_success'] = "Usuario inactivado de este sendero.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    if ($accion === 'reactivar') {
        if ($registro['estado'] === 'registrado') {
            $_SESSION['usuarios_senderos_error'] = "Este registro ya esta activo.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $stmt = mysqli_prepare($conn, "UPDATE registros_senderos SET estado = 'registrado' WHERE id = ? AND sendero_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['usuarios_senderos_success'] = "Usuario reactivado en este sendero.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    if ($accion === 'eliminar') {
        if ($registro['estado'] !== 'cancelado') {
            $_SESSION['usuarios_senderos_error'] = "Primero debes inactivar el registro antes de eliminarlo.";
            usuarios_senderos_redirect($conn, $senderoId);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM registros_senderos WHERE id = ? AND sendero_id = ? AND estado = 'cancelado'");
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['usuarios_senderos_success'] = "Registro eliminado permanentemente.";
        usuarios_senderos_redirect($conn, $senderoId);
    }

    $_SESSION['usuarios_senderos_error'] = "Accion no valida.";
    usuarios_senderos_redirect($conn, $senderoId);
} catch (Throwable $e) {
    $_SESSION['usuarios_senderos_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo procesar la accion.";
    usuarios_senderos_redirect($conn, $senderoId);
}
