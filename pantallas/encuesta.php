<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../componentes/recordar_sesion.php';
sg_restaurar_sesion_recordada();

if (empty($_SESSION['usuario_id']) || empty($_SESSION['logged_in'])) {
    $_SESSION['redirect_after_login'] = (string) ($_SERVER['REQUEST_URI'] ?? BASE_URL . 'pantallas/mi_perfil.php');
    $_SESSION['error_message'] = 'Inicia sesion para responder la encuesta.';
    header('Location: ' . BASE_URL . 'pantallas/inicio_sesion.php');
    exit;
}

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/encuestas_bootstrap.php';

encuestas_bootstrap($conn);

$pageTitle = 'Encuesta | Senderismo Go!';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/encuesta.css'];
$jsFiles = ['js/encuesta.js'];

function encuesta_scale_label_from_text(string $texto, int $valor): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }

    $texto = preg_replace('/^' . preg_quote((string) $valor, '/') . '\s*(?:-|:)?\s*/', '', $texto);
    return trim((string) $texto);
}

function encuesta_scale_data(array $opciones): array
{
    $valores = [];
    $textos = [];
    foreach ($opciones as $opcion) {
        $valor = (int) round((float) ($opcion['puntuacion'] ?? 0));
        $valores[] = $valor;
        $textos[$valor] = (string) ($opcion['texto'] ?? '');
    }

    if (empty($valores)) {
        return ['min' => 1, 'max' => 5, 'min_label' => 'Malo', 'max_label' => 'Excelente'];
    }

    $min = min($valores);
    $max = max($valores);
    return [
        'min' => $min,
        'max' => $max,
        'min_label' => encuesta_scale_label_from_text((string) ($textos[$min] ?? ''), $min),
        'max_label' => encuesta_scale_label_from_text((string) ($textos[$max] ?? ''), $max),
    ];
}

$usuarioId = (int) $_SESSION['usuario_id'];
$envioId = (int) ($_GET['envio_id'] ?? $_GET['id'] ?? 0);
$previewEncuestaId = (int) ($_GET['encuesta_id'] ?? 0);
$isPreview = !empty($_GET['preview']) && $previewEncuestaId > 0;

if ($isPreview) {
    sg_require_permission_action($conn, 'operaciones.encuestas', 'ver', BASE_URL . 'mantenimientos/mantenimiento_encuestas.php?vista=consultar');

    $stmt = mysqli_prepare($conn, "
        SELECT 0 AS id,
               e.id AS encuesta_id,
               e.titulo, e.descripcion, e.estado AS encuesta_estado, e.fecha_cierre,
               e.permite_editar_respuesta, e.anonima,
               'pendiente' AS estado,
               s.nombre AS sendero_nombre
        FROM encuestas e
        LEFT JOIN senderos s ON s.id = e.sendero_id
        WHERE e.id = ? AND e.activo = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'i', $previewEncuestaId);
    mysqli_stmt_execute($stmt);
    $envio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
} else {
    $stmt = mysqli_prepare($conn, "
        SELECT ee.*,
               e.titulo, e.descripcion, e.estado AS encuesta_estado, e.fecha_cierre,
               e.permite_editar_respuesta, e.anonima,
               s.nombre AS sendero_nombre
        FROM encuesta_envios ee
        INNER JOIN encuestas e ON e.id = ee.encuesta_id
        LEFT JOIN senderos s ON s.id = COALESCE(ee.sendero_id, e.sendero_id)
        WHERE ee.id = ? AND ee.usuario_id = ? AND e.activo = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $envioId, $usuarioId);
    mysqli_stmt_execute($stmt);
    $envio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);
}

if (!$envio) {
    mysqli_close($conn);
    if ($isPreview) {
        $_SESSION['encuesta_error'] = 'Encuesta no disponible para vista previa.';
        header('Location: ' . BASE_URL . 'mantenimientos/mantenimiento_encuestas.php?vista=consultar');
    } else {
        $_SESSION['perfil_senderista_error'] = 'Encuesta no disponible.';
        header('Location: ' . BASE_URL . 'pantallas/mi_perfil.php');
    }
    exit;
}

$puedeResponder = !$isPreview
    && (string) $envio['encuesta_estado'] === 'enviada'
    && (string) $envio['estado'] !== 'cancelada'
    && ((string) $envio['estado'] === 'pendiente' || (int) $envio['permite_editar_respuesta'] === 1);

if (!empty($envio['fecha_cierre']) && strtotime((string) $envio['fecha_cierre']) < strtotime(date('Y-m-d'))) {
    $puedeResponder = false;
}

$preguntas = [];
$opcionesPorPregunta = [];
$stmt = mysqli_prepare($conn, "
    SELECT *
    FROM encuesta_preguntas
    WHERE encuesta_id = ? AND activo = 1
    ORDER BY orden ASC, id ASC
");
$encuestaId = (int) $envio['encuesta_id'];
mysqli_stmt_bind_param($stmt, 'i', $encuestaId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($res && $row = mysqli_fetch_assoc($res)) {
    $preguntas[(int) $row['id']] = $row;
}
mysqli_stmt_close($stmt);

if (!empty($preguntas)) {
    $ids = implode(',', array_map('intval', array_keys($preguntas)));
    $resOpciones = mysqli_query($conn, "
        SELECT *
        FROM encuesta_opciones
        WHERE pregunta_id IN ({$ids}) AND activo = 1
        ORDER BY pregunta_id ASC, orden ASC, id ASC
    ");
    while ($resOpciones && $row = mysqli_fetch_assoc($resOpciones)) {
        $opcionesPorPregunta[(int) $row['pregunta_id']][] = $row;
    }
}

$respuestas = [];
if (!$isPreview) {
    $resActuales = mysqli_query($conn, "
        SELECT pregunta_id, opcion_id, respuesta_texto, respuesta_numero
        FROM encuesta_respuestas
        WHERE envio_id = " . (int) $envio['id'] . "
    ");
    while ($resActuales && $row = mysqli_fetch_assoc($resActuales)) {
        $pid = (int) $row['pregunta_id'];
        if ((int) ($row['opcion_id'] ?? 0) > 0) {
            $respuestas[$pid]['opciones'][] = (int) $row['opcion_id'];
        }
        if ($row['respuesta_texto'] !== null) {
            $respuestas[$pid]['texto'] = (string) $row['respuesta_texto'];
        }
        if ($row['respuesta_numero'] !== null) {
            $respuestas[$pid]['numero'] = (string) $row['respuesta_numero'];
        }
    }
}

$backUrl = $isPreview ? BASE_URL . 'mantenimientos/mantenimiento_encuestas.php?vista=consultar' : BASE_URL . 'pantallas/mi_perfil.php';
$backLabel = $isPreview ? 'Volver a encuestas' : 'Volver al perfil';
$disabledPreview = $isPreview ? 'disabled' : '';

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="survey-public-page">
    <section class="survey-public-card">
        <a class="survey-back" href="<?= $backUrl ?>"><i data-feather="arrow-left"></i> <?= sg_h($backLabel) ?></a>
        <span class="survey-public-kicker"><?= $isPreview ? 'Vista previa' : 'Encuesta' ?></span>
        <h1><?= sg_h($envio['titulo']) ?></h1>
        <?php if (!empty($envio['sendero_nombre'])): ?>
            <p class="survey-route"><i data-feather="map-pin"></i><?= sg_h($envio['sendero_nombre']) ?></p>
        <?php endif; ?>
        <?php if (trim((string) $envio['descripcion']) !== ''): ?>
            <p class="survey-intro"><?= nl2br(sg_h($envio['descripcion'])) ?></p>
        <?php endif; ?>

        <?php if (!empty($_SESSION['encuesta_respuesta_error'])): ?>
            <div class="survey-response-alert error"><?= sg_h($_SESSION['encuesta_respuesta_error']) ?></div>
            <?php unset($_SESSION['encuesta_respuesta_error']); ?>
        <?php endif; ?>

        <?php if ($isPreview): ?>
            <div class="survey-response-alert info">
                Esta es una vista previa administrativa. No genera ni guarda respuestas.
            </div>
        <?php elseif (!$puedeResponder): ?>
            <div class="survey-response-alert info">
                Esta encuesta ya no esta disponible para responder.
            </div>
        <?php endif; ?>

        <?php if ($isPreview || $puedeResponder): ?>
            <form class="survey-response-form" method="POST" action="<?= BASE_URL ?>procesos/proceso_responder_encuesta.php">
                <input type="hidden" name="envio_id" value="<?= (int) $envio['id'] ?>">

                <?php foreach ($preguntas as $pregunta): ?>
                    <?php
                    $pid = (int) $pregunta['id'];
                    $tipo = (string) $pregunta['tipo'];
                    $actualOpciones = $respuestas[$pid]['opciones'] ?? [];
                    ?>
                    <article class="survey-response-question">
                        <div class="survey-question-title">
                            <strong><?= sg_h($pregunta['pregunta']) ?><?= (int) $pregunta['requerido'] === 1 ? ' *' : '' ?></strong>
                            <?php if ((float) $pregunta['puntaje_max'] > 0): ?>
                                <span><?= number_format((float) $pregunta['puntaje_max'], 0) ?> pts</span>
                            <?php endif; ?>
                        </div>
                        <?php if (trim((string) $pregunta['ayuda']) !== ''): ?>
                            <p><?= sg_h($pregunta['ayuda']) ?></p>
                        <?php endif; ?>

                        <?php if ($tipo === 'textarea'): ?>
                            <textarea name="respuesta[<?= $pid ?>]" rows="4" <?= (int) $pregunta['requerido'] === 1 ? 'required' : '' ?> <?= $disabledPreview ?>><?= sg_h($respuestas[$pid]['texto'] ?? '') ?></textarea>
                        <?php elseif ($tipo === 'numero'): ?>
                            <input type="number" step="0.01" name="respuesta[<?= $pid ?>]" value="<?= sg_h($respuestas[$pid]['numero'] ?? '') ?>" <?= (int) $pregunta['requerido'] === 1 ? 'required' : '' ?> <?= $disabledPreview ?>>
                        <?php elseif ($tipo === 'select'): ?>
                            <select name="respuesta[<?= $pid ?>]" <?= (int) $pregunta['requerido'] === 1 ? 'required' : '' ?> <?= $disabledPreview ?>>
                                <option value="">Selecciona una opcion</option>
                                <?php foreach ($opcionesPorPregunta[$pid] ?? [] as $opcion): ?>
                                    <option value="<?= (int) $opcion['id'] ?>" <?= in_array((int) $opcion['id'], $actualOpciones, true) ? 'selected' : '' ?>><?= sg_h($opcion['texto']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($tipo === 'escala'): ?>
                            <?php
                            $scaleData = encuesta_scale_data($opcionesPorPregunta[$pid] ?? []);
                            $scaleValue = (int) $scaleData['min'];
                            if (isset($respuestas[$pid]['numero']) && is_numeric($respuestas[$pid]['numero'])) {
                                $scaleValue = (int) round((float) $respuestas[$pid]['numero']);
                            } elseif (!empty($actualOpciones)) {
                                foreach ($opcionesPorPregunta[$pid] ?? [] as $opcionActual) {
                                    if (in_array((int) $opcionActual['id'], $actualOpciones, true)) {
                                        $scaleValue = (int) round((float) ($opcionActual['puntuacion'] ?? $scaleValue));
                                        break;
                                    }
                                }
                            }
                            $scaleValue = max((int) $scaleData['min'], min((int) $scaleData['max'], $scaleValue));
                            ?>
                            <div class="survey-range-control">
                                <div class="survey-range-value">
                                    <span data-range-output><?= sg_h($scaleValue) ?></span>
                                </div>
                                <input
                                    type="range"
                                    name="respuesta[<?= $pid ?>]"
                                    min="<?= sg_h($scaleData['min']) ?>"
                                    max="<?= sg_h($scaleData['max']) ?>"
                                    step="1"
                                    value="<?= sg_h($scaleValue) ?>"
                                    data-range-input
                                    <?= (int) $pregunta['requerido'] === 1 ? 'required' : '' ?>
                                    <?= $disabledPreview ?>
                                >
                                <div class="survey-range-labels">
                                    <span><?= sg_h($scaleData['min']) ?> <?= sg_h($scaleData['min_label']) ?></span>
                                    <span><?= sg_h($scaleData['max']) ?> <?= sg_h($scaleData['max_label']) ?></span>
                                </div>
                            </div>
                        <?php elseif ($tipo === 'radio'): ?>
                            <div class="survey-option-grid">
                                <?php foreach ($opcionesPorPregunta[$pid] ?? [] as $opcion): ?>
                                    <label>
                                        <input type="radio" name="respuesta[<?= $pid ?>]" value="<?= (int) $opcion['id'] ?>" <?= in_array((int) $opcion['id'], $actualOpciones, true) ? 'checked' : '' ?> <?= (int) $pregunta['requerido'] === 1 ? 'required' : '' ?> <?= $disabledPreview ?>>
                                        <span><?= sg_h($opcion['texto']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($tipo === 'checkbox'): ?>
                            <div class="survey-option-grid">
                                <?php foreach ($opcionesPorPregunta[$pid] ?? [] as $opcion): ?>
                                    <label>
                                        <input type="checkbox" name="respuesta[<?= $pid ?>][]" value="<?= (int) $opcion['id'] ?>" <?= in_array((int) $opcion['id'], $actualOpciones, true) ? 'checked' : '' ?> <?= $disabledPreview ?>>
                                        <span><?= sg_h($opcion['texto']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <input type="text" name="respuesta[<?= $pid ?>]" value="<?= sg_h($respuestas[$pid]['texto'] ?? '') ?>" <?= (int) $pregunta['requerido'] === 1 ? 'required' : '' ?> <?= $disabledPreview ?>>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if (!$isPreview): ?>
                    <button class="survey-submit" type="submit">
                        <i data-feather="send"></i> Enviar respuesta
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
