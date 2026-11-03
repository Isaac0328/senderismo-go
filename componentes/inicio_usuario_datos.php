<?php
require_once __DIR__ . '/pasaporte_nivel.php';

function sg_inicio_perfil_completo(array $detalle): bool
{
    $requeridos = [
        'telefono', 'rango_edad', 'identificacion', 'grupo_sanguineo', 'enfermedad',
        'seguro_medico', 'experiencia_senderismo', 'via_entero', 'emergencia_nombre',
        'emergencia_parentesco', 'emergencia_telefono',
    ];
    foreach ($requeridos as $campo) {
        if (trim((string) ($detalle[$campo] ?? '')) === '') {
            return false;
        }
    }
    return (int) ($detalle['es_alergico'] ?? 0) !== 1 || trim((string) ($detalle['alergias_detalle'] ?? '')) !== '';
}

function sg_inicio_usuario(mysqli $conn, int $usuarioId): array
{
    $hoy = date('Y-m-d');
    $stmt = $conn->prepare('SELECT nombre FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $stmt = $conn->prepare('SELECT telefono, rango_edad, identificacion, es_alergico, alergias_detalle,
        grupo_sanguineo, enfermedad, seguro_medico, experiencia_senderismo, via_entero,
        emergencia_nombre, emergencia_parentesco, emergencia_telefono
        FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $detalleUsuario = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS rutas_asistidas,
        COALESCE(SUM(s.distancia_km), 0) AS km_asistidos,
        COUNT(DISTINCT NULLIF(TRIM(s.provincia), '')) AS provincias
        FROM registros_senderos rs JOIN senderos s ON s.id = rs.sendero_id
        WHERE rs.usuario_id = ? AND rs.estado = 'registrado' AND rs.asistio = 1");
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT s.id, s.nombre, s.fecha_sendero, s.lugar, s.provincia,
        s.imagen_principal, s.imagen_flyer, s.distancia_km,
        pe.nombre_punto, pe.hora_encuentro
        FROM registros_senderos rs JOIN senderos s ON s.id = rs.sendero_id
        LEFT JOIN sendero_puntos_encuentro pe ON pe.id = rs.sendero_punto_encuentro_id AND pe.sendero_id = s.id
        WHERE rs.usuario_id = ? AND rs.estado = 'registrado'
          AND s.activo = 1 AND s.estado = 'pendiente' AND s.fecha_sendero >= ?
        ORDER BY s.fecha_sendero, s.id LIMIT 1");
    $stmt->bind_param('is', $usuarioId, $hoy);
    $stmt->execute();
    $reserva = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Prices remain separate for adults and minors, including shared investments.
    $stmt = $conn->prepare("SELECT s.id, s.nombre, s.fecha_sendero, s.lugar, s.provincia,
        s.imagen_principal, s.imagen_flyer, s.distancia_km, nd.nombre AS dificultad,
        (SELECT MIN(i.monto) FROM sendero_inversiones i
         WHERE i.sendero_id = s.id AND i.activo = 1 AND i.es_menor = 0) AS precio_adulto,
        (SELECT MIN(CASE WHEN s.menores_misma_inversion = 1 THEN COALESCE(i.monto_menor, i.monto) ELSE i.monto END)
         FROM sendero_inversiones i WHERE i.sendero_id = s.id AND i.activo = 1
         AND i.es_menor = CASE WHEN s.menores_misma_inversion = 1 THEN 0 ELSE 1 END) AS precio_menor,
        EXISTS(SELECT 1 FROM registros_senderos rs WHERE rs.sendero_id = s.id
          AND rs.usuario_id = ? AND rs.estado = 'registrado') AS reservado
        FROM senderos s LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
        WHERE s.activo = 1 AND s.estado = 'pendiente' AND s.fecha_sendero >= ?
        ORDER BY s.fecha_sendero, s.id LIMIT 6");
    $stmt->bind_param('is', $usuarioId, $hoy);
    $stmt->execute();
    $senderos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT s.id, s.nombre, s.fecha_sendero, s.lugar, s.provincia,
        s.imagen_principal, s.imagen_flyer, s.distancia_km, nd.nombre AS dificultad,
        EXISTS(SELECT 1 FROM registros_senderos rs WHERE rs.sendero_id = s.id
          AND rs.usuario_id = ? AND rs.estado = 'registrado' AND rs.asistio = 1) AS completado
        FROM senderos s LEFT JOIN niveles_dificultad nd ON nd.id = s.nivel_dificultad_id
        WHERE s.activo = 1 AND (s.estado = 'visitado' OR s.fecha_sendero < ?)
        ORDER BY s.fecha_sendero DESC, s.id DESC");
    $stmt->bind_param('is', $usuarioId, $hoy);
    $stmt->execute();
    $visitados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $historial = array_values(array_filter($visitados, static fn(array $ruta): bool => (bool) $ruta['completado']));
    $descubrir = array_values(array_filter($visitados, static fn(array $ruta): bool => !(bool) $ruta['completado']));
    $niveles = $conn->query('SELECT * FROM pasaporte_niveles WHERE activo = 1
        ORDER BY min_senderos, min_km, orden, id')->fetch_all(MYSQLI_ASSOC);

    return [
        'nombre' => trim((string) ($usuario['nombre'] ?? '')) ?: 'senderista',
        'perfil_completo' => sg_inicio_perfil_completo($detalleUsuario),
        'stats' => $stats,
        'nivel' => sg_pasaporte_calcular_nivel($niveles, (int) $stats['rutas_asistidas'], (float) $stats['km_asistidos']),
        'niveles' => $niveles,
        'reserva' => $reserva,
        'senderos' => $senderos,
        'historial' => $historial,
        'descubrir' => $descubrir,
        'inspiracion' => array_slice($descubrir ?: $visitados, 0, 4),
    ];
}

function sg_inicio_fecha(string $fecha): string
{
    $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
        'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dia = new DateTimeImmutable($fecha);
    return $dia->format('j') . ' de ' . $meses[(int) $dia->format('n')] . ' de ' . $dia->format('Y');
}

function sg_inicio_imagen(array $sendero): string
{
    foreach (['imagen_principal', 'imagen_flyer'] as $campo) {
        $ruta = trim((string) ($sendero[$campo] ?? ''));
        if ($ruta !== '' && is_file(__DIR__ . '/../' . ltrim($ruta, '/'))) {
            return BASE_URL . ltrim($ruta, '/');
        }
    }
    return BASE_URL . 'imagenes/paisajes/hero.jpg';
}
