<?php
// This view is only included after the authenticated home has loaded its data.
if (!isset($inicioUsuario)) {
    return;
}
$reservaInicio = $inicioUsuario['reserva'];
$nivelInicio = $inicioUsuario['nivel'];
$statsInicio = $inicioUsuario['stats'];
$senderosInicio = $inicioUsuario['senderos'];
$destacadoInicio = $reservaInicio ?: ($senderosInicio[0] ?? null);
$perfilInicioUrl = sg_clean_url('pantallas/mi_perfil.php');
$galeriaPersonal = $inicioUsuario['inspiracion'];
$horaInicio = (int) date('G');
$saludoInicio = $horaInicio < 12 ? 'Buenos d&iacute;as' : ($horaInicio < 19 ? 'Buenas tardes' : 'Buenas noches');
$actividadesInicio = [];
if (!empty($inicioEncuestaPrincipal)) {
    $totalEncuestasInicio = (int) ($sgEncuestasUsuarioResumen['total'] ?? 1);
    $actividadesInicio[] = [
        'tipo' => 'survey', 'icono' => 'message-square', 'etiqueta' => $totalEncuestasInicio === 1 ? 'Encuesta pendiente' : $totalEncuestasInicio . ' encuestas pendientes',
        'titulo' => $inicioEncuestaPrincipal['titulo'], 'texto' => 'Tu opinion ayuda a mejorar cada experiencia.',
        'accion' => 'Llenar encuesta', 'url' => sg_clean_url('pantallas/encuesta.php?envio_id=' . (int) $inicioEncuestaPrincipal['envio_id']),
    ];
}
if ($reservaInicio) {
    $actividadesInicio[] = [
        'tipo' => 'reservation', 'icono' => 'calendar', 'etiqueta' => 'Proxima salida',
        'titulo' => $reservaInicio['nombre'],
        'texto' => sg_inicio_fecha($reservaInicio['fecha_sendero']) . (!empty($reservaInicio['nombre_punto']) ? ' · ' . $reservaInicio['nombre_punto'] : ''),
        'accion' => 'Ver mi registro', 'url' => sg_clean_url('pantallas/registro_sendero.php?id=' . (int) $reservaInicio['id']),
    ];
}
if (empty($inicioUsuario['perfil_completo'])) {
    $actividadesInicio[] = [
        'tipo' => 'profile', 'icono' => 'user-check', 'etiqueta' => 'Perfil pendiente',
        'titulo' => 'Completa tus datos de seguridad', 'texto' => 'Agiliza tus proximas reservas y prepara cada salida.',
        'accion' => 'Completar perfil', 'url' => $perfilInicioUrl,
    ];
}
if (!$actividadesInicio) {
    $actividadesInicio[] = [
        'tipo' => 'clear', 'icono' => 'check-circle', 'etiqueta' => 'Todo al dia',
        'titulo' => 'No tienes tareas pendientes', 'texto' => 'Explora nuevas rutas y prepara tu proxima aventura.',
        'accion' => 'Ver agenda', 'url' => sg_clean_url('pantallas/senderos.php'),
    ];
}
?>
<div class="home-member-wrap">
    <header class="home-welcome">
        <div class="home-welcome-copy">
            <span class="home-eyebrow">Tu espacio senderista</span>
            <h1><?= $saludoInicio ?>, <?= inicio_h($inicioUsuario['nombre']) ?>.</h1>
            <p><?= $reservaInicio ? 'Tu pr&oacute;xima aventura ya tiene fecha.' : 'Siempre hay un nuevo camino por descubrir.' ?></p>
            <div class="home-welcome-summary" aria-label="Resumen de tu actividad">
                <a class="home-welcome-metric" href="#home-passport">
                    <i data-feather="award" aria-hidden="true"></i>
                    <span><small>Tu nivel</small><strong><?= inicio_h($nivelInicio['nombre']) ?></strong></span>
                </a>
                <a class="home-welcome-metric" href="#home-passport">
                    <i data-feather="map" aria-hidden="true"></i>
                    <span><small>Completados</small><strong><?= (int) $statsInicio['rutas_asistidas'] ?> senderos</strong></span>
                </a>
                <a class="home-welcome-metric" href="#home-journey">
                    <i data-feather="navigation" aria-hidden="true"></i>
                    <span><small>Recorrido</small><strong><?= number_format((float) $statsInicio['km_asistidos'], 1) ?> km</strong></span>
                </a>
            </div>
        </div>
        <div class="home-welcome-actions">
            <button class="home-icon-button" type="button" data-motion-toggle aria-pressed="false" aria-label="Pausar animaciones" title="Pausar animaciones" hidden><i data-feather="pause" aria-hidden="true"></i></button>
            <a class="home-link" href="<?= inicio_h($perfilInicioUrl) ?>"><i data-feather="user" aria-hidden="true"></i> Mi perfil <i data-feather="arrow-up-right" aria-hidden="true"></i></a>
        </div>
    </header>
    <section class="home-activity" aria-labelledby="home-activity-title" data-reveal>
        <div class="home-activity-heading">
            <div><span class="home-eyebrow">Centro de actividad</span><h2 id="home-activity-title">Lo importante para ti</h2></div>
            <i data-feather="bell" aria-hidden="true"></i>
        </div>
        <div class="home-activity-grid">
            <?php foreach ($actividadesInicio as $actividadInicio): ?>
                <a class="home-activity-item is-<?= inicio_h($actividadInicio['tipo']) ?>" href="<?= inicio_h($actividadInicio['url']) ?>">
                    <span class="home-activity-icon"><i data-feather="<?= inicio_h($actividadInicio['icono']) ?>" aria-hidden="true"></i></span>
                    <span class="home-activity-copy"><small><?= inicio_h($actividadInicio['etiqueta']) ?></small><strong><?= inicio_h($actividadInicio['titulo']) ?></strong><span><?= inicio_h($actividadInicio['texto']) ?></span></span>
                    <span class="home-activity-action"><?= inicio_h($actividadInicio['accion']) ?> <i data-feather="arrow-right" aria-hidden="true"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php if ($destacadoInicio): ?>
    <?php
    $diasInicio = max(0, (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($destacadoInicio['fecha_sendero']))->format('%r%a'));
    $detalleInicioUrl = sg_clean_url('pantallas/senderos_detalle.php?id=' . (int) $destacadoInicio['id']);
    ?>
    <section class="home-adventure" aria-labelledby="home-adventure-title">
        <img class="home-adventure-image" src="<?= inicio_h(sg_inicio_imagen($destacadoInicio)) ?>" alt="<?= inicio_h($destacadoInicio['nombre']) ?>" fetchpriority="high">
        <div class="home-adventure-shade"></div>
        <div class="home-member-wrap home-adventure-content">
            <div class="home-adventure-top">
                <span class="home-pill"><?= $reservaInicio ? 'Tu pr&oacute;xima aventura' : 'Pr&oacute;xima salida' ?></span>
                <span class="home-countdown"><i data-feather="clock" aria-hidden="true"></i> <?= $diasInicio === 0 ? 'La aventura es hoy' : ($diasInicio === 1 ? 'Salimos ma&ntilde;ana' : 'Faltan ' . $diasInicio . ' d&iacute;as') ?></span>
            </div>
            <h2 id="home-adventure-title"><?= inicio_h($destacadoInicio['nombre']) ?></h2>
            <div class="home-adventure-meta">
                <span><i data-feather="calendar" aria-hidden="true"></i> <?= inicio_h(sg_inicio_fecha($destacadoInicio['fecha_sendero'])) ?></span>
                <span><i data-feather="map-pin" aria-hidden="true"></i> <?= inicio_h($destacadoInicio['provincia'] ?: $destacadoInicio['lugar']) ?></span>
            </div>
            <?php if ($reservaInicio && !empty($reservaInicio['nombre_punto'])): ?>
                <p class="home-meeting">Encuentro: <?= inicio_h($reservaInicio['nombre_punto']) ?><?php if ($reservaInicio['hora_encuentro']): ?> &middot; <?= inicio_h(date('h:i A', strtotime($reservaInicio['hora_encuentro']))) ?><?php endif; ?></p>
            <?php endif; ?>
            <div class="home-actions">
                <?php if ($reservaInicio): ?>
                    <a class="home-button" href="<?= inicio_h(sg_clean_url('pantallas/registro_sendero.php?id=' . (int) $reservaInicio['id'])) ?>">Ver mi registro <i data-feather="arrow-right" aria-hidden="true"></i></a>
                    <a class="home-light-link" href="<?= inicio_h($detalleInicioUrl) ?>">Ver detalles del sendero</a>
                <?php else: ?>
                    <a class="home-button" href="<?= inicio_h($detalleInicioUrl) ?>">Descubrir esta salida <i data-feather="arrow-right" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php elseif ($galeriaPersonal): ?>
    <section class="home-adventure home-discovery" aria-label="Paisajes para tu pr&oacute;xima aventura" aria-roledescription="carrusel" data-home-gallery>
        <?php foreach ($galeriaPersonal as $i => $paisaje): ?>
            <div class="home-slide" data-home-slide role="group" aria-roledescription="diapositiva" aria-label="<?= $i + 1 ?> de <?= count($galeriaPersonal) ?>" <?= $i > 0 ? 'hidden' : '' ?>>
                <img class="home-adventure-image" src="<?= inicio_h(sg_inicio_imagen($paisaje)) ?>" alt="<?= inicio_h($paisaje['nombre']) ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                <div class="home-adventure-shade"></div>
                <div class="home-member-wrap home-adventure-content">
                    <span class="home-pill">Insp&iacute;rate &middot; Ruta visitada</span>
                    <h2><?= inicio_h($paisaje['nombre']) ?></h2>
                    <p><i data-feather="map-pin" aria-hidden="true"></i> <?= inicio_h($paisaje['provincia'] ?: $paisaje['lugar']) ?><?php if ($paisaje['distancia_km'] !== null): ?> &middot; <?= number_format((float) $paisaje['distancia_km'], 1) ?> km<?php endif; ?></p>
                    <div class="home-actions"><a class="home-button" href="<?= inicio_h(sg_clean_url('pantallas/senderos_detalle.php?id=' . (int) $paisaje['id'])) ?>">Conocer esta ruta <i data-feather="arrow-right" aria-hidden="true"></i></a><span class="home-slide-note">Nuevas fechas, pr&oacute;ximamente</span></div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="home-member-wrap home-gallery-controls" hidden>
            <span data-gallery-position aria-live="polite" aria-atomic="true">1 / <?= count($galeriaPersonal) ?></span>
            <button type="button" class="home-icon-button" data-gallery-step="-1" aria-label="Ruta anterior" title="Ruta anterior"><i data-feather="arrow-left" aria-hidden="true"></i></button>
            <button type="button" class="home-icon-button" data-gallery-step="1" aria-label="Ruta siguiente" title="Ruta siguiente"><i data-feather="arrow-right" aria-hidden="true"></i></button>
        </div>
    </section>
<?php else: ?>
    <section class="home-adventure home-adventure-empty" aria-labelledby="home-adventure-title">
        <img class="home-adventure-image" src="<?= inicio_h(inicio_url($inicio['hero_imagen'])) ?>" alt="Paisaje de la actividad" fetchpriority="high">
        <div class="home-adventure-shade"></div>
        <div class="home-member-wrap home-adventure-content">
            <span class="home-pill">Tu pr&oacute;xima aventura</span>
            <h2 id="home-adventure-title">El camino contin&uacute;a.</h2>
            <p>A&uacute;n no hay nuevas fechas publicadas. Mientras tanto, descubre las rutas que hemos recorrido.</p>
            <a class="home-button" href="<?= inicio_h(sg_clean_url('pantallas/senderos_visitados.php')) ?>">Explorar senderos visitados <i data-feather="arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>
<?php endif; ?>

<div class="home-member-wrap">
    <div class="home-shortcuts" aria-label="Accesos a tu inicio">
        <a href="#home-passport"><i data-feather="compass" aria-hidden="true"></i> Mi pasaporte</a>
        <a href="#home-journey"><i data-feather="award" aria-hidden="true"></i> Mis niveles</a>
        <a href="#home-explore"><i data-feather="map" aria-hidden="true"></i> Explorar rutas</a>
    </div>
    <section class="home-passport" id="home-passport" aria-labelledby="home-passport-title" data-reveal>
        <div class="home-passport-heading">
            <span class="home-passport-icon"><i data-feather="compass" aria-hidden="true"></i></span>
            <div><span class="home-eyebrow">Tu pasaporte</span><h2 id="home-passport-title"><?= inicio_h($nivelInicio['nombre']) ?></h2></div>
        </div>
        <dl class="home-stats">
            <div><dt>Senderos completados</dt><dd><span data-count="<?= (int) $statsInicio['rutas_asistidas'] ?>"><?= (int) $statsInicio['rutas_asistidas'] ?></span></dd></div>
            <div><dt>Kil&oacute;metros recorridos</dt><dd><span data-count="<?= (float) $statsInicio['km_asistidos'] ?>" data-decimals="1"><?= number_format((float) $statsInicio['km_asistidos'], 1) ?></span> <small>km</small></dd></div>
        </dl>
        <div class="home-progress">
            <?php if ($nivelInicio['siguiente']): ?>
                <div class="home-progress-label"><span>Pr&oacute;ximo: <strong><?= inicio_h($nivelInicio['siguiente']['nombre']) ?></strong></span><b><?= (int) $nivelInicio['progreso'] ?>%</b></div>
                <progress max="100" value="<?= (int) $nivelInicio['progreso'] ?>" data-progress="<?= (int) $nivelInicio['progreso'] ?>" aria-label="Progreso al siguiente nivel"><?= (int) $nivelInicio['progreso'] ?>%</progress>
                <p>Te faltan <?php
                    $faltantesInicio = [];
                    if ($nivelInicio['faltan_senderos'] > 0) {
                        $faltantesInicio[] = (int) $nivelInicio['faltan_senderos'] . ($nivelInicio['faltan_senderos'] === 1 ? ' sendero' : ' senderos');
                    }
                    if ($nivelInicio['faltan_km'] > 0) {
                        $faltantesInicio[] = number_format((float) $nivelInicio['faltan_km'], 1) . ' km';
                    }
                    echo inicio_h(implode(' y ', $faltantesInicio));
                ?> con asistencia confirmada.</p>
            <?php else: ?>
                <p><?= inicio_h($nivelInicio['texto']) ?></p>
            <?php endif; ?>
            <div class="home-passport-actions">
                <a class="home-link" href="<?= inicio_h($perfilInicioUrl . '#pasaporte') ?>">Ver mi pasaporte <i data-feather="arrow-right" aria-hidden="true"></i></a>
                <button type="button" class="home-icon-button" data-open-share aria-label="Compartir mi progreso" title="Compartir mi progreso" hidden><i data-feather="share-2" aria-hidden="true"></i></button>
            </div>
        </div>
    </section>

    <section class="home-journey" id="home-journey" aria-labelledby="home-journey-title" data-reveal>
        <div class="home-section-heading"><div><span class="home-eyebrow">Cada paso cuenta</span><h2 id="home-journey-title">Tu camino, tus logros</h2></div><span class="home-provinces"><i data-feather="map-pin" aria-hidden="true"></i> <?= (int) $statsInicio['provincias'] ?> <?= (int) $statsInicio['provincias'] === 1 ? 'provincia recorrida' : 'provincias recorridas' ?></span></div>
        <ol class="home-level-list">
            <?php foreach ($inicioUsuario['niveles'] as $nivel): ?>
                <?php
                $ganado = (int) $statsInicio['rutas_asistidas'] >= (int) $nivel['min_senderos'] && (float) $statsInicio['km_asistidos'] >= (float) $nivel['min_km'];
                $proximo = isset($nivelInicio['siguiente']['id']) && (int) $nivelInicio['siguiente']['id'] === (int) $nivel['id'];
                ?>
                <li class="home-level <?= $ganado ? 'is-earned' : ($proximo ? 'is-next' : '') ?>">
                    <details>
                        <summary><span class="home-level-medal"><i data-feather="<?= inicio_h($nivel['icono'] ?: 'award') ?>" aria-hidden="true"></i></span><span class="home-level-name"><?= inicio_h($nivel['nombre']) ?></span><span class="home-level-state"><?= $ganado ? 'Alcanzado' : ($proximo ? 'Tu pr&oacute;ximo nivel' : 'Por alcanzar') ?><i data-feather="chevron-down" aria-hidden="true"></i></span></summary>
                        <div class="home-level-detail"><p><?= inicio_h($nivel['descripcion']) ?></p><strong><?= (int) $nivel['min_senderos'] ?> senderos &middot; <?= number_format((float) $nivel['min_km'], 1) ?> km</strong><?php if (!$ganado): ?><p>Asistencias confirmadas por el equipo.</p><?php endif; ?></div>
                    </details>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (!$inicioUsuario['niveles']): ?><p class="home-muted">Tu recorrido ya est&aacute; sumando experiencias a tu pasaporte.</p><?php endif; ?>
    </section>

    <?php
    $gruposInicio = [
        'proximas' => ['titulo' => 'Pr&oacute;ximas salidas', 'rutas' => $senderosInicio, 'icono' => 'calendar', 'vacio' => 'Pr&oacute;ximamente, nuevas salidas', 'mensaje' => 'Las nuevas fechas aparecer&aacute;n aqu&iacute; cuando se publiquen. Mientras tanto, explora otras rutas.'],
        'descubrir' => ['titulo' => 'Por descubrir', 'rutas' => $inicioUsuario['descubrir'], 'icono' => 'compass', 'vacio' => 'Ya conoces nuestras rutas publicadas', 'mensaje' => 'Tu pr&oacute;xima experiencia aparecer&aacute; aqu&iacute; cuando sumemos nuevos destinos.'],
        'recorrido' => ['titulo' => 'Mi recorrido', 'rutas' => $inicioUsuario['historial'], 'icono' => 'check-circle', 'vacio' => 'Tu primera huella est&aacute; por llegar', 'mensaje' => 'Cada sendero con asistencia confirmada formar&aacute; parte de tu recorrido.'],
    ];
    $tabInicio = $senderosInicio ? 'proximas' : ($inicioUsuario['descubrir'] ? 'descubrir' : 'recorrido');
    $provinciasInicio = [];
    foreach ($gruposInicio as $grupo) {
        foreach ($grupo['rutas'] as $ruta) {
            $provincia = trim((string) $ruta['provincia']);
            if ($provincia !== '') { $provinciasInicio[$provincia] = $provincia; }
        }
    }
    natcasesort($provinciasInicio);
    ?>
    <section class="home-upcoming" id="home-explore" aria-labelledby="home-upcoming-title" data-route-explorer data-default-tab="<?= $tabInicio ?>" data-reveal>
        <div class="home-section-heading"><div><span class="home-eyebrow">Una nueva perspectiva</span><h2 id="home-upcoming-title">Encuentra tu pr&oacute;ximo camino</h2></div><a class="home-link" href="<?= inicio_h(sg_clean_url('pantallas/senderos.php')) ?>">Ver agenda <i data-feather="arrow-right" aria-hidden="true"></i></a></div>
        <div class="home-tabs" role="tablist" aria-label="Explorar senderos" hidden>
            <?php foreach ($gruposInicio as $clave => $grupo): ?>
                <button type="button" role="tab" id="home-tab-<?= $clave ?>" aria-controls="home-panel-<?= $clave ?>" aria-selected="<?= $clave === $tabInicio ? 'true' : 'false' ?>" tabindex="<?= $clave === $tabInicio ? '0' : '-1' ?>" data-route-tab="<?= $clave ?>"><i data-feather="<?= $grupo['icono'] ?>" aria-hidden="true"></i><span><?= $grupo['titulo'] ?></span><small><?= count($grupo['rutas']) ?></small></button>
            <?php endforeach; ?>
        </div>
        <form class="home-route-filters" role="search" aria-label="Buscar en tus senderos" hidden>
            <label class="home-search"><i data-feather="search" aria-hidden="true"></i><span class="home-sr-only">Buscar sendero</span><input type="search" data-route-search placeholder="Buscar un sendero..." autocomplete="off"></label>
            <label class="home-province-filter"><span class="home-sr-only">Provincia</span><select data-route-province><option value="">Todas las provincias</option><?php foreach ($provinciasInicio as $provincia): ?><option value="<?= inicio_h($provincia) ?>"><?= inicio_h($provincia) ?></option><?php endforeach; ?></select></label>
            <span class="home-result-count" data-result-count role="status" aria-live="polite"></span>
        </form>
        <?php foreach ($gruposInicio as $clave => $grupo): ?>
            <section id="home-panel-<?= $clave ?>" data-route-panel="<?= $clave ?>" aria-label="<?= $grupo['titulo'] ?>">
                <h3 class="home-panel-title"><?= $grupo['titulo'] ?></h3>
                <?php if (!$grupo['rutas']): ?>
                    <div class="home-empty-agenda"><i data-feather="<?= $grupo['icono'] ?>" aria-hidden="true"></i><div><h3><?= $grupo['vacio'] ?></h3><p><?= $grupo['mensaje'] ?></p></div></div>
                <?php else: ?>
                <?php if ($clave === 'descubrir'): ?><p class="home-panel-note">Rutas que hemos visitado. Las nuevas fechas se publicar&aacute;n en Pr&oacute;ximas salidas.</p><?php endif; ?>
                <div class="home-route-grid">
                <?php foreach ($grupo['rutas'] as $rutaInicio): ?>
                    <article class="home-route-card" data-route-card data-search="<?= inicio_h($rutaInicio['nombre'] . ' ' . $rutaInicio['lugar'] . ' ' . $rutaInicio['provincia']) ?>" data-province="<?= inicio_h(trim((string) $rutaInicio['provincia'])) ?>">
                        <a class="home-route-photo" href="<?= inicio_h(sg_clean_url('pantallas/senderos_detalle.php?id=' . (int) $rutaInicio['id'])) ?>" aria-label="Ver <?= inicio_h($rutaInicio['nombre']) ?>" data-home-media>
                            <img src="<?= inicio_h(sg_inicio_imagen($rutaInicio)) ?>" alt="<?= inicio_h($rutaInicio['nombre']) ?>" loading="lazy">
                            <?php if (!empty($rutaInicio['reservado'])): ?><span class="home-reserved"><i data-feather="check" aria-hidden="true"></i> Ya tienes reserva</span><?php elseif ($clave === 'recorrido'): ?><span class="home-reserved"><i data-feather="award" aria-hidden="true"></i> Lo completaste</span><?php elseif ($clave === 'descubrir'): ?><span class="home-reserved home-visited">Ruta visitada</span><?php endif; ?>
                        </a>
                        <div class="home-route-body">
                            <?php if ($rutaInicio['fecha_sendero']): ?><time datetime="<?= inicio_h($rutaInicio['fecha_sendero']) ?>"><?= inicio_h(sg_inicio_fecha($rutaInicio['fecha_sendero'])) ?></time><?php endif; ?>
                            <h3><a href="<?= inicio_h(sg_clean_url('pantallas/senderos_detalle.php?id=' . (int) $rutaInicio['id'])) ?>"><?= inicio_h($rutaInicio['nombre']) ?></a></h3>
                            <p class="home-route-place"><i data-feather="map-pin" aria-hidden="true"></i> <?= inicio_h($rutaInicio['provincia'] ?: $rutaInicio['lugar']) ?></p>
                            <p class="home-route-facts"><?php if ($rutaInicio['distancia_km'] !== null): ?><?= number_format((float) $rutaInicio['distancia_km'], 1) ?> km<?php endif; ?><?php if ($rutaInicio['dificultad']): ?> &middot; <?= inicio_h($rutaInicio['dificultad']) ?><?php endif; ?></p>
                            <a class="home-route-action" href="<?= inicio_h(sg_clean_url((!empty($rutaInicio['reservado']) ? 'pantallas/registro_sendero.php?id=' : 'pantallas/senderos_detalle.php?id=') . (int) $rutaInicio['id'])) ?>"><?= !empty($rutaInicio['reservado']) ? 'Ver mi registro' : ($clave === 'proximas' ? 'Ver salida' : 'Explorar esta ruta') ?> <i data-feather="arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        <div class="home-no-results" data-no-results hidden><i data-feather="search" aria-hidden="true"></i><h3>No encontramos rutas con esos filtros</h3><button type="button" class="home-text-button" data-reset-filters>Limpiar filtros</button></div>
        <div class="home-more-routes" data-more-routes hidden><button type="button" class="home-text-button" data-show-more><i data-feather="plus" aria-hidden="true"></i> Ver m&aacute;s rutas</button></div>
    </section>
</div>

<dialog class="home-share-dialog" aria-labelledby="home-share-title" data-share-dialog>
    <div class="home-share-top"><span class="home-eyebrow">Mi pasaporte senderista</span><button type="button" class="home-icon-button" data-close-share aria-label="Cerrar" title="Cerrar"><i data-feather="x" aria-hidden="true"></i></button></div>
    <div class="home-share-medal"><i data-feather="award" aria-hidden="true"></i></div>
    <h2 id="home-share-title"><?= inicio_h($nivelInicio['nombre']) ?></h2>
    <p>Las mejores historias se viven caminando.</p>
    <dl class="home-share-stats"><div><dt>Senderos</dt><dd><?= (int) $statsInicio['rutas_asistidas'] ?></dd></div><div><dt>Kil&oacute;metros</dt><dd><?= number_format((float) $statsInicio['km_asistidos'], 1) ?></dd></div><div><dt>Provincias</dt><dd><?= (int) $statsInicio['provincias'] ?></dd></div></dl>
    <label class="home-sr-only" for="home-share-text">Texto para compartir</label>
    <textarea id="home-share-text" rows="3" readonly>Mi pasaporte: <?= inicio_h($nivelInicio['nombre']) ?>. He completado <?= (int) $statsInicio['rutas_asistidas'] ?> senderos y recorrido <?= number_format((float) $statsInicio['km_asistidos'], 1) ?> km con <?= inicio_h($siteName ?? 'Plataforma') ?>.</textarea>
    <div class="home-share-actions"><button type="button" class="home-button" data-native-share hidden><i data-feather="share-2" aria-hidden="true"></i> Compartir</button><button type="button" class="home-text-button" data-copy-share><i data-feather="copy" aria-hidden="true"></i> Copiar texto</button></div>
    <p class="home-share-status" data-share-status role="status" aria-live="polite"></p>
</dialog>
