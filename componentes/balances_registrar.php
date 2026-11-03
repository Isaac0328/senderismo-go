<?php
// Pestanas "Registrar gasto" y "Registrar ingreso" de Balances. El guardado lo hace
// bal_registrar() (componentes/balances_servicio.php) en una sola transaccion.

function bal_registrar_vista(mysqli $conn, string $nature, array $types, array $thirds, array $accounts, array $methods, string $obligationSql, string $url, bool $puedeCrear = true, bool $puedeEditar = false, bool $puedeCancelar = false): void
{
    $esGasto = $nature === 'salida';
    $palabra = $esGasto ? 'gasto' : 'ingreso';
    $cuentaPor = $esGasto ? 'pagar' : 'cobrar';
    $tipos = array_values(array_filter($types, static fn(array $t): bool => $t['naturaleza'] === $nature));
    // Los tipos muestran su nombre sin el prefijo "Ingreso: / Gasto: " que se usa en otras listas.
    foreach ($tipos as &$tipo) {
        $tipo['nombre'] = preg_replace('/^(Ingreso|Gasto|Salida): /', '', (string) $tipo['nombre']);
    }
    unset($tipo);
    $pendientes = bal_rows($conn, $obligationSql . ' WHERE o.anulada=0 AND o.naturaleza=? HAVING pendiente > 0 ORDER BY o.vencimiento, o.id', [$nature]);
    $pestana = $esGasto ? 'gasto' : 'ingreso';
    // Editar abre el formulario de Crear con los datos del movimiento elegido en Consultar.
    $edicion = null;
    $errorEdicion = '';
    $editarId = (int) ($_GET['editar'] ?? 0);
    if ($editarId > 0) {
        if (!$puedeEditar) {
            $errorEdicion = 'No tienes permiso para editar.';
        } else {
            $movimiento = bal_one($conn, 'SELECT * FROM fin_movimientos WHERE id=? AND naturaleza=?', [$editarId, $nature]);
            $errorEdicion = bal_movimiento_bloqueo($conn, $movimiento, true) ?? '';
            $edicion = $errorEdicion === '' ? $movimiento : null;
        }
    }
    $e = $edicion ?? [];
    // Crear y Consultar son dos vistas de la misma pestana; quien no puede crear solo consulta.
    $vista = $edicion ? 'crear' : (!$puedeCrear || $errorEdicion !== '' || ($_GET['vista'] ?? '') === 'consultar' ? 'consultar' : 'crear');
    $urlConsulta = $url . '?tab=' . $pestana . '&vista=consultar';
    // Conceptos y referencias usados antes con cada persona o entidad, del mas reciente al mas antiguo.
    $historial = bal_rows($conn, "SELECT tercero_id, concepto, referencia FROM (
            SELECT m.tercero_id, m.concepto, m.referencia, m.fecha, m.id FROM fin_movimientos m
            WHERE m.naturaleza = ? AND m.tercero_id IS NOT NULL AND m.reversa_id IS NULL
            UNION ALL
            SELECT o.tercero_id, o.concepto, '' referencia, DATE(o.created_at) fecha, o.id FROM fin_obligaciones o
            WHERE o.naturaleza = ? AND o.anulada = 0
        ) h ORDER BY fecha DESC, id DESC LIMIT 600", [$nature, $nature]);
    $sugeridos = [];
    foreach ($historial as $h) {
        $clave = (string) (int) $h['tercero_id'];
        foreach (['concepto' => 'conceptos', 'referencia' => 'referencias'] as $campo => $lista) {
            $valor = trim((string) $h[$campo]);
            $sugeridos[$clave][$lista] ??= [];
            if ($valor !== '' && count($sugeridos[$clave][$lista]) < 6 && !in_array($valor, $sugeridos[$clave][$lista], true)) {
                $sugeridos[$clave][$lista][] = $valor;
            }
        }
    }
    $modos = [
        'contado' => [$esGasto ? 'Pagado ahora' : 'Cobrado ahora', $esGasto ? 'El dinero sale hoy de una cuenta.' : 'El dinero entra hoy a una cuenta.', 'check-circle'],
        'credito' => ['Queda pendiente', 'Se registra una cuenta por ' . $cuentaPor . '. Puedes dar un abono inicial.', 'clock'],
        'abono' => ['Abono a cuenta por ' . $cuentaPor, ($esGasto ? 'Paga' : 'Cobra') . ' parte o todo de una cuenta pendiente (' . count($pendientes) . ').', 'corner-down-right'],
    ];
    $hoy = date('Y-m-d');
    ?>
<nav class="bal-subtabs bal-registro-tabs" aria-label="Crear o consultar">
<?php if ($puedeCrear): ?><a class="<?= $vista === 'crear' && !$edicion ? 'active' : '' ?>" href="<?= bal_h($url . '?tab=' . $pestana) ?>" <?= $vista === 'crear' && !$edicion ? 'aria-current="page"' : '' ?>><i data-feather="plus-circle"></i>Crear</a><?php endif; ?>
<a class="<?= $vista === 'consultar' ? 'active' : '' ?>" href="<?= bal_h($url . '?tab=' . $pestana . '&vista=consultar') ?>" <?= $vista === 'consultar' ? 'aria-current="page"' : '' ?>><i data-feather="search"></i>Consultar</a>
<?php if ($edicion): ?><a class="active" href="<?= bal_h($url . '?tab=' . $pestana . '&editar=' . (int) $edicion['id']) ?>" aria-current="page"><i data-feather="edit-2"></i>Editando #<?= (int) $edicion['id'] ?></a><?php endif; ?>
</nav>
<?php if ($errorEdicion !== ''): ?><p class="bal-notice error"><?= bal_h($errorEdicion) ?></p><?php endif; ?>
<?php if ($vista === 'consultar') { bal_registrar_consulta($conn, $nature, $tipos, $thirds, $accounts, $url, $puedeEditar, $puedeCancelar); return; } ?>
<div class="bal-registro bal-registro-solo">
<section class="bal-section bal-registro-principal">
<header><div><h2><?= $edicion ? 'Editar ' . $palabra . ' #' . (int) $edicion['id'] : 'Registrar ' . $palabra ?></h2><p class="bal-section-help"><?= $edicion ? 'Corrige los datos y pulsa Actualizar. Antes de guardar te pediremos confirmacion.' : ($esGasto
    ? 'Cualquier gasto que no sea de un sendero: servicios, compras, sueldos, impuestos... Elige si se paga ahora, queda como cuenta por pagar o es un abono a una que ya existe.'
    : 'Cualquier entrada de dinero que no sea un cobro de sendero: donaciones, patrocinios, ventas, aportes... Elige si se cobra ahora, queda como cuenta por cobrar o es un abono a una que ya existe.') ?></p></div></header>

<form class="bal-form bal-registro-form" method="post" action="<?= bal_h(BASE_URL . 'procesos/proceso_balances.php') ?>" data-registro data-naturaleza="<?= $nature ?>"<?php if ($edicion): ?> data-confirmar="<?= bal_h('Seguro que deseas actualizar este ' . $palabra . '? Se cambiaran sus datos y el saldo de la cuenta se recalculara.') ?>" data-destino="<?= bal_h($urlConsulta) ?>"<?php endif; ?>>
<?= csrf_field() ?>
<input type="hidden" name="action" value="<?= $edicion ? 'editar_movimiento' : 'registrar' ?>">
<?php if ($edicion): ?><input type="hidden" name="id" value="<?= (int) $edicion['id'] ?>"><?php endif; ?>
<input type="hidden" name="token" value="<?= bal_h(bin2hex(random_bytes(16))) ?>">
<input type="hidden" name="naturaleza" value="<?= $nature ?>">
<p class="bal-form-error" role="alert" tabindex="-1" hidden></p>

<fieldset class="bal-registro-paso" <?= $edicion ? 'hidden style="display:none"' : '' ?>>
<legend><span>1</span>¿Como se <?= $esGasto ? 'paga' : 'cobra' ?>?</legend>
<div class="bal-registro-modos">
<?php foreach ($modos as $valor => [$titulo, $detalle, $icono]): ?>
<label class="bal-registro-modo">
<input type="radio" name="modo" value="<?= $valor ?>" <?= $valor === 'contado' ? 'checked' : '' ?> <?= $valor === 'abono' && !$pendientes ? 'disabled' : '' ?>>
<span><i data-feather="<?= $icono ?>"></i><strong><?= bal_h($titulo) ?></strong><small><?= bal_h($valor === 'abono' && !$pendientes ? 'No hay cuentas por ' . $cuentaPor . ' pendientes.' : $detalle) ?></small></span>
</label>
<?php endforeach; ?>
</div>
</fieldset>

<fieldset class="bal-registro-paso" data-modo-campos="contado credito">
<legend><span>2</span>Detalle del <?= $palabra ?></legend>
<div class="bal-registro-grid">
<?php bal_select('tipo_id', 'Tipo de ' . $palabra, $tipos, true, $e['tipo_id'] ?? ''); ?>
<?php $etiquetaTercero = $esGasto ? 'Pagado a (persona o entidad)' : 'Recibido de (persona o entidad)';
if ($edicion) { bal_tercero_select($thirds, false, $e['tercero_id'] ?? '', $etiquetaTercero); } else { bal_tercero_o_usuario($conn, $thirds, false, '', $etiquetaTercero, $nature); } ?>
<?php bal_input('concepto', 'Concepto', 'text', $e['concepto'] ?? ''); ?>
<?php bal_sendero_select($conn, $e['sendero_id'] ?? ''); ?>
<?php if ($esGasto && bal_movimientos_reembolso_listo($conn)): ?><label data-modo-campos="contado"><span>Reembolsado a</span><input type="text" name="reembolsado_a" maxlength="160" placeholder="Opcional" autocomplete="off" value="<?= bal_h($e['reembolsado_a'] ?? '') ?>"></label><?php endif; ?>
<p class="bal-field-help" data-solo-credito hidden>Para una cuenta por <?= $cuentaPor ?> la persona o entidad es obligatoria.</p>
</div>
<?php if (!$tipos): ?><p class="bal-field-help">No hay tipos de <?= $palabra ?>. <a href="<?= bal_h($url . '?tab=catalogos&catalogo=tipos') ?>">Agregalos en Tipos de ingresos y gastos</a>.</p><?php endif; ?>
</fieldset>

<fieldset class="bal-registro-paso" data-modo-campos="abono">
<legend><span>2</span>Cuenta por <?= $cuentaPor ?></legend>
<div class="bal-registro-grid">
<label class="bal-registro-ancho"><span>Cuenta pendiente *</span><select name="obligacion_id" required data-obligacion>
<option value="">Seleccionar</option>
<?php foreach ($pendientes as $o): ?>
<option value="<?= (int) $o['id'] ?>" data-pendiente="<?= bal_h($o['pendiente']) ?>" data-tercero="<?= (int) $o['tercero_id'] ?>"><?= bal_h($o['concepto'] . ' · ' . $o['tercero'] . ' · pendiente ' . bal_money($o['pendiente']) . ' · vence ' . date('d/m/Y', strtotime($o['vencimiento']))) ?></option>
<?php endforeach; ?>
</select></label>
</div>
</fieldset>

<fieldset class="bal-registro-paso">
<legend><span>3</span>Monto</legend>
<div class="bal-registro-grid">
<label><span data-monto-titulo>Monto (RD$) *</span><input type="number" name="monto" step="0.01" min="0.01" required data-monto<?= $edicion ? ' value="' . bal_h(number_format(abs((float) $e['importe']), 2, '.', '')) . '"' : '' ?>></label>
<label data-modo-campos="credito"><span>Vencimiento *</span><input type="date" name="vencimiento" value="<?= $hoy ?>" required></label>
<label data-modo-campos="credito"><span>Abono inicial (opcional)</span><input type="number" name="abono_inicial" step="0.01" min="0" placeholder="0.00" data-abono-inicial></label>
</div>
<p class="bal-registro-resumen" data-resumen aria-live="polite"></p>
</fieldset>

<fieldset class="bal-registro-paso" data-pago>
<legend><span>4</span><?= $esGasto ? '¿De que cuenta sale el dinero?' : '¿A que cuenta entra el dinero?' ?></legend>
<div class="bal-registro-grid">
<?php bal_select('cuenta_id', 'Cuenta', $accounts, true, $e['cuenta_id'] ?? ''); ?>
<?php bal_input('fecha', 'Fecha efectiva', 'date', $e['fecha'] ?? $hoy); ?>
<?php bal_select('metodo_pago_id', 'Metodo de pago', $methods, false, $e['metodo_pago_id'] ?? ''); ?>
<?php bal_input('referencia', 'Referencia / comprobante', 'text', $e['referencia'] ?? '', false); ?>
</div>
</fieldset>

<script type="application/json" data-sugeridos><?= json_encode($sugeridos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<datalist id="bal-sugeridos-concepto-<?= $nature ?>"></datalist><datalist id="bal-sugeridos-referencia-<?= $nature ?>"></datalist>
<footer class="bal-form-footer"><span>* Obligatorio</span><?php if ($edicion): ?><a class="bal-cancelar-edicion" href="<?= bal_h($urlConsulta) ?>">Cancelar edicion</a><?php endif; ?><button class="bal-primary" type="submit"><i data-feather="check"></i><span data-boton><?= $edicion ? 'Actualizar ' . $palabra : 'Registrar ' . $palabra ?></span></button></footer>
<p class="bal-notice success bal-registro-feedback" data-bal-registro-feedback role="status" aria-live="polite" hidden></p>
</form>
</section>

</div>
<?php
}

// Consulta de gastos o ingresos registrados aqui (los que no son de sendero), con filtros, totales,
// y las acciones Editar y Cancelar. Cancelar no borra: el movimiento queda como Cancelado y deja de contar.
function bal_registrar_consulta(mysqli $conn, string $nature, array $tipos, array $thirds, array $accounts, string $url, bool $puedeEditar = false, bool $puedeCancelar = false): void
{
    $esGasto = $nature === 'salida';
    $pestana = $esGasto ? 'gasto' : 'ingreso';
    $palabra = $esGasto ? 'gastos' : 'ingresos';
    $singular = $esGasto ? 'gasto' : 'ingreso';
    $porPagina = 25;

    $fecha = static fn(string $clave): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$clave] ?? '')) ? (string) $_GET[$clave] : '';
    $desde = $fecha('desde');
    $hasta = $fecha('hasta');
    $terceroId = max(0, (int) ($_GET['entidad'] ?? 0));
    $tipoId = max(0, (int) ($_GET['tipo'] ?? 0));
    $cuentaId = max(0, (int) ($_GET['cuenta'] ?? 0));
    $buscar = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $estado = in_array($_GET['estado'] ?? '', ['cancelados', 'todos'], true) ? (string) $_GET['estado'] : 'validos';
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

    $where = "m.naturaleza = ? AND m.origen IN ('manual','obligacion') AND m.reversa_id IS NULL";
    $params = [$nature];
    if ($desde !== '') { $where .= ' AND m.fecha >= ?'; $params[] = $desde; }
    if ($hasta !== '') { $where .= ' AND m.fecha <= ?'; $params[] = $hasta; }
    if ($terceroId) { $where .= ' AND m.tercero_id = ?'; $params[] = $terceroId; }
    if ($tipoId) { $where .= ' AND m.tipo_id = ?'; $params[] = $tipoId; }
    if ($cuentaId) { $where .= ' AND m.cuenta_id = ?'; $params[] = $cuentaId; }
    if ($buscar !== '') {
        $where .= ' AND (m.concepto LIKE ? OR m.referencia LIKE ? OR te.nombre LIKE ?' . (bal_movimientos_reembolso_listo($conn) ? ' OR m.reembolsado_a LIKE ?' : '') . ')';
        array_push($params, "%{$buscar}%", "%{$buscar}%", "%{$buscar}%");
        if (bal_movimientos_reembolso_listo($conn)) { $params[] = "%{$buscar}%"; }
    }
    $cancelado = 'EXISTS (SELECT 1 FROM fin_movimientos rv WHERE rv.reversa_id = m.id)';
    $conReembolso = bal_movimientos_reembolso_listo($conn);
    $from = ' FROM fin_movimientos m JOIN fin_cuentas c ON c.id = m.cuenta_id LEFT JOIN fin_tipos t ON t.id = m.tipo_id LEFT JOIN fin_terceros te ON te.id = m.tercero_id LEFT JOIN senderos se ON se.id = m.sendero_id';

    // Los totales cuentan solo lo valido; los cancelados se muestran aparte.
    $validos = bal_one($conn, 'SELECT COUNT(*) total, COALESCE(SUM(ABS(m.importe)), 0) suma' . $from . ' WHERE ' . $where . ' AND NOT ' . $cancelado, $params);
    $cancelados = (int) bal_one($conn, 'SELECT COUNT(*) total' . $from . ' WHERE ' . $where . ' AND ' . $cancelado, $params)['total'];
    $whereTabla = $where . ($estado === 'validos' ? ' AND NOT ' . $cancelado : ($estado === 'cancelados' ? ' AND ' . $cancelado : ''));
    $total = (int) bal_one($conn, 'SELECT COUNT(*) total' . $from . ' WHERE ' . $whereTabla, $params)['total'];
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min($pagina, $paginas);
    $filas = bal_rows($conn, 'SELECT m.id, m.fecha, m.concepto, m.referencia, m.importe, m.origen, c.nombre cuenta, t.nombre tipo, te.nombre tercero, se.nombre sendero, '
        . ($conReembolso ? 'm.reembolsado_a' : 'NULL') . ' reembolsado_a, '
        . $cancelado . ' cancelado, (SELECT r.created_at FROM fin_movimientos r WHERE r.reversa_id = m.id LIMIT 1) cancelado_en'
        . $from . ' WHERE ' . $whereTabla . ' ORDER BY m.fecha DESC, m.id DESC LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina), $params);
    $hayFiltros = $desde !== '' || $hasta !== '' || $terceroId || $tipoId || $cuentaId || $buscar !== '' || $estado !== 'validos';
    $enlacePagina = static fn(int $n): string => $url . '?' . http_build_query(array_merge($_GET, ['tab' => $pestana, 'vista' => 'consultar', 'pagina' => $n]));
    $proceso = BASE_URL . 'procesos/proceso_balances.php';
    $columnas = 8;
    ?>
<section class="bal-section bal-registro-consulta">
<header><div><h2>Consultar <?= $palabra ?></h2><p class="bal-section-help">Lo registrado en esta pesta&ntilde;a. Cancelar un <?= $singular ?> no lo elimina: queda como Cancelado y deja de sumar y restar. Los <?= $esGasto ? 'gastos' : 'cobros' ?> de senderos se consultan en Movimientos.</p></div></header>
<form class="bal-filters" method="get">
<input type="hidden" name="tab" value="<?= $pestana ?>"><input type="hidden" name="vista" value="consultar">
<label>Desde<input type="date" name="desde" value="<?= bal_h($desde) ?>"></label>
<label>Hasta<input type="date" name="hasta" value="<?= bal_h($hasta) ?>"></label>
<label><?= $esGasto ? 'Pagado a' : 'Recibido de' ?><select name="entidad"><option value="">Todos</option><?php foreach ($thirds as $th): ?><option value="<?= (int) $th['id'] ?>" <?= (int) $th['id'] === $terceroId ? 'selected' : '' ?>><?= bal_h($th['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Tipo<select name="tipo"><option value="">Todos</option><?php foreach ($tipos as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $tipoId ? 'selected' : '' ?>><?= bal_h($t['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Cuenta<select name="cuenta"><option value="">Todas</option><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === $cuentaId ? 'selected' : '' ?>><?= bal_h($a['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Estado<select name="estado"><option value="validos">V&aacute;lidos</option><option value="cancelados" <?= $estado === 'cancelados' ? 'selected' : '' ?>>Cancelados</option><option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option></select></label>
<label>Buscar<input type="search" name="q" value="<?= bal_h($buscar) ?>" placeholder="Concepto, referencia o nombre"></label>
<button type="submit"><i data-feather="filter"></i>Consultar</button><a href="<?= bal_h($url . '?tab=' . $pestana . '&vista=consultar') ?>">Limpiar</a>
</form>

<div class="bal-metrics bal-metrics-compact bal-metrics-tres">
<div><span>Total <?= $palabra ?> v&aacute;lidos</span><strong class="<?= $esGasto ? 'bal-negative' : 'bal-positive' ?>"><?= bal_money($validos['suma']) ?></strong></div>
<div><span>Registros v&aacute;lidos</span><strong><?= (int) $validos['total'] ?></strong></div>
<div><span>Cancelados</span><strong><?= $cancelados ?></strong></div>
</div>

<div class="bal-table-wrap"><table>
<thead><tr><th>Fecha</th><th>Concepto</th><th><?= $esGasto ? 'Pagado a' : 'Recibido de' ?></th><th>Tipo</th><th>Cuenta</th><th>Importe</th><th>Estado</th><th></th></tr></thead>
<tbody>
<?php foreach ($filas as $f):
    $esCancelado = (int) $f['cancelado'] === 1;
    // Los ajustes de saldo inicial se manejan desde Cuadres.
    $gestionable = !$esCancelado && ($f['tipo'] ?? '') !== 'Ajuste de saldo inicial';
    $editable = $gestionable && $puedeEditar && $f['origen'] === 'manual';
    $cancelable = $gestionable && $puedeCancelar;
    $aviso = 'Cancelar este ' . $singular . '? "' . $f['concepto'] . '" por ' . bal_money(abs((float) $f['importe'])) . '. No se elimina: queda en el historial como Cancelado y deja de sumar y restar en la cuenta.';
?>
<tr class="<?= $esCancelado ? 'is-cancelado' : '' ?>"><td><?= bal_h(date('d/m/Y', strtotime($f['fecha']))) ?><small>#<?= (int) $f['id'] ?></small></td>
<td><?= bal_h($f['concepto']) ?><?php if ($f['referencia'] !== ''): ?><small><?= bal_h($f['referencia']) ?></small><?php endif; ?><?php if (!empty($f['sendero'])): ?><small>Sendero: <?= bal_h($f['sendero']) ?></small><?php endif; ?><?php if (!empty($f['reembolsado_a'])): ?><small>Reembolsado a: <?= bal_h($f['reembolsado_a']) ?></small><?php endif; ?><?php if ($f['origen'] === 'obligacion'): ?><small>Abono a cuenta por <?= $esGasto ? 'pagar' : 'cobrar' ?></small><?php endif; ?></td>
<td><?= bal_h($f['tercero'] ?: '—') ?></td><td><?= bal_h($f['tipo'] ?: '—') ?></td><td><?= bal_h($f['cuenta']) ?></td>
<td class="bal-registro-importe <?= bal_cents($f['importe']) < 0 ? 'bal-negative' : 'bal-positive' ?>"><?= bal_money($f['importe']) ?></td>
<td><?php if ($esCancelado): ?><span class="bal-estado is-cancelado">Cancelado</span><small><?= bal_h(date('d/m/Y', strtotime((string) $f['cancelado_en']))) ?></small><?php else: ?><span class="bal-estado is-valido">V&aacute;lido</span><?php endif; ?></td>
<td class="bal-registro-acciones"><?php if ($editable): ?><a class="bal-row-action" href="<?= bal_h($url . '?tab=' . $pestana . '&editar=' . (int) $f['id']) ?>"><i data-feather="edit-2"></i>Editar</a><?php endif; ?>
<?php if ($cancelable): ?><form method="post" action="<?= bal_h($proceso) ?>" data-bal-accion data-confirm="<?= bal_h($aviso) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="cancelar_movimiento"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><input type="hidden" name="token" value="<?= bal_h(bin2hex(random_bytes(16))) ?>"><button type="submit" class="bal-row-action is-danger"><i data-feather="slash"></i>Cancelar</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$filas): ?><tr><td colspan="<?= $columnas ?>" class="bal-empty"><?= $hayFiltros ? 'Nada coincide con los filtros.' : 'Aun no hay ' . $palabra . ' registrados aqui.' ?></td></tr><?php endif; ?>
</tbody></table></div>

<?php if ($paginas > 1): ?><div class="bal-pagination" aria-label="Paginas">
<?php if ($pagina > 1): ?><a href="<?= bal_h($enlacePagina($pagina - 1)) ?>">Anterior</a><?php endif; ?>
<span>Pagina <?= $pagina ?> de <?= $paginas ?></span>
<?php if ($pagina < $paginas): ?><a href="<?= bal_h($enlacePagina($pagina + 1)) ?>">Siguiente</a><?php endif; ?>
</div><?php endif; ?>
<p class="bal-registro-enlaces"><a href="<?= bal_h($url . '?tab=movimientos&tipo_mov=' . ($esGasto ? 'otros_gastos' : 'otros_ingresos') . '&estado=todos') ?>">Ver en Movimientos</a><a href="<?= bal_h($url . '?tab=' . ($esGasto ? 'pagar' : 'cobrar')) ?>">Ver cuentas por <?= $esGasto ? 'pagar' : 'cobrar' ?></a></p>
</section>
<?php
}
