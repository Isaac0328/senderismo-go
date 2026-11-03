<?php
// Pestanas "Por cobrar" y "Por pagar" de Balances, divididas en Crear y Consultar.
// En Por cobrar, la consulta incluye automaticamente lo que deben quienes asistieron a un sendero
// que ya paso y no han pagado (se calcula igual que en Ingresos por sendero, asi nunca se desfasa).

if (!function_exists('ic_cargar')) {
    require_once __DIR__ . '/ingresos_cobro.php';
}

if (!function_exists('bal_cobros_senderos_pendientes')) {
    // Asistentes de senderos ya realizados con saldo pendiente: [fila, ...] ordenadas por fecha reciente.
    function bal_cobros_senderos_pendientes(mysqli $conn): array
    {
        if (!ic_esquema_listo($conn)) {
            return [];
        }
        $senderos = bal_rows($conn, "SELECT DISTINCT s.id, s.nombre, s.fecha_sendero FROM senderos s
            JOIN registros_senderos rs ON rs.sendero_id = s.id
            WHERE rs.estado = 'registrado' AND rs.asistio = 1 AND s.fecha_sendero < CURDATE()
            ORDER BY s.fecha_sendero DESC, s.id DESC");
        $deudas = [];
        foreach ($senderos as $sendero) {
            foreach (ic_cargar($conn, (int) $sendero['id']) as $fila) {
                $calculo = $fila['calculo'];
                if ($calculo['modo'] !== 'asistio' || $calculo['saldo'] <= 0) {
                    continue;
                }
                $deudas[] = [
                    'sendero_id' => (int) $sendero['id'],
                    'sendero' => $sendero['nombre'],
                    'fecha' => $sendero['fecha_sendero'],
                    'registro_id' => (int) $fila['registro_id'],
                    'persona' => $fila['nombre'],
                    'telefono' => $fila['telefono'],
                    'con_cuenta' => (int) $fila['usuario_id'] > 0,
                    'monto' => $calculo['debe_pagar'],
                    'pagado' => $calculo['retenido'] + $calculo['credito'],
                    'pendiente' => $calculo['saldo'],
                ];
            }
        }
        return $deudas;
    }
}

// Abre el formulario de una cuenta por cobrar/pagar con los mismos campos ocultos que bal_form().
function bal_obligaciones_form(string $action, array $hidden = [], array $atributos = []): void
{
    $extra = '';
    foreach ($atributos as $nombre => $valor) {
        $extra .= ' ' . $nombre . '="' . bal_h($valor) . '"';
    }
    echo '<form class="bal-form" method="post" action="' . bal_h(BASE_URL . 'procesos/proceso_balances.php') . '"' . $extra . '>' . csrf_field();
    foreach (['action' => $action, 'token' => bin2hex(random_bytes(16))] + $hidden as $nombre => $valor) {
        echo '<input type="hidden" name="' . bal_h($nombre) . '" value="' . bal_h($valor) . '">';
    }
    echo '<p class="bal-form-error" role="alert" tabindex="-1" hidden></p>';
}

function bal_obligaciones_vista(
    mysqli $conn, string $tab, array $types, array $thirds, array $accounts, array $methods,
    string $obligationSql, string $url, bool $puedeCrear, bool $puedeEditar, bool $puedeAnular
): void {
    $esCobro = $tab === 'cobrar';
    $nature = $esCobro ? 'ingreso' : 'salida';
    $titulo = $esCobro ? 'cuenta por cobrar' : 'cuenta por pagar';
    $plural = $esCobro ? 'cuentas por cobrar' : 'cuentas por pagar';
    $tipos = array_values(array_filter($types, static fn($t) => $t['naturaleza'] === $nature));
    foreach ($tipos as &$tipo) {
        $tipo['nombre'] = preg_replace('/^(Ingreso|Gasto|Salida): /', '', (string) $tipo['nombre']);
    }
    unset($tipo);

    // Editar abre el formulario de Crear con los datos de la cuenta elegida.
    $edicion = null;
    $errorEdicion = '';
    $editarId = (int) ($_GET['editar'] ?? 0);
    if ($editarId > 0) {
        $cuenta = bal_one($conn, $obligationSql . ' WHERE o.id=? AND o.naturaleza=?', [$editarId, $nature]);
        if (!$puedeEditar) {
            $errorEdicion = 'No tienes permiso para editar.';
        } elseif (!$cuenta || $cuenta['anulada']) {
            $errorEdicion = 'Esa cuenta no existe o ya esta anulada.';
        } elseif (bal_cents($cuenta['pendiente']) !== bal_cents($cuenta['monto'])) {
            $errorEdicion = 'Una cuenta con pagos o cobros registrados ya no se edita. Registra el resto como abono.';
        } else {
            $edicion = $cuenta;
        }
    }
    $vista = $edicion ? 'crear' : (!$puedeCrear || $errorEdicion !== '' || ($_GET['vista'] ?? '') === 'consultar' ? 'consultar' : 'crear');
    $urlConsulta = $url . '?tab=' . $tab . '&vista=consultar';
    ?>
<nav class="bal-subtabs bal-registro-tabs" aria-label="Crear o consultar">
<?php if ($puedeCrear): ?><a class="<?= $vista === 'crear' && !$edicion ? 'active' : '' ?>" href="<?= bal_h($url . '?tab=' . $tab) ?>" <?= $vista === 'crear' && !$edicion ? 'aria-current="page"' : '' ?>><i data-feather="plus-circle"></i>Crear</a><?php endif; ?>
<a class="<?= $vista === 'consultar' ? 'active' : '' ?>" href="<?= bal_h($urlConsulta) ?>" <?= $vista === 'consultar' ? 'aria-current="page"' : '' ?>><i data-feather="search"></i>Consultar</a>
<?php if ($edicion): ?><a class="active" href="<?= bal_h($url . '?tab=' . $tab . '&editar=' . (int) $edicion['id']) ?>" aria-current="page"><i data-feather="edit-2"></i>Editando #<?= (int) $edicion['id'] ?></a><?php endif; ?>
</nav>
<?php if ($errorEdicion !== ''): ?><p class="bal-notice error"><?= bal_h($errorEdicion) ?></p><?php endif; ?>

<?php if ($vista === 'crear'): ?>
<div class="bal-registro bal-registro-solo">
<section class="bal-section bal-registro-principal">
<header><div><h2><?= $edicion ? 'Editar ' . $titulo . ' #' . (int) $edicion['id'] : 'Registrar ' . $titulo ?></h2><p class="bal-section-help"><?= $edicion
    ? 'Corrige los datos y pulsa Actualizar. Antes de guardar te pediremos confirmacion.'
    : ($esCobro ? 'Ingresos pendientes de recibir de clientes, entidades u otras personas. Las personas que asistieron a un sendero y no han pagado aparecen solas en Consultar.' : 'Pagos pendientes a empleados, directivos, proveedores u otras entidades.') ?></p></div></header>
<?php
    bal_obligaciones_form('obligacion', $edicion ? ['id' => $edicion['id']] : [], $edicion
        ? ['data-confirmar' => 'Seguro que deseas actualizar esta ' . $titulo . '? Se cambiaran sus datos.', 'data-destino' => $urlConsulta, 'class' => 'bal-form bal-registro-form']
        : []);
    echo '<div class="bal-registro-grid">';
    bal_select('tipo_id', 'Tipo', $tipos, true, $edicion['tipo_id'] ?? '');
    $etiquetaTercero = $esCobro ? 'Quien debe pagar (persona o entidad)' : 'A quien se le debe (persona o entidad)';
    if ($edicion) { bal_tercero_select($thirds, true, $edicion['tercero_id'] ?? '', $etiquetaTercero); }
    else { bal_tercero_o_usuario($conn, $thirds, true, '', $etiquetaTercero, $esCobro ? 'ingreso' : 'salida'); }
    bal_input('concepto', 'Concepto', 'text', $edicion['concepto'] ?? '');
    bal_input('monto', 'Monto (RD$)', 'number', $edicion['monto'] ?? '');
    bal_input('vencimiento', 'Vencimiento', 'date', $edicion['vencimiento'] ?? date('Y-m-d'));
    bal_sendero_select($conn, $edicion['sendero_id'] ?? '', true);
    echo '</div>';
    echo '<footer class="bal-form-footer"><span>* Obligatorio</span>';
    if ($edicion) { echo '<a class="bal-cancelar-edicion" href="' . bal_h($urlConsulta) . '">Cancelar edicion</a>'; }
    echo '<button class="bal-primary" type="submit"><i data-feather="check"></i>' . ($edicion ? 'Actualizar' : 'Registrar') . ' ' . $titulo . '</button></footer></form>';
    if (!$tipos) { echo '<p class="bal-field-help">No hay tipos de ' . ($esCobro ? 'ingreso' : 'gasto') . '. <a href="' . bal_h($url . '?tab=catalogos&catalogo=tipos') . '">Agregalos en Tipos de ingresos y gastos</a>.</p>'; }
?>
</section>
</div>
<?php return; endif; ?>
<?php
    // ---------------- Consultar ----------------
    $nombresSendero = array_column(bal_rows($conn, 'SELECT id, nombre FROM senderos'), 'nombre', 'id');
    $fecha = static fn(string $clave): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$clave] ?? '')) ? (string) $_GET[$clave] : '';
    $desde = $fecha('desde');
    $hasta = $fecha('hasta');
    $terceroId = max(0, (int) ($_GET['entidad'] ?? 0));
    $buscar = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $estado = in_array($_GET['estado'] ?? '', ['pendientes', 'vencidas', 'saldadas', 'anuladas'], true) ? (string) $_GET['estado'] : 'todas';
    $origen = $esCobro && in_array($_GET['origen'] ?? '', ['manual', 'senderos'], true) ? (string) $_GET['origen'] : 'todos';
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
    $porPagina = 25;
    $hoy = date('Y-m-d');

    // Cuentas registradas a mano.
    $manuales = [];
    if ($origen !== 'senderos') {
        $where = 'o.naturaleza = ?';
        $params = [$nature];
        if ($desde !== '') { $where .= ' AND o.vencimiento >= ?'; $params[] = $desde; }
        if ($hasta !== '') { $where .= ' AND o.vencimiento <= ?'; $params[] = $hasta; }
        if ($terceroId) { $where .= ' AND o.tercero_id = ?'; $params[] = $terceroId; }
        if ($buscar !== '') { $where .= ' AND (o.concepto LIKE ? OR te.nombre LIKE ?)'; array_push($params, "%{$buscar}%", "%{$buscar}%"); }
        $having = ['pendientes' => 'o.anulada=0 AND pendiente>0', 'vencidas' => 'o.anulada=0 AND pendiente>0 AND o.vencimiento<?', 'saldadas' => 'o.anulada=0 AND pendiente<=0', 'anuladas' => 'o.anulada=1'][$estado] ?? '';
        if ($estado === 'vencidas') { $params[] = $hoy; }
        $manuales = bal_rows($conn, $obligationSql . ' WHERE ' . $where . ($having !== '' ? ' HAVING ' . $having : '') . ' ORDER BY o.anulada, o.vencimiento DESC, o.id DESC LIMIT 2000', $params);
    }

    // Lo que deben quienes asistieron a senderos ya realizados (solo Por cobrar).
    $deSenderos = [];
    if ($esCobro && $origen !== 'manual' && !$terceroId && in_array($estado, ['todas', 'pendientes', 'vencidas'], true)) {
        foreach (bal_cobros_senderos_pendientes($conn) as $d) {
            if (($desde !== '' && $d['fecha'] < $desde) || ($hasta !== '' && $d['fecha'] > $hasta)) { continue; }
            if ($buscar !== '' && mb_stripos($d['persona'] . ' ' . $d['sendero'], $buscar) === false) { continue; }
            $deSenderos[] = $d;
        }
    }

    // Totales y lista unica, de la fecha mas reciente a la mas antigua.
    $totalPendiente = 0;
    $totalVencido = 0;
    $filas = [];
    foreach ($manuales as $o) {
        if (!$o['anulada'] && bal_cents($o['pendiente']) > 0) {
            $totalPendiente += bal_cents($o['pendiente']);
            if ($o['vencimiento'] < $hoy) { $totalVencido += bal_cents($o['pendiente']); }
        }
        $filas[] = ['tipo' => 'manual', 'fecha' => $o['vencimiento'], 'orden' => $o['anulada'] ? 1 : 0, 'dato' => $o];
    }
    $totalSenderos = 0;
    foreach ($deSenderos as $d) {
        $totalSenderos += $d['pendiente'];
        $totalPendiente += $d['pendiente'];
        $totalVencido += $d['pendiente'];
        $filas[] = ['tipo' => 'sendero', 'fecha' => $d['fecha'], 'orden' => 0, 'dato' => $d];
    }
    usort($filas, static fn($a, $b) => $a['orden'] <=> $b['orden'] ?: strcmp($b['fecha'], $a['fecha']));
    $total = count($filas);
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min($pagina, $paginas);
    $visibles = array_slice($filas, ($pagina - 1) * $porPagina, $porPagina);
    $hayFiltros = $desde !== '' || $hasta !== '' || $terceroId || $buscar !== '' || $estado !== 'todas' || $origen !== 'todos';
    $enlacePagina = static fn(int $n): string => $url . '?' . http_build_query(array_merge($_GET, ['tab' => $tab, 'vista' => 'consultar', 'pagina' => $n]));
    $urlIngresos = BASE_URL . 'admin/ingresos-sendero';
    ?>
<section class="bal-section bal-registro-consulta">
<header><div><h2>Consultar <?= $plural ?></h2><p class="bal-section-help"><?= $esCobro ? 'Incluye, de forma automatica, a quienes asistieron a un sendero que ya paso y no han pagado. Esos cobros se registran en Ingresos por sendero.' : 'Pagos pendientes a empleados, directivos, proveedores u otras entidades.' ?></p></div></header>
<form class="bal-filters" method="get">
<input type="hidden" name="tab" value="<?= $tab ?>"><input type="hidden" name="vista" value="consultar">
<label>Buscar<input type="search" name="q" value="<?= bal_h($buscar) ?>" placeholder="Concepto, persona<?= $esCobro ? ' o sendero' : '' ?>"></label>
<label>Estado<select name="estado"><?php foreach (['todas' => 'Todas', 'pendientes' => 'Pendientes', 'vencidas' => 'Vencidas', 'saldadas' => 'Saldadas', 'anuladas' => 'Anuladas'] as $valor => $texto): ?><option value="<?= $valor ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $texto ?></option><?php endforeach; ?></select></label>
<?php if ($esCobro): ?><label>Origen<select name="origen"><?php foreach (['todos' => 'Todos', 'senderos' => 'De senderos (automaticas)', 'manual' => 'Registradas a mano'] as $valor => $texto): ?><option value="<?= $valor ?>" <?= $origen === $valor ? 'selected' : '' ?>><?= $texto ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label><?= $esCobro ? 'Persona o entidad' : 'Pagar a' ?><select name="entidad"><option value="">Todos</option><?php foreach ($thirds as $th): ?><option value="<?= (int) $th['id'] ?>" <?= (int) $th['id'] === $terceroId ? 'selected' : '' ?>><?= bal_h($th['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Vence desde<input type="date" name="desde" value="<?= bal_h($desde) ?>"></label>
<label>Vence hasta<input type="date" name="hasta" value="<?= bal_h($hasta) ?>"></label>
<button type="submit"><i data-feather="filter"></i>Consultar</button><a href="<?= bal_h($urlConsulta) ?>">Limpiar</a>
</form>

<div class="bal-metrics bal-metrics-compact <?= $esCobro ? '' : 'bal-metrics-tres' ?>">
<div><span>Pendiente total</span><strong><?= bal_money(bal_decimal($totalPendiente)) ?></strong></div>
<div><span>Vencido</span><strong class="bal-negative"><?= bal_money(bal_decimal($totalVencido)) ?></strong></div>
<?php if ($esCobro): ?><div><span>De senderos</span><strong><?= bal_money(bal_decimal($totalSenderos)) ?></strong></div><?php endif; ?>
<div><span>Registros</span><strong><?= $total ?></strong></div>
</div>

<div class="bal-table-wrap"><table>
<thead><tr><th>Vencimiento</th><th>Concepto / persona</th><th>Tipo</th><th>Monto</th><th>Pendiente</th><th>Estado</th><th></th></tr></thead>
<tbody>
<?php foreach ($visibles as $f): ?>
<?php if ($f['tipo'] === 'sendero'): $d = $f['dato']; ?>
<tr><td><?= bal_h(date('d/m/Y', strtotime($d['fecha']))) ?></td>
<td><strong class="bal-persona"><?= bal_h($d['persona']) ?></strong><small><?= bal_h($d['sendero']) ?><?= $d['telefono'] !== '' ? ' · ' . bal_h($d['telefono']) : '' ?><?= $d['con_cuenta'] ? '' : ' · sin cuenta' ?></small></td>
<td>Cobro de sendero<small>Autom&aacute;tica</small></td>
<td><?= bal_money(bal_decimal($d['monto'])) ?></td><td class="bal-negative"><?= bal_money(bal_decimal($d['pendiente'])) ?></td>
<td><span class="bal-estado is-vencida">Vencida</span><small>Asisti&oacute; y no ha pagado</small></td>
<td><div class="bal-actions"><?php if ($puedeCrear) { bal_open('factura-' . (int) $d['registro_id'], 'Factura', 'file-text'); } ?><a class="bal-row-action" href="<?= bal_h($urlIngresos . '?sendero_id=' . (int) $d['sendero_id']) ?>"><i data-feather="dollar-sign"></i>Cobrar</a></div></td></tr>
<?php else: $o = $f['dato'];
    $pendiente = bal_cents($o['pendiente']);
    $textoEstado = $o['anulada'] ? 'Anulada' : ($pendiente <= 0 ? 'Saldada' : ($o['vencimiento'] < $hoy ? 'Vencida' : 'Pendiente'));
    $claseEstado = $o['anulada'] ? 'is-cancelado' : ($pendiente <= 0 ? 'is-valido' : ($o['vencimiento'] < $hoy ? 'is-vencida' : 'is-pendiente'));
?>
<tr class="<?= $o['anulada'] ? 'is-cancelado' : '' ?>"><td><?= bal_h(date('d/m/Y', strtotime($o['vencimiento']))) ?><small>#<?= (int) $o['id'] ?></small></td>
<td><?= bal_h($o['concepto']) ?><small><?= bal_h($o['tercero']) ?></small><?php if (!empty($o['sendero_id'])): ?><small>Sendero: <?= bal_h($nombresSendero[(int) $o['sendero_id']] ?? 'eliminado') ?></small><?php endif; ?></td><td><?= bal_h($o['tipo']) ?></td>
<td><?= bal_money($o['monto']) ?></td><td><?= $o['anulada'] ? '--' : bal_money($o['pendiente']) ?></td>
<td><span class="bal-estado <?= $claseEstado ?>"><?= $textoEstado ?></span></td>
<td><div class="bal-actions">
<?php if (!$o['anulada']):
    if ($puedeCrear && $pendiente > 0) { bal_open('pago-' . $o['id'], $esCobro ? 'Cobrar' : 'Pagar', 'dollar-sign'); }
    if ($pendiente === bal_cents($o['monto'])) {
        if ($puedeEditar) { echo '<a class="bal-row-action" href="' . bal_h($url . '?tab=' . $tab . '&editar=' . (int) $o['id']) . '"><i data-feather="edit-2"></i>Editar</a>'; }
        if ($puedeAnular) { bal_open('anular-' . $o['id'], 'Anular', 'x'); }
    }
endif; ?></div></td></tr>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!$visibles): ?><tr><td colspan="7" class="bal-empty"><?= $hayFiltros ? 'Nada coincide con los filtros.' : 'No hay ' . $plural . ' registradas.' ?></td></tr><?php endif; ?>
</tbody></table></div>

<?php if ($paginas > 1): ?><div class="bal-pagination" aria-label="Paginas">
<?php if ($pagina > 1): ?><a href="<?= bal_h($enlacePagina($pagina - 1)) ?>">Anterior</a><?php endif; ?>
<span>Pagina <?= $pagina ?> de <?= $paginas ?></span>
<?php if ($pagina < $paginas): ?><a href="<?= bal_h($enlacePagina($pagina + 1)) ?>">Siguiente</a><?php endif; ?>
</div><?php endif; ?>
</section>
<?php
    // Ventanas de las cuentas que se ven en esta pagina.
    foreach ($visibles as $f) {
        if ($f['tipo'] === 'sendero') {
            // Crear factura: se elige la fecha y se previsualiza el PDF en una pestana nueva.
            if (!$puedeCrear) { continue; }
            $d = $f['dato'];
            bal_dialog_start('factura-' . (int) $d['registro_id'], 'Crear factura');
            echo '<div class="bal-factura-resumen"><strong>' . bal_h($d['persona']) . '</strong><span>' . bal_h($d['sendero']) . ' · ' . date('d/m/Y', strtotime($d['fecha'])) . '</span>'
                . '<b>Total a pagar: ' . bal_money(bal_decimal($d['pendiente'])) . '</b></div>';
            echo '<form class="bal-factura-form" method="get" action="' . bal_h(BASE_URL . 'procesos/proceso_factura_sendero.php') . '" target="_blank">';
            echo '<input type="hidden" name="registro_id" value="' . (int) $d['registro_id'] . '">';
            bal_input('fecha_emision', 'Fecha de emision', 'date', date('Y-m-d'));
            bal_input('vencimiento', 'Vence (opcional)', 'date', '', false);
            echo '<div class="bal-factura-ancho">'; bal_input('nota', 'Nota (opcional)', 'text', '', false); echo '</div>';
            echo '<footer class="bal-form-footer"><span>El PDF se abre en una pestana nueva</span>'
                . '<button type="submit" class="bal-row-action" name="descargar" value="1"><i data-feather="download"></i>Descargar PDF</button>'
                . '<button class="bal-primary" type="submit"><i data-feather="eye"></i>Previsualizar</button></footer></form></dialog>';
            continue;
        }
        if ($f['tipo'] !== 'manual') { continue; }
        $o = $f['dato'];
        if ($o['anulada']) { continue; }
        if ($puedeCrear && bal_cents($o['pendiente']) > 0) {
            bal_dialog_start('pago-' . $o['id'], ($esCobro ? 'Registrar cobro: ' : 'Registrar pago: ') . $o['concepto']);
            bal_form('pagar', ['id' => $o['id']]);
            bal_input('monto', $esCobro ? 'Cobro (RD$)' : 'Pago (RD$)', 'number', $o['pendiente']);
            bal_payment_fields($accounts, $methods);
            bal_form_end($esCobro ? 'Confirmar cobro' : 'Confirmar pago');
            echo '</dialog>';
        }
        if ($puedeAnular && bal_cents($o['pendiente']) === bal_cents($o['monto'])) {
            bal_dialog_start('anular-' . $o['id'], 'Anular ' . $titulo);
            echo '<p class="bal-dialog-note">No se elimina: queda en el historial como Anulada y deja de contar como pendiente.</p>';
            bal_obligaciones_form('anular_obligacion', ['id' => $o['id']], ['data-confirmar' => 'Seguro que deseas anular esta ' . $titulo . ' (' . $o['concepto'] . ')? No se elimina: queda como Anulada y deja de contar.']);
            bal_input('motivo', 'Motivo');
            bal_form_end('Anular');
            echo '</dialog>';
        }
    }
}
