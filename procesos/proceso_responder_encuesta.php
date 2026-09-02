<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/../componentes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $_SESSION['error_message'] = 'Inicia sesion para responder la encuesta.';
    header('Location: ' . BASE_URL . 'pantallas/inicio_sesion.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . BASE_URL . 'pantallas/mi_perfil.php');
    exit;
}

csrf_validate_post(BASE_URL . 'pantallas/mi_perfil.php', 'encuesta_respuesta_error');

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/encuestas_bootstrap.php';

encuestas_bootstrap($conn);

function encuesta_responder_redirect(mysqli $conn, int $envioId): void
{
    mysqli_close($conn);
    header('Location: ' . BASE_URL . 'pantallas/encuesta.php?envio_id=' . $envioId);
    exit;
}

try {
    $usuarioId = (int) $_SESSION['usuario_id'];
    $envioId = (int) ($_POST['envio_id'] ?? 0);
    $respuestasPost = $_POST['respuesta'] ?? [];

    $stmt = mysqli_prepare($conn, "
        SELECT ee.*, e.estado AS encuesta_estado, e.fecha_cierre, e.permite_editar_respuesta
        FROM encuesta_envios ee
        INNER JOIN encuestas e ON e.id = ee.encuesta_id
        WHERE ee.id = ? AND ee.usuario_id = ? AND e.activo = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $envioId, $usuarioId);
    mysqli_stmt_execute($stmt);
    $envio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    if (!$envio) {
        throw new RuntimeException('Encuesta no disponible.');
    }

    if ((string) $envio['encuesta_estado'] !== 'enviada' || (string) $envio['estado'] === 'cancelada') {
        throw new RuntimeException('Esta encuesta no esta disponible para responder.');
    }

    if ((string) $envio['estado'] === 'respondida' && (int) $envio['permite_editar_respuesta'] !== 1) {
        throw new RuntimeException('Esta encuesta ya fue respondida.');
    }

    if (!empty($envio['fecha_cierre']) && strtotime((string) $envio['fecha_cierre']) < strtotime(date('Y-m-d'))) {
        throw new RuntimeException('La encuesta ya cerro.');
    }

    $preguntas = [];
    $stmt = mysqli_prepare($conn, "SELECT * FROM encuesta_preguntas WHERE encuesta_id = ? AND activo = 1 ORDER BY orden ASC, id ASC");
    $encuestaId = (int) $envio['encuesta_id'];
    mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
    mysqli_stmt_execute($stmt);
    $resPreguntas = mysqli_stmt_get_result($stmt);
    while ($resPreguntas && $row = mysqli_fetch_assoc($resPreguntas)) {
        $preguntas[(int) $row['id']] = $row;
    }
    mysqli_stmt_close($stmt);

    $opciones = [];
    if (!empty($preguntas)) {
        $ids = implode(',', array_map('intval', array_keys($preguntas)));
        $resOpciones = mysqli_query($conn, "SELECT * FROM encuesta_opciones WHERE pregunta_id IN ({$ids}) AND activo = 1");
        while ($resOpciones && $row = mysqli_fetch_assoc($resOpciones)) {
            $opciones[(int) $row['pregunta_id']][(int) $row['id']] = $row;
        }
    }

    mysqli_begin_transaction($conn);

    $stmt = mysqli_prepare($conn, "DELETE FROM encuesta_respuestas WHERE envio_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $envioId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $insert = mysqli_prepare($conn, "
        INSERT INTO encuesta_respuestas (envio_id, pregunta_id, opcion_id, respuesta_texto, respuesta_numero, puntuacion)
        VALUES (?, ?, NULLIF(?, 0), NULLIF(?, ''), NULLIF(?, ''), ?)
    ");

    foreach ($preguntas as $pid => $pregunta) {
        $tipo = (string) $pregunta['tipo'];
        $requerido = (int) $pregunta['requerido'] === 1;
        $value = $respuestasPost[$pid] ?? null;

        if (in_array($tipo, ['radio', 'select'], true)) {
            $opcionId = (int) ($value ?? 0);
            if ($requerido && $opcionId <= 0) {
                throw new RuntimeException('Completa todas las preguntas obligatorias.');
            }
            if ($opcionId > 0 && isset($opciones[$pid][$opcionId])) {
                $texto = '';
                $numero = '';
                $puntuacion = (float) $opciones[$pid][$opcionId]['puntuacion'];
                mysqli_stmt_bind_param($insert, 'iiissd', $envioId, $pid, $opcionId, $texto, $numero, $puntuacion);
                mysqli_stmt_execute($insert);
            }
            continue;
        }

        if ($tipo === 'escala') {
            $numero = trim((string) ($value ?? ''));
            if ($requerido && $numero === '') {
                throw new RuntimeException('Completa todas las preguntas obligatorias.');
            }
            if ($numero !== '' && !is_numeric($numero)) {
                throw new RuntimeException('Una respuesta de escala no es valida.');
            }

            if ($numero !== '') {
                $valor = (float) $numero;
                $opcionId = 0;
                foreach ($opciones[$pid] ?? [] as $opcion) {
                    if ((float) ($opcion['puntuacion'] ?? 0) === $valor) {
                        $opcionId = (int) $opcion['id'];
                        break;
                    }
                }
                $texto = '';
                $puntuacion = $valor;
                mysqli_stmt_bind_param($insert, 'iiissd', $envioId, $pid, $opcionId, $texto, $numero, $puntuacion);
                mysqli_stmt_execute($insert);
            }
            continue;
        }

        if ($tipo === 'checkbox') {
            $selected = is_array($value) ? array_map('intval', $value) : [];
            $selected = array_values(array_filter($selected, fn ($opcionId) => isset($opciones[$pid][$opcionId])));
            if ($requerido && empty($selected)) {
                throw new RuntimeException('Completa todas las preguntas obligatorias.');
            }
            foreach ($selected as $opcionId) {
                $texto = '';
                $numero = '';
                $puntuacion = (float) $opciones[$pid][$opcionId]['puntuacion'];
                mysqli_stmt_bind_param($insert, 'iiissd', $envioId, $pid, $opcionId, $texto, $numero, $puntuacion);
                mysqli_stmt_execute($insert);
            }
            continue;
        }

        if ($tipo === 'numero') {
            $numero = trim((string) ($value ?? ''));
            if ($requerido && $numero === '') {
                throw new RuntimeException('Completa todas las preguntas obligatorias.');
            }
            if ($numero !== '' && !is_numeric($numero)) {
                throw new RuntimeException('Una respuesta numerica no es valida.');
            }
            $opcionId = 0;
            $texto = '';
            $puntuacion = $numero !== '' ? (float) $numero : 0.0;
            mysqli_stmt_bind_param($insert, 'iiissd', $envioId, $pid, $opcionId, $texto, $numero, $puntuacion);
            mysqli_stmt_execute($insert);
            continue;
        }

        $texto = trim((string) ($value ?? ''));
        if ($requerido && $texto === '') {
            throw new RuntimeException('Completa todas las preguntas obligatorias.');
        }
        if ($texto !== '') {
            $opcionId = 0;
            $numero = '';
            $puntuacion = 0.0;
            mysqli_stmt_bind_param($insert, 'iiissd', $envioId, $pid, $opcionId, $texto, $numero, $puntuacion);
            mysqli_stmt_execute($insert);
        }
    }

    mysqli_stmt_close($insert);

    $stmt = mysqli_prepare($conn, "UPDATE encuesta_envios SET estado = 'respondida', respondido_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $envioId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    $_SESSION['perfil_senderista_success'] = 'Gracias. Tu encuesta fue enviada correctamente.';
    mysqli_close($conn);
    header('Location: ' . BASE_URL . 'pantallas/mi_perfil.php');
    exit;
} catch (Throwable $e) {
    @mysqli_rollback($conn);
    $_SESSION['encuesta_respuesta_error'] = APP_DEBUG ? $e->getMessage() : 'No se pudo guardar tu respuesta.';
    encuesta_responder_redirect($conn, (int) ($_POST['envio_id'] ?? 0));
}
