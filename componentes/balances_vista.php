<?php
function bal_h($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function bal_money($v): string { return 'RD$ ' . number_format((float)$v,2); }
// Como se lee la naturaleza de un movimiento o tipo: lo que sale es un gasto.
function bal_naturaleza_label($naturaleza): string
{
    return ['ingreso' => 'Ingreso', 'salida' => 'Gasto', 'transferencia' => 'Transferencia'][(string) $naturaleza] ?? (string) $naturaleza;
}
function bal_input(string $name, string $label, string $type='text', $value='', bool $required=true): void
{
    echo '<label><span>'.bal_h($label).($required?' *':'').'</span><input name="'.bal_h($name).'" type="'.$type.'" value="'.bal_h($value).'" '.($required?'required':'').
        ($type==='number'?' step="0.01"':'').($type==='text'?' maxlength="200"':'').'></label>';
}
function bal_select(string $name, string $label, array $rows, bool $required=true, $value=''): void
{
    echo '<label><span>'.bal_h($label).($required?' *':'').'</span><select name="'.bal_h($name).'" '.($required?'required':'').'><option value="">Seleccionar</option>';
    foreach ($rows as $row) {
        if (isset($row['activo']) && !$row['activo'] && (string)$value !== (string)$row['id']) { continue; }
        echo '<option value="'.bal_h($row['id']).'" '.((string)$value===(string)$row['id']?'selected':'').'>'.bal_h($row['nombre']).'</option>';
    }
    echo '</select></label>';
}
// Selector de persona o entidad; si no hay ninguna activa, explica donde crearlas.
function bal_tercero_select(array $thirds, bool $required=false, $value='', string $label='Persona o entidad'): void
{
    bal_select('tercero_id', $label, $thirds, $required, $value);
    $activos = array_filter($thirds, static fn(array $t): bool => !isset($t['activo']) || (int)$t['activo'] === 1);
    if (!$activos) {
        echo '<p class="bal-field-help">No hay personas ni entidades activas. <a href="'.bal_h(BASE_URL.'admin/balances?tab=catalogos&catalogo=terceros').'">Agrégalas en Personal y entidades</a>.</p>';
    }
}
function bal_options(array $values): array
{
    $rows=[]; foreach ($values as $id=>$name) { $rows[]=['id'=>$id,'nombre'=>$name]; } return $rows;
}
function bal_form(string $action, array $hidden=[]): void
{
    echo '<form class="bal-form" method="post" action="'.bal_h(BASE_URL.'procesos/proceso_balances.php').'">'.csrf_field();
    foreach (['action'=>$action,'token'=>bin2hex(random_bytes(16))]+$hidden as $name=>$value) {
        echo '<input type="hidden" name="'.bal_h($name).'" value="'.bal_h($value).'">';
    }
    echo '<p class="bal-form-error" role="alert" tabindex="-1" hidden></p>';
}
function bal_form_end(string $label='Guardar'): void
{
    echo '<footer class="bal-form-footer"><span>* Obligatorio</span><button class="bal-primary" type="submit"><i data-feather="check"></i>'.bal_h($label).'</button></footer></form>';
}
function bal_dialog_start(string $id, string $title): void
{
    echo '<dialog class="bal-dialog" id="'.bal_h($id).'"><header><h2>'.bal_h($title).'</h2><button type="button" class="bal-icon" data-close title="Cerrar" aria-label="Cerrar"><i data-feather="x"></i><span class="bal-icon-fallback" aria-hidden="true">&times;</span></button></header>';
}
function bal_open(string $id, string $label, string $icon='plus'): void
{
    echo '<button type="button" data-open="'.bal_h($id).'"><i data-feather="'.$icon.'"></i>'.bal_h($label).'</button>';
}
function bal_payment_fields(array $accounts,array $methods): void
{
    bal_select('cuenta_id','Cuenta',$accounts);
    bal_input('fecha','Fecha efectiva','date',date('Y-m-d'));
    bal_select('metodo_pago_id','Metodo de pago',$methods,false);
    bal_input('referencia','Referencia / comprobante','text','',false);
}

function bal_account_form(array $account, array $types, array $banks, array $responsibles): void
{
    $selected = (string)($account['tipo_id'] ?? '');
    echo '<label><span>Tipo de cuenta *</span><select name="tipo_id" data-account-type required><option value="">Seleccionar</option>';
    foreach ($types as $type) {
        if (!$type['activo'] && $selected !== (string)$type['id']) { continue; }
        echo '<option value="'.bal_h($type['id']).'" data-class="'.bal_h($type['clase']).'" '.($selected===(string)$type['id']?'selected':'').'>'.bal_h($type['nombre']).'</option>';
    }
    echo '</select></label>';
    echo '<fieldset class="bal-account-fields" data-account-fields="banco"><legend>Datos bancarios</legend>';
    bal_input('titular','Titular','text',$account['titular']??'');
    $currentBank = (string)($account['banco_id'] ?? '');
    $availableBanks = array_values(array_filter($banks, static fn(array $bank): bool => (bool)$bank['activo'] || $currentBank === (string)$bank['id']));
    if ($availableBanks) { bal_select('banco_id','Banco',$availableBanks,true,$currentBank); }
    else { echo '<p class="bal-field-help" data-bank-empty>No hay bancos activos. <a href="'.bal_h(BASE_URL.'admin/balances?tab=catalogos&catalogo=bancos').'">Agrégalo en Bancos</a>.</p>'; }
    bal_input('numero_cuenta','Número de cuenta','text',$account['numero_cuenta']??'');
    echo '</fieldset><fieldset class="bal-account-fields" data-account-fields="custodia"><legend>Custodia del dinero</legend>';
    $currentResponsible = (string)($account['responsable_tercero_id'] ?? '');
    $availableResponsibles = array_values(array_filter($responsibles, static fn(array $person): bool => (bool)$person['activo'] || $currentResponsible === (string)$person['id']));
    if ($availableResponsibles) { bal_select('responsable_tercero_id','Responsable',$availableResponsibles,true,$currentResponsible); }
    else { echo '<p class="bal-field-help">No hay empleados o directivos activos. <a href="'.bal_h(BASE_URL.'admin/balances?tab=catalogos&catalogo=terceros').'">Agrégalo en Personal y entidades</a>.</p>'; }
    bal_input('ubicacion','Ubicación / descripción','text',$account['ubicacion']??'',false);
    echo '</fieldset>';
    bal_input('referencia','Referencia / nota','text',$account['referencia']??'',false);
}

// "Cargar a usuario": en lugar de elegir una persona o entidad, se elige un usuario registrado
// (incluida la directiva). El servidor usa o crea su ficha en Personal y entidades.
function bal_tercero_o_usuario(mysqli $conn, array $thirds, bool $required, $value, string $label, string $nature): void
{
    if (!bal_usuario_sendero_listo($conn)) { bal_tercero_select($thirds, $required, $value, $label); return; }
    $usuarios = bal_rows($conn, "SELECT u.id, CONCAT(u.nombre, ' ', u.apellido, ' (', r.nombre, ')') nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.estado = 1 ORDER BY u.nombre, u.apellido");
    echo '<label class="bal-registro-ancho bal-cu-check"><input type="checkbox" name="cargar_usuario" value="1" data-cargar-usuario><span>' . ($nature === 'salida' ? 'Pagar a un usuario registrado' : 'Cargar a usuario') . '</span><small>Elige un usuario en lugar de una persona o entidad.</small></label>';
    echo '<label data-cu-usuario hidden><span>Usuario *</span><select name="usuario_id" disabled><option value="">Seleccionar</option>';
    foreach ($usuarios as $u) { echo '<option value="' . (int) $u['id'] . '">' . bal_h($u['nombre']) . '</option>'; }
    echo '</select></label>';
    bal_tercero_select($thirds, $required, $value, $label);
}

// Sendero al que se refiere el ingreso, gasto o cuenta (solo referencia).
function bal_sendero_select(mysqli $conn, $value = '', bool $paraCuenta = false): void
{
    if ($paraCuenta && !bal_columna_lista($conn, 'fin_obligaciones', 'sendero_id')) { return; }
    $senderos = bal_rows($conn, 'SELECT id, nombre, fecha_sendero FROM senderos ORDER BY fecha_sendero DESC, id DESC');
    echo '<label><span>Referenciar a un sendero</span><select name="sendero_id"><option value="">Ninguno</option>';
    foreach ($senderos as $s) {
        $fecha = !empty($s['fecha_sendero']) ? ' · ' . date('d/m/Y', strtotime($s['fecha_sendero'])) : '';
        echo '<option value="' . (int) $s['id'] . '" ' . ((string) $value === (string) $s['id'] ? 'selected' : '') . '>' . bal_h($s['nombre'] . $fecha) . '</option>';
    }
    echo '</select></label>';
}
