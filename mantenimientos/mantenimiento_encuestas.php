<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'operaciones.encuestas';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';

require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/encuestas_bootstrap.php';

encuestas_bootstrap($conn);
sg_seed_permission_catalog($conn);

$pageTitle = 'Mantenimiento Encuestas | Senderismo Go!';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/encuestas_admin.css'];
$jsFiles = ['js/encuestas_admin.js'];

function enc_h($value): string
{
    return sg_h($value);
}

function enc_estado_label(string $estado): string
{
    return [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'cancelada' => 'Cancelada',
        'cerrada' => 'Cerrada',
    ][$estado] ?? 'Borrador';
}

function enc_destinatarios_label(string $value): string
{
    return [
        'sendero_asistentes' => 'Asistentes del sendero',
        'sendero_registrados' => 'Registrados del sendero',
        'todos_usuarios' => 'Todos los usuarios activos',
    ][$value] ?? 'Asistentes del sendero';
}

function enc_tipo_label(string $value): string
{
    return [
        'texto' => 'Respuesta corta',
        'textarea' => 'Parrafo',
        'radio' => 'Una opcion',
        'checkbox' => 'Varias opciones',
        'select' => 'Lista desplegable',
        'escala' => 'Rango / escala',
        'numero' => 'Numero',
    ][$value] ?? 'Respuesta corta';
}

function enc_escala_label_desde_texto(string $texto, int $valor): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }

    $texto = preg_replace('/^' . preg_quote((string) $valor, '/') . '\s*(?:-|:)?\s*/', '', $texto);
    return trim((string) $texto);
}

function enc_escala_config(array $pregunta): array
{
    if (isset($pregunta['escala_min']) || isset($pregunta['escala_max'])) {
        return [
            'min' => (int) ($pregunta['escala_min'] ?? 1),
            'max' => (int) ($pregunta['escala_max'] ?? 5),
            'min_label' => (string) ($pregunta['escala_min_label'] ?? ''),
            'max_label' => (string) ($pregunta['escala_max_label'] ?? ''),
        ];
    }

    $valores = [];
    $textos = [];
    foreach (preg_split('/\R/u', (string) ($pregunta['opciones_texto'] ?? '')) ?: [] as $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $linea, 2));
        $texto = $parts[0] ?? '';
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $valor = (int) round((float) $parts[1]);
        } elseif (preg_match('/^-?\d+/', $texto, $match)) {
            $valor = (int) $match[0];
        } else {
            continue;
        }
        $valores[] = $valor;
        $textos[$valor] = $texto;
    }

    if (empty($valores)) {
        return ['min' => 1, 'max' => 5, 'min_label' => 'Malo', 'max_label' => 'Excelente'];
    }

    $min = min($valores);
    $max = max($valores);
    return [
        'min' => $min,
        'max' => $max,
        'min_label' => enc_escala_label_desde_texto((string) ($textos[$min] ?? ''), $min),
        'max_label' => enc_escala_label_desde_texto((string) ($textos[$max] ?? ''), $max),
    ];
}

function enc_opciones_builder_items(string $raw): array
{
    $items = [];
    foreach (preg_split('/\R/u', $raw) ?: [] as $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $linea, 2));
        if (($parts[0] ?? '') !== '') {
            $items[] = $parts[0];
        }
    }

    return !empty($items) ? $items : [''];
}

$senderos = [];
$resSenderos = mysqli_query($conn, "
    SELECT id, nombre, fecha_sendero, estado
    FROM senderos
    WHERE activo = 1
    ORDER BY fecha_sendero DESC, nombre ASC
");
while ($resSenderos && $row = mysqli_fetch_assoc($resSenderos)) {
    $senderos[] = $row;
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$editPreguntas = [];
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM encuestas WHERE id = ? AND activo = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    if ($edit) {
        $stmt = mysqli_prepare($conn, "
            SELECT p.*, GROUP_CONCAT(CONCAT(o.texto, '|', o.puntuacion) ORDER BY o.orden SEPARATOR '\n') AS opciones_texto
            FROM encuesta_preguntas p
            LEFT JOIN encuesta_opciones o ON o.pregunta_id = p.id AND o.activo = 1
            WHERE p.encuesta_id = ? AND p.activo = 1
            GROUP BY p.id
            ORDER BY p.orden ASC, p.id ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $editId);
        mysqli_stmt_execute($stmt);
        $resPreguntas = mysqli_stmt_get_result($stmt);
        while ($resPreguntas && $row = mysqli_fetch_assoc($resPreguntas)) {
            $editPreguntas[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

$formState = $_SESSION['encuesta_form_state'] ?? null;
unset($_SESSION['encuesta_form_state']);

if (is_array($formState) && (string) ($formState['action'] ?? '') === 'save') {
    $stateId = (int) ($formState['id'] ?? 0);
    if ($stateId > 0 && $editId <= 0) {
        $editId = $stateId;
    }

    $edit = [
        'id' => $stateId,
        'titulo' => (string) ($formState['titulo'] ?? ''),
        'descripcion' => (string) ($formState['descripcion'] ?? ''),
        'sendero_id' => (int) ($formState['sendero_id'] ?? 0),
        'destinatarios' => (string) ($formState['destinatarios'] ?? 'sendero_asistentes'),
        'fecha_cierre' => (string) ($formState['fecha_cierre'] ?? ''),
        'anonima' => !empty($formState['anonima']) ? 1 : 0,
        'permite_editar_respuesta' => !empty($formState['permite_editar_respuesta']) ? 1 : 0,
        'estado' => $stateId > 0 ? (string) ($edit['estado'] ?? 'borrador') : 'borrador',
    ];

    $editPreguntas = [];
    $statePreguntas = $formState['preguntas'] ?? [];
    if (is_array($statePreguntas)) {
        foreach ($statePreguntas as $preguntaState) {
            if (!is_array($preguntaState)) {
                continue;
            }
            $editPreguntas[] = [
                'pregunta' => (string) ($preguntaState['pregunta'] ?? ''),
                'ayuda' => (string) ($preguntaState['ayuda'] ?? ''),
                'tipo' => (string) ($preguntaState['tipo'] ?? 'texto'),
                'requerido' => !empty($preguntaState['requerido']) ? 1 : 0,
                'puntaje_max' => (string) ($preguntaState['puntaje_max'] ?? 0),
                'opciones_texto' => (string) ($preguntaState['opciones'] ?? ''),
                'escala_min' => (string) ($preguntaState['escala_min'] ?? 1),
                'escala_max' => (string) ($preguntaState['escala_max'] ?? 5),
                'escala_min_label' => (string) ($preguntaState['escala_min_label'] ?? ''),
                'escala_max_label' => (string) ($preguntaState['escala_max_label'] ?? ''),
            ];
        }
    }
}

$isEditing = (int) ($edit['id'] ?? 0) > 0;
$activePanel = (string) ($_GET['vista'] ?? '');
if ($isEditing || is_array($formState)) {
    $activePanel = 'crear';
}
if (!in_array($activePanel, ['crear', 'consultar'], true)) {
    $activePanel = 'crear';
}

if (empty($editPreguntas)) {
    $editPreguntas[] = [
        'pregunta' => '',
        'ayuda' => '',
        'tipo' => 'escala',
        'requerido' => 1,
        'puntaje_max' => 5,
        'opciones_texto' => '',
        'escala_min' => 1,
        'escala_max' => 5,
        'escala_min_label' => 'Malo',
        'escala_max_label' => 'Excelente',
    ];
}

$encuestas = [];
$resEncuestas = mysqli_query($conn, "
    SELECT e.*,
           s.nombre AS sendero_nombre,
           COUNT(DISTINCT p.id) AS preguntas,
           COUNT(DISTINCT ee.id) AS enviados,
           COUNT(DISTINCT CASE WHEN ee.estado = 'respondida' THEN ee.id END) AS respondidas
    FROM encuestas e
    LEFT JOIN senderos s ON s.id = e.sendero_id
    LEFT JOIN encuesta_preguntas p ON p.encuesta_id = e.id AND p.activo = 1
    LEFT JOIN encuesta_envios ee ON ee.encuesta_id = e.id
    WHERE e.activo = 1
    GROUP BY e.id
    ORDER BY e.updated_at DESC, e.id DESC
");
while ($resEncuestas && $row = mysqli_fetch_assoc($resEncuestas)) {
    $encuestas[] = $row;
}

$canAdd = sg_has_permission_action($conn, 'operaciones.encuestas', 'agregar');
$canEdit = sg_has_permission_action($conn, 'operaciones.encuestas', 'editar');
$canDelete = sg_has_permission_action($conn, 'operaciones.encuestas', 'eliminar');

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="survey-admin-page">
    <section class="survey-admin-hero">
        <div>
            <span class="survey-kicker">Experiencia del cliente</span>
            <h1>Mantenimiento de encuestas</h1>
            <p>Crea formularios de satisfaccion, enlazalos a senderos y envialos cuando quieras que aparezcan en el perfil del usuario.</p>
        </div>
        <div class="survey-hero-actions">
            <a class="survey-btn ghost" href="<?= BASE_URL ?>pantallas/panel_administrativo.php">
                <i data-feather="arrow-left"></i> Volver al panel
            </a>
            <?php if ($isEditing): ?>
                <a class="survey-btn light" href="<?= BASE_URL ?>mantenimientos/mantenimiento_encuestas.php">
                    <i data-feather="plus"></i> Nueva encuesta
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($_SESSION['encuesta_success'])): ?>
        <div class="survey-alert success"><?= enc_h($_SESSION['encuesta_success']) ?></div>
        <?php unset($_SESSION['encuesta_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['encuesta_error'])): ?>
        <div class="survey-alert error"><?= enc_h($_SESSION['encuesta_error']) ?></div>
        <?php unset($_SESSION['encuesta_error']); ?>
    <?php endif; ?>

    <section class="survey-admin-grid survey-tab-panel" data-survey-panel="crear" <?= $activePanel !== 'crear' ? 'hidden' : '' ?>>
        <form
            class="survey-builder-card"
            method="POST"
            action="<?= BASE_URL ?>procesos/proceso_encuestas.php"
            novalidate
            autocomplete="off"
            data-clear-url="<?= BASE_URL ?>mantenimientos/mantenimiento_encuestas.php"
            data-is-editing="<?= $isEditing ? '1' : '0' ?>"
            data-has-restored-state="<?= is_array($formState) ? '1' : '0' ?>"
        >
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

            <div class="survey-admin-tabs" data-survey-tabs role="tablist" aria-label="Opciones de mantenimiento de encuestas">
                <button type="button" class="<?= $activePanel === 'crear' ? 'active' : '' ?>" data-survey-tab="crear" aria-selected="<?= $activePanel === 'crear' ? 'true' : 'false' ?>">
                    Crear
                </button>
                <button type="button" class="<?= $activePanel === 'consultar' ? 'active' : '' ?>" data-survey-tab="consultar" aria-selected="<?= $activePanel === 'consultar' ? 'true' : 'false' ?>">
                    Consultar
                </button>
            </div>

            <div class="survey-card-head">
                <div>
                    <span class="survey-kicker"><?= $isEditing ? 'Editar formulario' : 'Nueva encuesta' ?></span>
                    <h2><?= $isEditing ? 'Constructor de encuesta' : 'Crea tu formulario' ?></h2>
                </div>
                <?php if ($isEditing): ?>
                    <span class="survey-status <?= enc_h($edit['estado']) ?>"><?= enc_estado_label((string) $edit['estado']) ?></span>
                <?php endif; ?>
            </div>

            <div class="survey-form-grid">
                <label>
                    Titulo *
                    <input type="text" name="titulo" maxlength="180" required value="<?= enc_h($edit['titulo'] ?? '') ?>" placeholder="Ej: Encuesta de satisfaccion">
                </label>
                <label>
                    Sendero enlazado
                    <select name="sendero_id">
                        <option value="0">Sin sendero especifico</option>
                        <?php foreach ($senderos as $sendero): ?>
                            <option value="<?= (int) $sendero['id'] ?>" <?= (int) ($edit['sendero_id'] ?? 0) === (int) $sendero['id'] ? 'selected' : '' ?>>
                                <?= enc_h($sendero['nombre']) ?> - <?= sg_fecha($sendero['fecha_sendero']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="full">
                    Descripcion
                    <textarea name="descripcion" rows="3" placeholder="Texto introductorio para el usuario."><?= enc_h($edit['descripcion'] ?? '') ?></textarea>
                </label>
                <label>
                    Destinatarios al enviar
                    <select name="destinatarios">
                        <?php foreach (['sendero_asistentes', 'sendero_registrados', 'todos_usuarios'] as $dest): ?>
                            <option value="<?= enc_h($dest) ?>" <?= (string) ($edit['destinatarios'] ?? 'sendero_asistentes') === $dest ? 'selected' : '' ?>>
                                <?= enc_destinatarios_label($dest) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Fecha de cierre
                    <input type="date" name="fecha_cierre" value="<?= enc_h(!empty($edit['fecha_cierre']) ? date('Y-m-d', strtotime($edit['fecha_cierre'])) : '') ?>">
                </label>
                <label class="survey-check">
                    <input type="checkbox" name="anonima" value="1" <?= !empty($edit['anonima']) ? 'checked' : '' ?>>
                    Respuestas anonimas
                </label>
                <label class="survey-check">
                    <input type="checkbox" name="permite_editar_respuesta" value="1" <?= !empty($edit['permite_editar_respuesta']) ? 'checked' : '' ?>>
                    Permitir editar respuesta
                </label>
            </div>

            <div class="survey-builder-head">
                <div>
                    <span class="survey-kicker">Preguntas</span>
                    <h3>Elementos del formulario</h3>
                </div>
                <button class="survey-btn light" type="button" data-add-question>
                    <i data-feather="plus"></i> Agregar pregunta
                </button>
            </div>

            <div class="survey-question-list" data-question-list>
                <?php foreach ($editPreguntas as $i => $pregunta): ?>
                    <?php $escalaConfig = enc_escala_config($pregunta); ?>
                    <article class="survey-question <?= $i > 0 ? 'is-collapsed' : '' ?>" data-question-card>
                        <div class="question-top">
                            <div class="question-title-wrap">
                                <strong>Pregunta <span data-question-number><?= $i + 1 ?></span></strong>
                                <small data-question-summary><?= enc_h(trim((string) ($pregunta['pregunta'] ?? '')) !== '' ? (string) $pregunta['pregunta'] : enc_tipo_label((string) ($pregunta['tipo'] ?? 'texto'))) ?></small>
                            </div>
                            <div class="question-actions">
                                <button type="button" class="survey-icon-btn light" data-toggle-question aria-label="Plegar o desplegar pregunta" aria-expanded="<?= $i > 0 ? 'false' : 'true' ?>">
                                    <i data-feather="chevron-down"></i>
                                </button>
                                <button type="button" class="survey-icon-btn danger" data-remove-question aria-label="Eliminar pregunta">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </div>
                        </div>
                        <div class="survey-form-grid" data-question-body>
                            <label class="question-half">
                                Pregunta *
                                <input type="text" name="preguntas[<?= $i ?>][pregunta]" required value="<?= enc_h($pregunta['pregunta'] ?? '') ?>" placeholder="Escribe la pregunta">
                            </label>
                            <label class="question-half">
                                Ayuda o descripcion
                                <input type="text" name="preguntas[<?= $i ?>][ayuda]" value="<?= enc_h($pregunta['ayuda'] ?? '') ?>" placeholder="Opcional">
                            </label>
                            <label>
                                Tipo de respuesta
                                <select name="preguntas[<?= $i ?>][tipo]" data-question-type>
                                    <?php foreach (['texto', 'textarea', 'radio', 'checkbox', 'select', 'escala', 'numero'] as $tipo): ?>
                                        <option value="<?= enc_h($tipo) ?>" <?= (string) ($pregunta['tipo'] ?? '') === $tipo ? 'selected' : '' ?>><?= enc_tipo_label($tipo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                Puntaje max.
                                <input type="number" step="0.01" min="0" name="preguntas[<?= $i ?>][puntaje_max]" value="<?= enc_h($pregunta['puntaje_max'] ?? 0) ?>" data-score-max>
                            </label>
                            <label class="survey-check">
                                <input type="checkbox" name="preguntas[<?= $i ?>][requerido]" value="1" <?= (int) ($pregunta['requerido'] ?? 1) === 1 ? 'checked' : '' ?>>
                                Obligatoria
                            </label>
                            <div class="full survey-options-builder" data-options-wrap>
                                <div class="survey-options-head">
                                    <strong>Opciones de respuesta</strong>
                                    <small>Agrega una opcion por fila. El orden se usara al mostrar la encuesta.</small>
                                </div>
                                <input type="hidden" name="preguntas[<?= $i ?>][opciones]" value="<?= enc_h($pregunta['opciones_texto'] ?? '') ?>" data-options-hidden>
                                <div class="survey-options-list" data-options-list>
                                    <?php foreach (enc_opciones_builder_items((string) ($pregunta['opciones_texto'] ?? '')) as $opcionIndex => $opcionTexto): ?>
                                        <div class="survey-option-row" data-option-row>
                                            <span class="survey-option-number" data-option-number><?= $opcionIndex + 1 ?> -</span>
                                            <input type="text" value="<?= enc_h($opcionTexto) ?>" placeholder="Opcion <?= $opcionIndex + 1 ?>" data-option-input>
                                            <button type="button" class="survey-option-mini add" data-add-option aria-label="Agregar opcion">+</button>
                                            <button type="button" class="survey-option-mini remove" data-remove-option aria-label="Quitar opcion">&times;</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="full survey-scale-config" data-scale-wrap>
                                <div class="survey-scale-head">
                                    <strong>Configurar rango</strong>
                                    <small>Define el inicio, el final y el significado de cada extremo.</small>
                                </div>
                                <div class="survey-scale-grid">
                                    <label>
                                        Desde
                                        <input type="number" step="1" min="0" max="100" name="preguntas[<?= $i ?>][escala_min]" value="<?= enc_h($escalaConfig['min']) ?>" data-scale-min>
                                    </label>
                                    <label>
                                        Hasta
                                        <input type="number" step="1" min="1" max="100" name="preguntas[<?= $i ?>][escala_max]" value="<?= enc_h($escalaConfig['max']) ?>" data-scale-max>
                                    </label>
                                    <label>
                                        Texto inicial
                                        <input type="text" maxlength="80" name="preguntas[<?= $i ?>][escala_min_label]" value="<?= enc_h($escalaConfig['min_label']) ?>" placeholder="Ej: Malo" data-scale-min-label>
                                    </label>
                                    <label>
                                        Texto final
                                        <input type="text" maxlength="80" name="preguntas[<?= $i ?>][escala_max_label]" value="<?= enc_h($escalaConfig['max_label']) ?>" placeholder="Ej: Excelente" data-scale-max-label>
                                    </label>
                                </div>
                                <div class="survey-scale-preview" aria-hidden="true">
                                    <input type="range" value="<?= enc_h($escalaConfig['min']) ?>" min="<?= enc_h($escalaConfig['min']) ?>" max="<?= enc_h($escalaConfig['max']) ?>" disabled data-scale-preview>
                                    <div class="survey-scale-labels">
                                        <span data-scale-preview-min><?= enc_h($escalaConfig['min']) ?> <?= enc_h($escalaConfig['min_label']) ?></span>
                                        <span data-scale-preview-max><?= enc_h($escalaConfig['max']) ?> <?= enc_h($escalaConfig['max_label']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="survey-actions">
                <button class="survey-btn primary" type="submit" <?= ($isEditing ? !$canEdit : !$canAdd) ? 'disabled' : '' ?>>
                    <i data-feather="save"></i> Guardar encuesta
                </button>
                <button class="survey-btn light" type="button" data-clear-survey>
                    <i data-feather="refresh-ccw"></i> Limpiar
                </button>
            </div>
        </form>
    </section>

    <section class="survey-tab-panel survey-management-panel" data-survey-panel="consultar" <?= $activePanel !== 'consultar' ? 'hidden' : '' ?>>
        <aside class="survey-list-card">
            <div class="survey-admin-tabs" data-survey-tabs role="tablist" aria-label="Opciones de mantenimiento de encuestas">
                <button type="button" class="<?= $activePanel === 'crear' ? 'active' : '' ?>" data-survey-tab="crear" aria-selected="<?= $activePanel === 'crear' ? 'true' : 'false' ?>">
                    Crear
                </button>
                <button type="button" class="<?= $activePanel === 'consultar' ? 'active' : '' ?>" data-survey-tab="consultar" aria-selected="<?= $activePanel === 'consultar' ? 'true' : 'false' ?>">
                    Consultar
                </button>
            </div>

            <div class="survey-card-head">
                <div>
                    <span class="survey-kicker">Gestion</span>
                    <h2>Encuestas creadas</h2>
                </div>
                <span class="survey-count"><?= count($encuestas) ?></span>
            </div>
            <input class="survey-search" type="search" placeholder="Buscar por titulo, sendero o estado..." data-survey-search>

            <div class="survey-list" data-survey-list>
                <?php if (empty($encuestas)): ?>
                    <div class="survey-empty">
                        <i data-feather="clipboard"></i>
                        <strong>Aun no hay encuestas</strong>
                        <p>Crea el primer formulario para medir satisfaccion y experiencia.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($encuestas as $encuesta): ?>
                    <article class="survey-item" data-survey-row>
                        <?php if ($canEdit || $canDelete): ?>
                            <details class="survey-item-menu">
                                <summary aria-label="Mas acciones de la encuesta">
                                    <i data-feather="more-horizontal"></i>
                                </summary>
                                <div class="survey-item-menu-list">
                                    <?php if ($canEdit): ?>
                                        <a href="<?= BASE_URL ?>mantenimientos/mantenimiento_encuestas.php?edit=<?= (int) $encuesta['id'] ?>">
                                            <i data-feather="edit-3"></i> Editar
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="POST" action="<?= BASE_URL ?>procesos/proceso_encuestas.php" data-confirm="Eliminar esta encuesta del listado activo?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $encuesta['id'] ?>">
                                            <button type="submit">
                                                <i data-feather="trash-2"></i> Eliminar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                        <div>
                            <span class="survey-status <?= enc_h($encuesta['estado']) ?>"><?= enc_estado_label((string) $encuesta['estado']) ?></span>
                            <h3><?= enc_h($encuesta['titulo']) ?></h3>
                            <p><?= enc_h($encuesta['sendero_nombre'] ?: 'Sin sendero especifico') ?></p>
                            <div class="survey-mini-stats">
                                <span><?= (int) $encuesta['preguntas'] ?> preguntas</span>
                                <span><?= (int) $encuesta['enviados'] ?> envios</span>
                                <span><?= (int) $encuesta['respondidas'] ?> respuestas</span>
                            </div>
                        </div>
                        <div class="survey-item-actions">
                            <a class="survey-btn preview" href="<?= BASE_URL ?>pantallas/encuesta.php?preview=1&encuesta_id=<?= (int) $encuesta['id'] ?>">
                                <i data-feather="eye"></i> Ver encuesta
                            </a>
                            <a class="survey-btn results" href="<?= BASE_URL ?>pantallas/resultados_encuesta.php?encuesta_id=<?= (int) $encuesta['id'] ?>">
                                <i data-feather="bar-chart-2"></i> Resultados
                            </a>
                            <?php if ($canEdit && in_array($encuesta['estado'], ['borrador', 'cancelada'], true)): ?>
                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_encuestas.php" data-confirm="Enviar esta encuesta ahora? Los usuarios seleccionados la veran en su perfil.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="send">
                                    <input type="hidden" name="id" value="<?= (int) $encuesta['id'] ?>">
                                    <button class="survey-btn primary" type="submit">
                                        <i data-feather="send"></i> Enviar
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canEdit && $encuesta['estado'] === 'enviada'): ?>
                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_encuestas.php" data-confirm="Cerrar esta encuesta? Dejaria de estar disponible para nuevas respuestas.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="close">
                                    <input type="hidden" name="id" value="<?= (int) $encuesta['id'] ?>">
                                    <button class="survey-btn warn" type="submit">
                                        <i data-feather="lock"></i> Cerrar
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canEdit && $encuesta['estado'] === 'cerrada'): ?>
                                <form method="POST" action="<?= BASE_URL ?>procesos/proceso_encuestas.php" data-confirm="Reabrir esta encuesta? Los participantes que no hayan respondido podran verla nuevamente.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reopen">
                                    <input type="hidden" name="id" value="<?= (int) $encuesta['id'] ?>">
                                    <button class="survey-btn primary" type="submit">
                                        <i data-feather="unlock"></i> Reabrir
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </aside>
    </section>
</main>

<dialog class="survey-save-dialog" data-survey-save-dialog aria-labelledby="survey-save-dialog-title">
    <div class="survey-save-dialog-icon" aria-hidden="true">
        <i data-feather="save"></i>
    </div>
    <div>
        <span class="survey-save-dialog-kicker">Confirmar actualizacion</span>
        <h2 id="survey-save-dialog-title">Guardar cambios</h2>
        <p>¿Deseas guardar los cambios realizados en esta encuesta?</p>
    </div>
    <div class="survey-save-dialog-actions">
        <button class="survey-btn light" type="button" data-cancel-survey-save>Cancelar</button>
        <button class="survey-btn primary" type="button" data-confirm-survey-save>
            <i data-feather="check"></i> Guardar cambios
        </button>
    </div>
</dialog>

<template id="survey-question-template">
    <article class="survey-question" data-question-card>
        <div class="question-top">
            <div class="question-title-wrap">
                <strong>Pregunta <span data-question-number></span></strong>
                <small data-question-summary>Nueva pregunta</small>
            </div>
            <div class="question-actions">
                <button type="button" class="survey-icon-btn light" data-toggle-question aria-label="Plegar o desplegar pregunta" aria-expanded="true">
                    <i data-feather="chevron-down"></i>
                </button>
                <button type="button" class="survey-icon-btn danger" data-remove-question aria-label="Eliminar pregunta">
                    <i data-feather="trash-2"></i>
                </button>
            </div>
        </div>
        <div class="survey-form-grid" data-question-body>
            <label class="question-half">
                Pregunta *
                <input type="text" data-name="pregunta" required placeholder="Escribe la pregunta">
            </label>
            <label class="question-half">
                Ayuda o descripcion
                <input type="text" data-name="ayuda" placeholder="Opcional">
            </label>
            <label>
                Tipo de respuesta
                <select data-name="tipo" data-question-type>
                    <?php foreach (['texto', 'textarea', 'radio', 'checkbox', 'select', 'escala', 'numero'] as $tipo): ?>
                        <option value="<?= enc_h($tipo) ?>"><?= enc_tipo_label($tipo) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Puntaje max.
                <input type="number" step="0.01" min="0" data-name="puntaje_max" value="5" data-score-max>
            </label>
            <label class="survey-check">
                <input type="checkbox" data-name="requerido" value="1" checked>
                Obligatoria
            </label>
            <div class="full survey-options-builder" data-options-wrap>
                <div class="survey-options-head">
                    <strong>Opciones de respuesta</strong>
                    <small>Agrega una opcion por fila. El orden se usara al mostrar la encuesta.</small>
                </div>
                <input type="hidden" data-name="opciones" value="" data-options-hidden>
                <div class="survey-options-list" data-options-list>
                    <div class="survey-option-row" data-option-row>
                        <span class="survey-option-number" data-option-number>1 -</span>
                        <input type="text" placeholder="Opcion 1" data-option-input>
                        <button type="button" class="survey-option-mini add" data-add-option aria-label="Agregar opcion">+</button>
                        <button type="button" class="survey-option-mini remove" data-remove-option aria-label="Quitar opcion">&times;</button>
                    </div>
                </div>
            </div>
            <div class="full survey-scale-config" data-scale-wrap>
                <div class="survey-scale-head">
                    <strong>Configurar rango</strong>
                    <small>Define el inicio, el final y el significado de cada extremo.</small>
                </div>
                <div class="survey-scale-grid">
                    <label>
                        Desde
                        <input type="number" step="1" min="0" max="100" data-name="escala_min" value="1" data-scale-min>
                    </label>
                    <label>
                        Hasta
                        <input type="number" step="1" min="1" max="100" data-name="escala_max" value="5" data-scale-max>
                    </label>
                    <label>
                        Texto inicial
                        <input type="text" maxlength="80" data-name="escala_min_label" value="Malo" placeholder="Ej: Malo" data-scale-min-label>
                    </label>
                    <label>
                        Texto final
                        <input type="text" maxlength="80" data-name="escala_max_label" value="Excelente" placeholder="Ej: Excelente" data-scale-max-label>
                    </label>
                </div>
                <div class="survey-scale-preview" aria-hidden="true">
                    <input type="range" value="1" min="1" max="5" disabled data-scale-preview>
                    <div class="survey-scale-labels">
                        <span data-scale-preview-min>1 Malo</span>
                        <span data-scale-preview-max>5 Excelente</span>
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>

<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
?>
