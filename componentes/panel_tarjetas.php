<?php
// Tarjetas del panel administrativo: cuales se muestran, en que orden y para que roles.
// El permiso de cada modulo sigue mandando: esta configuracion solo puede ocultar, nunca dar acceso.

if (!function_exists('sg_panel_tarjetas_catalogo')) {
    // Cada pieza del panel: [codigo, nombre, grupo, descripcion]. El orden aqui es el orden de fabrica.
    function sg_panel_tarjetas_catalogo(): array
    {
        return [
            ['proximo_sendero_destacado', 'Proximo sendero (destacado)', 'destacado', 'Ficha del proximo sendero con cobrado, personas y accesos directos.'],
            ['atencion', 'Requiere tu atencion', 'destacado', 'Lista de pendientes que el rol puede resolver.'],
            ['accesos_rapidos', 'Barra de accesos rapidos', 'accesos', 'Muestra u oculta toda la barra de botones.'],
            ['acceso_nuevo_sendero', 'Nuevo sendero', 'accesos', 'Boton para crear un sendero.'],
            ['acceso_participantes', 'Participantes', 'accesos', 'Boton de reservas y participantes.'],
            ['acceso_asistencia', 'Asistencia', 'accesos', 'Boton para confirmar quienes fueron al sendero.'],
            ['acceso_ingresos', 'Ingresos', 'accesos', 'Boton de pagos, creditos, cortesias y exentos.'],
            ['acceso_gastos', 'Gastos', 'accesos', 'Boton de gastos reales de cada ruta.'],
            ['acceso_usuarios', 'Usuarios', 'accesos', 'Boton para agregar o completar un participante.'],
            ['acceso_mensajes', 'Mensajes', 'accesos', 'Boton de solicitudes enviadas desde la web.'],
            ['acceso_finanzas', 'Finanzas', 'accesos', 'Boton de indicadores y rentabilidad.'],
            ['kpi_rutas', 'Indicador: Proximas rutas', 'indicadores', 'Cantidad de proximos senderos y registros activos.'],
            ['kpi_usuarios', 'Indicador: Usuarios', 'indicadores', 'Total de usuarios, nuevos y activos.'],
            ['kpi_registros', 'Indicador: Registros', 'indicadores', 'Inscripciones de los ultimos 30 dias y asistencias.'],
            ['kpi_contacto', 'Indicador: Contacto', 'indicadores', 'Mensajes sin leer y recibidos.'],
            ['proximos_senderos', 'Tarjeta: Proximos senderos', 'tarjetas', 'Agenda de los proximos senderos.'],
            ['resumen_financiero', 'Tarjeta: Resumen financiero', 'tarjetas', 'Balance de ingresos y gastos (requiere permiso de Finanzas).'],
            ['registros_recientes', 'Tarjeta: Registros recientes', 'tarjetas', 'Ultimas inscripciones.'],
            ['lectura_rapida', 'Tarjeta: Lectura rapida', 'tarjetas', 'Salud operativa: senderos visitados, imagenes, logins y creditos.'],
        ];
    }

    function sg_panel_tarjetas_grupos(): array
    {
        return [
            'destacado' => ['Parte superior', 'Los bloques grandes de arriba.'],
            'accesos' => ['Accesos rapidos', 'Los botones de acceso directo.'],
            'indicadores' => ['Indicadores', 'Las cuatro cifras principales.'],
            'tarjetas' => ['Tarjetas inferiores', 'Las tarjetas de abajo.'],
        ];
    }
}

if (!function_exists('sg_panel_tarjetas_listo')) {
    function sg_panel_tarjetas_listo(mysqli $conn): bool
    {
        static $listo = null;
        if ($listo === null) {
            $a = mysqli_query($conn, "SHOW TABLES LIKE 'panel_tarjetas'");
            $b = mysqli_query($conn, "SHOW TABLES LIKE 'panel_tarjeta_roles'");
            $listo = $a && $b && mysqli_num_rows($a) === 1 && mysqli_num_rows($b) === 1;
        }
        return $listo;
    }
}

if (!function_exists('sg_panel_tarjetas_config')) {
    // codigo => ['activo' => bool, 'orden' => int, 'roles' => int[]]. Sin la actualizacion, todo se muestra como siempre.
    function sg_panel_tarjetas_config(mysqli $conn): array
    {
        $config = [];
        foreach (sg_panel_tarjetas_catalogo() as $posicion => [$codigo]) {
            $config[$codigo] = ['activo' => true, 'orden' => ($posicion + 1) * 10, 'roles' => []];
        }
        if (!sg_panel_tarjetas_listo($conn)) {
            return $config;
        }
        $res = mysqli_query($conn, 'SELECT id, codigo, activo, orden FROM panel_tarjetas');
        $porId = [];
        while ($res && $fila = mysqli_fetch_assoc($res)) {
            if (isset($config[$fila['codigo']])) {
                $config[$fila['codigo']]['activo'] = (int) $fila['activo'] === 1;
                $config[$fila['codigo']]['orden'] = (int) $fila['orden'];
                $porId[(int) $fila['id']] = $fila['codigo'];
            }
        }
        $res = mysqli_query($conn, 'SELECT tarjeta_id, rol_id FROM panel_tarjeta_roles');
        while ($res && $fila = mysqli_fetch_assoc($res)) {
            if (isset($porId[(int) $fila['tarjeta_id']])) {
                $config[$porId[(int) $fila['tarjeta_id']]]['roles'][] = (int) $fila['rol_id'];
            }
        }
        return $config;
    }
}

if (!function_exists('sg_panel_tarjeta_visible')) {
    // Activa y, si se limito a ciertos roles, que el rol actual este entre ellos. Un codigo desconocido se muestra.
    function sg_panel_tarjeta_visible(array $config, string $codigo, int $rolId): bool
    {
        if (!isset($config[$codigo])) {
            return true;
        }
        $tarjeta = $config[$codigo];
        return $tarjeta['activo'] && ($tarjeta['roles'] === [] || in_array($rolId, $tarjeta['roles'], true));
    }
}

if (!function_exists('sg_panel_tarjetas_ordenar')) {
    // Ordena los elementos segun la posicion elegida; los que empatan conservan su orden original.
    function sg_panel_tarjetas_ordenar(array $elementos, array $config, callable $codigoDe): array
    {
        $con = [];
        foreach (array_values($elementos) as $indice => $elemento) {
            $con[] = [$config[$codigoDe($elemento)]['orden'] ?? 0, $indice, $elemento];
        }
        usort($con, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        return array_map(static fn(array $fila) => $fila[2], $con);
    }
}

if (!function_exists('sg_panel_tarjetas_guardar')) {
    // $datos: codigo => ['activo' => '1'|?, 'alcance' => 'todos'|'elegidos', 'roles' => int[], 'orden' => int]
    function sg_panel_tarjetas_guardar(mysqli $conn, array $datos): void
    {
        if (!sg_panel_tarjetas_listo($conn)) {
            throw new InvalidArgumentException('Aplica primero la actualizacion pendiente del sistema.');
        }
        $roles = [];
        $res = mysqli_query($conn, 'SELECT id FROM roles');
        while ($res && $fila = mysqli_fetch_row($res)) {
            $roles[(int) $fila[0]] = true;
        }
        mysqli_begin_transaction($conn);
        try {
            foreach (sg_panel_tarjetas_catalogo() as $posicion => [$codigo, $nombre, $grupo]) {
                $fila = is_array($datos[$codigo] ?? null) ? $datos[$codigo] : [];
                $activo = !empty($fila['activo']) ? 1 : 0;
                $orden = isset($fila['orden']) ? max(0, min(100000, (int) $fila['orden'])) : ($posicion + 1) * 10;
                $stmt = mysqli_prepare($conn, 'INSERT INTO panel_tarjetas (codigo, nombre, grupo, activo, orden) VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), grupo = VALUES(grupo), activo = VALUES(activo), orden = VALUES(orden)');
                mysqli_stmt_bind_param($stmt, 'sssii', $codigo, $nombre, $grupo, $activo, $orden);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($conn, 'SELECT id FROM panel_tarjetas WHERE codigo = ?');
                mysqli_stmt_bind_param($stmt, 's', $codigo);
                mysqli_stmt_execute($stmt);
                $tarjetaId = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['id'] ?? 0);
                mysqli_stmt_close($stmt);

                mysqli_query($conn, 'DELETE FROM panel_tarjeta_roles WHERE tarjeta_id = ' . $tarjetaId);
                if (($fila['alcance'] ?? 'todos') === 'elegidos') {
                    $elegidos = array_unique(array_filter(array_map('intval', (array) ($fila['roles'] ?? [])), static fn(int $r): bool => isset($roles[$r])));
                    if (!$elegidos) {
                        throw new InvalidArgumentException('Elige al menos un rol para "' . $nombre . '" o déjala para todos los roles.');
                    }
                    foreach ($elegidos as $rolId) {
                        mysqli_query($conn, 'INSERT INTO panel_tarjeta_roles (tarjeta_id, rol_id) VALUES (' . $tarjetaId . ', ' . (int) $rolId . ')');
                    }
                }
            }
            mysqli_commit($conn);
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }
}

if (!function_exists('sg_panel_tarjetas_restablecer')) {
    function sg_panel_tarjetas_restablecer(mysqli $conn): void
    {
        if (!sg_panel_tarjetas_listo($conn)) {
            throw new InvalidArgumentException('Aplica primero la actualizacion pendiente del sistema.');
        }
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, 'DELETE FROM panel_tarjeta_roles');
            foreach (sg_panel_tarjetas_catalogo() as $posicion => [$codigo, $nombre, $grupo]) {
                $orden = ($posicion + 1) * 10;
                $stmt = mysqli_prepare($conn, 'INSERT INTO panel_tarjetas (codigo, nombre, grupo, activo, orden) VALUES (?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), grupo = VALUES(grupo), activo = 1, orden = VALUES(orden)');
                mysqli_stmt_bind_param($stmt, 'sssi', $codigo, $nombre, $grupo, $orden);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            mysqli_commit($conn);
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }
}
