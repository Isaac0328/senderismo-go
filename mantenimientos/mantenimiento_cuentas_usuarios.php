<?php
require_once __DIR__ . '/../configuracion.php';
$PERMISO_REQUERIDO = 'finanzas.cuentas_usuarios';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
sg_require_permission_action($conn, $PERMISO_REQUERIDO, 'ver');
require_once __DIR__ . '/../componentes/cuentas_usuarios.php';

$error = '';
$data = null;
try {
    $filters = sgcu_filters($_GET);
    $data = sgcu_load($conn, $filters);
} catch (Throwable $e) {
    error_log('Estado de cuenta: ' . $e->getMessage());
    $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo cargar el estado de cuenta.';
    $filters = sgcu_filters([]);
}
$vista = in_array($_GET['vista'] ?? '', ['senderos', 'creditos', 'aplicaciones', 'movimientos'], true) ? $_GET['vista'] : 'senderos';
$selected = $data['selected'] ?? null;
$labels = sg_financial_statuses($conn);
$base = sg_clean_url('mantenimientos/mantenimiento_cuentas_usuarios.php');
function cu_url(string $base, array $f, array $changes = []): string
{
    return $base . '?' . http_build_query(array_merge($f, $changes));
}
function cu_date(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : 'Sin fecha';
}
$pageTitle = 'Estado de cuenta por usuario';
$cssFiles = ['css/global.css', 'css/barra_navegacion.css', 'css/contabilidad.css', 'css/cuentas_usuarios.css'];
$jsFiles = ['js/barra_navegacion.js', 'js/cuentas_usuarios.js'];
include_once __DIR__ . '/../componentes/encabezado.php';
include_once __DIR__ . '/../componentes/barra_navegacion.php';
?>
<main class="fin-page cu-page">
    <div class="fin-container">
        <header class="fin-header">
            <div><span class="fin-kicker">Finanzas</span><h1>Estado de cuenta por usuario</h1><p><?= $selected ? sgcu_h($selected['nombre']) : 'Por persona: cuánto ha pagado, qué debe y qué crédito tiene cada participante.' ?></p></div>
            <div class="cu-actions">
                <?php if ($data): ?><a class="fin-back" href="<?= sgcu_h(BASE_URL . 'procesos/proceso_exportar_cuentas_usuarios.php?' . http_build_query($filters)) ?>"><i data-feather="download"></i>Excel</a><?php endif; ?>
                <a class="fin-back" href="<?= sgcu_h(sg_clean_url('pantallas/panel_administrativo.php')) ?>"><i data-feather="arrow-left"></i>Panel</a>
            </div>
        </header>
        <?php if ($error): ?><div class="fin-alert error"><?= sgcu_h($error) ?></div><?php endif; ?>
        <?php if ($selected): ?>
        <section class="cu-identity">
            <a href="<?= sgcu_h(cu_url($base, $filters, ['cuenta' => '', 'pagina' => 1])) ?>"><i data-feather="arrow-left"></i>Todas las cuentas</a>
            <div><strong><?= sgcu_h($selected['nombre']) ?></strong><span><?= $selected['tipo'] === 'temporal' ? ($selected['key'][0] === 'p' ? 'Sin cuenta · ficha #' : 'Asistente temporal · registro #') . $selected['id'] : '@' . sgcu_h($selected['usuario']) ?><?= $selected['email'] ? ' · ' . sgcu_h($selected['email']) : '' ?><?= $selected['telefono'] ? ' · ' . sgcu_h($selected['telefono']) : '' ?></span></div>
        </section>
        <?php endif; ?>
        <form method="get" action="<?= sgcu_h($base) ?>" class="cu-filters">
            <?php if ($selected): ?><input type="hidden" name="cuenta" value="<?= sgcu_h($selected['key']) ?>"><input type="hidden" name="vista" value="<?= sgcu_h($vista) ?>"><?php endif; ?>
            <?php if (!$selected): ?>
            <div class="cu-search" data-participant-search>
                <label for="cu-participant"><span>Participante</span></label>
                <input id="cu-participant" type="search" name="q" value="<?= sgcu_h($filters['q']) ?>" placeholder="Nombre, usuario, correo o teléfono" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="cu-participants">
                <div class="cu-suggestions" hidden>
                    <div class="cu-suggestions-heading" role="status" aria-live="polite"></div>
                    <div id="cu-participants" role="listbox" aria-label="Participantes"></div>
                </div>
                <script type="application/json" id="cu-participant-data"><?= json_encode($data['participants'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
            </div>
            <label><span>Tipo</span><select name="tipo"><?php foreach (['todos' => 'Todos', 'registrado' => 'Registrados', 'temporal' => 'Sin cuenta'] as $value => $label): ?><option value="<?= $value ?>" <?= $filters['tipo'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label><span>Situación</span><select name="situacion"><?php foreach (['todos' => 'Todas', 'pendiente' => 'Balance pendiente actual', 'credito' => 'Crédito disponible actual', 'tuvo_credito' => 'Tiene o tuvo crédito', 'descuento' => 'Descuento en el período'] as $value => $label): ?><option value="<?= $value ?>" <?= $filters['situacion'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
            <label><span>Desde</span><input type="date" name="desde" value="<?= sgcu_h($filters['desde']) ?>"></label>
            <label><span>Hasta</span><input type="date" name="hasta" value="<?= sgcu_h($filters['hasta']) ?>"></label>
            <label><span>Fechas de importes</span><select name="criterio"><option value="sendero" <?= $filters['criterio'] === 'sendero' ? 'selected' : '' ?>>Fecha del sendero</option><option value="pago" <?= $filters['criterio'] === 'pago' ? 'selected' : '' ?>>Fecha de pago</option></select></label>
            <button type="submit"><i data-feather="filter"></i>Filtrar</button>
            <a class="cu-reset" href="<?= sgcu_h($selected ? cu_url($base, [], ['cuenta' => $selected['key']]) : $base) ?>" title="Limpiar filtros" aria-label="Limpiar filtros"><i data-feather="rotate-ccw"></i></a>
        </form>
        <?php if ($data): ?>
        <div class="cu-period"><span><?= $filters['desde'] || $filters['hasta'] ? sgcu_h(($filters['desde'] ? cu_date($filters['desde']) : 'Inicio') . ' — ' . ($filters['hasta'] ? cu_date($filters['hasta']) : 'Hoy')) : 'Todo el historial' ?> · <?= $filters['criterio'] === 'pago' ? 'Fecha de pago' : 'Fecha del sendero' ?></span><span><?= count($data['accounts']) ?> <?= $selected ? 'cuenta' : 'cuentas' ?></span></div>
        <section class="cu-metrics" aria-label="Totales del período">
            <?php foreach (['pagado' => 'Dinero pagado', 'descuento' => 'Descuentos', 'aplicado' => 'Crédito aplicado', 'generado' => 'Crédito generado', 'retenido' => 'Efectivo retenido', 'ingreso' => 'Total ingreso'] as $field => $title): ?>
            <div><span><?= $title ?></span><strong><?= sgcu_money($data['totals'][$field]) ?></strong><small>En el período</small></div>
            <?php endforeach; ?>
        </section>
        <section class="cu-current" aria-label="Situación actual">
            <div class="cu-debt"><i data-feather="alert-circle"></i><div><span>Balance pendiente actual</span><strong><?= sgcu_money($data['current']['pendiente']) ?></strong></div></div>
            <div class="cu-credit"><i data-feather="credit-card"></i><div><span>Crédito disponible actual</span><strong><?= sgcu_money($data['current']['credito']) ?></strong></div></div>
            <p><?php if ($data['current']['proximo'] > 0): ?><strong class="cu-upcoming"><?= sgcu_money($data['current']['proximo']) ?> del pendiente son de senderos que aún no se realizan.</strong> <?php endif; ?>Saldos actuales de todas las fechas, incluidas las inscripciones sin pago registrado en Ingresos. El crédito disponible no se descuenta automáticamente del balance pendiente.</p>
        </section>
        <?php if (!$selected): ?>
        <section class="cu-section">
            <header><h2>Cuentas de participantes</h2><span>Importes del período y saldos actuales</span></header>
            <div class="cu-table-wrap cu-action-table"><table><thead><tr><?php foreach (sgcu_sort_fields() as $campo => $titulo): $activo = $filters['orden'] === $campo; $siguiente = $activo ? ($filters['dir'] === 'asc' ? 'desc' : 'asc') : ($campo === 'nombre' ? 'asc' : 'desc'); ?><th<?= $activo ? ' aria-sort="' . ($filters['dir'] === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>><a class="cu-sort" href="<?= sgcu_h(cu_url($base, $filters, ['orden' => $campo, 'dir' => $siguiente, 'pagina' => 1])) ?>" title="Ordenar por <?= sgcu_h($titulo) ?>"><?= sgcu_h($titulo) ?><?php if ($activo): ?><i data-feather="<?= $filters['dir'] === 'asc' ? 'arrow-up' : 'arrow-down' ?>"></i><?php endif; ?></a></th><?php endforeach; ?><th></th></tr></thead><tbody>
            <?php $pageCount = max(1, (int) ceil(count($data['accounts']) / 25)); $pagina = min($filters['pagina'], $pageCount); foreach (array_slice($data['accounts'], ($pagina - 1) * 25, 25) as $a): ?>
                <?php $urlCuenta = cu_url($base, $filters, ['cuenta' => $a['key'], 'q' => '', 'situacion' => 'todos', 'tipo' => 'todos', 'pagina' => 1]); ?>
                <tr><td class="cu-person"><a class="cu-person-link" href="<?= sgcu_h($urlCuenta) ?>"><?= sgcu_h($a['nombre']) ?></a><small><?= $a['tipo'] === 'temporal' ? ($a['key'][0] === 'p' ? 'Sin cuenta · ficha #' : 'Temporal · registro #') . $a['id'] : '@' . sgcu_h($a['usuario']) ?></small><small><?= sgcu_h($a['telefono'] ?: $a['email']) ?></small></td><td><?= $a['inscripciones'] ?></td>
                <?php foreach (['pagado', 'descuento', 'aplicado', 'generado'] as $field): ?><td class="cu-money"><?= sgcu_money($a['totals'][$field]) ?></td><?php endforeach; ?>
                <td class="cu-money <?= $a['pendiente_actual'] > 0 ? 'cu-negative' : '' ?>"><?= sgcu_money($a['pendiente_actual']) ?><?php if ($a['pendiente_proximo'] > 0): ?><small class="cu-upcoming"><?= $a['pendiente_proximo'] === $a['pendiente_actual'] ? 'Sendero próximo' : sgcu_money($a['pendiente_proximo']) . ' de sendero próximo' ?></small><?php endif; ?></td><td class="cu-money cu-positive"><?= sgcu_money($a['credito_actual']) ?></td>
                <td><a class="cu-detail" href="<?= sgcu_h($urlCuenta) ?>"><i data-feather="arrow-right"></i>Ver cuenta</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$data['accounts']): ?><tr><td colspan="9" class="cu-empty">No hay cuentas para estos filtros.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
        <?php else: ?>
        <div class="cu-tabs" role="navigation" aria-label="Historial financiero">
            <?php foreach (['senderos' => 'Senderos y pagos', 'creditos' => 'Créditos', 'aplicaciones' => 'Aplicaciones vigentes', 'movimientos' => 'Movimientos de crédito'] as $key => $label): ?>
            <a href="<?= sgcu_h(cu_url($base, $filters, ['vista' => $key, 'pagina' => 1])) ?>" <?= $vista === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <section class="cu-section">
        <?php
            $items = $data[match ($vista) { 'creditos' => 'credits', 'aplicaciones' => 'applications', 'movimientos' => 'movements', default => 'history' }];
            $pageCount = max(1, (int) ceil(count($items) / 25)); $pagina = min($filters['pagina'], $pageCount);
            $itemsPage = array_slice($items, ($pagina - 1) * 25, 25);
        ?>
        <?php if ($vista === 'senderos'): ?>
            <header><h2>Senderos y pagos</h2><span><?= count($items) ?> registros en el período</span></header>
            <p class="cu-note">Cada fila muestra el pago acumulado registrado para ese sendero. Los abonos individuales no se guardan por separado. Las inscripciones canceladas conservan sus pagos, pero no generan balance por cobrar.</p>
            <div class="cu-table-wrap cu-action-table"><table><thead><tr><th>Sendero</th><th>Estado</th><th>Inversión</th><th>Descuento</th><th>Esperado</th><th>Pagado</th><th>Crédito aplicado</th><th>Balance</th><th>Crédito generado</th><th>Efectivo retenido</th><th>Total ingreso</th><th>Fecha pago</th><th>Método</th><th>Nota</th><th></th></tr></thead><tbody>
            <?php foreach ($itemsPage as $r): ?><tr>
                <td class="cu-route"><strong><?= sgcu_h($r['sendero']) ?></strong><small><?= cu_date($r['fecha_sendero']) ?> · Registro #<?= (int) $r['registro_id'] ?><?= (int) $r['menores'] > 0 ? ' · ' . (int) $r['menores'] . ((int) $r['menores'] === 1 ? ' menor' : ' menores') : '' ?></small></td>
                <td><span class="cu-badge"><?= sgcu_h($labels[$r['estado']] ?? $r['estado']) ?></span><?= $r['registro_estado'] === 'cancelado' ? '<small class="cu-negative">Inscripción cancelada</small>' : '' ?></td>
                <?php foreach (['inversion', 'descuento', 'esperado', 'pagado', 'aplicado', 'balance', 'generado', 'retenido', 'ingreso'] as $field): ?><td class="cu-money <?= $field === 'balance' ? ($r['values'][$field] > 0 ? 'cu-negative' : ($r['values'][$field] < 0 ? 'cu-positive' : '')) : '' ?>"><?= sgcu_money($r['values'][$field]) ?></td><?php endforeach; ?>
                <td><?= cu_date($r['fecha_pago']) ?></td><td><?= sgcu_h($r['metodo'] ?: 'Sin método') ?></td><td class="cu-note-cell"><?= sgcu_h($r['nota']) ?></td><td><?php if (sg_has_permission($conn, 'finanzas.ingresos_sendero')): ?><a class="cu-detail" href="<?= sgcu_h(sg_clean_url('mantenimientos/mantenimiento_ingresos_sendero.php?sendero_id=' . $r['sendero_id'])) ?>">Ingresos<i data-feather="external-link"></i></a><?php endif; ?></td>
            </tr><?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="15" class="cu-empty">No hay registros en el período seleccionado.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($items): ?>
            <tfoot><tr class="cu-total"><td class="cu-route"><strong>Total del período</strong><small><?= count($items) ?> registros</small></td><td></td>
                <?php foreach (['inversion', 'descuento', 'esperado', 'pagado', 'aplicado', 'balance', 'generado', 'retenido', 'ingreso'] as $field): ?><td class="cu-money"><?= sgcu_money(array_sum(array_map(static fn(array $r): int => $r['values'][$field], $items))) ?></td><?php endforeach; ?>
                <td></td><td></td><td></td><td></td></tr></tfoot>
            <?php endif; ?>
            </table></div>
        <?php elseif ($vista === 'creditos'): ?>
            <header><h2>Créditos de la cuenta</h2><span>Todas las fechas · <?= count($items) ?> créditos</span></header>
            <div class="cu-table-wrap"><table><thead><tr><th>Crédito</th><th>Creación</th><th>Sendero de origen</th><th>Monto registrado</th><th>Disponible actual</th><th>Estado</th><th>Motivo</th></tr></thead><tbody>
            <?php foreach ($itemsPage as $r): ?><tr><td>#<?= (int) $r['id'] ?></td><td><?= cu_date($r['created_at']) ?></td><td class="cu-route"><?= sgcu_h($r['origen'] ?: 'Sin sendero de origen') ?></td><td class="cu-money"><?= sgcu_money(sgcu_cents($r['monto'])) ?></td><td class="cu-money cu-positive"><?= sgcu_money($r['estado'] === 'activo' ? sgcu_cents($r['saldo_disponible']) : 0) ?></td><td><span class="cu-badge"><?= sgcu_h(ucfirst($r['estado'])) ?></span></td><td class="cu-note-cell"><?= sgcu_h($r['motivo']) ?></td></tr><?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="7" class="cu-empty">Esta cuenta no tiene créditos registrados.</td></tr><?php endif; ?>
            </tbody></table></div>
        <?php elseif ($vista === 'aplicaciones'): ?>
            <header><h2>Aplicaciones vigentes</h2><span>Todas las fechas · <?= count($items) ?> aplicaciones</span></header>
            <div class="cu-table-wrap"><table><thead><tr><th>Crédito</th><th>Origen</th><th>Aplicado en</th><th>Fecha del sendero</th><th>Crédito aplicado</th><th>Registro</th></tr></thead><tbody>
            <?php foreach ($itemsPage as $r): ?><tr><td>#<?= (int) $r['credito_id'] ?></td><td class="cu-route"><?= sgcu_h($r['origen'] ?: 'Sin origen registrado') ?></td><td class="cu-route"><?= sgcu_h($r['destino']) ?></td><td><?= cu_date($r['fecha_sendero']) ?></td><td class="cu-money"><?= sgcu_money(sgcu_cents($r['credito_aplicado'])) ?></td><td>#<?= (int) $r['registro_id'] ?></td></tr><?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="6" class="cu-empty">No hay aplicaciones vigentes de crédito.</td></tr><?php endif; ?>
            </tbody></table></div>
        <?php else: ?>
            <header><h2>Movimientos de crédito</h2><span>Por fecha del movimiento · <?= count($items) ?> registros</span></header>
            <p class="cu-note">Bitácora de movimientos registrados. Puede incluir aplicaciones repetidas al guardar; los totales usan las aplicaciones vigentes. Los cambios antiguos de monto o anulaciones que no generaron movimiento se consultan en Créditos.</p>
            <div class="cu-table-wrap"><table><thead><tr><th>Fecha</th><th>Movimiento</th><th>Crédito</th><th>Origen</th><th>Sendero del movimiento</th><th>Monto</th><th>Responsable</th><th>Nota</th></tr></thead><tbody>
            <?php foreach ($itemsPage as $r): ?><tr><td><?= sgcu_h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td><td><?= sgcu_h(['creacion' => 'Creación', 'aplicacion' => 'Aplicación', 'ajuste' => 'Ajuste', 'anulacion' => 'Anulación'][$r['tipo']] ?? $r['tipo']) ?></td><td>#<?= (int) $r['credito_id'] ?></td><td class="cu-route"><?= sgcu_h($r['origen'] ?: 'Sin origen') ?></td><td class="cu-route"><?= sgcu_h($r['destino'] ?: 'Sin sendero') ?></td><td class="cu-money"><?= sgcu_money(sgcu_cents($r['monto'])) ?></td><td><?= sgcu_h($r['responsable'] ?: 'Sin responsable') ?></td><td class="cu-note-cell"><?= sgcu_h($r['nota']) ?></td></tr><?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="8" class="cu-empty">No hay movimientos registrados en el período.</td></tr><?php endif; ?>
            </tbody></table></div>
        <?php endif; ?>
        </section>
        <?php endif; ?>
        <div class="cu-pagination" role="navigation" aria-label="Paginación"><span>Página <?= $pagina ?> de <?= $pageCount ?></span>
            <?php if ($pagina > 1): ?><a href="<?= sgcu_h(cu_url($base, $filters, ['pagina' => $pagina - 1, 'vista' => $vista])) ?>" aria-label="Página anterior"><i data-feather="chevron-left"></i></a><?php endif; ?>
            <?php if ($pagina < $pageCount): ?><a href="<?= sgcu_h(cu_url($base, $filters, ['pagina' => $pagina + 1, 'vista' => $vista])) ?>" aria-label="Página siguiente"><i data-feather="chevron-right"></i></a><?php endif; ?>
        </div>
        <?php if ($filters['criterio'] === 'pago' && ($filters['desde'] || $filters['hasta'])): ?><p class="cu-note">Los registros sin fecha de pago quedan fuera de los importes del período. Los saldos actuales siguen incluyendo todas las fechas.</p><?php endif; ?>
        <?php endif; ?>
    </div>
</main>
<?php
mysqli_close($conn);
include_once __DIR__ . '/../componentes/pie_pagina.php';
