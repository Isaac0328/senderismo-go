<?php

if (!function_exists('sg_finanzas_fecha_valida')) {
    function sg_finanzas_fecha_valida(string $fecha): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $fecha);
        return $date && $date->format('Y-m-d') === $fecha;
    }
}

if (!function_exists('sg_finanzas_resumen_periodo')) {
    function sg_finanzas_resumen_periodo(mysqli $conn, string $desde, string $hasta): array
    {
        $rows = [];
        $stmt = mysqli_prepare($conn, "
            SELECT
                s.id,
                s.nombre,
                s.fecha_sendero,
                s.estado,
                COALESCE(p.esperado, 0) AS esperado,
                COALESCE(p.cobrado_bruto, 0) AS cobrado_bruto,
                COALESCE(p.credito_aplicado, 0) AS credito_aplicado,
                COALESCE(p.credito_generado, 0) AS credito_generado,
                COALESCE(p.monto_retenido, 0) AS monto_retenido,
                COALESCE(p.por_cobrar, 0) AS por_cobrar,
                COALESCE(p.ingreso_reconocido, 0) AS ingreso_reconocido,
                COALESCE(p.pagos_registrados, 0) AS pagos_registrados,
                COALESCE(p.cuentas_pendientes, 0) AS cuentas_pendientes,
                COALESCE(g.gastos, 0) AS gastos
            FROM senderos s
            LEFT JOIN (
                SELECT
                    sendero_id,
                    SUM(CASE WHEN estado_financiero <> 'cortesia' THEN monto_esperado ELSE 0 END) AS esperado,
                    SUM(CASE WHEN estado_financiero <> 'cortesia' THEN monto_pagado ELSE 0 END) AS cobrado_bruto,
                    SUM(CASE WHEN estado_financiero <> 'cortesia' THEN credito_aplicado ELSE 0 END) AS credito_aplicado,
                    SUM(CASE WHEN estado_financiero <> 'cortesia' THEN credito_generado ELSE 0 END) AS credito_generado,
                    SUM(CASE WHEN estado_financiero <> 'cortesia' THEN monto_retenido ELSE 0 END) AS monto_retenido,
                    SUM(CASE WHEN estado_financiero IN ('cortesia', 'no_asistio_sin_pago') THEN 0 ELSE saldo_pendiente END) AS por_cobrar,
                    SUM(CASE
                        WHEN estado_financiero = 'cortesia' THEN 0
                        ELSE GREATEST(monto_pagado + credito_aplicado - credito_generado, 0)
                    END) AS ingreso_reconocido,
                    SUM(CASE WHEN monto_pagado > 0 OR credito_aplicado > 0 THEN 1 ELSE 0 END) AS pagos_registrados,
                    SUM(CASE
                        WHEN estado_financiero NOT IN ('cortesia', 'no_asistio_sin_pago') AND saldo_pendiente > 0 THEN 1
                        ELSE 0
                    END) AS cuentas_pendientes
                FROM contabilidad_registro_pagos
                GROUP BY sendero_id
            ) p ON p.sendero_id = s.id
            LEFT JOIN (
                SELECT sendero_id, SUM(total) AS gastos
                FROM contabilidad_sendero_gastos
                GROUP BY sendero_id
            ) g ON g.sendero_id = s.id
            WHERE s.fecha_sendero BETWEEN ? AND ?
            ORDER BY s.fecha_sendero ASC, s.nombre ASC
        ");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                foreach (['esperado', 'cobrado_bruto', 'credito_aplicado', 'credito_generado', 'monto_retenido', 'por_cobrar', 'ingreso_reconocido', 'gastos'] as $field) {
                    $row[$field] = (float) ($row[$field] ?? 0);
                }
                $row['pagos_registrados'] = (int) ($row['pagos_registrados'] ?? 0);
                $row['cuentas_pendientes'] = (int) ($row['cuentas_pendientes'] ?? 0);
                $row['utilidad'] = $row['ingreso_reconocido'] - $row['gastos'];
                $row['margen'] = $row['ingreso_reconocido'] > 0 ? ($row['utilidad'] / $row['ingreso_reconocido']) * 100 : 0;
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }

        $totals = [
            'senderos' => count($rows),
            'esperado' => 0.0,
            'cobrado_bruto' => 0.0,
            'credito_aplicado' => 0.0,
            'credito_generado' => 0.0,
            'monto_retenido' => 0.0,
            'por_cobrar' => 0.0,
            'ingreso_reconocido' => 0.0,
            'gastos' => 0.0,
            'utilidad' => 0.0,
            'margen' => 0.0,
            'retorno' => 0.0,
            'pagos_registrados' => 0,
            'cuentas_pendientes' => 0,
        ];

        $months = [];
        foreach ($rows as $row) {
            foreach (['esperado', 'cobrado_bruto', 'credito_aplicado', 'credito_generado', 'monto_retenido', 'por_cobrar', 'ingreso_reconocido', 'gastos', 'utilidad'] as $field) {
                $totals[$field] += $row[$field];
            }
            $totals['pagos_registrados'] += $row['pagos_registrados'];
            $totals['cuentas_pendientes'] += $row['cuentas_pendientes'];

            $month = substr((string) $row['fecha_sendero'], 0, 7);
            if (!isset($months[$month])) {
                $months[$month] = ['mes' => $month, 'ingresos' => 0.0, 'gastos' => 0.0, 'utilidad' => 0.0, 'senderos' => 0];
            }
            $months[$month]['ingresos'] += $row['ingreso_reconocido'];
            $months[$month]['gastos'] += $row['gastos'];
            $months[$month]['utilidad'] += $row['utilidad'];
            $months[$month]['senderos']++;
        }

        $totals['margen'] = $totals['ingreso_reconocido'] > 0 ? ($totals['utilidad'] / $totals['ingreso_reconocido']) * 100 : 0;
        $totals['retorno'] = $totals['gastos'] > 0 ? ($totals['utilidad'] / $totals['gastos']) * 100 : 0;

        $ranking = $rows;
        usort($ranking, static fn(array $a, array $b): int => $b['utilidad'] <=> $a['utilidad']);

        return [
            'totales' => $totals,
            'senderos' => $rows,
            'meses' => array_values($months),
            'ranking' => array_slice($ranking, 0, 6),
        ];
    }
}

if (!function_exists('sg_finanzas_metodos_periodo')) {
    function sg_finanzas_metodos_periodo(mysqli $conn, string $desde, string $hasta): array
    {
        $rows = [];
        $stmt = mysqli_prepare($conn, "
            SELECT
                COALESCE(cmp.nombre, NULLIF(TRIM(crp.metodo_pago), ''), 'Sin especificar') AS metodo,
                COUNT(*) AS operaciones,
                SUM(crp.monto_pagado) AS total
            FROM contabilidad_registro_pagos crp
            INNER JOIN senderos s ON s.id = crp.sendero_id
            LEFT JOIN contabilidad_metodo_pago cmp ON cmp.id = crp.metodo_pago_id
            WHERE s.fecha_sendero BETWEEN ? AND ?
              AND crp.estado_financiero <> 'cortesia'
              AND crp.monto_pagado > 0
            GROUP BY COALESCE(cmp.nombre, NULLIF(TRIM(crp.metodo_pago), ''), 'Sin especificar')
            ORDER BY total DESC
        ");
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $row['operaciones'] = (int) $row['operaciones'];
            $row['total'] = (float) $row['total'];
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('sg_finanzas_creditos_activos')) {
    function sg_finanzas_creditos_activos(mysqli $conn): array
    {
        $result = mysqli_query($conn, "
            SELECT COUNT(*) AS cuentas, COALESCE(SUM(saldo_disponible), 0) AS saldo
            FROM usuario_creditos
            WHERE estado = 'activo' AND saldo_disponible > 0
        ");
        $row = $result ? (mysqli_fetch_assoc($result) ?: []) : [];
        return ['cuentas' => (int) ($row['cuentas'] ?? 0), 'saldo' => (float) ($row['saldo'] ?? 0)];
    }
}
