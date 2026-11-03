<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../configuracion.php';
require __DIR__ . '/../bd/conexion.php';
require __DIR__ . '/../componentes/balances_servicio.php';
if (!in_array(DB_HOST, ['localhost','127.0.0.1'], true)) { exit("Ejecuta estas pruebas solo en la base local.\n"); }
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$invalid = static function (callable $action, string $message) use ($assert): void {
    try { $action(); } catch (InvalidArgumentException $e) { return; }
    $assert(false, $message);
};
$conn->begin_transaction();
$exitCode = 0;
try {
    $actor = (int)(bal_one($conn,'SELECT id FROM usuarios ORDER BY id LIMIT 1')['id'] ?? 0);
    $assert($actor > 0, 'Se requiere al menos un usuario local.');
    $suffix = bin2hex(random_bytes(6));
    $save = static function (string $catalog, array $data) use ($conn,$actor): void {
        bal_execute($conn, 'catalogo', ['catalogo'=>$catalog,'activo'=>'1'] + $data, $actor);
    };
    $save('bancos',['nombre'=>'Prueba banco '.$suffix]);
    $bank = (int)bal_one($conn,'SELECT id FROM fin_bancos WHERE nombre=?',['Prueba banco '.$suffix])['id'];
    $save('terceros',['nombre'=>'Responsable de prueba '.$suffix,'tipo'=>'empleado']);
    $responsible = (int)bal_one($conn,'SELECT id FROM fin_terceros WHERE nombre=?',['Responsable de prueba '.$suffix])['id'];
    $bankType = (int)bal_one($conn,"SELECT id FROM fin_cuenta_tipos WHERE clase='banco' AND activo=1 LIMIT 1")['id'];
    $cashType = (int)bal_one($conn,"SELECT id FROM fin_cuenta_tipos WHERE clase='efectivo' AND activo=1 LIMIT 1")['id'];
    $base = ['tipo_id'=>$bankType,'banco_id'=>$bank,'titular'=>'Titular de prueba', 'numero_cuenta'=>'0000123456',
        'nombre'=>'Cuenta prueba '.$suffix,'fecha_apertura'=>date('Y-m-d'),'saldo_inicial'=>'125.50'];
    foreach (['titular','banco_id','numero_cuenta'] as $field) {
        $invalid(static fn()=>bal_account_fields($conn,array_replace($base,[$field=>''])), 'Se acepto banco sin '.$field);
    }
    $save('cuentas',$base);
    $account = bal_one($conn,'SELECT * FROM fin_cuentas WHERE nombre=?',[$base['nombre']]);
    $assert($account['numero_cuenta']==='0000123456', 'Se perdieron ceros iniciales.');
    $assert($account['tipo']==='banco' && (int)$account['banco_id']===$bank,'No se guardaron las relaciones.');
    $assert(bal_mask_account_number($account['numero_cuenta']) === '•••• 3456', 'El número no se enmascara correctamente.');
    $invalid(static fn()=>bal_account_fields($conn, array_replace($base, ['nombre'=>'Otra cuenta '.$suffix])), 'Se aceptó un número de cuenta repetido en el mismo banco.');
    $save('cuentas',['id'=>$account['id'],'saldo_inicial'=>'999.00','fecha_apertura'=>'2000-01-01']+$base);
    $edited=bal_one($conn,'SELECT * FROM fin_cuentas WHERE id=?',[$account['id']]);
    $assert($edited['saldo_inicial']==='125.50' && $edited['fecha_apertura']===date('Y-m-d'),'La edicion altero la apertura.');
    $invalid(static fn()=>bal_execute($conn,'catalogo',['catalogo'=>'tipos_cuenta','id'=>$bankType,'nombre'=>'Banco prueba','clase'=>'otra','activo'=>1],$actor), 'Se cambio el comportamiento de un tipo utilizado.');
    bal_query($conn,'UPDATE fin_bancos SET activo=0 WHERE id=?',[$bank]);
    $invalid(static fn()=>bal_account_fields($conn,$base),'Se acepto un banco inactivo en una cuenta nueva.');
    $assert((int)bal_account_fields($conn,$base,$account)['banco_id']===$bank,'No permite conservar un banco historico inactivo.');
    $cash=['tipo_id'=>$cashType,'responsable_tercero_id'=>$responsible,'ubicacion'=>'Caja principal'];
    $invalid(static fn()=>bal_account_fields($conn,array_replace($cash,['responsable_tercero_id'=>''])), 'Se aceptó efectivo sin responsable catalogado.');
    $fields=bal_account_fields($conn,$cash+$base);
    $assert($fields['banco_id']===null && $fields['numero_cuenta']==='' && $fields['titular']==='' && $fields['responsable']==='Responsable de prueba '.$suffix, 'Efectivo no conservo el responsable del catalogo.');
    $assert(bal_account_fields($conn,array_replace($cash,['ubicacion'=>'']))['ubicacion']==='', 'La ubicación debería ser opcional.');
    $save('tipos_cuenta',['nombre'=>'Billetera prueba '.$suffix,'clase'=>'otra']);
    $other=(int)bal_one($conn,'SELECT id FROM fin_cuenta_tipos WHERE nombre=?',['Billetera prueba '.$suffix])['id'];
    $fields=bal_account_fields($conn,['tipo_id'=>$other]+$cash);
    $assert($fields['tipo']==='otra' && (int)$fields['responsable_tercero_id']===$responsible, 'El tipo personalizado no conserva el responsable.');
    echo "OK: bancos, tipos personalizados, responsable catalogado, unicidad, enmascarado, campos obligatorios, ubicación opcional, inactivos y apertura protegida. Pruebas revertidas.\n";
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage()."\n");
    $exitCode = 1;
} finally {
    $conn->rollback();
}
exit($exitCode);
