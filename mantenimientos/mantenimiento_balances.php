<?php
require_once __DIR__ . '/../configuracion.php';
$PERMISO_REQUERIDO = 'finanzas.balances';
require_once __DIR__ . '/../componentes/proteccion_autenticacion.php';
require_once __DIR__ . '/../bd/conexion.php';
require_once __DIR__ . '/../componentes/csrf.php';
require_once __DIR__ . '/../componentes/balances_servicio.php';
require_once __DIR__ . '/../componentes/balances_vista.php';
require_once __DIR__ . '/../componentes/balances_registrar.php';
require_once __DIR__ . '/../componentes/balances_obligaciones.php';

$tabs = ['resumen'=>'Balances','gasto'=>'Registrar gasto','ingreso'=>'Registrar ingreso','movimientos'=>'Movimientos','senderos'=>'Senderos','cobrar'=>'Por cobrar','pagar'=>'Por pagar','programaciones'=>'Pagos programados','cuadres'=>'Cuadres','catalogos'=>'Mantenimientos','auditoria'=>'Historial'];
$requestedTab = (string)($_GET['tab'] ?? '');
$tab = $requestedTab === 'obligaciones' ? 'pagar' : (isset($tabs[$requestedTab]) ? $requestedTab : 'resumen');
$url = BASE_URL.'admin/balances';
$pageTitle = 'Balances y cuentas';
$cssFiles = ['css/global.css','css/barra_navegacion.css','css/balances.css','css/balances_usuario_sendero.css'];
$jsFiles = ['js/barra_navegacion.js','js/balances.js','js/balances_usuario_sendero.js'];
$installed = bal_one($conn,"SHOW TABLES LIKE 'fin_auditoria'") && bal_one($conn,"SHOW COLUMNS FROM fin_cuentas LIKE 'responsable_tercero_id'");
if (!$installed) {
    include __DIR__.'/../componentes/encabezado.php';
    include __DIR__.'/../componentes/barra_navegacion.php';
    echo '<main class="bal-page"><h1>Balances y cuentas</h1><p>Este módulo requiere las actualizaciones de base de datos 20261021_011 y 20261021_012. Súbelas y aplícalas desde Actualizaciones antes de publicar esta pantalla.</p></main>';
    include __DIR__.'/../componentes/pie_pagina.php'; return;
}
$add = sg_has_permission_action($conn,'finanzas.balances','agregar');
$edit = sg_has_permission_action($conn,'finanzas.balances','editar');
$reverse = sg_has_permission_action($conn,'finanzas.balances','eliminar');
$canClose = sg_has_permission($conn,'finanzas.cierres');
$accounts = bal_rows($conn,'SELECT c.*,t.nombre tipo_nombre,b.nombre banco_nombre,r.nombre responsable_nombre FROM fin_cuentas c JOIN fin_cuenta_tipos t ON t.id=c.tipo_id LEFT JOIN fin_bancos b ON b.id=c.banco_id LEFT JOIN fin_terceros r ON r.id=c.responsable_tercero_id ORDER BY c.activo DESC,c.nombre');
$accountTypes = bal_rows($conn,'SELECT * FROM fin_cuenta_tipos ORDER BY activo DESC,nombre');
$banks = bal_rows($conn,'SELECT * FROM fin_bancos ORDER BY activo DESC,nombre');
$responsibles = bal_rows($conn,"SELECT * FROM fin_terceros WHERE tipo IN ('empleado','directivo') ORDER BY activo DESC,nombre");
$types = bal_rows($conn,"SELECT *,CONCAT(IF(naturaleza='ingreso','Ingreso: ','Gasto: '),nombre) nombre FROM fin_tipos ORDER BY naturaleza,nombre");
$thirds = bal_rows($conn,'SELECT * FROM fin_terceros ORDER BY activo DESC,nombre');
$frequencies = bal_rows($conn,'SELECT * FROM fin_frecuencias ORDER BY id');
$methods = bal_rows($conn,'SELECT id,nombre,activo FROM contabilidad_metodo_pago ORDER BY nombre');
$filterError = '';
try {
    // Sin fechas se ve todo: "Desde" vacio = desde el inicio; "Hasta" vacio = hoy (corte de los saldos).
    $fromInput = trim((string)($_GET['desde'] ?? ''));
    $toInput = trim((string)($_GET['hasta'] ?? ''));
    $from = $fromInput === '' ? '1900-01-01' : bal_date($fromInput);
    $to = $toInput === '' ? date('Y-m-d') : bal_date($toInput);
    if ($from > $to) { throw new InvalidArgumentException('La fecha inicial supera la final.'); }
} catch (InvalidArgumentException $e) { $filterError=$e->getMessage(); $fromInput=''; $toInput=''; $from='1900-01-01'; $to=date('Y-m-d'); }
$accountFilter = max(0,(int)($_GET['cuenta'] ?? 0));
$senderoFilter = max(0,(int)($_GET['sendero'] ?? 0));
$search = mb_substr(trim((string)($_GET['q'] ?? '')),0,100);
$where = 'm.fecha BETWEEN ? AND ?'; $params = [$from,$to];
if ($accountFilter) { $where.=' AND m.cuenta_id=?'; $params[]=$accountFilter; }
if ($senderoFilter) { $where.=' AND m.sendero_id=?'; $params[]=$senderoFilter; }
if ($search !== '') { $where.=' AND (m.concepto LIKE ? OR m.referencia LIKE ? OR te.nombre LIKE ?)'; for($i=0;$i<3;$i++){$params[]='%'.$search.'%';} }
// Clase de movimiento, agrupada como se lee en tesoreria. Proveedores y personal salen del tipo de la entidad.
$movementGroups = [
    'Ingresos' => ['ingresos'=>'Todos los ingresos','cobros'=>'Cobros de senderos','otros_ingresos'=>'Otros ingresos'],
    'Gastos' => ['salidas'=>'Todos los gastos','gastos'=>'Gastos de senderos','proveedores'=>'Pagos a proveedores','personal'=>'Pagos al personal','otros_gastos'=>'Gastos fuera de senderos'],
    'Otros' => ['transferencias'=>'Transferencias entre cuentas'],
];
// Los tipos del catalogo se agregan como detalle, salvo los que ya estan arriba con el mismo nombre.
$fixedNames = array_map(static fn($n) => mb_strtolower($n), ['Cobros de senderos','Otros ingresos','Gastos de senderos','Pagos al personal','Otros gastos']);
$categorySql = [];
foreach ($types as $t) {
    $plainName = preg_replace('/^(Ingreso|Gasto|Salida): /','',$t['nombre']);
    if (in_array(mb_strtolower($plainName), $fixedNames, true)) { continue; }
    $movementGroups[$t['naturaleza']==='ingreso' ? 'Ingresos' : 'Gastos']['cat'.(int)$t['id']] = $plainName;
    $categorySql['cat'.(int)$t['id']] = 'm.tipo_id='.(int)$t['id'];
}
$movementKinds = array_merge(...array_values($movementGroups));
$kindFilter = isset($movementKinds[$_GET['tipo_mov'] ?? '']) ? (string)$_GET['tipo_mov'] : '';
$terceroClase = bal_tercero_tipos_listo($conn)
    ? '(SELECT COALESCE(tt.clase,x.tipo) FROM fin_terceros x LEFT JOIN fin_tercero_tipos tt ON tt.id=x.tipo_id WHERE x.id=m.tercero_id)'
    : '(SELECT x.tipo FROM fin_terceros x WHERE x.id=m.tercero_id)';
$kindSql = [
    'cobros'=>"m.origen='cobro_sendero'",
    'otros_ingresos'=>"m.naturaleza='ingreso' AND m.origen<>'cobro_sendero'",
    'ingresos'=>"m.naturaleza='ingreso'",
    'gastos'=>"m.origen='gasto_sendero'",
    'proveedores'=>"m.naturaleza='salida' AND $terceroClase='proveedor'",
    'personal'=>"m.naturaleza='salida' AND $terceroClase IN ('empleado','directivo')",
    'otros_gastos'=>"m.naturaleza='salida' AND m.origen<>'gasto_sendero'",
    'salidas'=>"m.naturaleza='salida'",
    'transferencias'=>"m.naturaleza='transferencia'",
] + $categorySql;
if ($kindFilter !== '' && $tab !== 'senderos') { $where.=' AND '.$kindSql[$kindFilter]; }
// Solo en Movimientos: entidad y si se muestran los reversos.
$thirdFilter = max(0,(int)($_GET['entidad'] ?? 0));
$stateFilter = in_array($_GET['estado'] ?? '', ['todos','reversos'], true) ? $_GET['estado'] : 'vigentes';
if ($tab === 'movimientos') {
    if ($thirdFilter) { $where.=' AND m.tercero_id=?'; $params[]=$thirdFilter; }
    // Vigentes: ni los reversos ni lo que fue reversado (se anulan entre si y solo confunden la consulta).
    $reversedSql = 'EXISTS (SELECT 1 FROM fin_movimientos rv WHERE rv.reversa_id=m.id)';
    if ($stateFilter === 'vigentes') { $where.=' AND m.reversa_id IS NULL AND NOT '.$reversedSql; }
    if ($stateFilter === 'reversos') { $where.=' AND (m.reversa_id IS NOT NULL OR '.$reversedSql.')'; }
}
$originLabels = ['manual'=>'Registro manual','cobro_sendero'=>'Cobro de sendero','gasto_sendero'=>'Gasto de sendero','obligacion'=>'Pago o cobro de obligacion','transferencia'=>'Transferencia'];
$joins = ' FROM fin_movimientos m JOIN fin_cuentas c ON c.id=m.cuenta_id LEFT JOIN fin_tipos t ON t.id=m.tipo_id LEFT JOIN fin_terceros te ON te.id=m.tercero_id LEFT JOIN senderos s ON s.id=m.sendero_id';
$columns = 'm.*,c.nombre cuenta,t.nombre tipo,te.nombre tercero,s.nombre sendero,(SELECT r.id FROM fin_movimientos r WHERE r.reversa_id=m.id LIMIT 1) revertido';
if (isset($_GET['exportar'])) {
    if ($filterError) { http_response_code(422); exit($filterError); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="movimientos-balances.csv"');
    $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['ID','Fecha','Cuenta','Naturaleza','Tipo','Tercero','Sendero','Concepto','Referencia','Importe DOP','Origen','Reverso de','Creado por','Reembolsado a'], ';');
    $stmt=bal_query($conn,'SELECT '.$columns.$joins.' WHERE '.$where.' ORDER BY m.fecha,m.id',$params);
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc()) {
        $values=[$row['id'],$row['fecha'],$row['cuenta'],bal_naturaleza_label($row['naturaleza']),$row['tipo'],$row['tercero'],$row['sendero'],$row['concepto'],$row['referencia'],$row['importe'],$row['origen'],$row['reversa_id'],$row['creado_por'],$row['reembolsado_a'] ?? ''];
        foreach($values as &$v) { if (is_string($v) && preg_match('/^[=+@\-\t\r]/',$v)){$v="'".$v;} } unset($v);
        fputcsv($out,$values,';');
    }
    fclose($out);exit;
}
$page=max(1,(int)($_GET['pagina'] ?? 1)); $offset=($page-1)*50;
$summary=bal_one($conn,"SELECT COALESCE(SUM(CASE WHEN m.naturaleza='ingreso' THEN m.importe ELSE 0 END),0) ingresos,COALESCE(SUM(CASE WHEN m.naturaleza='salida' THEN -m.importe ELSE 0 END),0) salidas,COUNT(*) total".$joins.' WHERE '.$where,$params);
$movements=bal_rows($conn,'SELECT '.$columns.$joins.' WHERE '.$where." ORDER BY m.fecha DESC,m.id DESC LIMIT 50 OFFSET $offset",$params);
$sourceSplit = $tab==='senderos' ? bal_sources_cuadradas($conn) : ['rows'=>[],'pendientes'=>[]];
$sources = $sourceSplit['rows'];
if ($senderoFilter) { $sources=array_values(array_filter($sources,static fn($r)=>(int)$r['sendero_id']===$senderoFilter)); }
if ($search !== '') { $sources=array_values(array_filter($sources,static fn($r)=>mb_stripos($r['sendero'].' '.$r['concepto'],$search)!==false)); }
$originFilter = in_array($_GET['origen'] ?? '', ['cobro_sendero','gasto_sendero'], true) ? $_GET['origen'] : '';
$methodFilter = (string)($_GET['metodo'] ?? '');
if ($originFilter !== '') { $sources=array_values(array_filter($sources,static fn($r)=>$r['origen']===$originFilter)); }
if ($methodFilter === 'sin') { $sources=array_values(array_filter($sources,static fn($r)=>$r['origen']==='cobro_sendero' && empty($r['metodo_pago_id']))); }
elseif (ctype_digit($methodFilter)) { $sources=array_values(array_filter($sources,static fn($r)=>(string)$r['metodo_pago_id']===$methodFilter)); }
$methodNames = array_column($methods, 'nombre', 'id');
$obligationSql="SELECT o.*,te.nombre tercero,t.nombre tipo, o.monto-COALESCE((SELECT SUM(IF(o.naturaleza='salida',-m.importe,m.importe)) FROM fin_movimientos m WHERE m.obligacion_id=o.id),0) pendiente FROM fin_obligaciones o JOIN fin_terceros te ON te.id=o.tercero_id JOIN fin_tipos t ON t.id=o.tipo_id";
$obligationNature = $tab === 'cobrar' ? 'ingreso' : 'salida';
$programs = $tab==='programaciones' ? bal_rows($conn,'SELECT p.*,te.nombre tercero,f.nombre frecuencia,t.naturaleza FROM fin_programaciones p JOIN fin_terceros te ON te.id=p.tercero_id JOIN fin_frecuencias f ON f.id=p.frecuencia_id JOIN fin_tipos t ON t.id=p.tipo_id ORDER BY p.activo DESC,p.id DESC LIMIT 50 OFFSET '.$offset) : [];
$pending=bal_one($conn,"SELECT COALESCE(SUM(IF(naturaleza='salida',pendiente,0)),0) pagar,COALESCE(SUM(IF(naturaleza='ingreso',pendiente,0)),0) cobrar FROM (".$obligationSql.' WHERE o.anulada=0) x');
$trailOptions=bal_rows($conn,'SELECT id,nombre FROM senderos ORDER BY id DESC');
include __DIR__.'/../componentes/encabezado.php';
include __DIR__.'/../componentes/barra_navegacion.php';
?>
<main class="bal-page">
<header class="bal-heading"><div><span class="bal-eyebrow">Tesoreria · DOP</span><h1>Balances y cuentas</h1><p>Tesorería: saldos de caja y bancos, movimientos, y lo que queda por pagar o por cobrar.</p></div><a href="<?= bal_h(BASE_URL.'admin') ?>"><i data-feather="arrow-left"></i>Panel</a></header>
<nav class="bal-tabs" aria-label="Finanzas"><?php foreach($tabs as $key=>$label): ?><a class="<?= $tab===$key?'active':'' ?>" href="<?= bal_h($url.'?tab='.$key) ?>" <?= $tab===$key?'aria-current="page"':'' ?>><?= bal_h($label) ?></a><?php endforeach; ?></nav>
<div class="bal-notice" id="bal-feedback" role="status" hidden></div>
<?php if($filterError): ?><p class="bal-notice error"><?= bal_h($filterError) ?></p><?php endif; ?>
<?php if(in_array($tab,['resumen','movimientos','senderos'],true)): ?>
<form class="bal-filters" method="get"><input type="hidden" name="tab" value="<?= bal_h($tab) ?>">
<?php if($tab!=='senderos'): ?>
<label>Desde<input type="date" name="desde" value="<?= bal_h($fromInput) ?>" title="Vacio: desde el inicio"></label><label>Hasta<input type="date" name="hasta" value="<?= bal_h($toInput) ?>" title="Vacio: hasta hoy"></label>
<label>Cuenta<select name="cuenta"><option value="">Todas</option><?php foreach($accounts as $a): ?><option value="<?= $a['id'] ?>" <?= $accountFilter===$a['id']?'selected':((string)$accountFilter===(string)$a['id']?'selected':'') ?>><?= bal_h($a['nombre']) ?></option><?php endforeach; ?></select></label>
<?php if($tab==='movimientos'): ?><label>Tipo<select name="tipo_mov"><option value="">Todos</option><?php foreach($movementGroups as $groupLabel=>$groupKinds): ?><optgroup label="<?= bal_h($groupLabel) ?>"><?php foreach($groupKinds as $kindValue=>$kindLabel): ?><option value="<?= $kindValue ?>" <?= $kindFilter===$kindValue?'selected':'' ?>><?= bal_h($kindLabel) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label>Persona o entidad<select name="entidad"><option value="">Todas</option><?php foreach($thirds as $th): ?><option value="<?= (int)$th['id'] ?>" <?= (int)$th['id']===$thirdFilter?'selected':'' ?>><?= bal_h($th['nombre']) ?></option><?php endforeach; ?></select></label><label>Estado<select name="estado"><option value="vigentes">Vigentes</option><option value="todos" <?= $stateFilter==='todos'?'selected':'' ?>>Todos, con reversos</option><option value="reversos" <?= $stateFilter==='reversos'?'selected':'' ?>>Solo reversados y reversos</option></select></label><?php endif; ?>
<?php endif; ?>
<?php if($tab!=='resumen'): ?><label>Sendero<select name="sendero"><option value="">Todos</option><?php foreach($trailOptions as $s): ?><option value="<?= $s['id'] ?>" <?= (int)$s['id']===$senderoFilter?'selected':'' ?>><?= bal_h($s['nombre']) ?></option><?php endforeach; ?></select></label><label>Buscar<input type="search" name="q" value="<?= bal_h($search) ?>" placeholder="Concepto, referencia o persona"></label><?php endif; ?><?php if($tab==='senderos'): ?><label>Origen<select name="origen"><option value="">Cobros y gastos</option><option value="cobro_sendero" <?= $originFilter==='cobro_sendero'?'selected':'' ?>>Solo cobros</option><option value="gasto_sendero" <?= $originFilter==='gasto_sendero'?'selected':'' ?>>Solo gastos</option></select></label><label>Metodo de pago<select name="metodo"><option value="">Todos</option><option value="sin" <?= $methodFilter==='sin'?'selected':'' ?>>Cobros sin metodo</option><?php foreach($methods as $mp): ?><option value="<?= (int)$mp['id'] ?>" <?= $methodFilter===(string)$mp['id']?'selected':'' ?>><?= bal_h($mp['nombre']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<button type="submit"><i data-feather="filter"></i>Consultar</button><a href="<?= bal_h($url.'?tab='.$tab) ?>">Limpiar</a></form>
<?php endif; ?>

<?php if($tab==='resumen'): ?>
<section class="bal-metrics"><div><span>Ingresos del periodo</span><strong><?= bal_money($summary['ingresos']) ?></strong></div><div><span>Gastos del periodo</span><strong><?= bal_money($summary['salidas']) ?></strong></div><div><span>Flujo neto del periodo</span><strong><?= bal_money(bal_decimal(bal_cents($summary['ingresos'])-bal_cents($summary['salidas']))) ?></strong></div><div><span>Obligaciones por pagar · todas</span><strong><?= bal_money($pending['pagar']) ?></strong></div><div><span>Obligaciones por cobrar · todas</span><strong><?= bal_money($pending['cobrar']) ?></strong></div></section>
<section class="bal-section"><header><h2>Saldos al <?= bal_h(date('d/m/Y',strtotime($to))) ?></h2><a href="<?= bal_h($url.'?tab=catalogos&catalogo=cuentas') ?>">Administrar cuentas</a></header>
<div class="bal-table-wrap"><table><thead><tr><th>Cuenta</th><th>Tipo</th><th>Apertura</th><th>Saldo al inicio del periodo</th><th>Saldo al corte</th><th>Estado</th></tr></thead><tbody>
<?php $total=0;foreach($accounts as $a): if($accountFilter && (int)$a['id']!==$accountFilter){continue;} $balance=bal_balance($conn,(int)$a['id'],$to);$total+=$balance; ?>
<tr><td><?= bal_h($a['nombre']) ?><small><?= bal_h($a['referencia']) ?></small></td><td><?= bal_h($a['tipo_nombre']) ?><small><?= bal_h($a['tipo']==='banco' ? $a['banco_nombre'] : ($a['responsable_nombre'] ?: $a['responsable'])) ?></small></td><td><?= bal_h($a['fecha_apertura']) ?></td><td><?= bal_money(bal_decimal(bal_balance($conn,(int)$a['id'],date('Y-m-d',strtotime($from.' -1 day'))))) ?></td><td class="<?= $balance<0?'bal-negative':'bal-positive' ?>"><?= bal_money(bal_decimal($balance)) ?></td><td><?= $a['activo']?'Activa':'Inactiva' ?></td></tr>
<?php endforeach; ?>
<?php if(!$accounts): ?><tr><td colspan="6" class="bal-empty">Todavia no hay cuentas. Registra la primera en Mantenimientos.</td></tr><?php endif; ?>
</tbody><tfoot><tr><th colspan="4">Total disponible al corte</th><th><?= bal_money(bal_decimal($total)) ?></th><th>DOP</th></tr></tfoot></table></div></section>
<section class="bal-section"><header><h2>Ingresos y gastos por tipo</h2></header><div class="bal-table-wrap"><table><thead><tr><th>Tipo</th><th>Naturaleza</th><th>Total neto</th></tr></thead><tbody>
<?php foreach(bal_rows($conn,"SELECT t.nombre,m.naturaleza,SUM(IF(m.naturaleza='salida',-m.importe,m.importe)) total".$joins.' WHERE '.$where." AND m.naturaleza<>'transferencia' GROUP BY t.id,m.naturaleza ORDER BY m.naturaleza,t.nombre",$params) as $r): ?><tr><td><?= bal_h($r['nombre']) ?></td><td><?= bal_h(bal_naturaleza_label($r['naturaleza'])) ?></td><td><?= bal_money($r['total']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php elseif($tab==='gasto' || $tab==='ingreso'): ?>
<?php bal_registrar_vista($conn, $tab==='gasto' ? 'salida' : 'ingreso', $types, $thirds, $accounts, $methods, $obligationSql, $url, $add, $edit, $reverse); ?>
<?php elseif($tab==='movimientos'): ?>
<section class="bal-metrics bal-metrics-compact"><div><span>Ingresos</span><strong class="bal-positive"><?= bal_money($summary['ingresos']) ?></strong></div><div><span>Gastos</span><strong class="bal-negative"><?= bal_money($summary['salidas']) ?></strong></div><div><span>Neto</span><strong><?= bal_money(bal_decimal(bal_cents($summary['ingresos'])-bal_cents($summary['salidas']))) ?></strong></div><div><span>Movimientos</span><strong><?= (int)$summary['total'] ?></strong></div></section>
<section class="bal-section"><header><h2><?= bal_h($kindFilter!=='' ? $movementKinds[$kindFilter] : 'Movimientos confirmados') ?> <small><?= (int)$summary['total'] ?><?= $stateFilter==='vigentes' ? ' vigentes' : '' ?></small></h2><div class="bal-actions"><?php if($reverse && $senderoFilter): bal_open('reversar-sendero','Reversar asignaciones del sendero','rotate-ccw'); endif; ?><?php if($add): echo '<a class="bal-button" href="'.bal_h($url.'?tab=gasto').'"><i data-feather="minus-circle"></i>Registrar gasto</a><a class="bal-button" href="'.bal_h($url.'?tab=ingreso').'"><i data-feather="plus-circle"></i>Registrar ingreso</a>';bal_open('transferir','Transferir','repeat');endif; ?><a class="bal-button" href="<?= bal_h($url.'?'.http_build_query(array_merge($_GET,['exportar'=>1]))) ?>"><i data-feather="download"></i>Exportar</a></div></header>
<?php $reversibles = array_filter($movements, static fn($m) => in_array($m['origen'],['cobro_sendero','gasto_sendero'],true) && !$m['reversa_id'] && !$m['revertido']); $reversibleIds = array_flip(array_map('intval', array_column($reversibles, 'id'))); ?><?php if($reverse && $reversibles): ?><form class="bal-bulk-form" method="post" action="<?= bal_h(BASE_URL.'procesos/proceso_balances.php') ?>" data-confirm="Se desharan {n} asignaciones de senderos. Los registros vuelven a quedar pendientes de asignar. Deseas continuar?"><?= csrf_field() ?><input type="hidden" name="action" value="reversar_masivo"><input type="hidden" name="token" value="<?= bal_h(bin2hex(random_bytes(16))) ?>"><div class="bal-bulk-selection"><strong><span data-bulk-count>0</span> asignaciones seleccionadas</strong><button type="button" class="bal-link-button" data-bulk-all>Marcar los visibles</button><button type="button" class="bal-link-button" data-bulk-clear>Limpiar</button></div><div class="bal-bulk-fields"><?php bal_input('fecha','Fecha del reverso','date',date('Y-m-d')); bal_input('motivo','Motivo','text','Asignacion deshecha'); ?><button class="bal-primary" type="submit" disabled data-bulk-submit><i data-feather="rotate-ccw"></i>Deshacer seleccionadas</button></div></form><?php endif; ?><div class="bal-table-wrap"><table><thead><tr><?php if($reverse && $reversibles): ?><th class="bal-check-cell"><span class="sr-only">Seleccionar</span></th><?php endif; ?><th>Fecha / ID</th><th>Cuenta</th><th>Concepto</th><th>Tipo / origen</th><th>Importe</th><th>Registro</th><th></th></tr></thead><tbody>
<?php foreach($movements as $m): ?><tr><?php if($reverse && $reversibles): ?><td class="bal-check-cell"><?php if(isset($reversibleIds[(int)$m['id']])): ?><input type="checkbox" data-bulk-item value="<?= (int)$m['id'] ?>" data-origen="<?= bal_h($m['origen']) ?>" aria-label="Seleccionar movimiento #<?= (int)$m['id'] ?>"><?php endif; ?></td><?php endif; ?><td><?= bal_h($m['fecha']) ?><small>#<?= $m['id'] ?></small></td><td><?= bal_h($m['cuenta']) ?></td><td><?= bal_h($m['concepto']) ?><small><?= bal_h($m['tercero'].' '.$m['referencia']) ?></small><small><?= bal_h($m['sendero']) ?></small><?php if(!empty($m['reembolsado_a'])): ?><small>Reembolsado a: <?= bal_h($m['reembolsado_a']) ?></small><?php endif; ?></td><td><?= bal_h($m['tipo'] ?? bal_naturaleza_label($m['naturaleza'])) ?><small><?= bal_h($originLabels[$m['origen']] ?? $m['origen']) ?></small></td><td class="<?= bal_cents($m['importe'])<0?'bal-negative':'bal-positive' ?>"><?= bal_money($m['importe']) ?></td><td><?= $m['reversa_id']?'Reverso #'.$m['reversa_id']:($m['revertido']?'Revertido':'Confirmado') ?><small>Usuario #<?= $m['creado_por'] ?> · <?= bal_h($m['created_at']) ?></small></td><td><?php if($reverse && !$m['reversa_id'] && !$m['revertido']): bal_open('reverso-'.$m['id'],'Reversar','rotate-ccw');endif; ?></td></tr><?php endforeach; ?>
<?php if(!$movements): ?><tr><td colspan="7" class="bal-empty">No hay movimientos para los filtros seleccionados.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if($add): bal_dialog_start('transferir','Transferencia entre cuentas');bal_form('transferir');bal_select('cuenta_id','Cuenta de origen',$accounts);bal_select('destino_id','Cuenta de destino',$accounts);bal_input('monto','Monto (RD$)','number');bal_input('fecha','Fecha','date',date('Y-m-d'));bal_input('concepto','Concepto');bal_input('referencia','Referencia','text','',false);bal_form_end('Confirmar transferencia');echo '</dialog>';endif;
if($reverse && $senderoFilter){$vig=bal_one($conn,"SELECT COUNT(*) n,COALESCE(SUM(importe),0) neto FROM fin_movimientos m WHERE m.sendero_id=? AND m.origen IN ('cobro_sendero','gasto_sendero') AND m.reversa_id IS NULL AND NOT EXISTS (SELECT 1 FROM fin_movimientos r WHERE r.reversa_id=m.id)",[$senderoFilter]);bal_dialog_start('reversar-sendero','Reversar asignaciones del sendero');echo '<p class="bal-dialog-note">Se reversaran '.(int)$vig['n'].' asignaciones de cobros y gastos de este sendero (efecto neto en cuentas: '.bal_money($vig['neto']).'). Los movimientos originales y sus reversos quedan en el historial, y los registros vuelven a quedar pendientes de asignar.</p>';bal_form('reversar_sendero',['sendero_id'=>$senderoFilter]);bal_input('fecha','Fecha del reverso','date',date('Y-m-d'));bal_input('motivo','Motivo');bal_form_end('Reversar asignaciones');echo '</dialog>';}
foreach($movements as $m):if(!$reverse || $m['reversa_id'] || $m['revertido']){continue;}bal_dialog_start('reverso-'.$m['id'],'Reversar movimiento #'.$m['id']);bal_form('reversar',['id'=>$m['id']]);bal_input('fecha','Fecha del reverso','date',date('Y-m-d'));bal_input('motivo','Motivo');bal_form_end('Confirmar reverso');echo '</dialog>';endforeach;
?>
<?php elseif($tab==='senderos'): ?>
<section class="bal-section"><header><div><h2>Por asignar a cuentas <small><?= count($sources) ?></small></h2><p class="bal-section-help">Selecciona varios registros para contabilizarlos juntos en una misma cuenta.</p></div><div class="bal-actions"><a href="<?= bal_h(BASE_URL.'admin/ingresos-sendero') ?>">Cobros por sendero</a><a href="<?= bal_h(BASE_URL.'admin/gastos-sendero') ?>">Gastos por sendero</a></div></header>
<?php if($sourceSplit['pendientes']): ?><div class="bal-cuadre-pendiente"><strong><i data-feather="lock"></i>Sin cuadrar: no se muestran hasta marcarlos como cuadrados</strong><ul><?php foreach($sourceSplit['pendientes'] as $pend): ?><li><a href="<?= bal_h(BASE_URL.($pend['area']==='cobros'?'admin/ingresos-sendero':'admin/gastos-sendero').'?sendero_id='.$pend['sendero_id']) ?>"><?= bal_h($pend['sendero']) ?></a> · <?= $pend['area']==='cobros'?'cobros':'gastos' ?> (<?= (int)$pend['registros'] ?>)</li><?php endforeach; ?></ul></div><?php endif; ?><?php $sourcePage=array_slice($sources,$offset,50); if($add): ?><form class="bal-bulk-form" method="post" action="<?= bal_h(BASE_URL.'procesos/proceso_balances.php') ?>">
<?= csrf_field() ?><input type="hidden" name="action" value="importar_masivo"><input type="hidden" name="token" value="<?= bal_h(bin2hex(random_bytes(16))) ?>">
<div class="bal-bulk-selection"><strong><span data-bulk-count>0</span> seleccionados</strong><button type="button" class="bal-link-button" data-bulk-all>Marcar los visibles</button><button type="button" class="bal-link-button" data-bulk-clear>Limpiar</button></div>
<div class="bal-bulk-fields"><?php bal_select('cuenta_id','Cuenta destino',$accounts); bal_input('fecha','Fecha si el registro no tiene una','date',date('Y-m-d')); ?><label class="bal-check"><input type="checkbox" name="usar_fecha_registro" value="1" checked>Usar la fecha real de cada registro (pago del cobro o fecha del sendero en los gastos)</label><button class="bal-primary" type="submit" disabled data-bulk-submit><i data-feather="check-square"></i>Asignar seleccionados</button></div>
</form><?php endif; ?>
<div class="bal-table-wrap"><table><thead><tr><?php if($add): ?><th class="bal-check-cell"><span class="sr-only">Seleccionar</span></th><?php endif; ?><th>Sendero</th><th>Concepto</th><th>Origen</th><th>Fecha</th><th>Metodo</th><th>Registrado en sendero</th><th>Asignado a cuentas</th><th>Diferencia</th><th></th></tr></thead><tbody>
<?php foreach($sourcePage as $r): $delta=bal_cents($r['monto'])-bal_cents($r['contabilizado']); ?><tr><?php if($add): ?><td class="bal-check-cell"><input type="checkbox" data-bulk-item value="<?= (int)$r['origen_id'] ?>" data-origen="<?= bal_h($r['origen']) ?>" aria-label="Seleccionar <?= bal_h($r['concepto']) ?>"></td><?php endif; ?><td><?= bal_h($r['sendero']) ?></td><td><?= bal_h($r['concepto']) ?></td><td><?= $r['origen']==='cobro_sendero'?'Cobro':'Gasto' ?></td><td><?= !empty($r['fecha']) ? bal_h(date('d/m/Y',strtotime($r['fecha']))) : 'Sin fecha' ?></td><td><?= $r['origen']==='cobro_sendero' ? bal_h($methodNames[$r['metodo_pago_id']] ?? 'Sin metodo') : '' ?></td><td><?= bal_money($r['monto']) ?></td><td><?= bal_money($r['contabilizado']) ?></td><td><?= bal_money(bal_decimal($delta)) ?></td><td><?php if($add):bal_open('origen-'.$r['origen'].'-'.$r['origen_id'],'Asignar','arrow-right');endif; ?></td></tr><?php endforeach; ?>
<?php if(!$sources): ?><tr><td colspan="<?= $add ? 10 : 9 ?>" class="bal-empty">No hay diferencias pendientes de asignar.</td></tr><?php endif; ?></tbody></table></div></section>
<?php foreach($sourcePage as $r):if(!$add){continue;}$nature=$r['origen']==='cobro_sendero'?'ingreso':'salida';$displayDelta=bal_cents($r['monto'])-bal_cents($r['contabilizado']);$delta=$displayDelta*($nature==='ingreso'?1:-1);
bal_dialog_start('origen-'.$r['origen'].'-'.$r['origen_id'],'Asignar '.($nature==='ingreso'?'cobro':'gasto').' a cuenta');
echo '<p class="bal-dialog-note">'.bal_h($r['sendero']).' · Efecto en cuenta: '.bal_money(bal_decimal($delta)).'</p>';
bal_form('importar',['id'=>$r['origen_id'],'origen'=>$r['origen'],'diferencia'=>bal_decimal($delta)]);bal_select('cuenta_id','Cuenta',$accounts);bal_input('fecha','Fecha efectiva de cobro / pago','date',bal_fecha_origen($r['fecha'] ?? null,date('Y-m-d'),true));bal_input('concepto','Concepto','text',$r['concepto']);bal_select('tipo_id','Tipo',array_values(array_filter($types,static fn($t)=>$t['naturaleza']===$nature)));bal_form_end('Confirmar asignacion');echo '</dialog>';endforeach; ?>

<?php elseif(in_array($tab,['cobrar','pagar'],true)): ?>
<?php bal_obligaciones_vista($conn, $tab, $types, $thirds, $accounts, $methods, $obligationSql, $url, $add, $edit, $reverse); ?>

<?php elseif($tab==='programaciones'): ?>
<section class="bal-section"><header><h2>Pagos e ingresos recurrentes</h2><?php if($add):bal_open('programacion','Nueva programacion');endif; ?></header>
<div class="bal-table-wrap"><table><thead><tr><th>Persona / concepto</th><th>Monto</th><th>Frecuencia</th><th>Inicio / fin</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach($programs as $p): ?><tr><td><?= bal_h($p['tercero']) ?><small><?= bal_h($p['concepto']) ?></small></td><td><?= bal_money($p['monto']) ?><small><?= $p['naturaleza']==='salida'?'Pago':'Cobro' ?></small></td><td>Cada <?= (int)$p['intervalo'] ?> <?= bal_h($p['unidad']) ?></td><td><?= bal_h($p['inicio']) ?><small><?= bal_h($p['fin'] ?? 'Sin fecha final') ?></small></td><td><?= $p['activo']?'Activa':'Pausada' ?></td><td><div class="bal-actions"><?php if($add && $p['activo']){bal_open('generar-'.$p['id'],'Generar cuotas','calendar');}if($edit){bal_open('editar-programa-'.$p['id'],'Editar','edit-2');bal_open('pausar-'.$p['id'],$p['activo']?'Pausar':'Activar',$p['activo']?'pause':'play');} ?></div></td></tr><?php endforeach; ?>
<?php if(!$programs): ?><tr><td colspan="6" class="bal-empty">No hay programaciones registradas.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if($add):bal_dialog_start('programacion','Nueva programacion');bal_form('programacion');bal_input('concepto','Concepto');bal_tercero_select($thirds,true,'','Empleado, directivo u otra entidad');bal_select('tipo_id','Tipo de pago / ingreso',$types);bal_input('monto','Monto por vencimiento (RD$)','number');bal_select('frecuencia_id','Frecuencia',$frequencies);bal_input('inicio','Primer vencimiento','date',date('Y-m-d'));bal_input('fin','Ultimo vencimiento','date','',false);bal_form_end();echo '</dialog>';endif;
foreach($programs as $p):if($add && $p['activo']){bal_dialog_start('generar-'.$p['id'],'Generar vencimientos');bal_form('generar',['id'=>$p['id']]);bal_input('hasta','Generar hasta','date',date('Y-m-t'));bal_form_end('Generar');echo '</dialog>';}if($edit){bal_dialog_start('pausar-'.$p['id'],'Cambiar estado de programacion');bal_form('pausar',['id'=>$p['id']]);bal_input('motivo','Motivo');bal_form_end($p['activo']?'Pausar':'Activar');echo '</dialog>';}endforeach; ?>
<?php foreach($programs as $p):if(!$edit){continue;}
bal_dialog_start('editar-programa-'.$p['id'],'Editar programacion');bal_form('programacion',['id'=>$p['id'],'inicio'=>$p['inicio'],'frecuencia_id'=>$p['frecuencia_id']]);bal_input('concepto','Concepto','text',$p['concepto']);bal_tercero_select($thirds,true,$p['tercero_id']);bal_select('tipo_id','Tipo',$types,true,$p['tipo_id']);bal_input('monto','Monto de proximas cuotas (RD$)','number',$p['monto']);bal_input('fin','Ultimo vencimiento','date',$p['fin']??'',false);bal_form_end();echo '</dialog>';endforeach; ?>

<?php elseif($tab==='cuadres'): ?>
<section class="bal-section"><header><h2>Cuadres y cierres de cuentas</h2><?php if($add):bal_open('cuadre','Nuevo cuadre','check-square');endif; ?></header><div class="bal-table-wrap"><table><thead><tr><th>Cuenta / corte</th><th>Libro al registrar</th><th>Real</th><th>Diferencia</th><th>Estado</th><th>Nota / usuario</th></tr></thead><tbody>
<?php foreach(bal_rows($conn,'SELECT q.*,c.nombre cuenta FROM fin_cuadres q JOIN fin_cuentas c ON c.id=q.cuenta_id ORDER BY q.id DESC LIMIT 50 OFFSET '.$offset) as $q): ?><tr><td><?= bal_h($q['cuenta']) ?><small><?= bal_h($q['fecha']) ?></small></td><td><?= bal_money($q['saldo_libro']) ?></td><td><?= bal_money($q['saldo_real']) ?></td><td><?= bal_money($q['diferencia']) ?></td><td><?= $q['cerrado']?'Cerrado':'Revision' ?></td><td><?= bal_h($q['nota']) ?><small>Usuario #<?= $q['creado_por'] ?> · <?= bal_h($q['created_at']) ?></small></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php if($add):bal_dialog_start('cuadre','Cuadrar cuenta');bal_form('cuadre');bal_select('cuenta_id','Cuenta',$accounts);bal_input('fecha','Fecha de corte','date',date('Y-m-d'));bal_input('saldo_real','Saldo en banco / efectivo contado (RD$)','number');bal_input('nota','Referencia del estado bancario / nota');echo '<label class="bal-check"><input type="checkbox" name="ajustar_apertura" value="1">Registrar la diferencia como ajuste de saldo inicial</label>';echo '<p class="bal-dialog-note">Usalo al arrancar, cuando la diferencia es dinero que la empresa ya tenia antes de usar el sistema. Se registra en la fecha de apertura de la cuenta con el tipo "Ajuste de saldo inicial" y queda en el historial.</p>';if($canClose){echo '<label class="bal-check"><input type="checkbox" name="cerrado" value="1">Cerrar el periodo conciliado</label>';}bal_form_end('Registrar cuadre');echo '</dialog>';endif; ?>

<?php elseif($tab==='catalogos'): ?>
<?php $terceroTipos=bal_tercero_tipos_listo($conn);$catalogs=['cuentas'=>'Cuentas','bancos'=>'Bancos','tipos_cuenta'=>'Tipos de cuenta','tipos'=>'Tipos de ingresos y gastos','terceros'=>'Personal y entidades']+($terceroTipos?['tipos_tercero'=>'Tipos de entidad']:[])+['frecuencias'=>'Frecuencias'];$terceroClases=['empleado'=>'Empleado (puede custodiar efectivo)','directivo'=>'Directivo (puede custodiar efectivo)','proveedor'=>'Proveedor','cliente'=>'Cliente','otro'=>'Otro'];$catalog=isset($catalogs[$_GET['catalogo']??''])?$_GET['catalogo']:'cuentas';$tables=['cuentas'=>'fin_cuentas','bancos'=>'fin_bancos','tipos_cuenta'=>'fin_cuenta_tipos','tipos'=>'fin_tipos','terceros'=>'fin_terceros','tipos_tercero'=>'fin_tercero_tipos','frecuencias'=>'fin_frecuencias'];$items=$catalog==='cuentas'?$accounts:($catalog==='terceros'&&$terceroTipos?bal_rows($conn,'SELECT t.*,tt.nombre tipo_nombre FROM fin_terceros t LEFT JOIN fin_tercero_tipos tt ON tt.id=t.tipo_id ORDER BY t.activo DESC,t.nombre'):bal_rows($conn,'SELECT * FROM '.$tables[$catalog].' ORDER BY activo DESC,nombre'));$terceroTipoRows=$terceroTipos?bal_rows($conn,'SELECT * FROM fin_tercero_tipos ORDER BY activo DESC,nombre'):[]; ?>
<nav class="bal-subtabs"><?php foreach($catalogs as $k=>$v): ?><a class="<?= $catalog===$k?'active':'' ?>" href="<?= bal_h($url.'?tab=catalogos&catalogo='.$k) ?>"><?= bal_h($v) ?></a><?php endforeach; ?><a href="<?= bal_h(BASE_URL.'admin/metodo-pago') ?>">Metodos de pago <i data-feather="external-link"></i></a></nav>
<section class="bal-section"><header><h2><?= bal_h($catalogs[$catalog]) ?></h2><?php if($add):bal_open('catalogo-0','Agregar');endif; ?></header><div class="bal-table-wrap"><table><thead><tr><th>Nombre</th><th>Datos</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach($items as $r): ?><tr><td><?= bal_h($r['nombre']) ?></td><td>
<?php if($catalog==='cuentas'): ?>
<?= bal_h($r['tipo_nombre']) ?><small><?= bal_h($r['tipo']==='banco' ? ($r['banco_nombre'] ?: 'Banco pendiente') : ($r['responsable_nombre'] ?: $r['responsable'] ?: 'Responsable pendiente')) ?></small><small><?= bal_h($r['tipo']==='banco' ? $r['titular'].' · '.bal_mask_account_number($r['numero_cuenta']) : $r['ubicacion']) ?></small><small><?= bal_h($r['referencia']) ?></small>
<?php elseif($catalog==='terceros'): ?><?= bal_h($r['tipo_nombre']??ucfirst((string)($r['tipo']??''))) ?><small><?= bal_h($r['referencia']??'') ?></small><?php if(!empty($r['contacto'])): ?><small><?= bal_h($r['contacto']) ?></small><?php endif; ?>
<?php elseif($catalog==='tipos_tercero'): ?><?= bal_h($terceroClases[$r['clase']]??$r['clase']) ?>
<?php else: ?><?= bal_h((isset($r['naturaleza'])?bal_naturaleza_label($r['naturaleza']):null)??$r['tipo']??$r['clase']??(isset($r['intervalo'])?'Cada '.$r['intervalo'].' '.$r['unidad']:'')) ?><small><?= bal_h($r['referencia']??'') ?></small><?php endif; ?>
</td><td><?= $r['activo']?'Activo':'Inactivo' ?></td><td><?php if($edit):bal_open('catalogo-'.$r['id'],'Editar','edit-2');endif; ?></td></tr><?php endforeach; ?><?php if(!$items): ?><tr><td colspan="4" class="bal-empty">No hay registros en este mantenimiento.</td></tr><?php endif; ?></tbody></table></div></section>
<?php foreach(array_merge([['id'=>0,'activo'=>1]],$items) as $r):if(($r['id']&&!$edit)||(!$r['id']&&!$add)){continue;}
bal_dialog_start('catalogo-'.$r['id'],$catalog==='cuentas' ? ($r['id']?'Editar cuenta: ':'Nueva cuenta') : ($r['id']?'Editar: ':'Nuevo: ').$catalogs[$catalog]);bal_form('catalogo',['id'=>$r['id'],'catalogo'=>$catalog]);bal_input('nombre','Nombre','text',$r['nombre']??'');
if($catalog==='cuentas'){bal_account_form($r,$accountTypes,$banks,$responsibles);if(!$r['id']){bal_input('fecha_apertura','Fecha del saldo inicial','date',date('Y-m-d'));bal_input('saldo_inicial','Saldo inicial (RD$)','number','0.00');}}
elseif($catalog==='tipos_cuenta'){bal_select('clase','Comportamiento',bal_options(['banco'=>'Banco','efectivo'=>'Efectivo','otra'=>'Otro']),true,$r['clase']??'');}
elseif($catalog==='bancos'){/* Nombre y estado. */}
elseif($catalog==='tipos'){bal_select('naturaleza','Naturaleza',bal_options(['ingreso'=>'Ingreso','salida'=>'Gasto']),true,$r['naturaleza']??'');}
elseif($catalog==='tipos_tercero'){bal_select('clase','Comportamiento',bal_options($terceroClases),true,$r['clase']??'');}
elseif($catalog==='terceros'){if($terceroTipos){bal_select('tipo_id','Tipo',$terceroTipoRows,true,$r['tipo_id']??'');echo '<p class="bal-field-help">Los tipos se mantienen en <a href="'.bal_h($url.'?tab=catalogos&catalogo=tipos_tercero').'">Tipos de entidad</a>.</p>';}else{bal_select('tipo','Tipo',bal_options(['empleado'=>'Empleado','directivo'=>'Directivo','proveedor'=>'Proveedor','cliente'=>'Cliente','otro'=>'Otro']),true,$r['tipo']??'');}if(bal_terceros_contacto_listo($conn)){bal_input('referencia','Identificacion (cedula, RNC, pasaporte)','text',$r['referencia']??'',false);bal_input('contacto','Contacto (telefono o correo)','text',$r['contacto']??'',false);}else{bal_input('referencia','Identificacion / contacto','text',$r['referencia']??'',false);}}
else{bal_select('unidad','Unidad',bal_options(['dias'=>'Dias','meses'=>'Meses']),true,$r['unidad']??'');bal_input('intervalo','Cada','number',$r['intervalo']??1);}
echo '<label class="bal-check"><input type="checkbox" name="activo" value="1" '.($r['activo']?'checked':'').'>Activo</label>';bal_form_end();echo '</dialog>';endforeach; ?>

<?php elseif($tab==='auditoria'): ?>
<section class="bal-section"><header><h2>Historial de operaciones</h2></header><div class="bal-table-wrap"><table><thead><tr><th>Fecha</th><th>Usuario</th><th>Operacion</th><th>Registro</th><th>Detalle</th></tr></thead><tbody>
<?php foreach(bal_rows($conn,'SELECT * FROM fin_auditoria ORDER BY id DESC LIMIT 50 OFFSET '.$offset) as $a): ?><tr><td><?= bal_h($a['created_at']) ?></td><td>#<?= $a['usuario_id'] ?></td><td><?= bal_h($a['accion']) ?></td><td><?= bal_h($a['entidad']) ?> #<?= $a['entidad_id'] ?></td><td><details><summary>Ver cambios</summary><pre><?= bal_h(json_encode(['antes'=>json_decode($a['antes'],true),'despues'=>json_decode($a['despues'],true)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></details></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php
$totalRows = 0;
if($tab==='movimientos'){$totalRows=(int)$summary['total'];}
elseif($tab==='senderos'){$totalRows=count($sources);}
elseif(in_array($tab,['programaciones','cuadres','auditoria'],true)){$table=['programaciones'=>'fin_programaciones','cuadres'=>'fin_cuadres','auditoria'=>'fin_auditoria'][$tab];$totalRows=(int)bal_one($conn,'SELECT COUNT(*) total FROM '.$table)['total'];}
?>
<?php if(!in_array($tab,['resumen','catalogos','cobrar','pagar'],true) && $totalRows>50): ?><nav class="bal-pagination" aria-label="Paginas"><?php if($page>1): ?><a href="<?= bal_h($url.'?'.http_build_query(array_merge($_GET,['pagina'=>$page-1]))) ?>">Anterior</a><?php endif; ?><span>Pagina <?= $page ?> de <?= (int)ceil($totalRows/50) ?></span><?php if($offset+50<$totalRows): ?><a href="<?= bal_h($url.'?'.http_build_query(array_merge($_GET,['pagina'=>$page+1]))) ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?>
</main>
<?php include __DIR__.'/../componentes/pie_pagina.php'; ?>
