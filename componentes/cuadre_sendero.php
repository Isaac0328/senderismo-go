<?php
// Marca por sendero si sus cobros (Ingresos por sendero) y sus gastos (Gastos por
// sendero) ya estan cuadrados. Balances solo ofrece asignar a cuentas lo cuadrado.

if (!function_exists('sg_cuadre_sendero_listo')) {
    function sg_cuadre_sendero_listo(mysqli $conn): bool
    {
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'fin_sendero_cuadres'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('sg_cuadre_sendero_areas')) {
    // Areas que se pueden cuadrar: [titulo, pantalla donde se revisan].
    function sg_cuadre_sendero_areas(): array
    {
        return ['cobros' => ['Cobros', 'admin/ingresos-sendero'], 'gastos' => ['Gastos', 'admin/gastos-sendero']];
    }
}

if (!function_exists('sg_cuadre_sendero_estado')) {
    // Devuelve [sendero_id => ['cobros' => fecha|null, 'gastos' => fecha|null, 'cobros_por' => nombre, 'gastos_por' => nombre]].
    function sg_cuadre_sendero_estado(mysqli $conn, ?int $senderoId = null): array
    {
        if (!sg_cuadre_sendero_listo($conn)) {
            return [];
        }
        $sql = "SELECT c.sendero_id, c.cobros_cuadrado_at, c.gastos_cuadrado_at,
                       TRIM(CONCAT(COALESCE(uc.nombre, ''), ' ', COALESCE(uc.apellido, ''))) cobros_por,
                       TRIM(CONCAT(COALESCE(ug.nombre, ''), ' ', COALESCE(ug.apellido, ''))) gastos_por
                FROM fin_sendero_cuadres c
                LEFT JOIN usuarios uc ON uc.id = c.cobros_cuadrado_por
                LEFT JOIN usuarios ug ON ug.id = c.gastos_cuadrado_por";
        $rows = [];
        if ($senderoId !== null) {
            $stmt = mysqli_prepare($conn, $sql . ' WHERE c.sendero_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $senderoId);
            mysqli_stmt_execute($stmt);
            $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
        } else {
            $res = mysqli_query($conn, $sql);
            $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
        }
        $estado = [];
        foreach ($rows as $row) {
            $estado[(int) $row['sendero_id']] = [
                'cobros' => $row['cobros_cuadrado_at'], 'cobros_por' => $row['cobros_por'],
                'gastos' => $row['gastos_cuadrado_at'], 'gastos_por' => $row['gastos_por'],
            ];
        }
        return $estado;
    }
}

if (!function_exists('sg_cuadre_sendero_asignados')) {
    function sg_cuadre_sendero_asignados(mysqli $conn, int $senderoId, string $area): int
    {
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'fin_movimientos'");
        if (!$res || mysqli_num_rows($res) === 0) {
            return 0;
        }
        $origen = $area === 'cobros' ? 'cobro_sendero' : 'gasto_sendero';
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) total FROM fin_movimientos m WHERE m.sendero_id = ? AND m.origen = ? AND m.reversa_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM fin_movimientos r WHERE r.reversa_id = m.id)');
        mysqli_stmt_bind_param($stmt, 'is', $senderoId, $origen);
        mysqli_stmt_execute($stmt);
        $total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
        mysqli_stmt_close($stmt);
        return $total;
    }
}

if (!function_exists('sg_cuadre_sendero_cambiar')) {
    function sg_cuadre_sendero_cambiar(mysqli $conn, int $senderoId, string $area, bool $cuadrado, int $usuarioId): void
    {
        if (!isset(sg_cuadre_sendero_areas()[$area])) {
            throw new InvalidArgumentException('Area de cuadre no valida.');
        }
        // Lo ya asignado a cuentas sale de lo cuadrado: primero hay que reversarlo en Balances.
        $asignados = $cuadrado ? 0 : sg_cuadre_sendero_asignados($conn, $senderoId, $area);
        if ($asignados > 0) {
            throw new InvalidArgumentException('No se puede reabrir: los ' . $area . ' de este sendero tienen ' . $asignados
                . ' asignaciones en cuentas. Reversalas primero en Balances > Movimientos, filtrando por este sendero.');
        }
        $columnaFecha = $area . '_cuadrado_at';
        $columnaPor = $area . '_cuadrado_por';
        $fecha = $cuadrado ? date('Y-m-d H:i:s') : null;
        $por = $cuadrado ? $usuarioId : null;
        $stmt = mysqli_prepare($conn, "INSERT INTO fin_sendero_cuadres (sendero_id, $columnaFecha, $columnaPor) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE $columnaFecha = VALUES($columnaFecha), $columnaPor = VALUES($columnaPor)");
        mysqli_stmt_bind_param($stmt, 'isi', $senderoId, $fecha, $por);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('No se pudo guardar el cuadre del sendero.');
        }
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('sg_cuadre_sendero_render')) {
    // Etiqueta de estado y boton para marcar o reabrir el cuadre de un area del sendero.
    function sg_cuadre_sendero_render(mysqli $conn, int $senderoId, string $area): void
    {
        if (!sg_cuadre_sendero_listo($conn) || !isset(sg_cuadre_sendero_areas()[$area])) {
            return;
        }
        $estado = sg_cuadre_sendero_estado($conn, $senderoId)[$senderoId] ?? [];
        $fecha = $estado[$area] ?? null;
        $titulo = strtolower(sg_cuadre_sendero_areas()[$area][0]);
        $h = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $asignados = $fecha ? sg_cuadre_sendero_asignados($conn, $senderoId, $area) : 0;
        $mensaje = (string) ($_SESSION['sg_cuadre_mensaje'] ?? '');
        unset($_SESSION['sg_cuadre_mensaje']);
        if ($mensaje !== '') {
            echo '<p class="sg-cuadre-alerta" role="alert"><i data-feather="alert-triangle"></i>' . $h($mensaje) . '</p>';
        }
        echo '<form class="sg-cuadre-sendero ' . ($fecha ? 'is-cuadrado' : 'is-abierto') . '" method="post" action="' . $h(BASE_URL . 'procesos/proceso_cuadre_sendero.php') . '"'
            . ($asignados > 0 ? '' : ($fecha ? ' data-confirm="Al reabrir, los ' . $h($titulo) . ' de este sendero dejan de ofrecerse en Balances hasta que los vuelvas a marcar. Lo ya asignado a cuentas no cambia. Continuar?"'
                      : ' data-confirm="Al marcar los ' . $h($titulo) . ' como cuadrados, quedaran disponibles en Balances para asignarlos a una cuenta. Continuar?"')) . '>';
        echo function_exists('csrf_field') ? csrf_field() : '';
        echo '<input type="hidden" name="sendero_id" value="' . $senderoId . '"><input type="hidden" name="area" value="' . $h($area) . '">';
        if ($fecha) {
            echo '<span class="sg-cuadre-estado"><i data-feather="lock"></i><span>' . $h(ucfirst($titulo)) . ' cuadrados el ' . $h(date('d/m/Y', strtotime($fecha)))
                . ($estado[$area . '_por'] ? ' por ' . $h($estado[$area . '_por']) : '') . '</span></span>';
            if ($asignados > 0) {
                // Con asignaciones vigentes no se reabre: se explica que hacer y se enlaza a Balances.
                echo '<button type="button" class="sg-cuadre-boton is-soft" disabled title="Tiene ' . $asignados . ' asignaciones en cuentas"><i data-feather="unlock"></i>Reabrir</button>';
                echo '<span class="sg-cuadre-nota">Ya tiene ' . $asignados . ' asignaciones en cuentas. Para reabrir, primero <a href="'
                    . $h(BASE_URL . 'admin/balances?tab=movimientos&sendero=' . $senderoId) . '">reversalas en Balances</a>.</span>';
            } else {
                echo '<button type="submit" name="accion" value="reabrir" class="sg-cuadre-boton is-soft"><i data-feather="unlock"></i>Reabrir</button>';
            }
        } else {
            echo '<span class="sg-cuadre-estado"><i data-feather="circle"></i><span>' . $h(ucfirst($titulo)) . ' sin cuadrar</span></span>';
            echo '<button type="submit" name="accion" value="marcar" class="sg-cuadre-boton"><i data-feather="check-circle"></i>Marcar como cuadrados</button>';
        }
        echo '</form>';
    }
}
