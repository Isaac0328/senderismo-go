<?php

require_once __DIR__ . '/encuestas_usuario.php';
require_once __DIR__ . '/pasaporte_nivel.php';

function sg_notificaciones_permisos(mysqli $conn): array
{
    $relevantes = ['usuarios.usuarios', 'operaciones.asistencia', 'operaciones.encuestas', 'finanzas.balances'];
    $rolId = (int) ($_SESSION['usuario_rol_id'] ?? 0);
    return sg_is_admin_role($rolId) ? $relevantes : array_values(array_intersect($relevantes, sg_session_permissions($conn)));
}

function sg_notificaciones_tabla(mysqli $conn, string $tabla): bool
{
    static $cache = [];
    $key = spl_object_id($conn) . ':' . $tabla;
    if (!array_key_exists($key, $cache)) {
        $stmt = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->bind_param('s', $tabla);
        $stmt->execute();
        $cache[$key] = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
    }
    return $cache[$key];
}

function sg_notificaciones_tipos(): array
{
    return [
        'encuesta' => ['Encuesta pendiente', 'Encuestas enviadas que el usuario aun no responde.', 'usuario', true],
        'sendero' => ['Sendero proximo', 'Rutas inscritas que se realizaran en los proximos siete dias.', 'usuario', true],
        'pago' => ['Saldo pendiente', 'Pagos pendientes de una inscripcion.', 'usuario', true],
        'pasaporte' => ['Nivel alcanzado', 'Nuevo nivel del pasaporte senderista.', 'usuario', false],
        'admin:usuario' => ['Usuario nuevo', 'Usuarios registrados recientemente.', 'administracion', false],
        'admin:asistencia' => ['Asistencia sin revisar', 'Senderos recientes con asistencia pendiente de marcar.', 'administracion', true],
        'admin:encuesta' => ['Respuestas de encuesta', 'Encuestas con respuestas recientes.', 'administracion', false],
        'admin:obligacion' => ['Obligacion vencida', 'Cuentas por cobrar o pagar vencidas.', 'administracion', true],
        'admin:asignaciones' => ['Movimientos sin asignar', 'Cobros o gastos de senderos sin asignar a una cuenta.', 'administracion', true],
    ];
}

function sg_notificaciones_configuracion(mysqli $conn): array
{
    $config = [];
    foreach (sg_notificaciones_tipos() as $codigo => $tipo) {
        $config[$codigo] = ['activo' => true, 'importante' => $tipo[3]];
    }
    if (sg_notificaciones_tabla($conn, 'notificacion_tipos')) {
        $res = $conn->query('SELECT codigo, activo, importante FROM notificacion_tipos');
        while ($row = $res->fetch_assoc()) {
            if (isset($config[$row['codigo']])) {
                $config[$row['codigo']] = ['activo' => (bool) $row['activo'], 'importante' => (bool) $row['importante']];
            }
        }
    }
    return $config;
}

function sg_notificaciones_agregar(array &$items, string $clave, string $titulo, string $detalle, string $url, string $icono, bool $importante, string $fecha): void
{
    $items[] = compact('clave', 'titulo', 'detalle', 'url', 'icono', 'importante', 'fecha');
}

function sg_notificaciones_usuario(mysqli $conn, int $usuarioId): array
{
    $items = [];
    $encuestas = sg_encuestas_usuario_resumen($conn, $usuarioId, 8);
    foreach ($encuestas['items'] as $envio) {
        sg_notificaciones_agregar($items, 'encuesta:' . (int) $envio['envio_id'], 'Encuesta pendiente', (string) $envio['titulo'],
            BASE_URL . 'encuesta?envio_id=' . (int) $envio['envio_id'], 'clipboard', true, (string) ($envio['fecha_cierre'] ?? ''));
    }

    $stmt = $conn->prepare("SELECT s.id, s.nombre, s.fecha_sendero, pe.nombre_punto
        FROM registros_senderos rs JOIN senderos s ON s.id = rs.sendero_id
        LEFT JOIN sendero_puntos_encuentro pe ON pe.id = rs.sendero_punto_encuentro_id AND pe.sendero_id = s.id
        WHERE rs.usuario_id = ? AND rs.estado = 'registrado' AND s.activo = 1 AND s.estado = 'pendiente'
          AND s.fecha_sendero BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY s.fecha_sendero, s.id LIMIT 8");
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $ruta) {
        $fecha = (string) $ruta['fecha_sendero'];
        $detalle = 'Fecha: ' . date('d/m/Y', strtotime($fecha));
        if (!empty($ruta['nombre_punto'])) $detalle .= ' · Punto: ' . $ruta['nombre_punto'];
        sg_notificaciones_agregar($items, 'sendero:' . (int) $ruta['id'] . ':' . $fecha . ':' . substr(sha1((string) ($ruta['nombre_punto'] ?? '')), 0, 10),
            'Tu sendero se acerca', $ruta['nombre'] . ' · ' . $detalle,
            BASE_URL . 'sendero?id=' . (int) $ruta['id'], 'calendar', true, $fecha);
    }
    $stmt->close();

    if (sg_notificaciones_tabla($conn, 'contabilidad_registro_pagos')) {
        $stmt = $conn->prepare("SELECT rs.id, s.id sendero_id, s.nombre, s.estado sendero_estado, s.fecha_sendero,
                crp.saldo_pendiente, crp.updated_at
            FROM registros_senderos rs JOIN senderos s ON s.id = rs.sendero_id
            JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
            WHERE rs.usuario_id = ? AND rs.estado = 'registrado' AND crp.saldo_pendiente > 0
              AND crp.estado_financiero IN ('pendiente', 'parcial', 'deuda')
            ORDER BY crp.updated_at DESC LIMIT 8");
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $pago) {
            $url = $pago['sendero_estado'] === 'pendiente' && $pago['fecha_sendero'] >= date('Y-m-d')
                ? BASE_URL . 'registro-sendero?id=' . (int) $pago['sendero_id']
                : BASE_URL . 'sendero?id=' . (int) $pago['sendero_id'];
            sg_notificaciones_agregar($items, 'pago:' . (int) $pago['id'] . ':' . (int) round((float) $pago['saldo_pendiente'] * 100),
                'Saldo pendiente', $pago['nombre'] . ' · RD$ ' . number_format((float) $pago['saldo_pendiente'], 2),
                $url, 'credit-card', true, (string) $pago['updated_at']);
        }
        $stmt->close();
    }

    if (sg_notificaciones_tabla($conn, 'pasaporte_niveles')) {
        $stmt = $conn->prepare("SELECT COUNT(*) rutas, COALESCE(SUM(s.distancia_km),0) km
            FROM registros_senderos rs JOIN senderos s ON s.id = rs.sendero_id
            WHERE rs.usuario_id = ? AND rs.estado = 'registrado' AND rs.asistio = 1");
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc() ?: ['rutas' => 0, 'km' => 0];
        $stmt->close();
        $niveles = $conn->query('SELECT id,nombre,min_senderos,min_km FROM pasaporte_niveles WHERE activo = 1 ORDER BY min_senderos,min_km,orden,id')->fetch_all(MYSQLI_ASSOC);
        $nivel = null;
        foreach ($niveles as $candidato) {
            if ((int) $stats['rutas'] >= (int) $candidato['min_senderos'] && (float) $stats['km'] >= (float) $candidato['min_km']) $nivel = $candidato;
        }
        if ($nivel && (int) $nivel['min_senderos'] > 0) {
            sg_notificaciones_agregar($items, 'pasaporte:' . (int) $nivel['id'], 'Nivel alcanzado: ' . $nivel['nombre'],
                'Llevas ' . (int) $stats['rutas'] . ' senderos y ' . number_format((float) $stats['km'], 1) . ' km.',
                BASE_URL . 'mi-perfil#pasaporte', 'award', false, date('Y-m-d'));
        }
    }
    return $items;
}

function sg_notificaciones_admin(mysqli $conn, array $permisos): array
{
    $items = [];
    if (in_array('usuarios.usuarios', $permisos, true)) {
        $res = $conn->query("SELECT id,nombre,apellido,created_at FROM usuarios WHERE created_at >= NOW() - INTERVAL 3 DAY ORDER BY created_at DESC LIMIT 5");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $usuario) {
            sg_notificaciones_agregar($items, 'admin:usuario:' . (int) $usuario['id'], 'Usuario nuevo', trim($usuario['nombre'] . ' ' . $usuario['apellido']),
                BASE_URL . 'admin/usuarios', 'user-plus', false, $usuario['created_at']);
        }
    }
    if (in_array('operaciones.asistencia', $permisos, true)) {
        $res = $conn->query("SELECT s.id,s.nombre,s.fecha_sendero,COUNT(*) pendientes
            FROM senderos s JOIN registros_senderos rs ON rs.sendero_id=s.id AND rs.estado='registrado'
            WHERE s.fecha_sendero BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE() AND rs.asistio=0
            GROUP BY s.id ORDER BY s.fecha_sendero DESC LIMIT 5");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $ruta) {
            sg_notificaciones_agregar($items, 'admin:asistencia:' . (int) $ruta['id'] . ':' . $ruta['fecha_sendero'],
                'Revisar asistencia', $ruta['nombre'] . ' · ' . (int) $ruta['pendientes'] . ' sin marcar',
                BASE_URL . 'admin/asistencia-senderos?sendero_id=' . (int) $ruta['id'], 'check-square', true, $ruta['fecha_sendero']);
        }
    }
    if (in_array('operaciones.encuestas', $permisos, true) && sg_notificaciones_tabla($conn, 'encuesta_envios')) {
        $res = $conn->query("SELECT e.id,e.titulo,COUNT(*) respuestas,MAX(ee.respondido_at) ultima
            FROM encuesta_envios ee JOIN encuestas e ON e.id=ee.encuesta_id
            WHERE ee.estado='respondida' AND ee.respondido_at >= NOW() - INTERVAL 7 DAY
            GROUP BY e.id ORDER BY ultima DESC LIMIT 5");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $encuesta) {
            sg_notificaciones_agregar($items, 'admin:encuesta:' . (int) $encuesta['id'] . ':' . strtotime($encuesta['ultima']),
                'Respuestas recientes', $encuesta['titulo'] . ' · ' . (int) $encuesta['respuestas'] . ' en 7 dias',
                BASE_URL . 'admin/encuestas?vista=consultar', 'message-square', false, $encuesta['ultima']);
        }
    }
    if (in_array('finanzas.balances', $permisos, true) && sg_notificaciones_tabla($conn, 'fin_obligaciones')) {
        $res = $conn->query("SELECT o.id,o.concepto,o.naturaleza,o.vencimiento,o.monto,
                COALESCE(SUM(m.importe),0) abonado
            FROM fin_obligaciones o LEFT JOIN fin_movimientos m ON m.obligacion_id=o.id
            WHERE o.anulada=0 AND o.vencimiento < CURDATE()
            GROUP BY o.id HAVING o.monto > ABS(abonado)
            ORDER BY o.vencimiento DESC LIMIT 8");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $cuenta) {
            $cobrar = $cuenta['naturaleza'] === 'ingreso';
            sg_notificaciones_agregar($items, 'admin:obligacion:' . (int) $cuenta['id'], $cobrar ? 'Cobro vencido' : 'Pago vencido',
                $cuenta['concepto'] . ' · RD$ ' . number_format((float) $cuenta['monto'] - abs((float) $cuenta['abonado']), 2),
                BASE_URL . 'admin/balances?tab=' . ($cobrar ? 'cobrar' : 'pagar'), 'alert-circle', true, $cuenta['vencimiento']);
        }
        if (sg_notificaciones_tabla($conn, 'contabilidad_registro_pagos') && sg_notificaciones_tabla($conn, 'contabilidad_sendero_gastos')) {
            $res = $conn->query("SELECT COUNT(*) total FROM (
                SELECT p.id FROM contabilidad_registro_pagos p
                LEFT JOIN (SELECT origen_id,SUM(importe) total FROM fin_movimientos WHERE origen='cobro_sendero' GROUP BY origen_id) m ON m.origen_id=p.id
                WHERE p.monto_pagado <> COALESCE(m.total,0)
                UNION ALL
                SELECT g.id FROM contabilidad_sendero_gastos g
                LEFT JOIN (SELECT origen_id,SUM(importe) total FROM fin_movimientos WHERE origen='gasto_sendero' GROUP BY origen_id) m ON m.origen_id=g.id
                WHERE g.total <> -COALESCE(m.total,0)
            ) pendientes");
            $total = (int) ($res->fetch_assoc()['total'] ?? 0);
            if ($total > 0) {
                sg_notificaciones_agregar($items, 'admin:asignaciones:' . $total, 'Movimientos sin asignar',
                    $total . ' cobros o gastos de senderos pendientes de asignar a cuentas.',
                    BASE_URL . 'admin/balances?tab=senderos', 'layers', true, date('Y-m-d H:i:s'));
            }
        }
    }
    return $items;
}

function sg_notificaciones_resumen(mysqli $conn, int $usuarioId, array $permisos = []): array
{
    $items = array_merge(sg_notificaciones_usuario($conn, $usuarioId), sg_notificaciones_admin($conn, $permisos));
    $config = sg_notificaciones_configuracion($conn);
    $visibles = [];
    foreach ($items as $item) {
        foreach ($config as $codigo => $ajustes) {
            if ($item['clave'] === $codigo || str_starts_with($item['clave'], $codigo . ':')) {
                $item['importante'] = $ajustes['importante'];
                if ($ajustes['activo']) $visibles[] = $item;
                continue 2;
            }
        }
        $visibles[] = $item;
    }
    $items = $visibles;
    usort($items, static fn(array $a, array $b): int => strcmp($b['fecha'], $a['fecha']));
    $ready = sg_notificaciones_tabla($conn, 'usuario_notificacion_lecturas');
    $lecturas = [];
    if ($ready && $items) {
        $stmt = $conn->prepare('SELECT clave FROM usuario_notificacion_lecturas WHERE usuario_id = ?');
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $lecturas[$row['clave']] = true;
        $stmt->close();
    }
    $noLeidas = 0;
    foreach ($items as &$item) {
        $item['leida'] = isset($lecturas[$item['clave']]);
        if (!$item['leida']) $noLeidas++;
    }
    unset($item);
    return ['items' => $items, 'no_leidas' => $noLeidas, 'ready' => $ready];
}
