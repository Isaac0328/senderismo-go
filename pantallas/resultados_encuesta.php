<?php
require_once __DIR__ . '/../configuracion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PERMISO_REQUERIDO = 'operaciones.encuestas';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/helpers.php';
require_once __DIR__ . '/../componentes/permisos.php';
require_once __DIR__ . '/../componentes/encuestas_bootstrap.php';
require_once __DIR__ . '/../componentes/encuestas_resultados.php';

encuestas_bootstrap($conn);
sg_seed_permission_catalog($conn);
sg_require_permission_action($conn, 'operaciones.encuestas', 'ver', BASE_URL . 'mantenimientos/mantenimiento_encuestas.php?vista=consultar');

$encuestaId = (int) ($_GET['encuesta_id'] ?? $_GET['id'] ?? 0);
$resultados = sg_encuesta_resultados_cargar($conn, $encuestaId);
if (!$resultados) {
    $_SESSION['encuesta_error'] = 'Encuesta no encontrada.';
    mysqli_close($conn);
    header('Location: ' . BASE_URL . 'mantenimientos/mantenimiento_encuestas.php?vista=consultar');
    exit;
}

function sre_h($value): string
{
    return sg_h($value);
}

function sre_fecha(?string $fecha, bool $hora = false): string
{
    return sg_fecha($fecha, $hora);
}

function sre_numero($numero, int $decimales = 1): string
{
    if ($numero === null || $numero === '') {
        return 'N/A';
    }
    return number_format((float) $numero, $decimales, '.', ',');
}

function sre_tipo(string $tipo): string
{
    return [
        'texto' => 'Respuesta corta',
        'textarea' => 'Texto abierto',
        'radio' => 'Una opcion',
        'checkbox' => 'Varias opciones',
        'select' => 'Lista desplegable',
        'escala' => 'Rango / escala',
        'numero' => 'Numero',
    ][$tipo] ?? 'Respuesta';
}

function sre_estado(string $estado): string
{
    return [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'cancelada' => 'Cancelada',
        'cerrada' => 'Cerrada',
    ][$estado] ?? ucfirst($estado);
}

$encuesta = $resultados['encuesta'];
$preguntas = $resultados['preguntas'];
$envios = $resultados['envios'];
$analisis = $resultados['analisis'];
$metricas = $resultados['metricas'];
$esAnonima = (int) $encuesta['anonima'] === 1;
$totalRespuestas = (int) $encuesta['total_respuestas'];
$exportBase = BASE_URL . 'procesos/proceso_exportar_resultados_encuesta.php?encuesta_id=' . $encuestaId . '&formato=';

$pageTitle = 'Resultados de Encuesta | Senderismo Go!';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/resultados_encuesta.css'];
$jsFiles = ['js/barra_navegacion.js', 'js/resultados_encuesta.js'];

include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>

<main class="survey-results-page">
    <section class="survey-results-hero">
        <div class="survey-results-heading">
            <a class="survey-results-back" href="<?= BASE_URL ?>mantenimientos/mantenimiento_encuestas.php?vista=consultar">
                <i data-feather="arrow-left"></i> Volver a encuestas
            </a>
            <span class="survey-results-kicker">Analisis de resultados</span>
            <h1><?= sre_h($encuesta['titulo']) ?></h1>
            <div class="survey-results-meta">
                <span class="status <?= sre_h($encuesta['estado']) ?>"><?= sre_h(sre_estado((string) $encuesta['estado'])) ?></span>
                <?php if (!empty($encuesta['sendero_nombre'])): ?>
                    <span><i data-feather="map-pin"></i><?= sre_h($encuesta['sendero_nombre']) ?></span>
                <?php endif; ?>
                <span><i data-feather="shield"></i><?= $esAnonima ? 'Respuestas anonimas' : 'Respuestas identificadas' ?></span>
            </div>
        </div>
        <div class="survey-results-export">
            <a href="<?= sre_h($exportBase . 'excel') ?>"><i data-feather="download"></i> Excel</a>
            <a href="<?= sre_h($exportBase . 'pdf') ?>" target="_blank" rel="noopener"><i data-feather="file-text"></i> PDF</a>
        </div>
    </section>

    <section class="survey-results-shell">
        <div class="survey-results-tabs" role="tablist" aria-label="Vistas de resultados">
            <button class="active" type="button" data-results-tab="resumen" aria-selected="true">
                <i data-feather="bar-chart-2"></i> Resumen
            </button>
            <button type="button" data-results-tab="respuestas" aria-selected="false">
                <i data-feather="message-square"></i> Respuestas
                <span><?= $totalRespuestas ?></span>
            </button>
        </div>

        <section class="survey-results-panel" data-results-panel="resumen">
            <div class="survey-result-kpis">
                <article>
                    <span class="survey-kpi-icon"><i data-feather="send"></i></span>
                    <div><small>Envios</small><strong><?= (int) $encuesta['total_envios'] ?></strong><p>Usuarios seleccionados</p></div>
                </article>
                <article>
                    <span class="survey-kpi-icon green"><i data-feather="check-circle"></i></span>
                    <div><small>Respuestas</small><strong><?= $totalRespuestas ?></strong><p><?= sre_numero($metricas['tasa_respuesta']) ?>% de participacion</p></div>
                </article>
                <article>
                    <span class="survey-kpi-icon amber"><i data-feather="clock"></i></span>
                    <div><small>Pendientes</small><strong><?= (int) $encuesta['total_pendientes'] ?></strong><p><?= (int) $encuesta['total_cancelados'] ?> cancelados</p></div>
                </article>
                <article>
                    <span class="survey-kpi-icon red"><i data-feather="activity"></i></span>
                    <div><small>Satisfaccion</small><strong><?= $metricas['satisfaccion'] === null ? 'N/A' : sre_numero($metricas['satisfaccion']) . '%' ?></strong><p>Segun puntuaciones</p></div>
                </article>
            </div>

            <div class="survey-results-section-head">
                <div>
                    <span>Preguntas</span>
                    <h2>Analisis por pregunta</h2>
                </div>
                <strong><?= count($preguntas) ?></strong>
            </div>

            <?php if (empty($preguntas)): ?>
                <div class="survey-results-empty"><i data-feather="help-circle"></i><strong>Sin preguntas</strong><p>Esta encuesta no tiene preguntas activas.</p></div>
            <?php else: ?>
                <div class="survey-question-results">
                    <?php foreach ($preguntas as $index => $pregunta): ?>
                        <?php
                        $pid = (int) $pregunta['id'];
                        $resumen = $analisis[$pid] ?? [];
                        $tipo = (string) $pregunta['tipo'];
                        $distribucion = $resumen['distribucion'] ?? [];
                        $textos = $resumen['textos'] ?? [];
                        ?>
                        <article class="survey-question-result">
                            <header>
                                <span class="survey-question-number"><?= $index + 1 ?></span>
                                <div>
                                    <small><?= sre_h(sre_tipo($tipo)) ?></small>
                                    <h3><?= sre_h($pregunta['pregunta']) ?></h3>
                                </div>
                                <div class="survey-question-count">
                                    <strong><?= (int) ($resumen['respondidas'] ?? 0) ?></strong>
                                    <span>respuestas</span>
                                </div>
                            </header>

                            <?php if (in_array($tipo, ['radio', 'checkbox', 'select', 'escala'], true)): ?>
                                <?php if (empty($distribucion)): ?>
                                    <p class="survey-no-answer">Todavia no hay respuestas para esta pregunta.</p>
                                <?php else: ?>
                                    <div class="survey-distribution">
                                        <?php foreach ($distribucion as $item): ?>
                                            <div class="survey-distribution-row">
                                                <div><span><?= sre_h($item['etiqueta']) ?></span><strong><?= (int) $item['cantidad'] ?> · <?= sre_numero($item['porcentaje']) ?>%</strong></div>
                                                <span class="survey-bar"><i style="width: <?= min(100, max(0, (float) $item['porcentaje'])) ?>%"></i></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($tipo === 'numero'): ?>
                                <div class="survey-number-summary">
                                    <div><span>Promedio</span><strong><?= sre_numero($resumen['promedio_numero'] ?? null, 2) ?></strong></div>
                                    <div><span>Minimo</span><strong><?= sre_numero($resumen['minimo_numero'] ?? null, 2) ?></strong></div>
                                    <div><span>Maximo</span><strong><?= sre_numero($resumen['maximo_numero'] ?? null, 2) ?></strong></div>
                                </div>
                            <?php else: ?>
                                <?php if (empty($textos)): ?>
                                    <p class="survey-no-answer">Todavia no hay respuestas para esta pregunta.</p>
                                <?php else: ?>
                                    <div class="survey-text-answers">
                                        <?php foreach ($textos as $texto): ?>
                                            <blockquote><?= nl2br(sre_h($texto)) ?></blockquote>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <footer>
                                <span><?= (int) ($resumen['omitidas'] ?? 0) ?> omitidas</span>
                                <?php if ((float) $pregunta['puntaje_max'] > 0 && ($resumen['promedio_puntaje'] ?? null) !== null): ?>
                                    <span>Promedio: <strong><?= sre_numero($resumen['promedio_puntaje'], 2) ?> / <?= sre_numero($pregunta['puntaje_max'], 2) ?></strong></span>
                                <?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="survey-results-panel" data-results-panel="respuestas" hidden>
            <div class="survey-results-section-head response-head">
                <div>
                    <span>Detalle</span>
                    <h2>Respuestas recibidas</h2>
                    <p><?= $esAnonima ? 'La identidad de los participantes esta protegida.' : 'Consulta las respuestas de cada participante.' ?></p>
                </div>
                <?php if (!empty($envios)): ?>
                    <label class="survey-response-search">
                        <i data-feather="search"></i>
                        <input type="search" placeholder="Buscar respuesta..." data-response-search>
                    </label>
                <?php endif; ?>
            </div>

            <?php if (empty($envios)): ?>
                <div class="survey-results-empty"><i data-feather="inbox"></i><strong>Aun no hay respuestas</strong><p>Las respuestas apareceran aqui cuando los usuarios completen la encuesta.</p></div>
            <?php else: ?>
                <div class="survey-response-list">
                    <?php foreach ($envios as $envio): ?>
                        <?php
                        $nombre = $esAnonima
                            ? 'Respuesta anonima #' . (int) $envio['numero_respuesta']
                            : trim((string) ($envio['nombre'] . ' ' . $envio['apellido']));
                        $nombre = $nombre !== '' ? $nombre : 'Usuario #' . (int) $envio['usuario_id'];
                        $searchText = strtolower($nombre . ' ' . ($envio['user'] ?? '') . ' ' . ($envio['email'] ?? ''));
                        ?>
                        <?php $responseBodyId = 'survey-response-body-' . (int) $envio['id']; ?>
                        <article class="survey-response-item" data-response-item data-search-text="<?= sre_h($searchText) ?>">
                            <header>
                                <span class="survey-response-avatar"><i data-feather="<?= $esAnonima ? 'shield' : 'user' ?>"></i></span>
                                <div>
                                    <h3><?= sre_h($nombre) ?></h3>
                                    <?php if (!$esAnonima): ?>
                                        <p>@<?= sre_h($envio['user'] ?: 'sin.usuario') ?> · <?= sre_h($envio['email'] ?: 'Sin correo') ?></p>
                                    <?php else: ?>
                                        <p>Identidad protegida</p>
                                    <?php endif; ?>
                                </div>
                                <time><i data-feather="calendar"></i><?= sre_h(sre_fecha($envio['respondido_at'], true)) ?></time>
                                <button class="survey-response-toggle" type="button" aria-expanded="true" aria-controls="<?= sre_h($responseBodyId) ?>" aria-label="Plegar respuesta" title="Plegar respuesta" data-response-toggle>
                                    <i data-feather="chevron-up"></i>
                                </button>
                            </header>
                            <div class="survey-response-body" id="<?= sre_h($responseBodyId) ?>" data-response-body>
                                <dl>
                                    <?php foreach ($preguntas as $pregunta): ?>
                                        <?php $filas = $envio['respuestas'][(int) $pregunta['id']] ?? []; ?>
                                        <div>
                                            <dt><?= sre_h($pregunta['pregunta']) ?></dt>
                                            <dd><?= nl2br(sre_h(sg_encuesta_resultado_valor($filas))) ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="survey-response-no-results" data-response-empty hidden>No hay respuestas que coincidan con la busqueda.</div>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php mysqli_close($conn); ?>
<?php include_once __DIR__ . '/../componentes/pie_pagina.php'; ?>
