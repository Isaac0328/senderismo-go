<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $_SESSION['error_message'] = "Inicia sesion para registrarte en este sendero.";
    header("Location: " . BASE_URL . "pantallas/inicio_sesion.php");
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: " . BASE_URL . "pantallas/senderos.php");
    exit;
}
csrf_validate_post(BASE_URL . "pantallas/senderos.php", 'registro_sendero_error');

require_once __DIR__ . '/../bd/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$senderoId = (int) ($_POST['sendero_id'] ?? 0);
$inversionId = (int) ($_POST['inversion_id'] ?? 0);

function registro_redirect(mysqli $conn, int $senderoId): void
{
    mysqli_close($conn);
    header("Location: " . BASE_URL . "pantallas/registro_sendero.php?id=" . $senderoId);
    exit;
}

function clean_text(string $value, int $max = 255): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return substr((string) $value, 0, $max);
}

function only_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function guardar_formulario_anterior(int $senderoId, array $data): void
{
    $_SESSION['registro_sendero_old'] = array_merge(['sendero_id' => $senderoId], $data);
}

function crear_tabla_menores_registro(mysqli $conn): void
{
    // La estructura se gestiona desde scripts_bd/migracion_estructura_configuracion_2026_06_17.sql.
}

function obtener_menores_post(): array
{
    $menoresPost = $_POST['menores'] ?? [];
    if (!is_array($menoresPost)) {
        return [];
    }

    $menores = [];
    foreach ($menoresPost as $menor) {
        if (!is_array($menor)) {
            continue;
        }

        $nombre = clean_text((string) ($menor['nombre'] ?? ''), 100);
        $apellido = clean_text((string) ($menor['apellido'] ?? ''), 100);
        if ($nombre === '' && $apellido === '') {
            continue;
        }

        $menores[] = [
            'menor_usuario_id' => (int) ($menor['menor_usuario_id'] ?? 0),
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => clean_text((string) ($menor['telefono'] ?? ''), 30),
            'inversion_id' => (int) ($menor['inversion_id'] ?? 0),
            'rango_edad' => clean_text((string) ($menor['rango_edad'] ?? ''), 20),
            'es_alergico' => (string) ($menor['es_alergico'] ?? '0') === '1' ? 1 : 0,
            'alergias_detalle' => clean_text((string) ($menor['alergias_detalle'] ?? ''), 255),
            'grupo_sanguineo' => clean_text((string) ($menor['grupo_sanguineo'] ?? ''), 10),
            'enfermedad' => clean_text((string) ($menor['enfermedad'] ?? ''), 255),
            'seguro_medico' => clean_text((string) ($menor['seguro_medico'] ?? ''), 255),
            'experiencia_senderismo' => clean_text((string) ($menor['experiencia_senderismo'] ?? ''), 80),
            'emergencia_nombre' => clean_text((string) ($menor['emergencia_nombre'] ?? ''), 150),
            'emergencia_parentesco' => clean_text((string) ($menor['emergencia_parentesco'] ?? ''), 80),
            'emergencia_telefono' => only_digits((string) ($menor['emergencia_telefono'] ?? '')),
        ];
    }

    return $menores;
}

function validar_menores(array $menores, array $inversionesValidas): array
{
    $errores = [];
    $gruposPermitidos = ['O+', 'O-', 'A+', 'A-', 'AB+', 'AB-', 'B+', 'B-'];
    $experienciasPermitidas = ['Primera vez', 'Principiante', 'Intermedio', 'Avanzado'];
    $rangosPermitidos = ['8-12', '13-17'];

    foreach ($menores as $idx => $menor) {
        $numero = $idx + 1;
        $requeridos = ['nombre', 'apellido', 'rango_edad', 'grupo_sanguineo', 'enfermedad', 'seguro_medico', 'experiencia_senderismo', 'emergencia_nombre', 'emergencia_parentesco', 'emergencia_telefono'];
        foreach ($requeridos as $campo) {
            if (trim((string) ($menor[$campo] ?? '')) === '') {
                $errores[] = "Completa los datos obligatorios del menor {$numero}.";
                break;
            }
        }
        if (!in_array((int) $menor['inversion_id'], $inversionesValidas, true)) {
            $errores[] = "Selecciona una inversion valida para el menor {$numero}.";
        }
        if (!in_array($menor['rango_edad'], $rangosPermitidos, true)) {
            $errores[] = "Selecciona un rango de edad valido para el menor {$numero}.";
        }
        if (!in_array($menor['grupo_sanguineo'], $gruposPermitidos, true)) {
            $errores[] = "Selecciona un grupo sanguineo valido para el menor {$numero}.";
        }
        if (!in_array($menor['experiencia_senderismo'], $experienciasPermitidas, true)) {
            $errores[] = "Selecciona la experiencia del menor {$numero}.";
        }
        if ((int) $menor['es_alergico'] === 1 && trim((string) $menor['alergias_detalle']) === '') {
            $errores[] = "Especifica la alergia del menor {$numero}.";
        }
    }

    return array_values(array_unique($errores));
}

function guardar_menor_frecuente(mysqli $conn, int $usuarioId, array $menor): int
{
    $menorUsuarioId = (int) ($menor['menor_usuario_id'] ?? 0);
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

    if ($menorUsuarioId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE menores_usuarios
             SET nombre = ?, apellido = ?, telefono = ?, rango_edad = ?, es_alergico = ?, alergias_detalle = ?,
                 grupo_sanguineo = ?, enfermedad = ?, seguro_medico = ?, experiencia_senderismo = ?,
                 emergencia_nombre = ?, emergencia_parentesco = ?, emergencia_telefono = ?, activo = 1
             WHERE id = ? AND usuario_id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "ssssissssssssii",
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
            $menorUsuarioId,
            $usuarioId
        );
        mysqli_stmt_execute($stmt);
        $actualizado = mysqli_stmt_affected_rows($stmt) >= 0;
        mysqli_stmt_close($stmt);
        if ($actualizado) {
            return $menorUsuarioId;
        }
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO menores_usuarios (
            usuario_id, nombre, apellido, telefono, rango_edad, es_alergico, alergias_detalle,
            grupo_sanguineo, enfermedad, seguro_medico, experiencia_senderismo,
            emergencia_nombre, emergencia_parentesco, emergencia_telefono, activo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "issssissssssss",
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
        $emergenciaTelefono
    );
    mysqli_stmt_execute($stmt);
    $nuevoId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $nuevoId;
}

function perfil_senderista_completo_registro(array $detalle): bool
{
    $requeridos = [
        'telefono',
        'rango_edad',
        'identificacion',
        'grupo_sanguineo',
        'enfermedad',
        'seguro_medico',
        'experiencia_senderismo',
        'via_entero',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono',
    ];

    foreach ($requeridos as $campo) {
        if (trim((string) ($detalle[$campo] ?? '')) === '') {
            return false;
        }
    }

    return (int) ($detalle['es_alergico'] ?? 0) !== 1 || trim((string) ($detalle['alergias_detalle'] ?? '')) !== '';
}

if ($senderoId <= 0) {
    $_SESSION['registro_sendero_error'] = "Sendero no valido.";
    registro_redirect($conn, 0);
}

crear_tabla_menores_registro($conn);
$menores = obtener_menores_post();

$stmt = mysqli_prepare($conn, "SELECT id FROM senderos WHERE id = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $senderoId);
mysqli_stmt_execute($stmt);
$senderoExiste = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$senderoExiste) {
    $_SESSION['registro_sendero_error'] = "El sendero seleccionado no esta disponible.";
    registro_redirect($conn, $senderoId);
}

$stmt = mysqli_prepare($conn, "SELECT id FROM sendero_inversiones WHERE id = ? AND sendero_id = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $inversionId, $senderoId);
mysqli_stmt_execute($stmt);
$inversionExiste = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$inversionesValidas = [];
$stmt = mysqli_prepare($conn, "SELECT id FROM sendero_inversiones WHERE sendero_id = ? AND activo = 1");
mysqli_stmt_bind_param($stmt, "i", $senderoId);
mysqli_stmt_execute($stmt);
$resInversionesValidas = mysqli_stmt_get_result($stmt);
while ($rowInversionValida = mysqli_fetch_assoc($resInversionesValidas)) {
    $inversionesValidas[] = (int) $rowInversionValida['id'];
}
mysqli_stmt_close($stmt);

$consentimiento = isset($_POST['consentimiento']);
$rgpd = isset($_POST['rgpd']);

$stmt = mysqli_prepare($conn, "SELECT * FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $usuarioId);
mysqli_stmt_execute($stmt);
$detalle = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$detalleId = (int) ($detalle['id'] ?? 0);

if ($detalleId <= 0 || !perfil_senderista_completo_registro($detalle ?: [])) {
    guardar_formulario_anterior($senderoId, [
        'inversion_id' => (string) $inversionId,
        'consentimiento' => $consentimiento ? '1' : '',
        'rgpd' => $rgpd ? '1' : '',
        'menores' => $menores,
    ]);
    $_SESSION['perfil_senderista_info'] = "Completa tus datos de senderista antes de reservar este sendero.";
    mysqli_close($conn);
    header("Location: " . BASE_URL . "pantallas/completar_perfil.php?sendero_id=" . $senderoId);
    exit;
}

$errores = [];
if (!$inversionExiste) {
    $errores[] = "Selecciona un tipo de inversion valido.";
}
if (!$consentimiento || !$rgpd) {
    $errores[] = "Debes aceptar el consentimiento y el acuerdo RGPD.";
}
$errores = array_merge($errores, validar_menores($menores, $inversionesValidas));

if (!empty($errores)) {
    guardar_formulario_anterior($senderoId, [
        'inversion_id' => (string) $inversionId,
        'consentimiento' => $consentimiento ? '1' : '',
        'rgpd' => $rgpd ? '1' : '',
        'menores' => $menores,
    ]);
    $_SESSION['registro_sendero_error'] = implode(' ', $errores);
    registro_redirect($conn, $senderoId);
}

$consentimientoTexto = "Estoy de acuerdo que: Yo, siendo mayor de edad, en pleno uso de mis facultades y con total capacidad para comprender el contenido de este documento, declaro que he leido y entiendo completamente la informacion proporcionada en esta pagina sobre la actividad en la que participare. Reconozco que se trata de una actividad fisicamente exigente y que conlleva riesgos inherentes a su naturaleza. Estoy consciente de los posibles desafios y riesgos involucrados, incluyendo aquellos relacionados con el esfuerzo fisico, las condiciones del terreno y cualquier otro factor mencionado en la informacion suministrada. Asimismo, acepto que, en caso de emergencia medica, el acceso a asistencia puede estar sujeto a condiciones y tiempos de respuesta variables. Acepto y doy mi consentimiento a recibir asistencia de primeros auxilios por el personal de la directiva de ser necesario. Comprendo que la organizacion y su personal no son responsables de los riesgos que pudiera enfrentar debido a mi participacion o al incumplimiento de las recomendaciones y medidas de seguridad indicadas. Declaro que participo de manera voluntaria, asumiendo plena responsabilidad por mi bienestar y cualquier consecuencia derivada de mi participacion. Finalmente, autorizo el uso y publicacion de imagenes en las que pueda aparecer durante la actividad, siempre que se respete mi integridad y dignidad.";
$rgpdTexto = "Doy mi consentimiento para que esta web almacene la informacion que envio para que puedan responder a mi peticion. Politica de Privacidad.";

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO registros_senderos (
            sendero_id, usuario_id, detalle_usuario_id, estado, consentimiento_aceptado,
            rgpd_aceptado, consentimiento_texto, rgpd_texto, inversion_id
        ) VALUES (?, ?, ?, 'registrado', 1, 1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            detalle_usuario_id = VALUES(detalle_usuario_id),
            inversion_id = VALUES(inversion_id),
            estado = 'registrado',
            consentimiento_aceptado = 1,
            rgpd_aceptado = 1,
            consentimiento_texto = VALUES(consentimiento_texto),
            rgpd_texto = VALUES(rgpd_texto)"
    );
    mysqli_stmt_bind_param($stmt, "iiissi", $senderoId, $usuarioId, $detalleId, $consentimientoTexto, $rgpdTexto, $inversionId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT id FROM registros_senderos WHERE usuario_id = ? AND sendero_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $usuarioId, $senderoId);
    mysqli_stmt_execute($stmt);
    $registroRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $registroId = (int) ($registroRow['id'] ?? 0);
    if ($registroId <= 0) {
        throw new RuntimeException("No se pudo obtener el registro creado.");
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM registro_sendero_menores WHERE registro_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $registroId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!empty($menores)) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO registro_sendero_menores (
                registro_id, inversion_id, nombre, apellido, telefono, rango_edad, es_alergico, alergias_detalle,
                grupo_sanguineo, enfermedad, seguro_medico, experiencia_senderismo,
                emergencia_nombre, emergencia_parentesco, emergencia_telefono
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($menores as $menor) {
            $menorUsuarioId = guardar_menor_frecuente($conn, $usuarioId, $menor);
            $menorInversionId = (int) $menor['inversion_id'];
            $menorNombre = $menor['nombre'];
            $menorApellido = $menor['apellido'];
            $menorTelefono = $menor['telefono'];
            $menorRangoEdad = $menor['rango_edad'];
            $menorEsAlergico = (int) $menor['es_alergico'];
            $menorAlergiasDetalle = $menor['alergias_detalle'];
            $menorGrupoSanguineo = $menor['grupo_sanguineo'];
            $menorEnfermedad = $menor['enfermedad'];
            $menorSeguroMedico = $menor['seguro_medico'];
            $menorExperiencia = $menor['experiencia_senderismo'];
            $menorEmergenciaNombre = $menor['emergencia_nombre'];
            $menorEmergenciaParentesco = $menor['emergencia_parentesco'];
            $menorEmergenciaTelefono = $menor['emergencia_telefono'];

            mysqli_stmt_bind_param(
                $stmt,
                "iissssissssssss",
                $registroId,
                $menorInversionId,
                $menorNombre,
                $menorApellido,
                $menorTelefono,
                $menorRangoEdad,
                $menorEsAlergico,
                $menorAlergiasDetalle,
                $menorGrupoSanguineo,
                $menorEnfermedad,
                $menorSeguroMedico,
                $menorExperiencia,
                $menorEmergenciaNombre,
                $menorEmergenciaParentesco,
                $menorEmergenciaTelefono
            );
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    guardar_formulario_anterior($senderoId, [
        'inversion_id' => (string) $inversionId,
        'consentimiento' => $consentimiento ? '1' : '',
        'rgpd' => $rgpd ? '1' : '',
        'menores' => $menores,
    ]);
    $_SESSION['registro_sendero_error'] = APP_DEBUG ? $e->getMessage() : "No se pudo completar el registro.";
    registro_redirect($conn, $senderoId);
}

unset($_SESSION['registro_sendero_old']);
$_SESSION['registro_sendero_success'] = "Registro enviado correctamente.";
mysqli_close($conn);

header("Location: " . BASE_URL . "pantallas/senderos_detalle.php?id=" . $senderoId);
exit;

