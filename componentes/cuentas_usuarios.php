<?php
require_once __DIR__ . '/helpers.php';

function sgcu_query(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { throw new RuntimeException('No se pudo preparar la consulta del estado de cuenta.'); }
    try {
        if ($types !== '') { mysqli_stmt_bind_param($stmt, $types, ...$params); }
        if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException('No se pudo consultar el estado de cuenta.'); }
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    } finally { mysqli_stmt_close($stmt); }
}

function sgcu_filters(array $input): array
{
    $f = ['desde' => trim((string) ($input['desde'] ?? '')), 'hasta' => trim((string) ($input['hasta'] ?? '')),
        'criterio' => (string) ($input['criterio'] ?? 'sendero'), 'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 120),
        'situacion' => (string) ($input['situacion'] ?? 'todos'), 'tipo' => (string) ($input['tipo'] ?? 'todos'),
        'cuenta' => (string) ($input['cuenta'] ?? ''), 'pagina' => max(1, (int) ($input['pagina'] ?? 1)),
        'orden' => (string) ($input['orden'] ?? 'pendiente'), 'dir' => (string) ($input['dir'] ?? 'desc')];
    foreach (['desde', 'hasta'] as $key) {
        $date = $f[$key] === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $f[$key]);
        if ($f[$key] !== '' && (!$date || $date->format('Y-m-d') !== $f[$key])) {
            throw new InvalidArgumentException('La fecha indicada no es valida.');
        }
    }
    if ($f['desde'] !== '' && $f['hasta'] !== '' && $f['desde'] > $f['hasta']) {
        throw new InvalidArgumentException('La fecha desde debe ser anterior o igual a la fecha hasta.');
    }
    if (!in_array($f['criterio'], ['sendero', 'pago'], true)
        || !isset(sgcu_sort_fields()[$f['orden']]) || !in_array($f['dir'], ['asc', 'desc'], true)
        || !in_array($f['situacion'], ['todos', 'pendiente', 'credito', 'tuvo_credito', 'descuento'], true)
        || !in_array($f['tipo'], ['todos', 'registrado', 'temporal'], true)
        || ($f['cuenta'] !== '' && !preg_match('/^[ump][1-9][0-9]*$/', $f['cuenta']))) {
        throw new InvalidArgumentException('Los filtros no son validos.');
    }
    return $f;
}

// Columnas por las que se puede ordenar la lista de cuentas.
function sgcu_sort_fields(): array
{
    return ['nombre' => 'Participante', 'senderos' => 'Senderos', 'pagado' => 'Pagado', 'descuento' => 'Descuentos',
        'aplicado' => 'Crédito aplicado', 'generado' => 'Crédito generado', 'pendiente' => 'Pendiente actual', 'credito' => 'Crédito actual'];
}

function sgcu_cents($amount): int { return (int) round((float) $amount * 100); }
function sgcu_money(int $cents): string { return 'RD$ ' . number_format($cents / 100, 2); }
function sgcu_h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function sgcu_fold(string $value): string
{
    return strtr(mb_strtolower($value, 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}
function sgcu_in_period(?string $date, array $f): bool
{
    if ($f['desde'] === '' && $f['hasta'] === '') { return true; }
    if (!$date) { return false; }
    $date = substr($date, 0, 10);
    return ($f['desde'] === '' || $date >= $f['desde']) && ($f['hasta'] === '' || $date <= $f['hasta']);
}
function sgcu_empty_totals(): array
{
    return array_fill_keys(['inversion', 'esperado', 'pagado', 'descuento', 'aplicado', 'generado', 'retenido', 'ingreso', 'balance'], 0);
}

function sgcu_registration(array $row): array
{
    // Con cuenta: 'u'. Sin cuenta con ficha: 'p' (todas sus inscripciones juntas). Sin ficha: 'm' por inscripcion.
    $row['key'] = (int) $row['usuario_id'] > 0 ? 'u' . $row['usuario_id']
        : ((int) ($row['persona_id'] ?? 0) > 0 ? 'p' . $row['persona_id'] : 'm' . $row['registro_id']);
    $values = sgcu_empty_totals();
    $values['inversion'] = sgcu_cents($row['inversion_base']);
    $base = $row['monto_esperado'] === null ? $values['inversion'] : sgcu_cents($row['monto_esperado']);
    $values['pagado'] = (int) $row['pagado'] === 1 ? sgcu_cents($row['monto_pagado']) : 0;
    $values['descuento'] = sgcu_cents($row['descuento_autorizado']);
    $values['aplicado'] = sgcu_cents($row['credito_aplicado']);
    $values['generado'] = sgcu_cents($row['credito_generado']);
    $row['estado'] = in_array($row['estado_financiero'], ['cortesia', 'exento'], true) ? $row['estado_financiero']
        : sg_financial_status_derive((int) $row['asistio'] === 1, (int) $row['pagado'] === 1,
            $base / 100, $values['pagado'] / 100, $values['aplicado'] / 100, $values['descuento'] / 100, $row['fecha_sendero']);
    $excepcion = in_array($row['estado'], ['cortesia', 'exento'], true);
    $cancelado = $row['registro_estado'] === 'cancelado';
    $values['esperado'] = $excepcion || $cancelado ? 0 : $base - $values['descuento'];
    $values['balance'] = $excepcion || $cancelado || $row['estado'] === 'no_asistio_sin_pago' ? 0
        : $base - $values['descuento'] - $values['pagado'] - $values['aplicado'];
    $values['retenido'] = max(0, $values['pagado'] - $values['generado']);
    $values['ingreso'] = $values['retenido'] + $values['aplicado'];
    $row['values'] = $values;
    return $row;
}

function sgcu_load(mysqli $conn, array $f): array
{
    $accounts = [];
    $makeAccount = static function (array $row, string $key, string $tipo): array {
        return ['key' => $key, 'id' => (int) $row['id'], 'tipo' => $tipo,
            'nombre' => trim($row['nombre'] . ' ' . $row['apellido']), 'usuario' => $row['usuario'] ?? '',
            'email' => $row['email'] ?? '', 'telefono' => $row['telefono'] ?? '',
            'totals' => sgcu_empty_totals(), 'pendiente_actual' => 0, 'pendiente_proximo' => 0, 'credito_actual' => 0,
            'tuvo_credito' => false, 'inscripciones' => 0, 'actividad' => 0];
    };
    foreach (sgcu_query($conn, "SELECT u.id, u.nombre, u.apellido, u.user AS usuario, u.email, COALESCE(d.telefono, '') AS telefono
        FROM usuarios u LEFT JOIN detalles_usuarios d ON d.usuario_id = u.id") as $user) {
        $accounts['u' . $user['id']] = $makeAccount($user, 'u' . $user['id'], 'registrado');
    }
    $conFicha = function_exists('psc_listo') ? psc_listo($conn) : (bool) mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'personas_sin_cuenta'"));
    $rows = sgcu_query($conn, "SELECT rs.id AS registro_id, COALESCE(rs.usuario_id, 0) AS usuario_id,
        " . ($conFicha ? 'COALESCE(rs.persona_sin_cuenta_id, 0)' : '0') . " AS persona_id,
        rs.manual_nombre, rs.manual_apellido, rs.manual_email, rs.manual_telefono,
        rs.estado AS registro_estado, rs.asistio, s.id AS sendero_id, s.nombre AS sendero, s.fecha_sendero,
        COALESCE(si.nombre, 'Sin inversion') AS inversion_nombre,
        COALESCE(si.monto, 0) + COALESCE(m.monto, 0) AS inversion_base, COALESCE(m.menores, 0) AS menores,
        p.monto_esperado, COALESCE(p.pagado, 0) AS pagado, COALESCE(p.monto_pagado, 0) AS monto_pagado,
        COALESCE(p.descuento_autorizado, 0) AS descuento_autorizado, COALESCE(p.credito_aplicado, 0) AS credito_aplicado,
        COALESCE(p.credito_generado, 0) AS credito_generado, COALESCE(p.estado_financiero, '') AS estado_financiero,
        p.fecha_pago, p.credito_id, COALESCE(p.nota, '') AS nota, COALESCE(mp.nombre, p.metodo_pago, '') AS metodo
        FROM registros_senderos rs JOIN senderos s ON s.id = rs.sendero_id
        LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
        LEFT JOIN (SELECT rm.registro_id, COUNT(*) AS menores, SUM(CASE WHEN COALESCE(mi.es_menor, 0) = 1 THEN mi.monto ELSE COALESCE(mi.monto_menor, mi.monto) END) AS monto FROM registro_sendero_menores rm
            LEFT JOIN sendero_inversiones mi ON mi.id = rm.inversion_id GROUP BY rm.registro_id) m ON m.registro_id = rs.id
        LEFT JOIN contabilidad_registro_pagos p ON p.registro_id = rs.id
        LEFT JOIN contabilidad_metodo_pago mp ON mp.id = p.metodo_pago_id
        ORDER BY s.fecha_sendero DESC, rs.id DESC");
    $history = [];
    $today = date('Y-m-d');
    foreach ($rows as $raw) {
        $row = sgcu_registration($raw);
        $key = $row['key'];
        if (!isset($accounts[$key])) {
            $accounts[$key] = $makeAccount(['id' => $key[0] === 'p' ? $row['persona_id'] : $row['registro_id'], 'nombre' => $row['manual_nombre'] ?: 'Asistente',
                'apellido' => $row['manual_apellido'] ?? '', 'email' => $row['manual_email'] ?? '', 'telefono' => $row['manual_telefono'] ?? ''], $key, 'temporal');
        }
        $accounts[$key]['actividad']++;
        $accounts[$key]['pendiente_actual'] += max(0, $row['values']['balance']);
        // Lo pendiente de un sendero que aun no se realiza no es deuda vencida: se informa aparte.
        if ($row['values']['balance'] > 0 && (string) $row['fecha_sendero'] >= $today) {
            $accounts[$key]['pendiente_proximo'] += $row['values']['balance'];
        }
        if (sgcu_in_period($f['criterio'] === 'pago' ? $row['fecha_pago'] : $row['fecha_sendero'], $f)) {
            foreach ($row['values'] as $field => $value) { $accounts[$key]['totals'][$field] += $value; }
            $accounts[$key]['inscripciones']++;
            $history[] = $row;
        }
    }
    $credits = sgcu_query($conn, "SELECT c.*, s.nombre AS origen FROM usuario_creditos c
        LEFT JOIN senderos s ON s.id = c.sendero_origen_id ORDER BY c.created_at DESC, c.id DESC");
    // El credito es del usuario o, si no tiene cuenta, de su ficha de persona sin cuenta.
    $creditKey = static fn(array $c): string => !empty($c['usuario_id']) ? 'u' . $c['usuario_id'] : 'p' . (int) ($c['persona_sin_cuenta_id'] ?? 0);
    foreach ($credits as $credit) {
        $key = $creditKey($credit);
        if (!isset($accounts[$key])) { continue; }
        $accounts[$key]['actividad']++;
        $accounts[$key]['tuvo_credito'] = true;
        if ($credit['estado'] === 'activo') { $accounts[$key]['credito_actual'] += max(0, sgcu_cents($credit['saldo_disponible'])); }
    }
    $selected = $f['cuenta'] === '' ? null : ($accounts[$f['cuenta']] ?? null);
    if ($f['cuenta'] !== '' && !$selected) { throw new InvalidArgumentException('La cuenta seleccionada no existe.'); }
    $search = sgcu_fold($f['q']);
    $visible = array_filter($accounts, static function (array $a) use ($f, $search): bool {
        if ($a['actividad'] === 0 || ($f['cuenta'] !== '' && $a['key'] !== $f['cuenta'])) { return false; }
        if ($f['tipo'] !== 'todos' && $a['tipo'] !== $f['tipo']) { return false; }
        if ($search !== '' && !str_contains(sgcu_fold($a['nombre'] . ' ' . $a['usuario'] . ' ' . $a['email'] . ' ' . $a['telefono']), $search)) { return false; }
        return match ($f['situacion']) {
            'pendiente' => $a['pendiente_actual'] > 0,
            'credito' => $a['credito_actual'] > 0,
            'tuvo_credito' => $a['tuvo_credito'],
            'descuento' => $a['totals']['descuento'] > 0,
            default => true,
        };
    });
    $sortValue = static fn(array $a): int => match ($f['orden']) {
        'senderos' => $a['inscripciones'], 'pendiente' => $a['pendiente_actual'], 'credito' => $a['credito_actual'],
        default => $a['totals'][$f['orden']] ?? 0,
    };
    $direction = $f['dir'] === 'asc' ? 1 : -1;
    uasort($visible, static function (array $a, array $b) use ($f, $sortValue, $direction): int {
        $byName = strnatcasecmp($a['nombre'], $b['nombre']);
        return $f['orden'] === 'nombre' ? $direction * $byName : ($direction * ($sortValue($a) <=> $sortValue($b)) ?: $byName);
    });
    $history = array_values(array_filter($history, static fn(array $row): bool => isset($visible[$row['key']])));
    $credits = array_values(array_filter($credits, static fn(array $row): bool => isset($visible[$creditKey($row)])));
    $movements = [];
    // Movimientos de credito de la cuenta, o de la ficha si es una persona sin cuenta con credito.
    $creditOwnerColumn = !$selected ? '' : ($selected['tipo'] === 'registrado' ? 'c.usuario_id'
        : (str_starts_with((string) $selected['key'], 'p') && $selected['tuvo_credito'] ? 'c.persona_sin_cuenta_id' : ''));
    if ($creditOwnerColumn !== '') {
        $movements = sgcu_query($conn, "SELECT m.*, c.estado AS credito_estado, c.usuario_id, c.sendero_origen_id,
            so.nombre AS origen, sd.nombre AS destino, CONCAT_WS(' ', u.nombre, u.apellido) AS responsable
            FROM usuario_credito_movimientos m JOIN usuario_creditos c ON c.id = m.credito_id
            LEFT JOIN senderos so ON so.id = c.sendero_origen_id LEFT JOIN senderos sd ON sd.id = m.sendero_id
            LEFT JOIN usuarios u ON u.id = m.creado_por WHERE {$creditOwnerColumn} = ? ORDER BY m.created_at DESC, m.id DESC", 'i', [$selected['id']]);
        $movements = array_values(array_filter($movements, static fn(array $row): bool => sgcu_in_period($row['created_at'], $f)));
    }
    // La aplicacion vigente viene del pago; el log puede contener guardados repetidos.
    $applicationOwner = $creditOwnerColumn === 'c.persona_sin_cuenta_id' ? 'rs.persona_sin_cuenta_id' : 'rs.usuario_id';
    $applications = $creditOwnerColumn !== '' ? sgcu_query($conn, "SELECT p.registro_id, p.sendero_id,
        p.credito_id, p.credito_aplicado, p.fecha_pago, s.nombre AS destino, s.fecha_sendero,
        so.nombre AS origen, c.sendero_origen_id FROM contabilidad_registro_pagos p
        JOIN registros_senderos rs ON rs.id = p.registro_id JOIN senderos s ON s.id = p.sendero_id
        LEFT JOIN usuario_creditos c ON c.id = p.credito_id LEFT JOIN senderos so ON so.id = c.sendero_origen_id
        WHERE {$applicationOwner} = ? AND p.credito_aplicado > 0 ORDER BY s.fecha_sendero DESC, p.registro_id DESC", 'i', [$selected['id']]) : [];
    $totals = sgcu_empty_totals(); $current = ['pendiente' => 0, 'proximo' => 0, 'credito' => 0];
    foreach ($visible as $account) {
        foreach ($totals as $field => $_) { $totals[$field] += $account['totals'][$field]; }
        $current['pendiente'] += $account['pendiente_actual']; $current['proximo'] += $account['pendiente_proximo']; $current['credito'] += $account['credito_actual'];
    }
    $participants = [];
    foreach ($accounts as $account) {
        if ($account['actividad'] === 0) { continue; }
        $participants[] = array_intersect_key($account, array_flip(['key', 'id', 'nombre', 'usuario', 'email', 'telefono', 'tipo']));
    }
    usort($participants, static fn(array $a, array $b): int => strnatcasecmp(sgcu_fold($a['nombre']), sgcu_fold($b['nombre'])));
    return ['participants' => $participants, 'accounts' => $visible, 'selected' => $selected, 'history' => $history, 'credits' => $credits,
        'movements' => $movements, 'applications' => $applications, 'totals' => $totals, 'current' => $current];
}
