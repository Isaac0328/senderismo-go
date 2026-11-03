<?php
// Usuarios sin cuenta: personas que asisten inscritas a mano. Sus inscripciones apuntan
// a una ficha (registros_senderos.persona_sin_cuenta_id) y, cuando la persona crea su
// cuenta, la ficha se reconcilia: sus inscripciones pasan al usuario con todo su historial.

if (!function_exists('psc_listo')) {
    function psc_listo(mysqli $conn): bool
    {
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'personas_sin_cuenta'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('psc_normalizar')) {
    // Nombre comparable: minusculas, sin tildes y sin espacios repetidos.
    function psc_normalizar(?string $texto): string
    {
        $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }
}

if (!function_exists('psc_uno')) {
    function psc_uno(mysqli $conn, string $sql, string $tipos = '', array $valores = []): ?array
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo consultar usuarios sin cuenta.');
        }
        if ($tipos !== '') {
            mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
        }
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $fila ?: null;
    }
}

if (!function_exists('psc_ejecutar')) {
    function psc_ejecutar(mysqli $conn, string $sql, string $tipos = '', array $valores = []): int
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new RuntimeException('No se pudo guardar el usuario sin cuenta.');
        }
        if ($tipos !== '') {
            mysqli_stmt_bind_param($stmt, $tipos, ...$valores);
        }
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('No se pudo guardar el usuario sin cuenta.');
        }
        $afectados = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $afectados;
    }
}

if (!function_exists('psc_listar')) {
    // Fichas con sus cifras: inscripciones, ultima salida, total pagado y usuario reconciliado.
    function psc_listar(mysqli $conn): array
    {
        $res = mysqli_query($conn, "SELECT p.*,
                COUNT(r.id) inscripciones,
                COALESCE(SUM(r.estado = 'registrado'), 0) inscripciones_activas,
                MAX(s.fecha_sendero) ultima_salida,
                COALESCE(SUM(pg.monto_pagado), 0) total_pagado,
                TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) usuario_nombre,
                u.user usuario_user
            FROM personas_sin_cuenta p
            LEFT JOIN registros_senderos r ON r.persona_sin_cuenta_id = p.id
            LEFT JOIN senderos s ON s.id = r.sendero_id
            LEFT JOIN contabilidad_registro_pagos pg ON pg.registro_id = r.id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            GROUP BY p.id
            ORDER BY (p.usuario_id IS NOT NULL), p.activo DESC, p.nombre, p.apellido");
        return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    }
}

if (!function_exists('psc_coincidencias_cuentas')) {
    /**
     * Cuentas activas que parecen corresponder a una ficha sin cuenta. Solo se
     * señalan coincidencias exactas para que el aviso no sugiera reconciliaciones
     * inseguras: nombre completo, telefono o correo.
     *
     * @return array<int, array<int, array{id:int,nombre:string,apellido:string,user:string,motivos:array<int,string>}>>
     */
    function psc_coincidencias_cuentas(mysqli $conn, array $personas): array
    {
        if (!$personas) {
            return [];
        }

        $cuentas = [];
        $res = mysqli_query($conn, "SELECT u.id, u.nombre, u.apellido, u.user, u.email,
                COALESCE(MAX(d.telefono), '') telefono
            FROM usuarios u
            LEFT JOIN detalles_usuarios d ON d.usuario_id = u.id
            WHERE u.estado = 1
            GROUP BY u.id
            ORDER BY u.nombre, u.apellido");
        while ($res && $cuenta = mysqli_fetch_assoc($res)) {
            $cuenta['telefono'] = preg_replace('/\\D+/', '', (string) $cuenta['telefono']) ?? '';
            $cuentas[] = $cuenta;
        }

        $coincidencias = [];
        foreach ($personas as $persona) {
            if (!empty($persona['usuario_id']) || (int) ($persona['activo'] ?? 0) !== 1) {
                continue;
            }
            $nombre = psc_normalizar(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));
            $telefono = preg_replace('/\\D+/', '', (string) ($persona['telefono'] ?? '')) ?? '';
            $email = mb_strtolower(trim((string) ($persona['email'] ?? '')), 'UTF-8');

            foreach ($cuentas as $cuenta) {
                $motivos = [];
                if ($nombre !== '' && $nombre === psc_normalizar($cuenta['nombre'] . ' ' . $cuenta['apellido'])) {
                    $motivos[] = 'nombre';
                }
                if ($telefono !== '' && $telefono === $cuenta['telefono']) {
                    $motivos[] = 'teléfono';
                }
                if ($email !== '' && $email === mb_strtolower(trim((string) $cuenta['email']), 'UTF-8')) {
                    $motivos[] = 'correo';
                }
                if ($motivos) {
                    $coincidencias[(int) $persona['id']][] = [
                        'id' => (int) $cuenta['id'],
                        'nombre' => $cuenta['nombre'],
                        'apellido' => $cuenta['apellido'],
                        'user' => $cuenta['user'],
                        'motivos' => $motivos,
                    ];
                }
            }
        }
        return $coincidencias;
    }
}

if (!function_exists('psc_disponibles')) {
    // Fichas activas y sin reconciliar, para elegirlas al inscribir a alguien a mano.
    function psc_disponibles(mysqli $conn): array
    {
        if (!psc_listo($conn)) {
            return [];
        }
        $res = mysqli_query($conn, "SELECT p.id, p.nombre, p.apellido, p.telefono, p.email, COUNT(r.id) inscripciones
            FROM personas_sin_cuenta p LEFT JOIN registros_senderos r ON r.persona_sin_cuenta_id = p.id
            WHERE p.activo = 1 AND p.usuario_id IS NULL
            GROUP BY p.id ORDER BY p.nombre, p.apellido");
        return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    }
}

if (!function_exists('psc_obtener')) {
    function psc_obtener(mysqli $conn, int $id): array
    {
        $persona = psc_uno($conn, 'SELECT * FROM personas_sin_cuenta WHERE id = ?', 'i', [$id]);
        if (!$persona) {
            throw new InvalidArgumentException('La persona sin cuenta ya no existe.');
        }
        return $persona;
    }
}

if (!function_exists('psc_inscripciones')) {
    function psc_inscripciones(mysqli $conn, int $id): int
    {
        return (int) (psc_uno($conn, 'SELECT COUNT(*) total FROM registros_senderos WHERE persona_sin_cuenta_id = ?', 'i', [$id])['total'] ?? 0);
    }
}

if (!function_exists('psc_datos')) {
    // Valida y limpia los datos basicos de una ficha.
    function psc_datos(array $entrada): array
    {
        $limpiar = static fn($valor, int $max): string => mb_substr(trim(preg_replace('/\s+/', ' ', (string) $valor) ?? ''), 0, $max);
        $datos = [
            'nombre' => $limpiar($entrada['nombre'] ?? '', 100),
            'apellido' => $limpiar($entrada['apellido'] ?? '', 100),
            'telefono' => mb_substr(preg_replace('/\D+/', '', (string) ($entrada['telefono'] ?? '')) ?? '', 0, 20),
            'email' => $limpiar($entrada['email'] ?? '', 100),
            'notas' => $limpiar($entrada['notas'] ?? '', 255),
        ];
        if ($datos['nombre'] === '' || $datos['apellido'] === '') {
            throw new InvalidArgumentException('Completa el nombre y el apellido.');
        }
        if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no es valido.');
        }
        return $datos;
    }
}

if (!function_exists('psc_sincronizar_inscripciones')) {
    // Las inscripciones sin reconciliar muestran siempre los datos actuales de la ficha.
    function psc_sincronizar_inscripciones(mysqli $conn, int $id): void
    {
        psc_ejecutar($conn, "UPDATE registros_senderos r JOIN personas_sin_cuenta p ON p.id = r.persona_sin_cuenta_id
            SET r.manual_nombre = p.nombre, r.manual_apellido = p.apellido,
                r.manual_telefono = NULLIF(p.telefono, ''), r.manual_email = NULLIF(p.email, '')
            WHERE p.id = ? AND (r.usuario_id IS NULL OR r.usuario_id = 0)", 'i', [$id]);
    }
}

if (!function_exists('psc_guardar')) {
    // Crea o edita una ficha. Devuelve su id.
    function psc_guardar(mysqli $conn, int $id, array $entrada, int $actor): int
    {
        $datos = psc_datos($entrada);
        if ($id > 0) {
            $actual = psc_obtener($conn, $id);
            if ($actual['usuario_id']) {
                throw new InvalidArgumentException('Esta persona ya fue reconciliada con una cuenta. Edita sus datos en Usuarios.');
            }
        }
        // No se duplica a la misma persona: mismo nombre y mismo telefono.
        if ($datos['telefono'] !== '') {
            foreach (psc_disponibles($conn) as $otra) {
                if ((int) $otra['id'] !== $id && $otra['telefono'] === $datos['telefono']
                    && psc_normalizar($otra['nombre'] . ' ' . $otra['apellido']) === psc_normalizar($datos['nombre'] . ' ' . $datos['apellido'])) {
                    throw new InvalidArgumentException('Ya existe ' . $otra['nombre'] . ' ' . $otra['apellido'] . ' con ese telefono. Usa esa ficha.');
                }
            }
        }
        if ($id > 0) {
            psc_ejecutar($conn, 'UPDATE personas_sin_cuenta SET nombre = ?, apellido = ?, telefono = ?, email = ?, notas = ? WHERE id = ?', 'sssssi',
                [$datos['nombre'], $datos['apellido'], $datos['telefono'], $datos['email'], $datos['notas'], $id]);
            psc_sincronizar_inscripciones($conn, $id);
            return $id;
        }
        psc_ejecutar($conn, 'INSERT INTO personas_sin_cuenta (nombre, apellido, telefono, email, notas, creado_por) VALUES (?, ?, ?, ?, ?, ?)', 'sssssi',
            [$datos['nombre'], $datos['apellido'], $datos['telefono'], $datos['email'], $datos['notas'], $actor]);
        return (int) mysqli_insert_id($conn);
    }
}

if (!function_exists('psc_para_inscripcion')) {
    // Ficha a la que se enlaza una inscripcion manual: la elegida en la lista, la que ya
    // existe con el mismo nombre y telefono, o una nueva.
    function psc_para_inscripcion(mysqli $conn, int $personaId, array $entrada, int $actor): array
    {
        if ($personaId > 0) {
            $persona = psc_obtener($conn, $personaId);
            if ($persona['usuario_id'] || !(int) $persona['activo']) {
                throw new InvalidArgumentException('Esa persona esta inactiva o ya tiene cuenta. Si tiene cuenta, inscribela como usuario existente.');
            }
            return $persona;
        }
        $datos = psc_datos($entrada);
        if ($datos['telefono'] !== '') {
            foreach (psc_disponibles($conn) as $otra) {
                if ($otra['telefono'] === $datos['telefono']
                    && psc_normalizar($otra['nombre'] . ' ' . $otra['apellido']) === psc_normalizar($datos['nombre'] . ' ' . $datos['apellido'])) {
                    return psc_obtener($conn, (int) $otra['id']);
                }
            }
        }
        return psc_obtener($conn, psc_guardar($conn, 0, $datos, $actor));
    }
}

if (!function_exists('psc_cambiar_estado')) {
    function psc_cambiar_estado(mysqli $conn, int $id, bool $activo): void
    {
        $persona = psc_obtener($conn, $id);
        if ($persona['usuario_id']) {
            throw new InvalidArgumentException('Esta persona ya fue reconciliada con una cuenta.');
        }
        if (!$activo && psc_inscripciones($conn, $id) > 0) {
            throw new InvalidArgumentException('No se puede inactivar: tiene inscripciones en senderos. Solo se inactivan fichas sin movimientos.');
        }
        psc_ejecutar($conn, 'UPDATE personas_sin_cuenta SET activo = ? WHERE id = ?', 'ii', [$activo ? 1 : 0, $id]);
    }
}

if (!function_exists('psc_creditos_listo')) {
    // Los creditos pueden pertenecer a una ficha (actualizacion 015).
    function psc_creditos_listo(mysqli $conn): bool
    {
        static $existe = null;
        if ($existe === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM usuario_creditos LIKE 'persona_sin_cuenta_id'");
            $existe = $res && mysqli_num_rows($res) === 1;
        }
        return $existe;
    }

    /** Saldo de credito a favor de cada ficha: [persona_id => pesos]. */
    function psc_creditos_por_persona(mysqli $conn): array
    {
        if (!psc_creditos_listo($conn)) {
            return [];
        }
        $saldos = [];
        $res = mysqli_query($conn, "SELECT persona_sin_cuenta_id, SUM(saldo_disponible) AS saldo FROM usuario_creditos
            WHERE persona_sin_cuenta_id IS NOT NULL AND estado = 'activo' GROUP BY persona_sin_cuenta_id");
        while ($res && $fila = mysqli_fetch_assoc($res)) {
            $saldos[(int) $fila['persona_sin_cuenta_id']] = (float) $fila['saldo'];
        }
        return $saldos;
    }
}

if (!function_exists('psc_eliminar')) {
    function psc_eliminar(mysqli $conn, int $id): void
    {
        $persona = psc_obtener($conn, $id);
        if ($persona['usuario_id'] || (int) $persona['activo'] === 1) {
            throw new InvalidArgumentException('Solo se eliminan fichas inactivas y sin reconciliar.');
        }
        if (psc_inscripciones($conn, $id) > 0) {
            throw new InvalidArgumentException('No se puede eliminar: tiene inscripciones en senderos.');
        }
        psc_ejecutar($conn, 'DELETE FROM personas_sin_cuenta WHERE id = ?', 'i', [$id]);
    }
}

if (!function_exists('psc_senderos_en_comun')) {
    // Senderos donde las dos partes ya estan inscritas: unirlas dejaria a una persona dos veces.
    function psc_senderos_en_comun(mysqli $conn, int $personaId, string $columnaOtro, int $otroId): array
    {
        $columna = $columnaOtro === 'usuario_id' ? 'b.usuario_id' : 'b.persona_sin_cuenta_id';
        $stmt = mysqli_prepare($conn, "SELECT DISTINCT s.nombre, s.fecha_sendero FROM registros_senderos a
            JOIN registros_senderos b ON b.sendero_id = a.sendero_id AND b.id <> a.id
            JOIN senderos s ON s.id = a.sendero_id
            WHERE a.persona_sin_cuenta_id = ? AND (a.usuario_id IS NULL OR a.usuario_id = 0) AND $columna = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $personaId, $otroId);
        mysqli_stmt_execute($stmt);
        $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return array_map(static fn($fila) => $fila['nombre'] . ' (' . date('d/m/Y', strtotime($fila['fecha_sendero'])) . ')', $filas);
    }
}

if (!function_exists('psc_unir')) {
    // Une dos fichas de la misma persona: las inscripciones de origen pasan a destino.
    function psc_unir(mysqli $conn, int $origenId, int $destinoId): int
    {
        if ($origenId === $destinoId) {
            throw new InvalidArgumentException('Elige otra persona para unir.');
        }
        $origen = psc_obtener($conn, $origenId);
        $destino = psc_obtener($conn, $destinoId);
        if ($origen['usuario_id'] || $destino['usuario_id']) {
            throw new InvalidArgumentException('Solo se unen personas que aun no tienen cuenta.');
        }
        $comunes = psc_senderos_en_comun($conn, $origenId, 'persona', $destinoId);
        if ($comunes) {
            throw new InvalidArgumentException('Las dos fichas estan inscritas en el mismo sendero: ' . implode(', ', $comunes) . '. Cancela una de esas inscripciones antes de unirlas.');
        }
        mysqli_begin_transaction($conn);
        try {
            psc_ejecutar($conn, "UPDATE personas_sin_cuenta d JOIN personas_sin_cuenta o ON o.id = ?
                SET d.telefono = IF(d.telefono = '', o.telefono, d.telefono), d.email = IF(d.email = '', o.email, d.email),
                    d.activo = GREATEST(d.activo, o.activo)
                WHERE d.id = ?", 'ii', [$origenId, $destinoId]);
            $movidas = psc_ejecutar($conn, 'UPDATE registros_senderos SET persona_sin_cuenta_id = ? WHERE persona_sin_cuenta_id = ?', 'ii', [$destinoId, $origenId]);
            // Su credito a favor tambien pasa a la ficha que queda.
            if (psc_creditos_listo($conn)) {
                psc_ejecutar($conn, 'UPDATE usuario_creditos SET persona_sin_cuenta_id = ? WHERE persona_sin_cuenta_id = ?', 'ii', [$destinoId, $origenId]);
            }
            psc_ejecutar($conn, 'DELETE FROM personas_sin_cuenta WHERE id = ?', 'i', [$origenId]);
            psc_sincronizar_inscripciones($conn, $destinoId);
            mysqli_commit($conn);
            return $movidas;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
}

if (!function_exists('psc_reconciliar')) {
    // Pasa todas las inscripciones de la ficha al usuario con cuenta. Pagos, menores,
    // asistencia y asignaciones contables siguen a la inscripcion, asi que viajan con ella.
    function psc_reconciliar(mysqli $conn, int $personaId, int $usuarioId, int $actor): int
    {
        $persona = psc_obtener($conn, $personaId);
        if ($persona['usuario_id']) {
            throw new InvalidArgumentException('Esta persona ya fue reconciliada.');
        }
        $usuario = psc_uno($conn, 'SELECT id, nombre, apellido FROM usuarios WHERE id = ? AND estado = 1', 'i', [$usuarioId]);
        if (!$usuario) {
            throw new InvalidArgumentException('Elige un usuario activo con cuenta.');
        }
        $comunes = psc_senderos_en_comun($conn, $personaId, 'usuario_id', $usuarioId);
        if ($comunes) {
            throw new InvalidArgumentException('El usuario ya esta inscrito en el mismo sendero que esta persona: ' . implode(', ', $comunes)
                . '. Cancela o elimina una de esas inscripciones antes de reconciliar.');
        }
        mysqli_begin_transaction($conn);
        try {
            $movidas = psc_ejecutar($conn, "UPDATE registros_senderos
                SET usuario_id = ?,
                    detalle_usuario_id = COALESCE(detalle_usuario_id, (SELECT d.id FROM detalles_usuarios d WHERE d.usuario_id = ? LIMIT 1)),
                    manual_nombre = NULL, manual_apellido = NULL, manual_telefono = NULL, manual_email = NULL
                WHERE persona_sin_cuenta_id = ? AND (usuario_id IS NULL OR usuario_id = 0)", 'iii', [$usuarioId, $usuarioId, $personaId]);
            // El credito a favor de la ficha pasa a la cuenta, con su historial de movimientos.
            if (psc_creditos_listo($conn)) {
                psc_ejecutar($conn, 'UPDATE usuario_creditos SET usuario_id = ?, persona_sin_cuenta_id = NULL WHERE persona_sin_cuenta_id = ?', 'ii', [$usuarioId, $personaId]);
            }
            psc_ejecutar($conn, 'UPDATE personas_sin_cuenta SET usuario_id = ?, reconciliado_at = NOW(), reconciliado_por = ?, activo = 0 WHERE id = ?', 'iii',
                [$usuarioId, $actor, $personaId]);
            mysqli_commit($conn);
            return $movidas;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
}
