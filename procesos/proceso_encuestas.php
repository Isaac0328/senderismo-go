<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'operaciones.encuestas';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $_SESSION['encuesta_error'] = 'Metodo no permitido.';
    header('Location: ' . BASE_URL . 'mantenimientos/mantenimiento_encuestas.php');
    exit;
}

csrf_validate_post(BASE_URL . 'mantenimientos/mantenimiento_encuestas.php', 'encuesta_error');

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/encuestas_destinatarios.php';

function encuesta_redirect(mysqli $conn, int $id = 0, string $vista = '', int $plantillaEditarId = 0): void
{
    mysqli_close($conn);
    $url = BASE_URL . 'mantenimientos/mantenimiento_encuestas.php';
    if ($plantillaEditarId > 0) {
        $url .= '?editar_plantilla=' . $plantillaEditarId;
    } elseif ($id > 0) {
        $url .= '?edit=' . $id;
    } elseif (in_array($vista, ['consultar', 'plantillas'], true)) {
        $url .= '?vista=' . $vista;
    } elseif ($vista === 'crear_plantilla') {
        $url .= '?crear_plantilla=1';
    }
    header('Location: ' . $url);
    exit;
}

function encuesta_preservar_formulario(array $post): void
{
    if (!in_array((string) ($post['action'] ?? ''), ['save', 'template_save', 'template_update'], true)) {
        return;
    }

    unset($post['csrf_token'], $post['_csrf'], $post['token']);
    $_SESSION['encuesta_form_state'] = $post;
}

function encuesta_tipo_valido(string $tipo): string
{
    $permitidos = ['texto', 'textarea', 'radio', 'checkbox', 'select', 'escala', 'numero'];
    return in_array($tipo, $permitidos, true) ? $tipo : 'texto';
}

function encuesta_destinatarios_valido(string $destinatarios): string
{
    $permitidos = ['sendero_asistentes', 'sendero_registrados', 'todos_usuarios'];
    return in_array($destinatarios, $permitidos, true) ? $destinatarios : 'sendero_asistentes';
}

function encuesta_parse_opciones(array $preguntaData, string $tipo): array
{
    $items = [];
    if ($tipo === 'escala') {
        $min = (int) ($preguntaData['escala_min'] ?? 1);
        $max = (int) ($preguntaData['escala_max'] ?? 5);
        $min = max(0, min(100, $min));
        $max = max(1, min(100, $max));
        if ($max <= $min) {
            throw new RuntimeException('La escala necesita un valor final mayor al valor inicial.');
        }
        if (($max - $min) > 50) {
            throw new RuntimeException('La escala no debe tener mas de 50 niveles.');
        }

        $minLabel = sg_clean_text((string) ($preguntaData['escala_min_label'] ?? ''), 80);
        $maxLabel = sg_clean_text((string) ($preguntaData['escala_max_label'] ?? ''), 80);
        for ($i = $min; $i <= $max; $i++) {
            $texto = (string) $i;
            if ($i === $min && $minLabel !== '') {
                $texto .= ' - ' . $minLabel;
            }
            if ($i === $max && $maxLabel !== '') {
                $texto .= ' - ' . $maxLabel;
            }
            $items[] = ['texto' => $texto, 'puntuacion' => (float) $i];
        }

        return $items;
    }

    $raw = (string) ($preguntaData['opciones'] ?? '');
    foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        $texto = sg_clean_text($parts[0] ?? '', 255);
        if ($texto === '') {
            continue;
        }
        $puntuacion = isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 0.0;
        $items[] = ['texto' => $texto, 'puntuacion' => $puntuacion];
    }

    return $items;
}

function encuesta_enviar(mysqli $conn, int $encuestaId): int
{
    $stmt = mysqli_prepare($conn, "SELECT id, sendero_id, destinatarios FROM encuestas WHERE id = ? AND activo = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
    mysqli_stmt_execute($stmt);
    $encuesta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    if (!$encuesta) {
        throw new RuntimeException('Encuesta no encontrada.');
    }

    $senderoId = (int) ($encuesta['sendero_id'] ?? 0);
    $destinatarios = (string) $encuesta['destinatarios'];

    if ($destinatarios !== 'todos_usuarios' && $senderoId <= 0) {
        throw new RuntimeException('Para enviar por sendero debes enlazar la encuesta a una ruta.');
    }

    $sql = 'INSERT IGNORE INTO encuesta_envios (encuesta_id, usuario_id, sendero_id) '
        . encuesta_destinatarios_select($encuestaId, $senderoId, $destinatarios);

    mysqli_query($conn, $sql);
    $creados = mysqli_affected_rows($conn);

    $stmt = mysqli_prepare($conn, "UPDATE encuestas SET estado = 'enviada', fecha_envio = COALESCE(fecha_envio, NOW()) WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return max(0, $creados);
}

function encuesta_plantilla_desde_post(array $preguntas): array
{
    $plantilla = [];
    foreach ($preguntas as $preguntaData) {
        if (!is_array($preguntaData)) {
            continue;
        }

        $texto = sg_clean_text((string) ($preguntaData['pregunta'] ?? ''), 255);
        if ($texto === '') {
            continue;
        }
        $tipo = encuesta_tipo_valido((string) ($preguntaData['tipo'] ?? 'texto'));
        $opciones = encuesta_parse_opciones($preguntaData, $tipo);
        if (in_array($tipo, ['radio', 'checkbox', 'select'], true) && empty($opciones)) {
            throw new RuntimeException('Las preguntas de seleccion necesitan opciones.');
        }

        $puntajeMax = max(0, (float) ($preguntaData['puntaje_max'] ?? 0));
        if ($tipo === 'escala' && !empty($opciones)) {
            $puntajeMax = max(array_map(fn ($opcion) => (float) $opcion['puntuacion'], $opciones));
        }
        $opcionesTexto = implode("\n", array_map(
            static fn(array $opcion): string => $opcion['texto'] . '|' . $opcion['puntuacion'],
            $opciones
        ));

        $plantilla[] = [
            'pregunta' => $texto,
            'ayuda' => sg_clean_text((string) ($preguntaData['ayuda'] ?? ''), 255),
            'tipo' => $tipo,
            'requerido' => !empty($preguntaData['requerido']) ? 1 : 0,
            'puntaje_max' => $puntajeMax,
            'opciones_texto' => $opcionesTexto,
        ];
    }

    return $plantilla;
}

try {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        sg_require_permission_action($conn, 'operaciones.encuestas', $id > 0 ? 'editar' : 'agregar');

        $titulo = sg_clean_text((string) ($_POST['titulo'] ?? ''), 180);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $senderoId = (int) ($_POST['sendero_id'] ?? 0);
        $destinatarios = encuesta_destinatarios_valido((string) ($_POST['destinatarios'] ?? 'sendero_asistentes'));
        $anonima = !empty($_POST['anonima']) ? 1 : 0;
        $permiteEditar = !empty($_POST['permite_editar_respuesta']) ? 1 : 0;
        $fechaCierre = trim((string) ($_POST['fecha_cierre'] ?? ''));
        $fechaCierreSql = $fechaCierre !== '' ? sg_fecha_visual_a_sql($fechaCierre) : '';
        $adminId = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($titulo === '') {
            throw new RuntimeException('El titulo de la encuesta es obligatorio.');
        }

        $preguntas = $_POST['preguntas'] ?? [];
        if (!is_array($preguntas) || empty($preguntas)) {
            throw new RuntimeException('Agrega al menos una pregunta.');
        }

        mysqli_begin_transaction($conn);

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "
                UPDATE encuestas
                SET titulo = ?, descripcion = ?, sendero_id = NULLIF(?, 0), destinatarios = ?,
                    anonima = ?, permite_editar_respuesta = ?, fecha_cierre = NULLIF(?, '')
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, 'ssisiisi', $titulo, $descripcion, $senderoId, $destinatarios, $anonima, $permiteEditar, $fechaCierreSql, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $encuestaId = $id;

            $stmt = mysqli_prepare($conn, "
                SELECT COUNT(*) AS total
                FROM encuesta_respuestas er
                INNER JOIN encuesta_envios ee ON ee.id = er.envio_id
                WHERE ee.encuesta_id = ?
            ");
            mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
            mysqli_stmt_execute($stmt);
            $respuestasExistentes = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ((int) ($respuestasExistentes['total'] ?? 0) > 0) {
                mysqli_commit($conn);
                $_SESSION['encuesta_success'] = 'Datos generales actualizados. Las preguntas se conservaron porque la encuesta ya tiene respuestas.';
                encuesta_redirect($conn);
            }

            $stmt = mysqli_prepare($conn, "DELETE FROM encuesta_preguntas WHERE encuesta_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO encuestas (titulo, descripcion, sendero_id, destinatarios, anonima, permite_editar_respuesta, fecha_cierre, creado_por)
                VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, NULLIF(?, ''), ?)
            ");
            mysqli_stmt_bind_param($stmt, 'ssisiisi', $titulo, $descripcion, $senderoId, $destinatarios, $anonima, $permiteEditar, $fechaCierreSql, $adminId);
            mysqli_stmt_execute($stmt);
            $encuestaId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }

        $insertPregunta = mysqli_prepare($conn, "
            INSERT INTO encuesta_preguntas (encuesta_id, pregunta, ayuda, tipo, requerido, puntaje_max, orden)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertOpcion = mysqli_prepare($conn, "
            INSERT INTO encuesta_opciones (pregunta_id, texto, valor, puntuacion, orden)
            VALUES (?, ?, ?, ?, ?)
        ");

        $orden = 0;
        foreach ($preguntas as $preguntaData) {
            if (!is_array($preguntaData)) {
                continue;
            }
            $texto = sg_clean_text((string) ($preguntaData['pregunta'] ?? ''), 255);
            if ($texto === '') {
                continue;
            }
            $ayuda = sg_clean_text((string) ($preguntaData['ayuda'] ?? ''), 255);
            $tipo = encuesta_tipo_valido((string) ($preguntaData['tipo'] ?? 'texto'));
            $requerido = !empty($preguntaData['requerido']) ? 1 : 0;
            $puntajeMax = max(0, (float) ($preguntaData['puntaje_max'] ?? 0));
            $opciones = encuesta_parse_opciones($preguntaData, $tipo);
            if ($tipo === 'escala' && !empty($opciones)) {
                $puntajeMax = max(array_map(fn ($opcion) => (float) $opcion['puntuacion'], $opciones));
            }

            if (in_array($tipo, ['radio', 'checkbox', 'select'], true) && empty($opciones)) {
                throw new RuntimeException('Las preguntas de seleccion necesitan opciones.');
            }

            $orden++;
            mysqli_stmt_bind_param($insertPregunta, 'isssidi', $encuestaId, $texto, $ayuda, $tipo, $requerido, $puntajeMax, $orden);
            mysqli_stmt_execute($insertPregunta);
            $preguntaId = (int) mysqli_insert_id($conn);

            $ordenOpcion = 0;
            foreach ($opciones as $opcion) {
                $ordenOpcion++;
                $valor = sg_slugify($opcion['texto'], 'opcion-' . $ordenOpcion);
                $puntuacion = (float) $opcion['puntuacion'];
                mysqli_stmt_bind_param($insertOpcion, 'issdi', $preguntaId, $opcion['texto'], $valor, $puntuacion, $ordenOpcion);
                mysqli_stmt_execute($insertOpcion);
            }
        }

        mysqli_stmt_close($insertPregunta);
        mysqli_stmt_close($insertOpcion);

        if ($orden === 0) {
            throw new RuntimeException('Agrega al menos una pregunta valida.');
        }

        mysqli_commit($conn);
        $_SESSION['encuesta_success'] = 'Encuesta guardada correctamente.';
        encuesta_redirect($conn);
    }

    if ($action === 'send') {
        sg_require_permission_action($conn, 'operaciones.encuestas', 'editar');
        if ($id <= 0) {
            throw new RuntimeException('Encuesta no valida.');
        }
        $creados = encuesta_enviar($conn, $id);
        $_SESSION['encuesta_success'] = 'Encuesta enviada. Notificaciones generadas: ' . $creados . '.';
        encuesta_redirect($conn, 0, 'consultar');
    }

    if ($action === 'sync_recipients') {
        sg_require_permission_action($conn, 'operaciones.encuestas', 'editar');
        if ($id <= 0) {
            throw new RuntimeException('Encuesta no valida.');
        }

        $stmt = mysqli_prepare($conn, "SELECT id FROM encuestas WHERE id = ? AND activo = 1 AND estado = 'enviada' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $encuestaEnviada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$encuestaEnviada) {
            throw new RuntimeException('Solo puedes actualizar destinatarios de una encuesta enviada.');
        }

        $creados = encuesta_enviar($conn, $id);
        $_SESSION['encuesta_success'] = $creados > 0
            ? "Destinatarios actualizados. Nuevas invitaciones generadas: {$creados}."
            : 'Destinatarios actualizados. No hay nuevas personas que agregar.';
        encuesta_redirect($conn, 0, 'consultar');
    }

    if (in_array($action, ['template_save', 'template_update'], true)) {
        $isTemplateUpdate = $action === 'template_update';
        sg_require_permission_action($conn, 'operaciones.encuestas', $isTemplateUpdate ? 'editar' : 'agregar');

        if ($isTemplateUpdate && $id <= 0) {
            throw new RuntimeException('Plantilla no valida.');
        }
        if ($isTemplateUpdate) {
            $stmt = mysqli_prepare($conn, 'SELECT id FROM encuesta_plantillas WHERE id = ? AND activo = 1 LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $plantillaExiste = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$plantillaExiste) {
                throw new RuntimeException('La plantilla ya no esta disponible.');
            }
        }

        $nombre = sg_clean_text((string) ($_POST['nombre'] ?? ''), 180);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la plantilla es obligatorio.');
        }

        $preguntas = $_POST['preguntas'] ?? [];
        if (!is_array($preguntas) || empty($preguntas)) {
            throw new RuntimeException('Agrega al menos una pregunta a la plantilla.');
        }
        $preguntasPlantilla = encuesta_plantilla_desde_post($preguntas);
        if (empty($preguntasPlantilla)) {
            throw new RuntimeException('Agrega al menos una pregunta valida a la plantilla.');
        }

        $preguntasJson = json_encode($preguntasPlantilla, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($isTemplateUpdate) {
            $stmt = mysqli_prepare($conn, "
                UPDATE encuesta_plantillas
                SET nombre = ?, descripcion = ?, preguntas_json = ?
                WHERE id = ? AND activo = 1
            ");
            mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $descripcion, $preguntasJson, $id);
        } else {
            $creadoPor = (int) ($_SESSION['usuario_id'] ?? 0);
            $stmt = mysqli_prepare($conn, "
                INSERT INTO encuesta_plantillas (nombre, descripcion, preguntas_json, creado_por)
                VALUES (?, ?, ?, NULLIF(?, 0))
            ");
            mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $descripcion, $preguntasJson, $creadoPor);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['encuesta_success'] = $isTemplateUpdate
            ? 'Plantilla actualizada correctamente.'
            : 'Plantilla guardada correctamente.';
        encuesta_redirect($conn, 0, 'plantillas');
    }

    if ($action === 'template_delete') {
        sg_require_permission_action($conn, 'operaciones.encuestas', 'eliminar');
        if ($id <= 0) {
            throw new RuntimeException('Plantilla no valida.');
        }

        $stmt = mysqli_prepare($conn, "UPDATE encuesta_plantillas SET activo = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['encuesta_success'] = 'Plantilla eliminada del listado activo.';
        encuesta_redirect($conn, 0, 'plantillas');
    }

    if ($action === 'cancel') {
        sg_require_permission_action($conn, 'operaciones.encuestas', 'editar');
        $stmt = mysqli_prepare($conn, "UPDATE encuestas SET estado = 'cancelada' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn, "UPDATE encuesta_envios SET estado = 'cancelada' WHERE encuesta_id = ? AND estado = 'pendiente'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['encuesta_success'] = 'Encuesta cancelada.';
        encuesta_redirect($conn, 0, 'consultar');
    }

    if ($action === 'close') {
        sg_require_permission_action($conn, 'operaciones.encuestas', 'editar');
        $stmt = mysqli_prepare($conn, "UPDATE encuestas SET estado = 'cerrada' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn, "UPDATE encuesta_envios SET estado = 'cancelada' WHERE encuesta_id = ? AND estado = 'pendiente'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['encuesta_success'] = 'Encuesta cerrada.';
        encuesta_redirect($conn, 0, 'consultar');
    }

    if ($action === 'reopen') {
        sg_require_permission_action($conn, 'operaciones.encuestas', 'editar');
        if ($id <= 0) {
            throw new RuntimeException('Encuesta no valida.');
        }

        mysqli_begin_transaction($conn);

        $stmt = mysqli_prepare($conn, "
            UPDATE encuestas
            SET estado = 'enviada', fecha_envio = COALESCE(fecha_envio, NOW())
            WHERE id = ? AND activo = 1 AND estado = 'cerrada'
        ");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "
            UPDATE encuesta_envios ee
            LEFT JOIN encuesta_respuestas er ON er.envio_id = ee.id
            SET ee.estado = 'pendiente', ee.respondido_at = NULL
            WHERE ee.encuesta_id = ?
              AND ee.estado = 'cancelada'
              AND er.id IS NULL
        ");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        $_SESSION['encuesta_success'] = 'Encuesta reabierta. Los participantes pendientes podran responderla nuevamente.';
        encuesta_redirect($conn, 0, 'consultar');
    }

    if ($action === 'delete') {
        sg_require_permission_action($conn, 'operaciones.encuestas', 'eliminar');
        $stmt = mysqli_prepare($conn, "UPDATE encuestas SET activo = 0, estado = 'cancelada' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['encuesta_success'] = 'Encuesta eliminada del listado activo.';
        encuesta_redirect($conn, 0, 'consultar');
    }

    throw new RuntimeException('Accion no valida.');
} catch (Throwable $e) {
    @mysqli_rollback($conn);
    encuesta_preservar_formulario($_POST);
    $_SESSION['encuesta_error'] = APP_DEBUG ? $e->getMessage() : 'No se pudo procesar la encuesta.';
    $plantillaEditarId = ($action ?? '') === 'template_update' ? $id : 0;
    encuesta_redirect(
        $conn,
        $plantillaEditarId > 0 ? 0 : $id,
        in_array(($action ?? ''), ['template_save', 'template_update'], true) ? 'crear_plantilla' : '',
        $plantillaEditarId
    );
}
