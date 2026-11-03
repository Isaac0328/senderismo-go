<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$PERMISO_REQUERIDO = 'usuarios.sin_cuenta';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/personas_sin_cuenta.php';

function psc_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function psc_iniciales(string $nombre, string $apellido): string
{
    return mb_strtoupper(mb_substr(trim($nombre), 0, 1, 'UTF-8') . mb_substr(trim($apellido), 0, 1, 'UTF-8'), 'UTF-8');
}

function psc_fecha(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '';
}

// Dos fichas parecen la misma persona: mismo telefono, nombre casi igual o mismo apellido con nombre parecido.
function psc_parecidas(array $a, array $b): bool
{
    if ($a['telefono'] !== '' && $a['telefono'] === $b['telefono']) {
        return true;
    }
    $nombreA = psc_normalizar($a['nombre'] . ' ' . $a['apellido']);
    $nombreB = psc_normalizar($b['nombre'] . ' ' . $b['apellido']);
    if (levenshtein($nombreA, $nombreB) <= 3) {
        return true;
    }
    $apellidoA = explode(' ', psc_normalizar($a['apellido']))[0];
    $apellidoB = explode(' ', psc_normalizar($b['apellido']))[0];
    return $apellidoA !== '' && $apellidoA === $apellidoB
        && strncmp(psc_normalizar($a['nombre']), psc_normalizar($b['nombre']), 3) === 0;
}

// Lista de senderos de una ficha, para revisar antes de unir o reconciliar.
function psc_render_senderos(array $senderos): void
{
    if (!$senderos) {
        echo '<p class="psc-muted">Sin senderos.</p>';
        return;
    }
    echo '<ul class="psc-trails">';
    foreach ($senderos as $fila) {
        $estado = $fila['estado'] === 'cancelado' ? 'Cancelada' : ((int) $fila['asistio'] ? 'Asisti&oacute;' : 'Inscrita');
        echo '<li><i data-feather="map"></i><div><strong>' . psc_h($fila['nombre']) . '</strong><small>' . psc_h(psc_fecha($fila['fecha_sendero']))
            . ' &middot; ' . $estado . ' &middot; RD$ ' . number_format((float) $fila['monto_pagado'], 2) . '</small></div></li>';
    }
    echo '</ul>';
}

$canAdd = sg_has_permission_action($conn, $PERMISO_REQUERIDO, 'agregar');
$canEdit = sg_has_permission_action($conn, $PERMISO_REQUERIDO, 'editar');
$canDelete = sg_has_permission_action($conn, $PERMISO_REQUERIDO, 'eliminar');
$listo = psc_listo($conn);
$personas = $listo ? psc_listar($conn) : [];
$creditosPersonas = $listo ? psc_creditos_por_persona($conn) : [];
$coincidenciasCuentas = $listo ? psc_coincidencias_cuentas($conn, $personas) : [];
$porId = array_column($personas, null, 'id');
$pageUrl = 'mantenimientos/mantenimiento_usuarios_sin_cuenta.php';
$proceso = BASE_URL . 'procesos/proceso_usuarios_sin_cuenta.php';

// La ventana que se abre segun la accion elegida en la lista.
$modo = '';
$actual = null;
foreach (['edit', 'reconciliar', 'unir'] as $posible) {
    $id = (int) ($_GET[$posible] ?? 0);
    if ($id > 0 && isset($porId[$id]) && !$porId[$id]['usuario_id'] && $canEdit) {
        $modo = $posible;
        $actual = $porId[$id];
        break;
    }
}
if ($modo === '' && isset($_GET['nueva']) && $canAdd) {
    $modo = 'nueva';
}

// Senderos de cada ficha: se usan para mostrar el historial y detectar choques en el mismo sendero.
$senderosPorPersona = [];
if ($listo && ($modo === 'unir' || $modo === 'reconciliar')) {
    $res = mysqli_query($conn, "SELECT r.persona_sin_cuenta_id, r.sendero_id, r.estado, r.asistio, s.nombre, s.fecha_sendero, COALESCE(pg.monto_pagado, 0) monto_pagado
        FROM registros_senderos r JOIN senderos s ON s.id = r.sendero_id
        LEFT JOIN contabilidad_registro_pagos pg ON pg.registro_id = r.id
        WHERE r.persona_sin_cuenta_id IS NOT NULL AND (r.usuario_id IS NULL OR r.usuario_id = 0)
        ORDER BY s.fecha_sendero DESC");
    while ($res && $fila = mysqli_fetch_assoc($res)) {
        $senderosPorPersona[(int) $fila['persona_sin_cuenta_id']][(int) $fila['sendero_id']] = $fila;
    }
}
$senderosActual = $actual ? ($senderosPorPersona[(int) $actual['id']] ?? []) : [];

// Unir: otras fichas sin reconciliar, las parecidas primero; las que comparten sendero no se pueden elegir.
$destinos = ['parecidas' => [], 'otras' => []];
if ($modo === 'unir') {
    foreach ($personas as $otra) {
        if ((int) $otra['id'] === (int) $actual['id'] || $otra['usuario_id']) {
            continue;
        }
        $choque = array_intersect_key($senderosPorPersona[(int) $otra['id']] ?? [], $senderosActual);
        $otra['choque'] = $choque ? reset($choque)['nombre'] : '';
        $destinos[psc_parecidas($actual, $otra) ? 'parecidas' : 'otras'][] = $otra;
    }
}

// Reconciliar: cuentas activas, las que coinciden primero; las inscritas en el mismo sendero no se pueden elegir.
$cuentas = ['parecidas' => [], 'otras' => []];
if ($modo === 'reconciliar') {
    $choquesUsuario = [];
    if ($senderosActual) {
        $ids = implode(',', array_map('intval', array_keys($senderosActual)));
        $res = mysqli_query($conn, "SELECT usuario_id, sendero_id FROM registros_senderos WHERE usuario_id > 0 AND sendero_id IN ($ids)");
        while ($res && $fila = mysqli_fetch_assoc($res)) {
            $choquesUsuario[(int) $fila['usuario_id']] = $senderosActual[(int) $fila['sendero_id']]['nombre'];
        }
    }
    $res = mysqli_query($conn, "SELECT u.id, u.nombre, u.apellido, u.user, u.email, COALESCE(MAX(d.telefono), '') telefono
        FROM usuarios u LEFT JOIN detalles_usuarios d ON d.usuario_id = u.id
        WHERE u.estado = 1 GROUP BY u.id ORDER BY u.nombre, u.apellido");
    while ($res && $usuario = mysqli_fetch_assoc($res)) {
        $telefono = preg_replace('/\D+/', '', $usuario['telefono']) ?? '';
        $usuario['choque'] = $choquesUsuario[(int) $usuario['id']] ?? '';
        $coincide = psc_parecidas($actual, ['nombre' => $usuario['nombre'], 'apellido' => $usuario['apellido'], 'telefono' => $telefono])
            || ($actual['telefono'] !== '' && $telefono !== '' && str_ends_with($telefono, substr($actual['telefono'], -7)))
            || ($actual['email'] !== '' && strcasecmp($usuario['email'], $actual['email']) === 0)
            || str_contains(psc_normalizar($usuario['nombre'] . ' ' . $usuario['apellido']), psc_normalizar($actual['nombre']) . ' ')
                && str_contains(psc_normalizar($usuario['apellido']), explode(' ', psc_normalizar($actual['apellido']))[0]);
        $cuentas[$coincide ? 'parecidas' : 'otras'][] = $usuario;
    }
}

$totales = ['todas' => count($personas), 'activa' => 0, 'inactiva' => 0, 'reconciliada' => 0];
foreach ($personas as $persona) {
    $totales[$persona['usuario_id'] ? 'reconciliada' : ((int) $persona['activo'] === 1 ? 'activa' : 'inactiva')]++;
}
$nombreCompleto = static fn(array $p): string => trim($p['nombre'] . ' ' . $p['apellido']);
$contacto = static fn(array $p): string => implode(' · ', array_filter([$p['telefono'] ?? '', $p['email'] ?? ''])) ?: 'Sin contacto';

$pageTitle = 'Usuarios sin cuenta';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/contabilidad.css', 'css/usuarios_sin_cuenta.css'];
$jsFiles = ['js/barra_navegacion.js', 'js/usuarios_sin_cuenta.js'];
include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>
<main class="fin-page psc-page" data-psc-base="<?= psc_h(sg_clean_url($pageUrl)) ?>">
    <section class="fin-container">
        <header class="fin-header">
            <div><span class="fin-kicker">Usuarios</span><h1>Usuarios sin cuenta</h1><p>Personas inscritas a mano en los senderos. Cuando creen su cuenta, reconc&iacute;lialas para que su historial pase al usuario.</p></div>
            <div class="psc-header-actions">
                <?php if ($canAdd && $listo): ?><a class="psc-btn psc-btn-primary" href="<?= psc_h(sg_clean_url($pageUrl . '?nueva=1')) ?>" data-psc-open="psc-dialog-nueva"><i data-feather="user-plus"></i>Nueva persona</a><?php endif; ?>
                <a class="fin-back" href="<?= psc_h(sg_clean_url('pantallas/panel_administrativo.php')) ?>"><i data-feather="arrow-left"></i>Volver al panel</a>
            </div>
        </header>
        <?php foreach (['success', 'error'] as $type): $key = 'sin_cuenta_' . $type; ?>
            <?php if (!empty($_SESSION[$key])): ?>
                <div class="fin-alert <?= $type ?>"><?= psc_h($_SESSION[$key]); unset($_SESSION[$key]); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$listo): ?>
            <div class="fin-alert error">Este m&oacute;dulo necesita la actualizaci&oacute;n pendiente del sistema. <a href="<?= psc_h(sg_clean_url('mantenimientos/mantenimiento_actualizaciones.php')) ?>">Actualizaciones del sistema</a></div>
        <?php else: ?>

        <div class="psc-toolbar">
            <div class="psc-filters" role="tablist" aria-label="Filtrar por estado">
                <?php foreach (['todas' => ['Todas', 'users'], 'activa' => ['Activas', 'user-check'], 'inactiva' => ['Inactivas', 'user-minus'], 'reconciliada' => ['Reconciliadas', 'link']] as $valor => [$etiqueta, $icono]): ?>
                    <button type="button" role="tab" class="psc-filter <?= $valor === 'todas' ? 'is-active' : '' ?>" data-psc-filter="<?= $valor ?>" aria-selected="<?= $valor === 'todas' ? 'true' : 'false' ?>">
                        <i data-feather="<?= $icono ?>"></i><span><?= $etiqueta ?></span><strong><?= $totales[$valor] ?></strong>
                    </button>
                <?php endforeach; ?>
            </div>
            <label class="psc-search"><i data-feather="search"></i><input type="search" data-psc-search placeholder="Buscar por nombre, tel&eacute;fono o correo..." aria-label="Buscar persona"></label>
        </div>

        <div class="psc-grid" data-psc-list>
            <?php foreach ($personas as $persona):
                $pid = (int) $persona['id'];
                $reconciliada = (bool) $persona['usuario_id'];
                $activa = (int) $persona['activo'] === 1;
                $estado = $reconciliada ? 'reconciliada' : ($activa ? 'activa' : 'inactiva');
                $senderos = (int) $persona['inscripciones'];
                $coincidencias = $coincidenciasCuentas[$pid] ?? [];
            ?>
            <article class="psc-person is-<?= $estado ?><?= $coincidencias ? ' has-match' : '' ?>" data-estado="<?= $estado ?>" data-buscar="<?= psc_h(psc_normalizar($nombreCompleto($persona) . ' ' . $persona['telefono'] . ' ' . $persona['email'] . ' ' . $persona['usuario_nombre'] . ' ' . $persona['usuario_user'])) ?>">
                <div class="psc-person-top">
                    <span class="psc-avatar"><?= psc_h(psc_iniciales($persona['nombre'], $persona['apellido'])) ?></span>
                    <div class="psc-person-name">
                        <div class="psc-person-title"><h3><?= psc_h($nombreCompleto($persona)) ?></h3>
                            <?php if ($coincidencias): ?><span class="psc-match-icon" title="Hay una cuenta activa que coincide. Revisa y reconcilia si corresponde." aria-label="Hay una cuenta activa que coincide"><i data-feather="alert-circle"></i></span><?php endif; ?>
                        </div>
                        <small><?= psc_h($contacto($persona)) ?></small>
                    </div>
                    <span class="psc-state psc-state-<?= $estado ?>"><?= ['activa' => 'Activa', 'inactiva' => 'Inactiva', 'reconciliada' => 'Reconciliada'][$estado] ?></span>
                </div>
                <div class="psc-person-stats">
                    <div><i data-feather="map"></i><span><strong><?= $senderos ?></strong> <?= $senderos === 1 ? 'sendero' : 'senderos' ?></span></div>
                    <div><i data-feather="calendar"></i><span><?= $senderos ? 'Último ' . psc_h(psc_fecha($persona['ultima_salida'])) : 'Sin salidas' ?></span></div>
                    <div><i data-feather="dollar-sign"></i><span>RD$ <?= number_format((float) $persona['total_pagado'], 2) ?></span></div>
                    <?php if (!empty($creditosPersonas[(int) $persona['id']])): ?>
                        <div class="psc-credito" title="Credito a favor; pasa a su cuenta al reconciliarla"><i data-feather="gift"></i><span>Credito <strong>RD$ <?= number_format($creditosPersonas[(int) $persona['id']], 2) ?></strong></span></div>
                    <?php endif; ?>
                </div>
                <?php if ($coincidencias): ?>
                    <div class="psc-match-note"><i data-feather="alert-circle"></i><span><strong><?= count($coincidencias) === 1 ? 'Posible cuenta creada' : 'Posibles cuentas creadas' ?></strong>
                        <?php foreach ($coincidencias as $indice => $cuenta): ?><?= $indice ? '; ' : ': ' ?><b><?= psc_h(trim($cuenta['nombre'] . ' ' . $cuenta['apellido'])) ?></b> (@<?= psc_h($cuenta['user']) ?>, <?= psc_h(implode(', ', $cuenta['motivos'])) ?>)<?php endforeach; ?>. Reconc&iacute;liala solo si es la misma persona.</span></div>
                <?php endif; ?>
                <?php if ($persona['notas'] !== ''): ?><p class="psc-note"><?= psc_h($persona['notas']) ?></p><?php endif; ?>
                <?php if ($reconciliada): ?>
                    <div class="psc-linked"><i data-feather="check-circle"></i><span>Ahora es <strong><?= psc_h($persona['usuario_nombre']) ?></strong> (@<?= psc_h($persona['usuario_user']) ?>)<small>desde el <?= psc_h(psc_fecha($persona['reconciliado_at'])) ?></small></span></div>
                <?php elseif ($canEdit): ?>
                    <div class="psc-person-actions">
                        <?php if ($senderos > 0): ?>
                            <a class="psc-btn psc-btn-primary" href="<?= psc_h(sg_clean_url($pageUrl . '?reconciliar=' . $pid)) ?>"><i data-feather="link"></i>Reconciliar</a>
                            <a class="psc-btn" href="<?= psc_h(sg_clean_url($pageUrl . '?unir=' . $pid)) ?>"><i data-feather="git-merge"></i>Unir</a>
                        <?php endif; ?>
                        <a class="psc-btn" href="<?= psc_h(sg_clean_url($pageUrl . '?edit=' . $pid)) ?>"><i data-feather="edit-2"></i>Editar</a>
                        <?php if ($senderos === 0): ?>
                            <form method="post" action="<?= $proceso ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $activa ? 'inactivar' : 'activar' ?>"><input type="hidden" name="id" value="<?= $pid ?>"><button type="submit" class="psc-btn"><i data-feather="power"></i><?= $activa ? 'Inactivar' : 'Activar' ?></button></form>
                            <?php if (!$activa && $canDelete): ?><form method="post" action="<?= $proceso ?>" data-psc-confirm="Se eliminara la ficha de <?= psc_h($nombreCompleto($persona)) ?>. Deseas continuar?"><?= csrf_field() ?><input type="hidden" name="action" value="eliminar"><input type="hidden" name="id" value="<?= $pid ?>"><button type="submit" class="psc-btn psc-btn-danger"><i data-feather="trash-2"></i>Eliminar</button></form><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
            <div class="psc-empty" data-psc-empty <?= $personas ? 'hidden' : '' ?>><i data-feather="users"></i><p><?= $personas ? 'Nadie coincide con la b&uacute;squeda.' : 'Todav&iacute;a no hay personas sin cuenta.' ?></p></div>
        </div>
        <p class="psc-footnote"><i data-feather="info"></i>Solo se inactivan las fichas sin senderos y solo se eliminan las inactivas. Las que tienen senderos se reconcilian con su cuenta o se unen con su ficha repetida.</p>

        <?php if ($canAdd || $modo === 'edit'): $ficha = $modo === 'edit' ? $actual : null; ?>
        <dialog class="psc-dialog psc-dialog-sm" id="psc-dialog-<?= $ficha ? 'edit' : 'nueva' ?>" <?= in_array($modo, ['edit', 'nueva'], true) ? 'data-psc-autoopen' : '' ?>>
            <form method="post" action="<?= $proceso ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($ficha['id'] ?? 0) ?>">
                <header class="psc-dialog-head">
                    <span class="psc-dialog-icon"><i data-feather="<?= $ficha ? 'edit-2' : 'user-plus' ?>"></i></span>
                    <div><h2><?= $ficha ? 'Editar persona' : 'Nueva persona sin cuenta' ?></h2><p><?= $ficha ? 'Los cambios se reflejan en todos sus senderos.' : 'Para alguien que asiste sin haber creado su cuenta.' ?></p></div>
                    <button type="button" class="psc-close" data-psc-close aria-label="Cerrar"><i data-feather="x"></i></button>
                </header>
                <div class="psc-dialog-body psc-fields">
                    <label><span>Nombre *</span><input type="text" name="nombre" required maxlength="100" value="<?= psc_h($ficha['nombre'] ?? '') ?>" autocomplete="off"></label>
                    <label><span>Apellido *</span><input type="text" name="apellido" required maxlength="100" value="<?= psc_h($ficha['apellido'] ?? '') ?>" autocomplete="off"></label>
                    <label><span>Tel&eacute;fono</span><input type="tel" name="telefono" maxlength="20" inputmode="tel" placeholder="8090000000" value="<?= psc_h($ficha['telefono'] ?? '') ?>"></label>
                    <label><span>Correo</span><input type="email" name="email" maxlength="100" placeholder="Opcional" value="<?= psc_h($ficha['email'] ?? '') ?>"></label>
                    <label class="psc-field-full"><span>Notas</span><input type="text" name="notas" maxlength="255" placeholder="Ej.: amiga de..., viene con..." value="<?= psc_h($ficha['notas'] ?? '') ?>"></label>
                </div>
                <footer class="psc-dialog-foot">
                    <button type="button" class="psc-btn" data-psc-close>Cancelar</button>
                    <button type="submit" class="psc-btn psc-btn-primary"><i data-feather="save"></i><?= $ficha ? 'Guardar cambios' : 'Crear persona' ?></button>
                </footer>
            </form>
        </dialog>
        <?php endif; ?>

        <?php if ($modo === 'unir' || $modo === 'reconciliar'):
            $esUnir = $modo === 'unir';
            $grupos = $esUnir ? $destinos : $cuentas;
            $totalSenderos = count($senderosActual);
        ?>
        <dialog class="psc-dialog" id="psc-dialog-<?= $modo ?>" data-psc-autoopen>
            <form method="post" action="<?= $proceso ?>" class="psc-pick-form" data-psc-pick data-psc-mode="<?= $modo ?>" data-psc-count="<?= $totalSenderos ?>"
                data-psc-from="<?= psc_h($nombreCompleto($actual)) ?>"
                data-psc-confirm="<?= $esUnir ? 'Se uniran las dos fichas en una sola. Deseas continuar?' : 'Los senderos de ' . psc_h($nombreCompleto($actual)) . ' pasaran a la cuenta elegida y la ficha quedara cerrada. Deseas continuar?' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $modo ?>">
                <input type="hidden" name="id" value="<?= (int) $actual['id'] ?>">
                <?php if ($esUnir): ?><input type="hidden" name="conservar" value="destino" data-psc-keep><?php endif; ?>
                <header class="psc-dialog-head">
                    <span class="psc-dialog-icon"><i data-feather="<?= $esUnir ? 'git-merge' : 'link' ?>"></i></span>
                    <div>
                        <h2><?= $esUnir ? 'Unir fichas repetidas' : 'Reconciliar con una cuenta' ?></h2>
                        <p><?= $esUnir ? 'Si dos fichas son la misma persona, &uacute;nelas para que todo su historial quede junto.' : 'Elige la cuenta que cre&oacute; esta persona. Sus senderos, pagos y asistencia pasar&aacute;n a esa cuenta.' ?></p>
                    </div>
                    <button type="button" class="psc-close" data-psc-close aria-label="Cerrar"><i data-feather="x"></i></button>
                </header>

                <div class="psc-dialog-body">
                    <div class="psc-compare" data-psc-compare>
                        <section class="psc-side psc-side-from">
                            <span class="psc-side-label" data-psc-label-from><?= $esUnir ? 'Se elimina' : 'Persona sin cuenta' ?></span>
                            <div class="psc-side-person">
                                <span class="psc-avatar"><?= psc_h(psc_iniciales($actual['nombre'], $actual['apellido'])) ?></span>
                                <div><strong><?= psc_h($nombreCompleto($actual)) ?></strong><small><?= psc_h($contacto($actual)) ?></small></div>
                            </div>
                            <details class="psc-side-trails" <?= $totalSenderos <= 3 ? 'open' : '' ?>>
                                <summary><?= $totalSenderos ?> <?= $totalSenderos === 1 ? 'sendero' : 'senderos' ?></summary>
                                <?php psc_render_senderos(array_values($senderosActual)); ?>
                            </details>
                        </section>
                        <div class="psc-arrow">
                            <i data-feather="arrow-right"></i>
                            <?php if ($esUnir): ?><button type="button" class="psc-swap" data-psc-swap title="Cambiar cual ficha se conserva"><i data-feather="repeat"></i><span>Invertir</span></button><?php endif; ?>
                        </div>
                        <section class="psc-side psc-side-to is-empty" data-psc-target>
                            <span class="psc-side-label" data-psc-label-to><?= $esUnir ? 'Se conserva' : 'Cuenta' ?></span>
                            <div class="psc-side-person">
                                <span class="psc-avatar psc-avatar-ghost" data-psc-target-avatar>?</span>
                                <div><strong data-psc-target-name><?= $esUnir ? 'Elige la otra ficha' : 'Elige la cuenta' ?></strong><small data-psc-target-detail>En la lista de abajo</small></div>
                            </div>
                        </section>
                    </div>

                    <div class="psc-picker">
                        <label class="psc-search"><i data-feather="search"></i><input type="search" data-psc-pick-search placeholder="<?= $esUnir ? 'Buscar persona...' : 'Buscar por nombre, usuario, tel&eacute;fono o correo...' ?>" aria-label="Buscar"></label>
                        <div class="psc-options" role="radiogroup">
                            <?php foreach (['parecidas' => $esUnir ? 'Parecidas a esta persona' : 'Coinciden con esta persona', 'otras' => $esUnir ? 'Otras personas sin cuenta' : 'Todas las cuentas activas'] as $grupo => $titulo): ?>
                                <?php if (!$grupos[$grupo]) { continue; } ?>
                                <p class="psc-options-title" data-psc-group><?= $grupo === 'parecidas' ? '<i data-feather="star"></i>' : '' ?><?= $titulo ?></p>
                                <?php foreach ($grupos[$grupo] as $opcion):
                                    $detalle = $esUnir
                                        ? $contacto($opcion) . ' · ' . (int) $opcion['inscripciones'] . ' senderos'
                                        : '@' . $opcion['user'] . ($opcion['telefono'] ? ' · ' . $opcion['telefono'] : '') . ($opcion['email'] ? ' · ' . $opcion['email'] : '');
                                    $bloqueada = $opcion['choque'] !== '';
                                ?>
                                <label class="psc-option <?= $bloqueada ? 'is-blocked' : '' ?>" data-psc-option data-buscar="<?= psc_h(psc_normalizar($nombreCompleto($opcion) . ' ' . $detalle)) ?>">
                                    <input type="radio" name="<?= $esUnir ? 'destino_id' : 'usuario_id' ?>" value="<?= (int) $opcion['id'] ?>" required <?= $bloqueada ? 'disabled' : '' ?>
                                        data-nombre="<?= psc_h($nombreCompleto($opcion)) ?>" data-detalle="<?= psc_h($detalle) ?>" data-iniciales="<?= psc_h(psc_iniciales($opcion['nombre'], $opcion['apellido'])) ?>"
                                        data-senderos="<?= $esUnir ? (int) $opcion['inscripciones'] : 0 ?>">
                                    <span class="psc-avatar"><?= psc_h(psc_iniciales($opcion['nombre'], $opcion['apellido'])) ?></span>
                                    <span class="psc-option-text"><strong><?= psc_h($nombreCompleto($opcion)) ?></strong><small><?= psc_h($detalle) ?></small>
                                        <?php if ($bloqueada): ?><em><i data-feather="alert-triangle"></i>Ya est&aacute; en <?= psc_h($opcion['choque']) ?>. Cancela una de las dos inscripciones primero.</em><?php endif; ?></span>
                                    <span class="psc-check"><i data-feather="check"></i></span>
                                </label>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <p class="psc-muted" data-psc-pick-empty hidden>Nadie coincide con la b&uacute;squeda.</p>
                        </div>
                    </div>
                </div>

                <footer class="psc-dialog-foot">
                    <p class="psc-result" data-psc-result><?= $esUnir ? 'Elige la ficha con la que se une.' : 'Elige la cuenta para continuar.' ?></p>
                    <button type="button" class="psc-btn" data-psc-close>Cancelar</button>
                    <button type="submit" class="psc-btn psc-btn-primary" disabled data-psc-submit><i data-feather="<?= $esUnir ? 'git-merge' : 'link' ?>"></i><?= $esUnir ? 'Unir fichas' : 'Reconciliar' ?></button>
                </footer>
            </form>
        </dialog>
        <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
