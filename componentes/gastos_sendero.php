<?php
// Gastos de un sendero: lectura y guardado por lineas.
//
// Cada linea es una fila de contabilidad_sendero_gastos:
//  - del catalogo: gasto_id apunta al catalogo (su categoria la segmenta: alimentos, transporte...).
//  - otro gasto: gasto_id NULL, con grupo (ej.: "Exploracion") y descripcion propios.
// Guardar no borra y recrea: actualiza, agrega o quita lineas por id, asi las asignaciones
// hechas en Balances (fin_movimientos.origen_id) siguen apuntando a la misma linea.

require_once __DIR__ . '/helpers.php';

if (!function_exists('gs_esquema_listo')) {
    function gs_esquema_listo(mysqli $conn): bool
    {
        static $listo = null;
        if ($listo === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM contabilidad_sendero_gastos LIKE 'grupo'");
            $listo = $res && mysqli_num_rows($res) === 1;
        }
        return $listo;
    }

    function gs_cents($valor): int
    {
        return (int) round(((float) str_replace(',', '', (string) $valor)) * 100);
    }

    /** Categorias activas (o usadas) y el catalogo activo. */
    function gs_catalogo(mysqli $conn): array
    {
        $categorias = [];
        // Todas: las inactivas solo se muestran si el sendero ya tiene gastos en ellas.
        $res = mysqli_query($conn, "SELECT id, nombre, activo FROM contabilidad_categoria_gasto ORDER BY nombre");
        while ($res && $c = mysqli_fetch_assoc($res)) {
            $categorias[] = ['id' => (int) $c['id'], 'nombre' => $c['nombre'], 'activo' => (int) $c['activo'] === 1];
        }
        $articulos = [];
        $res = mysqli_query(
            $conn,
            "SELECT cg.id, cg.nombre, cg.unidad, cg.costo_unitario, cg.categoria_gasto_id, cg.activo
             FROM contabilidad_gastos_catalogo cg
             ORDER BY cg.nombre"
        );
        while ($res && $a = mysqli_fetch_assoc($res)) {
            $articulos[] = [
                'id' => (int) $a['id'],
                'nombre' => $a['nombre'],
                'unidad' => $a['unidad'],
                'costo' => (float) $a['costo_unitario'],
                'categoria_id' => (int) ($a['categoria_gasto_id'] ?? 0),
                'activo' => (int) $a['activo'] === 1,
            ];
        }
        return ['categorias' => $categorias, 'articulos' => $articulos];
    }

    /** Lineas de gasto del sendero, con lo ya contabilizado en Balances. */
    function gs_lineas(mysqli $conn, int $senderoId): array
    {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT g.id, g.gasto_id, g.grupo, g.descripcion, g.cantidad, g.costo_unitario, g.total, g.nota, g.orden,
                    g.updated_at, COALESCE(m.contabilizado, 0) AS contabilizado
             FROM contabilidad_sendero_gastos g
             LEFT JOIN (
                SELECT origen_id, -SUM(importe) AS contabilizado
                FROM fin_movimientos WHERE origen = 'gasto_sendero' GROUP BY origen_id
             ) m ON m.origen_id = g.id
             WHERE g.sendero_id = ?
             ORDER BY g.orden, g.id"
        );
        if (!$stmt) {
            // Instalaciones sin el modulo de balances.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id, gasto_id, grupo, descripcion, cantidad, costo_unitario, total, nota, orden, updated_at, 0 AS contabilizado
                 FROM contabilidad_sendero_gastos WHERE sendero_id = ? ORDER BY orden, id"
            );
        }
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $lineas = [];
        while ($l = mysqli_fetch_assoc($res)) {
            $lineas[] = [
                'id' => (int) $l['id'],
                'gasto_id' => $l['gasto_id'] !== null ? (int) $l['gasto_id'] : null,
                'grupo' => (string) ($l['grupo'] ?? ''),
                'descripcion' => (string) ($l['descripcion'] ?? ''),
                'cantidad' => (float) $l['cantidad'],
                'costo' => (float) $l['costo_unitario'],
                'total' => (float) $l['total'],
                'nota' => (string) ($l['nota'] ?? ''),
                'contabilizado' => (float) $l['contabilizado'] > 0,
                'updated_at' => (string) $l['updated_at'],
            ];
        }
        mysqli_stmt_close($stmt);
        return $lineas;
    }

    // Huella de las lineas guardadas: si cambia mientras alguien edita, no se pisa.
    function gs_version(array $lineas): string
    {
        $partes = array_map(static fn(array $l): string => $l['id'] . ':' . $l['updated_at'] . ':' . $l['total'], $lineas);
        return md5(implode('|', $partes));
    }

    /**
     * Guarda todas las lineas del sendero. $lineas llega del navegador.
     * Devuelve ['ok' => bool, 'mensaje' => string, 'errores' => [indice => mensaje]].
     */
    function gs_guardar(mysqli $conn, int $senderoId, array $lineas, string $version): array
    {
        $catalogo = gs_catalogo($conn);
        $articulos = [];
        foreach ($catalogo['articulos'] as $a) {
            $articulos[$a['id']] = $a;
        }

        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "SELECT id FROM senderos WHERE id = " . (int) $senderoId . " FOR UPDATE");
            $actuales = gs_lineas($conn, $senderoId);
            if ($version !== gs_version($actuales)) {
                mysqli_rollback($conn);
                return ['ok' => false, 'mensaje' => 'Otra persona cambio los gastos de este sendero mientras editabas. Recarga la pagina para ver lo ultimo.', 'errores' => []];
            }
            $existentes = [];
            foreach ($actuales as $a) {
                $existentes[$a['id']] = $a;
            }
            $usadosEnCatalogo = array_flip(array_filter(array_map(static fn(array $l) => $l['gasto_id'], $actuales)));

            $errores = [];
            $validas = [];
            foreach (array_values($lineas) as $indice => $l) {
                if (!is_array($l)) {
                    continue;
                }
                $id = (int) ($l['id'] ?? 0);
                $gastoId = (int) ($l['gasto_id'] ?? 0);
                $esOtro = ($l['tipo'] ?? '') === 'otro';
                $grupo = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($l['grupo'] ?? ''))), 0, 120);
                $descripcion = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($l['descripcion'] ?? ''))), 0, 160);
                $nota = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($l['nota'] ?? ''))), 0, 255);
                $cantidadC = gs_cents($l['cantidad'] ?? 0);
                $costoC = gs_cents($l['costo'] ?? 0);

                if ($id > 0 && !isset($existentes[$id])) {
                    $errores[$indice] = 'Esta linea ya no existe. Recarga la pagina.';
                    continue;
                }
                if ($esOtro) {
                    if ($grupo === '') {
                        $errores[$indice] = 'Ponle nombre al grupo de otros gastos.';
                        continue;
                    }
                    if ($descripcion === '') {
                        $errores[$indice] = 'Escribe en que se gasto.';
                        continue;
                    }
                    $gastoId = 0;
                } else {
                    $articulo = $articulos[$gastoId] ?? null;
                    // Un gasto inactivo del catalogo solo se acepta si ya estaba en el sendero.
                    if (!$articulo || (!$articulo['activo'] && !isset($usadosEnCatalogo[$gastoId]))) {
                        $errores[$indice] = 'Elige el gasto del catalogo.';
                        continue;
                    }
                    $grupo = '';
                    $descripcion = '';
                }
                if ($cantidadC <= 0) {
                    $errores[$indice] = 'La cantidad debe ser mayor que cero.';
                    continue;
                }
                if ($costoC < 0) {
                    $errores[$indice] = 'El costo no puede ser negativo.';
                    continue;
                }
                $totalC = (int) round($cantidadC * $costoC / 100);
                if ($totalC <= 0) {
                    $errores[$indice] = 'Indica el costo: el total no puede quedar en cero.';
                    continue;
                }
                $validas[] = [
                    'id' => $id,
                    'gasto_id' => $gastoId > 0 ? $gastoId : null,
                    'grupo' => $grupo !== '' ? $grupo : null,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'cantidad' => number_format($cantidadC / 100, 2, '.', ''),
                    'costo' => number_format($costoC / 100, 2, '.', ''),
                    'total' => number_format($totalC / 100, 2, '.', ''),
                    'nota' => $nota !== '' ? $nota : null,
                    'orden' => $indice + 1,
                ];
            }
            if ($errores) {
                mysqli_rollback($conn);
                return ['ok' => false, 'mensaje' => 'Revisa las lineas marcadas: no se guardo nada.', 'errores' => $errores];
            }

            $stmtUpdate = mysqli_prepare(
                $conn,
                "UPDATE contabilidad_sendero_gastos
                 SET gasto_id = ?, grupo = ?, descripcion = ?, cantidad = ?, costo_unitario = ?, total = ?, nota = ?, orden = ?
                 WHERE id = ? AND sendero_id = ?"
            );
            $stmtInsert = mysqli_prepare(
                $conn,
                "INSERT INTO contabilidad_sendero_gastos (sendero_id, gasto_id, grupo, descripcion, cantidad, costo_unitario, total, nota, orden)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $conservados = [];
            $total = 0;
            foreach ($validas as $v) {
                $total += (float) $v['total'];
                if ($v['id'] > 0) {
                    mysqli_stmt_bind_param($stmtUpdate, 'issssssiii', $v['gasto_id'], $v['grupo'], $v['descripcion'], $v['cantidad'], $v['costo'], $v['total'], $v['nota'], $v['orden'], $v['id'], $senderoId);
                    mysqli_stmt_execute($stmtUpdate);
                    $conservados[$v['id']] = true;
                } else {
                    mysqli_stmt_bind_param($stmtInsert, 'iissssssi', $senderoId, $v['gasto_id'], $v['grupo'], $v['descripcion'], $v['cantidad'], $v['costo'], $v['total'], $v['nota'], $v['orden']);
                    mysqli_stmt_execute($stmtInsert);
                }
            }
            mysqli_stmt_close($stmtUpdate);
            mysqli_stmt_close($stmtInsert);

            $quitados = array_diff(array_keys($existentes), array_keys($conservados));
            if ($quitados) {
                mysqli_query($conn, "DELETE FROM contabilidad_sendero_gastos WHERE sendero_id = " . (int) $senderoId
                    . " AND id IN (" . implode(',', array_map('intval', $quitados)) . ")");
            }

            mysqli_commit($conn);
            return ['ok' => true, 'mensaje' => 'Gastos guardados. Total del sendero: ' . sg_money($total) . '.', 'errores' => []];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
}
