<?php

function perfil_nivel_senderista(mysqli $conn, int $asistidas, float $kmAcumulados): array
{
    $niveles = $conn->query('SELECT * FROM pasaporte_niveles WHERE activo = 1
        ORDER BY min_senderos, min_km, orden, id')->fetch_all(MYSQLI_ASSOC);
    return sg_pasaporte_calcular_nivel($niveles, $asistidas, $kmAcumulados);
}

function sg_pasaporte_calcular_nivel(array $niveles, int $asistidas, float $kmAcumulados): array
{
    $actual = ['nombre' => 'Explorador inicial', 'descripcion' => 'Tu aventura apenas esta tomando forma.',
        'icono' => 'compass', 'color' => '#0f7a3f', 'min_senderos' => 0, 'min_km' => 0];
    $siguiente = null;
    foreach ($niveles as $nivel) {
        if ($asistidas >= (int) $nivel['min_senderos'] && $kmAcumulados >= (float) $nivel['min_km']) {
            $actual = $nivel;
        }
    }
    foreach ($niveles as $nivel) {
        $superaActual = (int) $nivel['min_senderos'] > (int) $actual['min_senderos']
            || (float) $nivel['min_km'] > (float) $actual['min_km'];
        if ($superaActual && ($asistidas < (int) $nivel['min_senderos'] || $kmAcumulados < (float) $nivel['min_km'])) {
            $siguiente = $nivel;
            break;
        }
    }

    $progreso = 100;
    if ($siguiente) {
        // A requirement already met must not block progress in the other metric.
        $baseSenderos = (int) $actual['min_senderos'];
        $baseKm = (float) $actual['min_km'];
        $metaSenderos = (int) $siguiente['min_senderos'];
        $metaKm = (float) $siguiente['min_km'];
        $progresoSenderos = $asistidas >= $metaSenderos ? 100 : (($asistidas - $baseSenderos) / max(1, $metaSenderos - $baseSenderos)) * 100;
        $progresoKm = $kmAcumulados >= $metaKm ? 100 : (($kmAcumulados - $baseKm) / max(1, $metaKm - $baseKm)) * 100;
        $progreso = (int) min(99, max(0, min($progresoSenderos, $progresoKm)));
    }
    return [
        'nombre' => $actual['nombre'],
        'texto' => $actual['descripcion'] ?: 'Sigue sumando rutas y kilometros para avanzar de nivel.',
        'icono' => $actual['icono'] ?: 'map',
        'color' => $actual['color'] ?: '#0f7a3f',
        'siguiente' => $siguiente,
        'faltan_senderos' => $siguiente ? max(0, (int) $siguiente['min_senderos'] - $asistidas) : 0,
        'faltan_km' => $siguiente ? max(0, (float) $siguiente['min_km'] - $kmAcumulados) : 0,
        'progreso' => $progreso,
    ];
}
