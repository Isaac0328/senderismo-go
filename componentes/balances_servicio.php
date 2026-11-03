<?php

// Todos los cambios se ejecutan en una transaccion, bajo el bloqueo fin_control.
function bal_query(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if (!$stmt) { throw new RuntimeException('No se pudo preparar la operacion financiera.'); }
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) { throw new RuntimeException('No se pudo guardar la operacion financiera.'); }
    return $stmt;
}

function bal_rows(mysqli $db, string $sql, array $params = []): array
{
    $stmt = bal_query($db, $sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function bal_one(mysqli $db, string $sql, array $params = []): array
{
    return bal_rows($db, $sql, $params)[0] ?? [];
}

function bal_cents($value): int
{
    $value = trim((string) $value);
    if (!preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/D', $value)) {
        throw new InvalidArgumentException('Escribe un monto valido con hasta dos decimales.');
    }
    $parts = explode('.', ltrim($value, '-'));
    return (($value[0] ?? '') === '-' ? -1 : 1) * ((int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0'));
}

function bal_decimal(int $cents): string
{
    return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}

function bal_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) { throw new InvalidArgumentException('Selecciona una fecha valida.'); }
    return $date;
}

function bal_text(array $data, string $key, int $limit): string
{
    $value = trim((string) ($data[$key] ?? ''));
    $labels = ['titular' => 'titular', 'numero_cuenta' => 'número de cuenta'];
    $label = $labels[$key] ?? $key;
    if ($value === '') { throw new InvalidArgumentException('Completa el ' . $label . '.'); }
    if (mb_strlen($value) > $limit) { throw new InvalidArgumentException('El campo ' . $label . ' admite hasta ' . $limit . ' caracteres.'); }
    return $value;
}

function bal_audit(mysqli $db, int $actor, string $action, string $table, int $id, array $before, array $after): void
{
    unset($before['csrf_token'], $before['token'], $after['csrf_token'], $after['token']);
    bal_query($db, 'INSERT INTO fin_auditoria (usuario_id,accion,entidad,entidad_id,antes,despues) VALUES (?,?,?,?,?,?)',
        [$actor, $action, $table, $id, json_encode($before, JSON_UNESCAPED_UNICODE), json_encode($after, JSON_UNESCAPED_UNICODE)]);
}

// Catalogo de tipos de entidad (actualizacion 016).
function bal_tercero_tipos_listo(mysqli $db): bool
{
    static $listo = null;
    if ($listo === null) {
        $res = $db->query("SHOW TABLES LIKE 'fin_tercero_tipos'");
        $listo = $res && $res->num_rows === 1;
    }
    return $listo;
}

// Contacto separado de la identificacion en Personal y entidades (actualizacion 017).
function bal_terceros_contacto_listo(mysqli $db): bool
{
    static $listo = null;
    if ($listo === null) {
        $res = $db->query("SHOW COLUMNS FROM fin_terceros LIKE 'contacto'");
        $listo = $res && $res->num_rows === 1;
    }
    return $listo;
}

// Campo opcional "Reembolsado a" en los gastos (actualizacion 018).
function bal_movimientos_reembolso_listo(mysqli $db): bool
{
    static $listo = null;
    if ($listo === null) {
        $res = $db->query("SHOW COLUMNS FROM fin_movimientos LIKE 'reembolsado_a'");
        $listo = $res && $res->num_rows === 1;
    }
    return $listo;
}

// Columnas de la migracion 20261102_020: sin ella todo sigue funcionando como antes.
function bal_columna_lista(mysqli $db, string $tabla, string $columna): bool
{
    static $cache = [];
    $clave = $tabla . '.' . $columna;
    if (!isset($cache[$clave])) {
        $res = $db->query("SHOW COLUMNS FROM `$tabla` LIKE '$columna'");
        $cache[$clave] = $res && $res->num_rows === 1;
    }
    return $cache[$clave];
}
function bal_usuario_sendero_listo(mysqli $db): bool
{
    return bal_columna_lista($db, 'fin_terceros', 'usuario_id') && bal_columna_lista($db, 'fin_obligaciones', 'sendero_id');
}

// Sendero referenciado en el formulario: vacio es "ninguno"; si se elige, debe existir.
function bal_sendero_referencia(mysqli $db, array $p): ?int
{
    $id = (int) ($p['sendero_id'] ?? 0);
    if ($id <= 0) { return null; }
    if (!bal_one($db, 'SELECT id FROM senderos WHERE id=?', [$id])) { throw new InvalidArgumentException('El sendero elegido no existe.'); }
    return $id;
}

// Persona o entidad del formulario. Con "Cargar a usuario" se usa la ficha del usuario registrado
// (se crea la primera vez, como Directivo si su rol es Directiva y como Cliente en los demas casos).
function bal_tercero_formulario(mysqli $db, array $p, int $actor, bool $obligatorio): ?array
{
    if (empty($p['cargar_usuario'])) {
        return !empty($p['tercero_id']) || $obligatorio ? bal_active($db, 'fin_terceros', (int) ($p['tercero_id'] ?? 0)) : null;
    }
    if (!bal_usuario_sendero_listo($db)) { throw new InvalidArgumentException('Falta aplicar la actualizacion pendiente del sistema para cargar a un usuario.'); }
    $usuarioId = (int) ($p['usuario_id'] ?? 0);
    $usuario = bal_one($db, 'SELECT u.id, u.nombre, u.apellido, r.nombre rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.estado=1', [$usuarioId]);
    if (!$usuario) { throw new InvalidArgumentException('Elige un usuario activo.'); }
    $ficha = bal_one($db, 'SELECT * FROM fin_terceros WHERE usuario_id=?', [$usuarioId]);
    if ($ficha) {
        if (!$ficha['activo']) { throw new InvalidArgumentException('La ficha de ' . $ficha['nombre'] . ' esta inactiva en Personal y entidades. Activala para usarla.'); }
        return $ficha;
    }
    require_once __DIR__ . '/directiva.php';
    $clase = sg_usuario_es_directiva($db, $usuarioId) ? 'directivo' : 'cliente';
    $tipo = bal_tercero_tipos_listo($db) ? bal_one($db, 'SELECT id FROM fin_tercero_tipos WHERE clase=? AND activo=1 ORDER BY id LIMIT 1', [$clase]) : [];
    $nombre = mb_substr(trim($usuario['nombre'] . ' ' . $usuario['apellido']), 0, 160);
    bal_query($db, 'INSERT INTO fin_terceros (nombre,tipo,tipo_id,referencia,activo,usuario_id) VALUES (?,?,?,?,1,?)',
        [$nombre, $clase, $tipo['id'] ?? null, '', $usuarioId]);
    $id = (int) $db->insert_id;
    bal_audit($db, $actor, 'crear', 'fin_terceros', $id, [], ['nombre' => $nombre, 'usuario_id' => $usuarioId, 'origen' => 'cargar_a_usuario']);
    return bal_one($db, 'SELECT * FROM fin_terceros WHERE id=?', [$id]);
}

function bal_active(mysqli $db, string $table, int $id): array
{
    if (!in_array($table, ['fin_cuentas','fin_tipos','fin_terceros','fin_frecuencias','contabilidad_metodo_pago'], true)) { throw new LogicException('Catalogo no permitido.'); }
    $row = bal_one($db, "SELECT * FROM $table WHERE id=? AND activo=1", [$id]);
    if (!$row) { throw new InvalidArgumentException('Selecciona una opcion activa del catalogo.'); }
    return $row;
}

function bal_balance(mysqli $db, int $account, string $date): int
{
    $row = bal_one($db, 'SELECT c.saldo_inicial + COALESCE((SELECT SUM(m.importe) FROM fin_movimientos m WHERE m.cuenta_id=c.id AND m.fecha<=?),0) saldo FROM fin_cuentas c WHERE c.id=? AND c.fecha_apertura<=?', [$date, $account, $date]);
    return bal_cents($row['saldo'] ?? '0');
}

function bal_check_date(mysqli $db, array $account, string $date): void
{
    bal_date($date);
    if ($date > date('Y-m-d') || $date < $account['fecha_apertura']) { throw new InvalidArgumentException('La fecha debe estar entre la apertura de la cuenta y hoy.'); }
    $closed = bal_one($db, 'SELECT MAX(fecha) fecha FROM fin_cuadres WHERE cuenta_id=? AND cerrado=1', [$account['id']]);
    if (!empty($closed['fecha']) && $date <= $closed['fecha']) { throw new InvalidArgumentException('Ese periodo esta cerrado. Utiliza una fecha posterior al ultimo cierre.'); }
}

// Fecha con la que un registro de sendero entra a la cuenta: la suya propia si se
// pidio y la tiene (nunca futura), o la fecha elegida en el formulario.
function bal_fecha_origen(?string $fechaOrigen, string $fechaElegida, bool $usarOrigen): string
{
    if (!$usarOrigen || !$fechaOrigen || $fechaOrigen === '0000-00-00') { return $fechaElegida; }
    $fecha = substr($fechaOrigen, 0, 10);
    return min($fecha, date('Y-m-d'));
}

// Registra en la fecha de apertura la diferencia encontrada al cuadrar, para que el
// saldo inicial refleje el dinero que la empresa ya tenia antes de usar el sistema.
function bal_ajustar_apertura(mysqli $db, array $account, int $difference, string $cutDate, int $actor): void
{
    $nature = $difference > 0 ? 'ingreso' : 'salida';
    $type = bal_one($db, 'SELECT id,activo FROM fin_tipos WHERE naturaleza=? AND nombre=?', [$nature, 'Ajuste de saldo inicial']);
    if (!$type) {
        bal_query($db, 'INSERT INTO fin_tipos (nombre,naturaleza,activo) VALUES (?,?,1)', ['Ajuste de saldo inicial', $nature]);
        $type = ['id' => $db->insert_id];
    } elseif (!$type['activo']) {
        bal_query($db, 'UPDATE fin_tipos SET activo=1 WHERE id=?', [$type['id']]);
    }
    bal_post($db, [
        'token' => bin2hex(random_bytes(16)), 'cuenta_id' => $account['id'], 'fecha' => $account['fecha_apertura'],
        'naturaleza' => $nature, 'tipo_id' => (int) $type['id'], 'origen' => 'manual',
        'concepto' => 'Ajuste de saldo inicial segun cuadre al ' . date('d/m/Y', strtotime($cutDate)),
    ], $difference, $actor);
}

function bal_post(mysqli $db, array $data, int $amount, int $actor): int
{
    if ($amount === 0 || abs($amount) > 99999999999999) { throw new InvalidArgumentException('El monto debe ser mayor que cero y estar dentro del limite permitido.'); }
    $account = bal_active($db, 'fin_cuentas', (int) $data['cuenta_id']);
    bal_check_date($db, $account, $data['fecha']);
    $columnas = 'token,cuenta_id,fecha,naturaleza,importe,concepto,tipo_id,tercero_id,metodo_pago_id,referencia,sendero_id,origen,origen_id,obligacion_id,reversa_id,creado_por';
    $valores = [
        $data['token'], $account['id'], $data['fecha'], $data['naturaleza'], bal_decimal($amount), $data['concepto'],
        $data['tipo_id'] ?? null, $data['tercero_id'] ?? null, $data['metodo_pago_id'] ?? null,
        $data['referencia'] ?? '', $data['sendero_id'] ?? null, $data['origen'], $data['origen_id'] ?? null,
        $data['obligacion_id'] ?? null, $data['reversa_id'] ?? null, $actor,
    ];
    if (bal_movimientos_reembolso_listo($db)) {
        $columnas .= ',reembolsado_a';
        $valores[] = trim((string) ($data['reembolsado_a'] ?? '')) !== '' ? trim((string) $data['reembolsado_a']) : null;
    }
    bal_query($db, "INSERT INTO fin_movimientos ($columnas) VALUES (" . implode(',', array_fill(0, count($valores), '?')) . ')', $valores);
    $id = $db->insert_id;
    bal_audit($db, $actor, 'registrar', 'fin_movimientos', $id, [], $data + ['importe' => bal_decimal($amount)]);
    return $id;
}

function bal_due_date(array $program, int $index): string
{
    $start = new DateTimeImmutable($program['inicio']);
    $step = (int) $program['intervalo'] * $index;
    if ($program['unidad'] === 'dias') { return $start->modify("+$step days")->format('Y-m-d'); }
    $month = $start->modify('first day of this month')->modify("+$step months");
    return $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min((int) $start->format('d'), (int) $month->format('t')))->format('Y-m-d');
}

function bal_obligation_paid(mysqli $db, int $id, string $nature): int
{
    $row = bal_one($db, 'SELECT COALESCE(SUM(importe),0) total FROM fin_movimientos WHERE obligacion_id=?', [$id]);
    return bal_cents($row['total']) * ($nature === 'salida' ? -1 : 1);
}

function bal_sources(mysqli $db): array
{
    $rows = bal_rows($db, "SELECT 'cobro_sendero' origen,p.id origen_id,p.sendero_id,s.nombre sendero,
        CONCAT('Cobro registro #',p.registro_id) concepto,p.monto_pagado monto,p.fecha_pago fecha,
        p.metodo_pago_id,COALESCE(SUM(m.importe),0) contabilizado
        FROM contabilidad_registro_pagos p JOIN senderos s ON s.id=p.sendero_id
        LEFT JOIN fin_movimientos m ON m.origen='cobro_sendero' AND m.origen_id=p.id
        GROUP BY p.id HAVING monto<>contabilizado
        UNION ALL SELECT 'gasto_sendero',g.id,g.sendero_id,s.nombre,COALESCE(cat.nombre,CONCAT(COALESCE(g.grupo,'Otros gastos'),': ',COALESCE(g.descripcion,''))),g.total,s.fecha_sendero,NULL,
        -COALESCE(SUM(m.importe),0)
        FROM contabilidad_sendero_gastos g JOIN senderos s ON s.id=g.sendero_id
        LEFT JOIN contabilidad_gastos_catalogo cat ON cat.id=g.gasto_id
        LEFT JOIN fin_movimientos m ON m.origen='gasto_sendero' AND m.origen_id=g.id
        GROUP BY g.id HAVING g.total<>-COALESCE(SUM(m.importe),0)
        ORDER BY sendero_id DESC,origen_id DESC");
    $missing = bal_rows($db,"SELECT m.origen,m.origen_id,MAX(m.sendero_id) sendero_id,
        COALESCE(MAX(s.nombre),'Sendero eliminado') sendero,'Origen eliminado: regularizacion' concepto,
        '0.00' monto,NULL fecha,NULL metodo_pago_id,
        SUM(IF(m.origen='gasto_sendero',-m.importe,m.importe)) contabilizado
        FROM fin_movimientos m LEFT JOIN senderos s ON s.id=m.sendero_id
        LEFT JOIN contabilidad_registro_pagos p ON m.origen='cobro_sendero' AND p.id=m.origen_id
        LEFT JOIN contabilidad_sendero_gastos g ON m.origen='gasto_sendero' AND g.id=m.origen_id
        WHERE (m.origen='cobro_sendero' AND p.id IS NULL) OR (m.origen='gasto_sendero' AND g.id IS NULL)
        GROUP BY m.origen,m.origen_id HAVING contabilizado<>0");
    return array_merge($rows,$missing);
}

// Pendientes de asignar, separados entre los de senderos ya cuadrados (se pueden
// asignar) y los que aun no lo estan (se cuentan, pero no se ofrecen).
function bal_sources_cuadradas(mysqli $db): array
{
    require_once __DIR__ . '/cuadre_sendero.php';
    $rows = bal_sources($db);
    if (!sg_cuadre_sendero_listo($db)) { return ['rows' => $rows, 'pendientes' => []]; }
    $estado = sg_cuadre_sendero_estado($db);
    $listas = []; $pendientes = [];
    foreach ($rows as $row) {
        $area = $row['origen'] === 'cobro_sendero' ? 'cobros' : 'gastos';
        // Las regularizaciones de registros borrados siempre se muestran.
        if ($row['concepto'] === 'Origen eliminado: regularizacion' || !empty($estado[(int) $row['sendero_id']][$area])) { $listas[] = $row; continue; }
        $clave = $row['sendero_id'] . '-' . $area;
        $pendientes[$clave] ??= ['sendero_id' => (int) $row['sendero_id'], 'sendero' => $row['sendero'], 'area' => $area, 'registros' => 0];
        $pendientes[$clave]['registros']++;
    }
    return ['rows' => $listas, 'pendientes' => array_values($pendientes)];
}

function bal_mask_account_number(string $number): string
{
    $lastFour = mb_substr($number, -4);
    return $number === '' ? '' : '•••• ' . $lastFour;
}

function bal_account_fields(mysqli $db, array $p, array $before = []): array
{
    $type = bal_one($db, 'SELECT * FROM fin_cuenta_tipos WHERE id=?', [(int)($p['tipo_id'] ?? 0)]);
    if (!$type || (!$type['activo'] && (int)($before['tipo_id'] ?? 0) !== (int)$type['id'])) {
        throw new InvalidArgumentException('Selecciona un tipo de cuenta activo.');
    }
    $values = ['tipo_id'=>$type['id'], 'tipo'=>$type['clase'],
        'referencia'=>mb_substr(trim((string)($p['referencia'] ?? '')),0,120),
        'banco_id'=>null, 'titular'=>'', 'numero_cuenta'=>'', 'responsable'=>'', 'responsable_tercero_id'=>null, 'ubicacion'=>''];
    if ($type['clase'] === 'banco') {
        $bank = bal_one($db, 'SELECT * FROM fin_bancos WHERE id=?', [(int)($p['banco_id'] ?? 0)]);
        if (!$bank || (!$bank['activo'] && (int)($before['banco_id'] ?? 0) !== (int)$bank['id'])) {
            throw new InvalidArgumentException('Selecciona un banco activo del mantenimiento de bancos.');
        }
        $values['banco_id'] = $bank['id'];
        $values['titular'] = bal_text($p, 'titular', 160);
        $values['numero_cuenta'] = bal_text($p, 'numero_cuenta', 50);
        $duplicate = bal_one($db, 'SELECT id FROM fin_cuentas WHERE banco_id=? AND numero_cuenta=? AND id<>? LIMIT 1', [$bank['id'], $values['numero_cuenta'], (int)($before['id'] ?? 0)]);
        if ($duplicate) { throw new InvalidArgumentException('Ya existe una cuenta con ese banco y número de cuenta.'); }
    } else {
        $responsible = bal_one($db, "SELECT * FROM fin_terceros WHERE id=? AND tipo IN ('empleado','directivo')", [(int)($p['responsable_tercero_id'] ?? 0)]);
        if (!$responsible || (!$responsible['activo'] && (int)($before['responsable_tercero_id'] ?? 0) !== (int)$responsible['id'])) {
            throw new InvalidArgumentException('Selecciona un responsable activo de Personal y entidades.');
        }
        $values['responsable_tercero_id'] = $responsible['id'];
        $values['responsable'] = $responsible['nombre'];
        $values['ubicacion'] = mb_substr(trim((string)($p['ubicacion'] ?? '')), 0, 200);
    }
    return $values;
}

function bal_execute(mysqli $db, string $action, array $p, int $actor): string
{
    bal_one($db, 'SELECT id FROM fin_control WHERE id=1 FOR UPDATE');
    if ($action === 'importar_masivo') {
        $ids = isset($p['ids']) && is_array($p['ids']) ? array_values(array_filter(array_map('intval', $p['ids']))) : [];
        $origins = isset($p['origenes']) && is_array($p['origenes']) ? array_values($p['origenes']) : [];
        if (count($ids) === 0 || count($ids) !== count($origins)) { throw new InvalidArgumentException('Selecciona al menos un registro para asignar.'); }
        if (count($ids) > 100) { throw new InvalidArgumentException('Puedes asignar hasta 100 registros por operacion.'); }
        $account = bal_active($db, 'fin_cuentas', (int)($p['cuenta_id'] ?? 0));
        $date = bal_date((string)($p['fecha'] ?? ''));
        bal_check_date($db, $account, $date);
        $assigned = 0;
        foreach ($ids as $index => $sourceId) {
            $origin = (string)$origins[$index];
            if (!in_array($origin, ['cobro_sendero', 'gasto_sendero'], true)) { throw new InvalidArgumentException('Origen de sendero no valido.'); }
            $nature = $origin === 'cobro_sendero' ? 'ingreso' : 'salida';
            $type = bal_one($db, 'SELECT id FROM fin_tipos WHERE naturaleza=? AND activo=1 ORDER BY id LIMIT 1', [$nature]);
            if (!$type) { throw new InvalidArgumentException('Crea primero un tipo activo de ' . $nature . '.'); }
            $sourceTable = $origin === 'cobro_sendero' ? 'contabilidad_registro_pagos' : 'contabilidad_sendero_gastos';
            $sourceAmountColumn = $origin === 'cobro_sendero' ? 'monto_pagado' : 'total';
            $sourceRow = $origin === 'cobro_sendero'
                ? bal_one($db, 'SELECT p.monto_pagado monto,p.fecha_pago fecha,s.nombre sendero FROM contabilidad_registro_pagos p JOIN senderos s ON s.id=p.sendero_id WHERE p.id=? FOR UPDATE', [$sourceId])
                : bal_one($db, 'SELECT g.total monto,s.fecha_sendero fecha,s.nombre sendero FROM contabilidad_sendero_gastos g JOIN senderos s ON s.id=g.sendero_id WHERE g.id=? FOR UPDATE', [$sourceId]);
            $itemDate = bal_fecha_origen($sourceRow['fecha'] ?? null, $date, !empty($p['usar_fecha_registro']));
            try {
                bal_check_date($db, $account, $itemDate);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException('Sendero "' . ($sourceRow['sendero'] ?? '#' . $sourceId) . '", fecha ' . date('d/m/Y', strtotime($itemDate)) . ': ' . $e->getMessage() . ' La apertura de la cuenta es ' . date('d/m/Y', strtotime($account['fecha_apertura'])) . '.');
            }
            $posted = bal_one($db, 'SELECT COALESCE(SUM(importe),0) total FROM fin_movimientos WHERE origen=? AND origen_id=?', [$origin, $sourceId]);
            $sourceAmount = (float)($sourceRow['monto'] ?? 0);
            $difference = bal_cents((string)($sourceAmount ?: '0')) * ($nature === 'ingreso' ? 1 : -1) - bal_cents((string)($posted['total'] ?? '0'));
            $assigned += (int)(bal_execute($db, 'importar', [
                'id' => $sourceId, 'origen' => $origin, 'cuenta_id' => $account['id'], 'fecha' => $itemDate,
                'tipo_id' => $type['id'], 'concepto' => 'Asignacion masiva de sendero', 'diferencia' => bal_decimal($difference),
                'token' => bin2hex(random_bytes(16)),
            ], $actor) !== 'El registro ya esta contabilizado.');
        }
        bal_audit($db, $actor, 'asignar_masivo', 'fin_movimientos', 0, [], ['cuenta_id'=>$account['id'], 'fecha'=>$date, 'registros'=>$assigned]);
        return $assigned . ' registros asignados a la cuenta.';
    }
    $id = (int) ($p['id'] ?? 0);
    if ($action === 'catalogo') {
        $catalog = (string) ($p['catalogo'] ?? '');
        $table = ['cuentas'=>'fin_cuentas','bancos'=>'fin_bancos','tipos_cuenta'=>'fin_cuenta_tipos','tipos'=>'fin_tipos','terceros'=>'fin_terceros','tipos_tercero'=>'fin_tercero_tipos','frecuencias'=>'fin_frecuencias'][$catalog] ?? '';
        if (!$table) { throw new InvalidArgumentException('Catalogo no valido.'); }
        $before = $id ? bal_one($db, "SELECT * FROM $table WHERE id=?", [$id]) : [];
        if ($id && !$before) { throw new InvalidArgumentException('Registro no encontrado.'); }
        $values = ['nombre' => bal_text($p, 'nombre', $catalog === 'terceros' ? 160 : 120), 'activo' => isset($p['activo']) ? 1 : 0];
        if ($catalog === 'cuentas') {
            $values += bal_account_fields($db, $p, $before);
            if (!$id) {
                $date = bal_date($p['fecha_apertura'] ?? '');
                if ($date > date('Y-m-d')) { throw new InvalidArgumentException('La apertura no puede ser futura.'); }
                $values += ['fecha_apertura'=>$date,'saldo_inicial'=>bal_decimal(bal_cents($p['saldo_inicial'] ?? '0')),'creado_por'=>$actor];
            } elseif (!$values['activo'] && bal_balance($db,$id,date('Y-m-d')) !== 0) { throw new InvalidArgumentException('Transfiere o regulariza el saldo antes de inactivar la cuenta.'); }
        } elseif ($catalog === 'tipos_cuenta') {
            $class = (string)($p['clase'] ?? '');
            if (!in_array($class, ['banco','efectivo','otra'], true)) { throw new InvalidArgumentException('Selecciona el comportamiento del tipo de cuenta.'); }
            if ($id && $class !== $before['clase'] && bal_one($db, 'SELECT id FROM fin_cuentas WHERE tipo_id=? LIMIT 1', [$id])) {
                throw new InvalidArgumentException('Este tipo ya tiene cuentas. Crea otro tipo para cambiar su comportamiento.');
            }
            $values['clase'] = $class;
        } elseif ($catalog === 'bancos') {
            // Los bancos solo tienen nombre y estado; no se eliminan referencias historicas.
        } elseif ($catalog === 'tipos') {
            if (!in_array($p['naturaleza'] ?? '', ['ingreso','salida'], true)) { throw new InvalidArgumentException('Naturaleza no valida.'); }
            if ($id && $before['naturaleza'] !== $p['naturaleza']) { throw new InvalidArgumentException('La naturaleza de un tipo existente no se cambia. Crea otro tipo.'); }
            $values['naturaleza'] = $p['naturaleza'];
        } elseif ($catalog === 'tipos_tercero') {
            // La clase decide las reglas (solo empleado y directivo custodian efectivo).
            $class = (string)($p['clase'] ?? '');
            if (!in_array($class, ['empleado','directivo','proveedor','cliente','otro'], true)) { throw new InvalidArgumentException('Selecciona el comportamiento del tipo de entidad.'); }
            if ($id && $class !== $before['clase'] && bal_one($db, 'SELECT id FROM fin_terceros WHERE tipo_id=? LIMIT 1', [$id])) {
                throw new InvalidArgumentException('Este tipo ya tiene entidades. Crea otro tipo para cambiar su comportamiento.');
            }
            $values['clase'] = $class;
        } elseif ($catalog === 'terceros') {
            if (bal_tercero_tipos_listo($db)) {
                $type = bal_one($db, 'SELECT * FROM fin_tercero_tipos WHERE id=?', [(int)($p['tipo_id'] ?? 0)]);
                // Un tipo inactivo solo se acepta si la entidad ya lo tenia.
                if (!$type || (!(int)$type['activo'] && (int)($before['tipo_id'] ?? 0) !== (int)$type['id'])) {
                    throw new InvalidArgumentException('Selecciona un tipo de entidad activo.');
                }
                $values += ['tipo_id'=>(int)$type['id'],'tipo'=>$type['clase']];
            } else {
                if (!in_array($p['tipo'] ?? '', ['empleado','directivo','proveedor','cliente','otro'], true)) { throw new InvalidArgumentException('Tipo de tercero no valido.'); }
                $values['tipo'] = $p['tipo'];
            }
            $values['referencia'] = mb_substr(trim($p['referencia'] ?? ''),0,160);
            if (bal_terceros_contacto_listo($db)) { $values['contacto'] = mb_substr(trim($p['contacto'] ?? ''),0,160); }
        } else {
            $interval = filter_var($p['intervalo'] ?? '', FILTER_VALIDATE_INT);
            if (!$interval || $interval < 1 || $interval > 365 || !in_array($p['unidad'] ?? '', ['dias','meses'], true)) { throw new InvalidArgumentException('Frecuencia no valida (intervalo de 1 a 365).'); }
            $values += ['unidad'=>$p['unidad'],'intervalo'=>$interval];
        }
        if ($id) {
            bal_query($db, "UPDATE $table SET " . implode(',', array_map(static fn($k) => "$k=?", array_keys($values))) . ' WHERE id=?', array_merge(array_values($values),[$id]));
        } else {
            bal_query($db, "INSERT INTO $table (" . implode(',',array_keys($values)) . ') VALUES (' . implode(',',array_fill(0,count($values),'?')) . ')',array_values($values));
            $id = $db->insert_id;
        }
        bal_audit($db,$actor,'guardar',$table,$id,$before,$values);
        return 'Catalogo guardado.';
    }
    if ($action === 'programacion' || $action === 'obligacion') {
        $type = bal_active($db,'fin_tipos',(int)($p['tipo_id'] ?? 0));
        $third = $action === 'obligacion' ? bal_tercero_formulario($db, $p, $actor, true) : bal_active($db,'fin_terceros',(int)($p['tercero_id'] ?? 0));
        $senderoRef = $action === 'obligacion' && bal_columna_lista($db, 'fin_obligaciones', 'sendero_id') ? bal_sendero_referencia($db, $p) : null;
        $concept = bal_text($p,'concepto',200);
        $amount = bal_cents($p['monto'] ?? '');
        if ($amount <= 0) { throw new InvalidArgumentException('El monto debe ser mayor que cero.'); }
        $table = $action === 'programacion' ? 'fin_programaciones' : 'fin_obligaciones';
        $before = $id ? bal_one($db,"SELECT * FROM $table WHERE id=?",[$id]) : [];
        if ($id && (!$before || !empty($before['anulada']))) { throw new InvalidArgumentException('Registro no disponible.'); }
        if ($id && $action === 'obligacion' && bal_obligation_paid($db,$id,$before['naturaleza']) !== 0) { throw new InvalidArgumentException('No se edita una obligacion con abonos. Revierte sus pagos primero.'); }
        if ($action === 'programacion') {
            $frequency = bal_active($db,'fin_frecuencias',(int)($p['frecuencia_id'] ?? 0));
            $start = bal_date($p['inicio'] ?? '');
            $end = empty($p['fin']) ? null : bal_date($p['fin']);
            if ($end && $end < $start) { throw new InvalidArgumentException('La fecha final debe ser posterior al inicio.'); }
            if ($id) {
                if ($start !== $before['inicio'] || $frequency['unidad'] !== $before['unidad'] || (int)$frequency['intervalo'] !== (int)$before['intervalo']) { throw new InvalidArgumentException('Para cambiar el calendario, pausa esta programacion y crea otra.'); }
                bal_query($db,'UPDATE fin_programaciones SET concepto=?,tercero_id=?,tipo_id=?,monto=?,fin=? WHERE id=?',[$concept,$third['id'],$type['id'],bal_decimal($amount),$end,$id]);
            } else { bal_query($db,'INSERT INTO fin_programaciones (concepto,tercero_id,tipo_id,frecuencia_id,monto,inicio,fin,unidad,intervalo,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$concept,$third['id'],$type['id'],$frequency['id'],bal_decimal($amount),$start,$end,$frequency['unidad'],$frequency['intervalo'],$actor]);
            }
        } else {
            $conSendero = bal_columna_lista($db, 'fin_obligaciones', 'sendero_id');
            if ($id) { bal_query($db,'UPDATE fin_obligaciones SET concepto=?,naturaleza=?,tercero_id=?,tipo_id=?,monto=?,vencimiento=?'.($conSendero ? ',sendero_id=?' : '').' WHERE id=?',array_merge([$concept,$type['naturaleza'],$third['id'],$type['id'],bal_decimal($amount),bal_date($p['vencimiento'] ?? '')],$conSendero ? [$senderoRef] : [],[$id])); }
            else { bal_query($db,'INSERT INTO fin_obligaciones (concepto,naturaleza,tercero_id,tipo_id,monto,vencimiento,creado_por'.($conSendero ? ',sendero_id' : '').') VALUES (?,?,?,?,?,?,?'.($conSendero ? ',?' : '').')',
                array_merge([$concept,$type['naturaleza'],$third['id'],$type['id'],bal_decimal($amount),bal_date($p['vencimiento'] ?? ''),$actor],$conSendero ? [$senderoRef] : []));
            }
        }
        bal_audit($db,$actor,$id?'editar':'crear',$action,$id ?: $db->insert_id,$before,$p);
        if ($id) { return 'Cambios guardados. Las cuotas ya generadas conservan sus importes y vencimientos.'; }
        return $action === 'programacion' ? 'Programacion creada. Genera los vencimientos para registrar sus cuotas.' : 'Obligacion registrada.';
    }
    if ($action === 'generar') {
        $program = bal_one($db,'SELECT p.*,t.naturaleza FROM fin_programaciones p JOIN fin_tipos t ON t.id=p.tipo_id WHERE p.id=? AND p.activo=1',[$id]);
        if (!$program) { throw new InvalidArgumentException('Programacion no disponible.'); }
        $until = bal_date($p['hasta'] ?? '');
        if ($until > date('Y-m-d',strtotime('+2 years'))) { throw new InvalidArgumentException('Genera como maximo dos anos por adelantado.'); }
        $until = min($until,$program['fin'] ?? $until);
        $count = 0;
        for ($i=0; $i<10000; $i++) {
            $date = bal_due_date($program,$i);
            if ($date > $until) { break; }
            $stmt = bal_query($db,'INSERT INTO fin_obligaciones (concepto,naturaleza,tercero_id,tipo_id,monto,vencimiento,programacion_id,creado_por) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',
                [$program['concepto'],$program['naturaleza'],$program['tercero_id'],$program['tipo_id'],$program['monto'],$date,$id,$actor]);
            $count += $stmt->affected_rows === 1 ? 1 : 0;
        }
        if ($i === 10000) { throw new InvalidArgumentException('El rango contiene demasiados vencimientos.'); }
        bal_audit($db,$actor,'generar','fin_programaciones',$id,[],['hasta'=>$until,'cuotas'=>$count]);
        return "$count vencimientos nuevos generados.";
    }
    if ($action === 'pausar' || $action === 'anular_obligacion') {
        $table = $action === 'pausar' ? 'fin_programaciones' : 'fin_obligaciones';
        $before = bal_one($db,"SELECT * FROM $table WHERE id=?",[$id]);
        if (!$before) { throw new InvalidArgumentException('Registro no encontrado.'); }
        $reason = bal_text($p,'motivo',200);
        if ($action === 'anular_obligacion') {
            if (bal_obligation_paid($db,$id,$before['naturaleza']) !== 0) { throw new InvalidArgumentException('Revierte sus pagos antes de anular la obligacion.'); }
            bal_query($db,'UPDATE fin_obligaciones SET anulada=1 WHERE id=?',[$id]);
        } else { bal_query($db,'UPDATE fin_programaciones SET activo=? WHERE id=?',[(int)!$before['activo'],$id]); }
        bal_audit($db,$actor,$action,$table,$id,$before,['motivo'=>$reason]);
        return 'Estado actualizado. Los vencimientos ya generados conservan su historial.';
    }
    if ($action === 'cuadre') {
        $account = bal_active($db,'fin_cuentas',(int)($p['cuenta_id'] ?? 0));
        $date = bal_date($p['fecha'] ?? '');
        bal_check_date($db,$account,$date);
        $book = bal_balance($db,(int)$account['id'],$date);
        $real = bal_cents($p['saldo_real'] ?? '');
        $adjusted = false;
        if (!empty($p['ajustar_apertura']) && $real !== $book) {
            bal_ajustar_apertura($db, $account, $real - $book, $date, $actor);
            $book = bal_balance($db,(int)$account['id'],$date);
            $adjusted = true;
        }
        $close = isset($p['cerrado']) ? 1 : 0;
        if ($close && $book !== $real) { throw new InvalidArgumentException('El saldo real y el libro difieren en RD$ ' . bal_decimal($real-$book) . '. Revisa los movimientos antes de cerrar.'); }
        bal_query($db,'INSERT INTO fin_cuadres (cuenta_id,fecha,saldo_libro,saldo_real,diferencia,cerrado,nota,creado_por) VALUES (?,?,?,?,?,?,?,?)',
            [$account['id'],$date,bal_decimal($book),bal_decimal($real),bal_decimal($real-$book),$close,bal_text($p,'nota',255),$actor]);
        bal_audit($db,$actor,$close?'cerrar':'cuadrar','fin_cuadres',$db->insert_id,[],$p);
        $adjustedText = $adjusted ? ' La diferencia se registro como ajuste de saldo inicial.' : '';
        return ($close ? 'Cuenta conciliada y cerrada hasta la fecha seleccionada.' : 'Cuadre guardado.') . $adjustedText;
    }
    if ($action === 'registrar') {
        return bal_registrar($db, $p, $actor);
    }
    if ($action === 'editar_movimiento') {
        return bal_editar_movimiento($db, $p, $actor);
    }
    if ($action === 'cancelar_movimiento') {
        return bal_cancelar_movimiento($db, $p, $actor);
    }
    $token = (string)($p['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/D',$token)) { throw new InvalidArgumentException('Recarga el formulario e intenta de nuevo.'); }
    if (bal_one($db,'SELECT id FROM fin_movimientos WHERE token=? OR token=?',[$token,$token . '-destino'])) { return 'Esta operacion ya fue registrada.'; }
    $date = bal_date($p['fecha'] ?? '');
    $data = ['token'=>$token,'fecha'=>$date,'cuenta_id'=>(int)($p['cuenta_id'] ?? 0)];
    if ($action === 'reversar_masivo') {
        // Deshace asignaciones de cobros y gastos de senderos elegidas en la lista de movimientos.
        $ids = isset($p['ids']) && is_array($p['ids']) ? array_values(array_unique(array_filter(array_map('intval', $p['ids'])))) : [];
        if (!$ids) { throw new InvalidArgumentException('Selecciona al menos una asignacion para deshacer.'); }
        if (count($ids) > 100) { throw new InvalidArgumentException('Puedes deshacer hasta 100 asignaciones por operacion.'); }
        $reason = bal_text($p,'motivo',200);
        foreach ($ids as $movementId) {
            $original = bal_one($db,'SELECT * FROM fin_movimientos WHERE id=?',[$movementId]);
            if (!$original || !in_array($original['origen'],['cobro_sendero','gasto_sendero'],true)) { throw new InvalidArgumentException('El movimiento #' . $movementId . ' no es una asignacion de sendero.'); }
            if ($original['reversa_id'] || bal_one($db,'SELECT id FROM fin_movimientos WHERE reversa_id=?',[$movementId])) { throw new InvalidArgumentException('El movimiento #' . $movementId . ' ya fue deshecho. Recarga la pagina.'); }
            if ($date < $original['fecha']) { throw new InvalidArgumentException('La fecha del reverso no puede ser anterior a ' . date('d/m/Y', strtotime($original['fecha'])) . ' (movimiento #' . $movementId . ').'); }
            $original['token'] = bin2hex(random_bytes(16));
            $original['fecha'] = $date;
            $original['reversa_id'] = $original['id'];
            $original['concepto'] = 'Reverso #' . $original['id'] . ': ' . $reason;
            bal_post($db,$original,-bal_cents($original['importe']),$actor);
        }
        return count($ids) . ' asignaciones deshechas. Sus cobros y gastos vuelven a quedar pendientes de asignar.';
    }
    if ($action === 'reversar_sendero') {
        $sendero = (int)($p['sendero_id'] ?? 0);
        $reason = bal_text($p,'motivo',200);
        $entries = bal_rows($db,"SELECT m.* FROM fin_movimientos m WHERE m.sendero_id=? AND m.origen IN ('cobro_sendero','gasto_sendero') AND m.reversa_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM fin_movimientos r WHERE r.reversa_id=m.id) ORDER BY m.id",[$sendero]);
        if (!$entries) { throw new InvalidArgumentException('Este sendero no tiene asignaciones vigentes para reversar.'); }
        foreach ($entries as $entry) {
            if ($date < $entry['fecha']) { throw new InvalidArgumentException('La fecha del reverso no puede ser anterior a ' . date('d/m/Y', strtotime($entry['fecha'])) . ', la del movimiento mas reciente del sendero.'); }
            $entry['token'] = bin2hex(random_bytes(16));
            $entry['fecha'] = $date;
            $entry['reversa_id'] = $entry['id'];
            $entry['concepto'] = 'Reverso #' . $entry['id'] . ': ' . $reason;
            bal_post($db,$entry,-bal_cents($entry['importe']),$actor);
        }
        return count($entries) . ' asignaciones del sendero reversadas. Sus cobros y gastos vuelven a quedar pendientes de asignar.';
    }
    if ($action === 'reversar') {
        $original = bal_one($db,'SELECT * FROM fin_movimientos WHERE id=?',[$id]);
        if (!$original || $original['reversa_id'] || bal_one($db,'SELECT id FROM fin_movimientos WHERE reversa_id=?',[$id])) { throw new InvalidArgumentException('Movimiento inexistente o ya revertido.'); }
        if ($date < $original['fecha']) { throw new InvalidArgumentException('El reverso no puede ser anterior al movimiento original.'); }
        $reason = bal_text($p,'motivo',200);
        $entries = [$original];
        if ($original['origen'] === 'transferencia') {
            $baseToken = str_replace('-destino','',$original['token']);
            $entries = bal_rows($db,'SELECT * FROM fin_movimientos WHERE token=? OR token=?',[$baseToken,$baseToken.'-destino']);
        }
        foreach ($entries as $i=>$entry) {
            $entry['token'] = $token . ($i ? '-destino' : '');
            $entry['fecha'] = $date;
            $entry['reversa_id'] = $entry['id'];
            $entry['concepto'] = 'Reverso #' . $entry['id'] . ': ' . $reason;
            bal_post($db,$entry,-bal_cents($entry['importe']),$actor);
        }
        return 'Reverso registrado con su historial.';
    }
    if ($action === 'importar') {
        $origin = $p['origen'] ?? '';
        if (!in_array($origin,['cobro_sendero','gasto_sendero'],true)) { throw new InvalidArgumentException('Origen no valido.'); }
        $source = $origin === 'cobro_sendero'
            ? bal_one($db,'SELECT id,sendero_id,monto_pagado monto,metodo_pago_id FROM contabilidad_registro_pagos WHERE id=? FOR UPDATE',[$id])
            : bal_one($db,'SELECT id,sendero_id,total monto,NULL metodo_pago_id FROM contabilidad_sendero_gastos WHERE id=? FOR UPDATE',[$id]);
        if (!$source) {
            $previous = bal_one($db,'SELECT sendero_id FROM fin_movimientos WHERE origen=? AND origen_id=? ORDER BY id LIMIT 1',[$origin,$id]);
            if (!$previous) { throw new InvalidArgumentException('El registro de origen no existe.'); }
            $source = ['sendero_id'=>$previous['sendero_id'],'monto'=>'0.00','metodo_pago_id'=>null];
        }
        $direction = $origin === 'cobro_sendero' ? 1 : -1;
        require_once __DIR__ . '/cuadre_sendero.php';
        if (!empty($source['id']) && sg_cuadre_sendero_listo($db)) {
            $area = $direction === 1 ? 'cobros' : 'gastos';
            if (empty(sg_cuadre_sendero_estado($db, (int) $source['sendero_id'])[(int) $source['sendero_id']][$area])) {
                throw new InvalidArgumentException('Los ' . $area . ' de este sendero aun no estan cuadrados. Marcalos como cuadrados en ' . ($area === 'cobros' ? 'Ingresos' : 'Gastos') . ' por sendero antes de asignarlos.');
            }
        }
        $posted = bal_one($db,'SELECT COALESCE(SUM(importe),0) total FROM fin_movimientos WHERE origen=? AND origen_id=?',[$origin,$id]);
        $delta = bal_cents($source['monto']) * $direction - bal_cents($posted['total']);
        // El importe mostrado funciona como control de concurrencia entre revision y confirmacion.
        if ($delta !== bal_cents($p['diferencia'] ?? '')) { throw new InvalidArgumentException('El importe de origen cambio. Recarga y revisa la diferencia.'); }
        if (!$delta) { return 'El registro ya esta contabilizado.'; }
        $type = bal_active($db,'fin_tipos',(int)($p['tipo_id'] ?? 0));
        $nature = $direction === 1 ? 'ingreso' : 'salida';
        if ($type['naturaleza'] !== $nature) { throw new InvalidArgumentException('El tipo no corresponde al origen.'); }
        $data += ['concepto'=>bal_text($p,'concepto',255),'naturaleza'=>$nature,'tipo_id'=>$type['id'],'origen'=>$origin,'origen_id'=>$id,
            'sendero_id'=>$source['sendero_id'],'metodo_pago_id'=>$source['metodo_pago_id']];
        bal_post($db,$data,$delta,$actor);
        return 'Movimiento del sendero contabilizado en la cuenta.';
    }
    $amount = bal_cents($p['monto'] ?? '');
    if ($amount <= 0) { throw new InvalidArgumentException('El monto debe ser mayor que cero.'); }
    $data['referencia'] = mb_substr(trim($p['referencia'] ?? ''),0,180);
    if ($action === 'transferir') {
        $destination = (int)($p['destino_id'] ?? 0);
        if ($destination === $data['cuenta_id']) { throw new InvalidArgumentException('Selecciona dos cuentas diferentes.'); }
        $data += ['concepto'=>bal_text($p,'concepto',255),'naturaleza'=>'transferencia','origen'=>'transferencia'];
        bal_post($db,$data,-$amount,$actor);
        $data['cuenta_id'] = $destination;
        $data['token'] .= '-destino';
        bal_post($db,$data,$amount,$actor);
        return 'Transferencia registrada en ambas cuentas.';
    }
    if ($action === 'pagar') {
        $obligation = bal_one($db,'SELECT * FROM fin_obligaciones WHERE id=? AND anulada=0',[$id]);
        if (!$obligation) { throw new InvalidArgumentException('Obligacion no disponible.'); }
        $pending = bal_cents($obligation['monto'])-bal_obligation_paid($db,$id,$obligation['naturaleza']);
        if ($amount > $pending) { throw new InvalidArgumentException('El abono supera el saldo pendiente.'); }
        $data += ['concepto'=>$obligation['concepto'],'naturaleza'=>$obligation['naturaleza'],'tipo_id'=>$obligation['tipo_id'],
            'tercero_id'=>$obligation['tercero_id'],'sendero_id'=>$obligation['sendero_id'] ?? null,'origen'=>'obligacion','origen_id'=>$id,'obligacion_id'=>$id];
    } elseif ($action === 'movimiento') {
        $type = bal_active($db,'fin_tipos',(int)($p['tipo_id'] ?? 0));
        $data += ['concepto'=>bal_text($p,'concepto',255),'naturaleza'=>$type['naturaleza'],'tipo_id'=>$type['id'],'origen'=>'manual'];
        if (!empty($p['tercero_id'])) { $data['tercero_id'] = bal_active($db,'fin_terceros',(int)$p['tercero_id'])['id']; }
    } else { throw new InvalidArgumentException('Operacion no valida.'); }
    if (!empty($p['metodo_pago_id'])) { $data['metodo_pago_id'] = bal_active($db,'contabilidad_metodo_pago',(int)$p['metodo_pago_id'])['id']; }
    bal_post($db,$data,$amount * ($data['naturaleza'] === 'salida' ? -1 : 1),$actor);
    return 'Movimiento confirmado.';
}

/**
 * Registrar gasto o ingreso (pestanas "Registrar gasto" / "Registrar ingreso").
 *   contado : sale o entra dinero de una cuenta ahora.
 *   credito : queda una cuenta por pagar / cobrar, con abono inicial opcional.
 *   abono   : se paga o cobra parte o todo de una cuenta por pagar / cobrar existente.
 * Todo ocurre dentro de la transaccion de proceso_balances.php: si algo falla, no queda nada.
 */
function bal_pesos(int $cents): string
{
    return 'RD$ ' . number_format($cents / 100, 2);
}

function bal_registrar(mysqli $db, array $p, int $actor): string
{
    $nature = (string)($p['naturaleza'] ?? '');
    if (!in_array($nature, ['ingreso','salida'], true)) { throw new InvalidArgumentException('Operacion no valida.'); }
    $mode = (string)($p['modo'] ?? '');
    if (!in_array($mode, ['contado','credito','abono'], true)) { throw new InvalidArgumentException('Elige como se ' . ($nature === 'salida' ? 'paga' : 'cobra') . '.'); }
    $token = (string)($p['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { throw new InvalidArgumentException('Recarga el formulario e intenta de nuevo.'); }
    if (bal_one($db, 'SELECT id FROM fin_movimientos WHERE token=?', [$token])) { return 'Esta operacion ya fue registrada.'; }
    $amount = bal_cents($p['monto'] ?? '');
    if ($amount <= 0) { throw new InvalidArgumentException('El monto debe ser mayor que cero.'); }
    $sign = $nature === 'salida' ? -1 : 1;
    $word = $nature === 'salida' ? 'Gasto' : 'Ingreso';
    $method = !empty($p['metodo_pago_id']) ? bal_active($db, 'contabilidad_metodo_pago', (int)$p['metodo_pago_id'])['id'] : null;
    $payment = static fn(array $extra): array => ['token' => $token, 'fecha' => bal_date((string)($p['fecha'] ?? '')),
        'cuenta_id' => (int)($p['cuenta_id'] ?? 0), 'metodo_pago_id' => $method,
        'referencia' => mb_substr(trim((string)($p['referencia'] ?? '')), 0, 180), 'naturaleza' => $nature] + $extra;

    if ($mode === 'abono') {
        $obligationId = (int)($p['obligacion_id'] ?? 0);
        $obligation = bal_one($db, 'SELECT * FROM fin_obligaciones WHERE id=? AND anulada=0 FOR UPDATE', [$obligationId]);
        if (!$obligation || $obligation['naturaleza'] !== $nature) {
            throw new InvalidArgumentException('Elige una cuenta por ' . ($nature === 'salida' ? 'pagar' : 'cobrar') . ' pendiente.');
        }
        $pending = bal_cents($obligation['monto']) - bal_obligation_paid($db, $obligationId, $nature);
        if ($amount > $pending) { throw new InvalidArgumentException('El abono supera lo pendiente (' . bal_pesos($pending) . ').'); }
        bal_post($db, $payment(['concepto' => $obligation['concepto'], 'tipo_id' => $obligation['tipo_id'], 'tercero_id' => $obligation['tercero_id'],
            'sendero_id' => $obligation['sendero_id'] ?? null, 'origen' => 'obligacion', 'origen_id' => $obligationId, 'obligacion_id' => $obligationId]), $amount * $sign, $actor);
        $left = $pending - $amount;
        return ($nature === 'salida' ? 'Pago' : 'Cobro') . ' registrado. ' . ($left > 0 ? 'Queda pendiente ' . bal_pesos($left) . '.' : 'La cuenta quedo saldada.');
    }

    $type = bal_active($db, 'fin_tipos', (int)($p['tipo_id'] ?? 0));
    if ($type['naturaleza'] !== $nature) { throw new InvalidArgumentException('El tipo no corresponde a un ' . strtolower($word) . '.'); }
    $third = bal_tercero_formulario($db, $p, $actor, false);
    $senderoRef = bal_sendero_referencia($db, $p);

    if ($mode === 'contado') {
        $reembolsado = $nature === 'salida' ? mb_substr(trim((string)($p['reembolsado_a'] ?? '')), 0, 160) : '';
        bal_post($db, $payment(['concepto' => bal_text($p, 'concepto', 255), 'tipo_id' => $type['id'], 'tercero_id' => $third['id'] ?? null, 'sendero_id' => $senderoRef, 'origen' => 'manual', 'reembolsado_a' => $reembolsado]), $amount * $sign, $actor);
        return $word . ' registrado en la cuenta.';
    }

    // credito: cuenta por pagar / cobrar, y si se indica, su primer abono.
    if (!$third) { throw new InvalidArgumentException('Elige la persona o entidad a la que ' . ($nature === 'salida' ? 'se le debe' : 'le corresponde pagar') . '.'); }
    $concept = bal_text($p, 'concepto', 200);
    $due = bal_date((string)($p['vencimiento'] ?? ''));
    $initial = bal_cents($p['abono_inicial'] ?? '0');
    if ($initial < 0 || $initial > $amount) { throw new InvalidArgumentException('El abono inicial no puede superar el monto.'); }
    $conSendero = bal_columna_lista($db, 'fin_obligaciones', 'sendero_id');
    bal_query($db, 'INSERT INTO fin_obligaciones (concepto,naturaleza,tercero_id,tipo_id,monto,vencimiento,creado_por'.($conSendero ? ',sendero_id' : '').') VALUES (?,?,?,?,?,?,?'.($conSendero ? ',?' : '').')',
        array_merge([$concept, $nature, $third['id'], $type['id'], bal_decimal($amount), $due, $actor], $conSendero ? [$senderoRef] : []));
    $obligationId = (int)$db->insert_id;
    bal_audit($db, $actor, 'crear', 'obligacion', $obligationId, [], ['concepto' => $concept, 'monto' => bal_decimal($amount), 'vencimiento' => $due, 'tercero_id' => $third['id'], 'tipo_id' => $type['id'], 'sendero_id' => $conSendero ? $senderoRef : null]);
    $label = 'cuenta por ' . ($nature === 'salida' ? 'pagar' : 'cobrar');
    if ($initial > 0) {
        bal_post($db, $payment(['concepto' => $concept, 'tipo_id' => $type['id'], 'tercero_id' => $third['id'], 'sendero_id' => $senderoRef,
            'origen' => 'obligacion', 'origen_id' => $obligationId, 'obligacion_id' => $obligationId]), $initial * $sign, $actor);
        $left = $amount - $initial;
        return ucfirst($label) . ' registrada con un abono de ' . bal_pesos($initial) . '. ' . ($left > 0 ? 'Queda pendiente ' . bal_pesos($left) . '.' : 'Quedo saldada.');
    }
    return ucfirst($label) . ' registrada por ' . bal_pesos($amount) . ', vence el ' . date('d/m/Y', strtotime($due)) . '.';
}

// Gastos e ingresos de Registrar gasto / ingreso que se pueden corregir o cancelar. Devuelve el motivo si no se puede.
// Los movimientos no se borran: cancelar deja el original y un reverso, y por eso dejan de sumar y restar.
function bal_movimiento_bloqueo(mysqli $db, array $m, bool $paraEditar): ?string
{
    if (!$m) { return 'El movimiento ya no existe.'; }
    if (!in_array($m['naturaleza'], ['ingreso', 'salida'], true) || !in_array($m['origen'], ['manual', 'obligacion'], true) || $m['reversa_id']) {
        return 'Este movimiento no se gestiona desde aqui.';
    }
    if (bal_one($db, 'SELECT id FROM fin_movimientos WHERE reversa_id=?', [$m['id']])) { return 'Este movimiento ya esta cancelado.'; }
    $tipo = bal_one($db, 'SELECT nombre FROM fin_tipos WHERE id=?', [(int) $m['tipo_id']]);
    if (($tipo['nombre'] ?? '') === 'Ajuste de saldo inicial') { return 'Los ajustes de saldo inicial se manejan desde Cuadres.'; }
    if ($paraEditar && $m['origen'] !== 'manual') { return 'Los abonos a cuentas por pagar o cobrar no se editan: cancela el abono y registralo de nuevo.'; }
    return null;
}

function bal_editar_movimiento(mysqli $db, array $p, int $actor): string
{
    $id = (int) ($p['id'] ?? 0);
    $original = bal_one($db, 'SELECT * FROM fin_movimientos WHERE id=? FOR UPDATE', [$id]);
    $bloqueo = bal_movimiento_bloqueo($db, $original, true);
    if ($bloqueo !== null) { throw new InvalidArgumentException($bloqueo); }
    $nature = $original['naturaleza'];
    $word = $nature === 'salida' ? 'Gasto' : 'Ingreso';
    $amount = bal_cents($p['monto'] ?? '');
    if ($amount <= 0) { throw new InvalidArgumentException('El monto debe ser mayor que cero.'); }

    // Si un dato del catalogo no cambia, se acepta aunque ahora este inactivo; si cambia, debe estar activo.
    $pick = static function (mysqli $db, string $table, int $nuevo, $actual): array {
        return $nuevo === (int) $actual ? bal_one($db, "SELECT * FROM $table WHERE id=?", [$nuevo]) : bal_active($db, $table, $nuevo);
    };
    $account = $pick($db, 'fin_cuentas', (int) ($p['cuenta_id'] ?? 0), $original['cuenta_id']);
    $type = $pick($db, 'fin_tipos', (int) ($p['tipo_id'] ?? 0), $original['tipo_id']);
    if (!$account || !$type) { throw new InvalidArgumentException('Selecciona una opcion activa del catalogo.'); }
    if ($type['naturaleza'] !== $nature) { throw new InvalidArgumentException('El tipo no corresponde a un ' . strtolower($word) . '.'); }
    $third = !empty($p['tercero_id']) ? $pick($db, 'fin_terceros', (int) $p['tercero_id'], $original['tercero_id']) : null;
    $method = !empty($p['metodo_pago_id']) ? $pick($db, 'contabilidad_metodo_pago', (int) $p['metodo_pago_id'], $original['metodo_pago_id']) : null;
    if ((!empty($p['tercero_id']) && !$third) || (!empty($p['metodo_pago_id']) && !$method)) { throw new InvalidArgumentException('Selecciona una opcion activa del catalogo.'); }

    $date = bal_date((string) ($p['fecha'] ?? ''));
    $cierre = bal_one($db, 'SELECT MAX(fecha) fecha FROM fin_cuadres WHERE cuenta_id=? AND cerrado=1', [$original['cuenta_id']]);
    if (!empty($cierre['fecha']) && $original['fecha'] <= $cierre['fecha']) {
        throw new InvalidArgumentException('Este ' . strtolower($word) . ' pertenece a un periodo ya cerrado y no se puede editar. Puedes cancelarlo y registrarlo de nuevo.');
    }
    bal_check_date($db, $account, $date);

    $nuevo = [
        'cuenta_id' => $account['id'], 'fecha' => $date, 'importe' => bal_decimal($amount * ($nature === 'salida' ? -1 : 1)),
        'concepto' => bal_text($p, 'concepto', 255), 'tipo_id' => $type['id'], 'tercero_id' => $third['id'] ?? null,
        'metodo_pago_id' => $method['id'] ?? null, 'referencia' => mb_substr(trim((string) ($p['referencia'] ?? '')), 0, 180),
        'sendero_id' => bal_sendero_referencia($db, $p),
    ];
    if (bal_movimientos_reembolso_listo($db) && $nature === 'salida') {
        $reembolsado = mb_substr(trim((string) ($p['reembolsado_a'] ?? '')), 0, 160);
        $nuevo['reembolsado_a'] = $reembolsado !== '' ? $reembolsado : null;
    }
    $antes = [];
    $despues = [];
    foreach ($nuevo as $campo => $valor) {
        if ((string) ($original[$campo] ?? '') !== (string) ($valor ?? '')) {
            $antes[$campo] = $original[$campo];
            $despues[$campo] = $valor;
        }
    }
    if (!$despues) { return 'No hubo cambios que guardar.'; }
    bal_query($db, 'UPDATE fin_movimientos SET ' . implode(',', array_map(static fn($campo) => "$campo=?", array_keys($nuevo))) . ' WHERE id=?',
        array_merge(array_values($nuevo), [$id]));
    bal_audit($db, $actor, 'editar', 'fin_movimientos', $id, $antes, $despues);
    return $word . ' #' . $id . ' actualizado.';
}

function bal_cancelar_movimiento(mysqli $db, array $p, int $actor): string
{
    $id = (int) ($p['id'] ?? 0);
    $original = bal_one($db, 'SELECT * FROM fin_movimientos WHERE id=? FOR UPDATE', [$id]);
    $bloqueo = bal_movimiento_bloqueo($db, $original, false);
    if ($bloqueo !== null) { throw new InvalidArgumentException($bloqueo); }
    $token = (string) ($p['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { throw new InvalidArgumentException('Recarga la pagina e intenta de nuevo.'); }

    // El reverso lleva la fecha del movimiento para que deje de contar tambien en saldos pasados.
    // Si ese periodo ya esta cerrado, se registra hoy.
    $account = bal_active($db, 'fin_cuentas', (int) $original['cuenta_id']);
    $fecha = $original['fecha'];
    $nota = '';
    try {
        bal_check_date($db, $account, $fecha);
    } catch (InvalidArgumentException $e) {
        $fecha = date('Y-m-d');
        $nota = ' Como su periodo esta cerrado, la cancelacion cuenta desde hoy.';
    }
    $entry = $original;
    $entry['token'] = $token;
    $entry['fecha'] = $fecha;
    $entry['reversa_id'] = $original['id'];
    $entry['concepto'] = 'Reverso #' . $original['id'] . ': Cancelado';
    bal_post($db, $entry, -bal_cents($original['importe']), $actor);
    return ($original['naturaleza'] === 'salida' ? 'Gasto' : 'Ingreso') . ' #' . $id . ' cancelado. Ya no suma ni resta en la cuenta; queda en el historial.' . $nota;
}
