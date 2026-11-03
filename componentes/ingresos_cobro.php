<?php
// Cobro de una inscripcion a un sendero: calculo, lectura, guardado y presentacion.
//
// Todo el dinero se maneja en centavos (enteros) para no arrastrar redondeos.
// El mismo calculo existe en js/ingresos_sendero.js para que la pantalla muestre
// en vivo lo mismo que el servidor va a guardar; si cambias uno, cambia el otro.
//
// Modelo: de lo que se RECIBIO, una parte se queda para el sendero (retenido),
// otra queda como credito del usuario y otra se le devuelve. Retenido + credito
// + devuelto = recibido. En la base, monto_pagado guarda lo que se quedo
// (recibido - devuelto), asi los reportes existentes siguen cuadrando la caja.

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cargos.php';

if (!class_exists('IngresoValidacionException')) {
    // Error pensado para el administrador; se muestra tal cual.
    class IngresoValidacionException extends RuntimeException
    {
        // [['campo' => ..., 'mensaje' => ...]] para marcar los campos del panel.
        public array $errores = [];
    }
}

if (!function_exists('ic_cents')) {
    function ic_cents($valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        return (int) round(((float) str_replace(',', '', (string) $valor)) * 100);
    }

    function ic_dinero(int $centavos): string
    {
        return sg_money($centavos / 100);
    }

    function ic_decimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    // Lo que se suma para menores: su propio precio de menor, o el de la inversion.
    function ic_sql_monto_menor(string $alias): string
    {
        return "CASE WHEN COALESCE({$alias}.es_menor, 0) = 1 THEN {$alias}.monto ELSE COALESCE({$alias}.monto_menor, {$alias}.monto) END";
    }

    function ic_esquema_listo(mysqli $conn): bool
    {
        static $listo = null;
        if ($listo !== null) {
            return $listo;
        }
        $columna = mysqli_query($conn, "SHOW COLUMNS FROM contabilidad_registro_pagos LIKE 'monto_devuelto'");
        $tabla = mysqli_query($conn, "SHOW TABLES LIKE 'contabilidad_credito_aplicaciones'");
        $listo = $columna && mysqli_num_rows($columna) === 1 && $tabla && mysqli_num_rows($tabla) === 1;
        return $listo;
    }

    /**
     * Calcula el cobro de una inscripcion.
     *
     * $entrada (centavos salvo lo indicado): tipo (automatico|cortesia|exento), asistio (bool),
     *   descuento, credito, recibido, a_credito, devuelto, metodo_id (int), fecha (Y-m-d o '').
     * $contexto: valor (centavos), registrado (bool), credito_usable, generado_ya_usado,
     *   sendero_paso (bool: la fecha del sendero ya paso), hoy (Y-m-d).
     */
    function ic_calcular(array $entrada, array $contexto): array
    {
        $errores = [];
        $error = static function (string $campo, string $mensaje) use (&$errores): void {
            $errores[] = ['campo' => $campo, 'mensaje' => $mensaje];
        };

        $tipo = in_array($entrada['tipo'] ?? '', ['cortesia', 'exento'], true) ? $entrada['tipo'] : 'automatico';
        $asistio = !empty($entrada['asistio']);
        $valor = max(0, (int) ($contexto['valor'] ?? 0));
        $registrado = !empty($contexto['registrado']);
        $usable = max(0, (int) ($contexto['credito_usable'] ?? 0));
        $yaUsado = max(0, (int) ($contexto['generado_ya_usado'] ?? 0));
        $senderoPaso = !empty($contexto['sendero_paso']);
        $hoy = (string) ($contexto['hoy'] ?? date('Y-m-d'));

        foreach (['descuento', 'credito', 'recibido', 'a_credito', 'devuelto'] as $campo) {
            if ((int) ($entrada[$campo] ?? 0) < 0) {
                $error($campo, 'El monto no puede ser negativo.');
            }
        }
        $descuento = max(0, (int) ($entrada['descuento'] ?? 0));
        $credito = max(0, (int) ($entrada['credito'] ?? 0));
        $recibido = max(0, (int) ($entrada['recibido'] ?? 0));
        $aCredito = max(0, (int) ($entrada['a_credito'] ?? 0));
        $devuelto = max(0, (int) ($entrada['devuelto'] ?? 0));
        $metodoId = max(0, (int) ($entrada['metodo_id'] ?? 0));
        $fecha = trim((string) ($entrada['fecha'] ?? ''));

        // Cortesia y exento: no se debe nada. Si habia dinero recibido, hay que decidir
        // si queda como credito o se devuelve.
        if ($tipo !== 'automatico') {
            $descuento = 0;
            if ($credito > 0) {
                $error('credito', 'En cortesia o exento no se usa credito. Ponlo en 0.');
            }
        } elseif ($descuento > $valor) {
            $error('descuento', 'El descuento no puede ser mayor que el valor (' . ic_dinero($valor) . ').');
        }

        $debePagar = $tipo === 'automatico' ? max(0, $valor - $descuento) : 0;

        if ($credito > 0 && !$registrado) {
            $error('credito', 'Solo un usuario registrado puede usar credito.');
        } elseif ($credito > $usable) {
            $error('credito', 'Solo tiene ' . ic_dinero($usable) . ' de credito disponible.');
        } elseif ($credito > $debePagar && $tipo === 'automatico') {
            $error('credito', 'El credito no puede pasar de lo que debe pagar (' . ic_dinero($debePagar) . ').');
        }

        // Parte del cobro asumida por otro usuario (cuenta por cobrar a su nombre): cuenta como cubierta.
        $cargado = max(0, (int) ($entrada['cargado'] ?? 0));
        $cargadoA = max(0, (int) ($entrada['cargado_a'] ?? 0));
        if ($cargado > 0 && $tipo !== 'automatico') {
            $error('cargado', 'En cortesia o exento no se carga nada a otro usuario.');
        } elseif ($cargado > 0 && $cargadoA <= 0) {
            $error('cargado_a', 'Elige a que usuario se le carga.');
        } elseif ($cargado > max(0, $debePagar - min($credito, $debePagar))) {
            $error('cargado', 'Lo que se carga a otro usuario no puede pasar de lo que falta por pagar (' . ic_dinero(max(0, $debePagar - min($credito, $debePagar))) . ').');
        }
        if ($cargado <= 0) {
            $cargadoA = 0;
        }

        if ($aCredito > 0 && !$registrado) {
            $error('a_credito', 'Solo un usuario registrado puede quedar con credito. Devuelvelo.');
        }
        if ($aCredito + $devuelto > $recibido) {
            $error('a_credito', 'Lo que queda en credito y lo que se devuelve suman mas de lo recibido (' . ic_dinero($recibido) . ').');
        }
        if ($aCredito < $yaUsado) {
            $error('a_credito', 'De este credito ya se usaron ' . ic_dinero($yaUsado) . '; no puede quedar en menos.');
        }

        $necesario = max(0, $debePagar - min($credito, $debePagar) - $cargado);
        $retenido = max(0, $recibido - $aCredito - $devuelto);
        $exceso = max(0, $recibido - $necesario);
        $sinRepartir = max(0, $retenido - $necesario);
        if ($sinRepartir > 0) {
            $error('reparto', 'Recibio ' . ic_dinero($sinRepartir) . ' de mas: indica cuanto queda en credito y cuanto se devuelve.');
        } elseif ($tipo === 'automatico' && $asistio && $aCredito + $devuelto > $exceso) {
            // Quien asistio no puede quedar debiendo por haber pasado su pago a credito o devuelto.
            $error('reparto', $exceso > 0
                ? 'Asistio: solo se puede pasar a credito o devolver lo que pago de mas (' . ic_dinero($exceso) . '). Si el resto es un beneficio, ponlo como descuento.'
                : 'Asistio y no pago de mas: para dejarle credito o devolverle dinero, ese monto va como descuento.');
        }

        if ($recibido > 0) {
            // Cobros viejos sin metodo ni fecha se respetan mientras no cambie lo recibido.
            $recibidoCambio = !array_key_exists('recibido_guardado', $contexto) || $recibido !== (int) $contexto['recibido_guardado'];
            if ($metodoId <= 0 && $recibidoCambio) {
                $error('metodo', 'Indica el metodo de pago.');
            }
            if ($fecha === '' && $recibidoCambio) {
                $fecha = $hoy;
            }
            if ($fecha !== '') {
                $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
                if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
                    $error('fecha', 'La fecha de pago no es valida.');
                } elseif ($fecha > $hoy) {
                    $error('fecha', 'La fecha de pago no puede ser futura.');
                }
            }
        } else {
            $fecha = '';
            $metodoId = 0;
        }

        $retenidoEfectivo = min($retenido, $necesario);
        $falta = max(0, $debePagar - min($credito, $debePagar) - $cargado - $retenidoEfectivo);
        $modo = $asistio ? 'asistio' : ($senderoPaso ? 'no_asistio' : 'pendiente');
        // Quien no asistio a un sendero que ya paso no queda debiendo.
        $saldo = $modo === 'no_asistio' || $tipo !== 'automatico' ? 0 : $falta;

        $estadoCubierto = static function () use ($credito, $retenido, $descuento): string {
            if ($credito > 0 && $retenido <= 0 && $descuento <= 0) {
                return 'credito_aplicado';
            }
            if ($credito > 0) {
                return 'pagado_con_credito';
            }
            if ($descuento > 0) {
                return 'descuento';
            }
            return 'pagado';
        };

        if ($tipo !== 'automatico') {
            $estado = $tipo;
        } elseif ($valor <= 0) {
            $estado = !empty($contexto['directiva']) ? 'exento' : 'cortesia';
        } elseif ($modo === 'no_asistio') {
            $estado = ($retenido + $credito + $cargado) > 0 ? $estadoCubierto() : 'no_asistio_sin_pago';
        } elseif ($retenido + $credito + $descuento + $cargado >= $valor) {
            $estado = $estadoCubierto();
        } elseif ($retenido + $credito + $descuento + $cargado > 0) {
            $estado = 'parcial';
        } elseif ($asistio) {
            $estado = 'deuda';
        } else {
            $estado = 'pendiente';
        }

        $pagadoNeto = $recibido - $devuelto;
        $pagado = $pagadoNeto > 0 || in_array($estado, ['pagado', 'credito_aplicado', 'pagado_con_credito', 'descuento'], true);

        return [
            'tipo' => $tipo,
            'asistio' => $asistio,
            'modo' => $modo,
            'valor' => $valor,
            'descuento' => $descuento,
            'debe_pagar' => $debePagar,
            'credito' => $credito,
            'recibido' => $recibido,
            'a_credito' => $aCredito,
            'devuelto' => $devuelto,
            'cargado' => $cargado,
            'cargado_a' => $cargadoA,
            'retenido' => $retenido,
            'exceso' => $exceso,
            'necesario' => $necesario,
            'falta' => $falta,
            'saldo' => $saldo,
            'ingreso' => $retenido + $credito,
            'monto_pagado' => max(0, $pagadoNeto),
            'pagado' => $pagado,
            'estado' => $estado,
            'metodo_id' => $metodoId,
            'fecha' => $fecha,
            'errores' => $errores,
        ];
    }

    // Grupo para los filtros de la lista.
    function ic_grupo(array $calculo): string
    {
        if ($calculo['tipo'] !== 'automatico') {
            return 'al_dia';
        }
        if ($calculo['modo'] === 'no_asistio') {
            return 'no_asistio';
        }
        if ($calculo['saldo'] > 0) {
            return $calculo['modo'] === 'asistio' ? 'debe' : 'pendiente';
        }
        return 'al_dia';
    }

    /**
     * Inscripciones activas de un sendero con su cobro calculado.
     * Si $registroIds se indica, solo esas.
     */
    function ic_cargar(mysqli $conn, int $senderoId, array $registroIds = []): array
    {
        $menor = ic_sql_monto_menor('si2');
        $filtroIds = '';
        $registroIds = array_values(array_filter(array_map('intval', $registroIds)));
        if ($registroIds) {
            $filtroIds = ' AND rs.id IN (' . implode(',', $registroIds) . ')';
        }

        $columnaPersona = ic_tiene_personas($conn) ? 'COALESCE(rs.persona_sin_cuenta_id, 0)' : '0';
        $sqlDirectiva = sg_directiva_sql($conn);
        $cargoListo = ic_cargado_listo($conn);
        $colCargado = $cargoListo
            ? "COALESCE(crp.monto_cargado, 0) AS monto_cargado, COALESCE(crp.cargado_usuario_id, 0) AS cargado_usuario_id, TRIM(CONCAT(COALESCE(cu.nombre, ''), ' ', COALESCE(cu.apellido, ''))) AS cargado_nombre,"
            : "0 AS monto_cargado, 0 AS cargado_usuario_id, '' AS cargado_nombre,";
        $joinCargado = $cargoListo ? 'LEFT JOIN usuarios cu ON cu.id = crp.cargado_usuario_id' : '';
        $sql = "SELECT
                rs.id AS registro_id,
                rs.asistio,
                s.fecha_sendero,
                COALESCE(u.id, 0) AS usuario_id,
                {$columnaPersona} AS persona_id,
                TRIM(CONCAT(COALESCE(u.nombre, rs.manual_nombre, 'Asistente'), ' ', COALESCE(u.apellido, rs.manual_apellido, ''))) AS nombre,
                COALESCE(u.user, '') AS usuario,
                COALESCE(ro.nombre, '') AS rol_nombre,
                {$sqlDirectiva} AS es_directiva,
                COALESCE(u.email, rs.manual_email, '') AS email,
                COALESCE(du.telefono, rs.manual_telefono, '') AS telefono,
                COALESCE(si.nombre, 'Sin inversion') AS inversion_nombre,
                COALESCE(si.monto, 0) AS inversion_monto,
                COALESCE(m.total_menores, 0) AS total_menores,
                COALESCE(m.total_menores_monto, 0) AS total_menores_monto,
                crp.estado_financiero,
                COALESCE(crp.monto_pagado, 0) AS monto_pagado,
                COALESCE(crp.monto_devuelto, 0) AS monto_devuelto,
                COALESCE(crp.credito_aplicado, 0) AS credito_aplicado,
                COALESCE(crp.descuento_autorizado, 0) AS descuento_autorizado,
                COALESCE(crp.credito_generado, 0) AS credito_generado,
                {$colCargado}
                crp.fecha_pago,
                crp.metodo_pago_id,
                COALESCE(mp.nombre, crp.metodo_pago, '') AS metodo_nombre,
                COALESCE(crp.nota, '') AS nota,
                COALESCE(crp.updated_at, '') AS version
            FROM registros_senderos rs
            INNER JOIN senderos s ON s.id = rs.sendero_id
            LEFT JOIN usuarios u ON u.id = rs.usuario_id
            LEFT JOIN roles ro ON ro.id = u.rol_id
            LEFT JOIN detalles_usuarios du ON du.id = rs.detalle_usuario_id
            LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
            LEFT JOIN (
                SELECT rsm.registro_id, COUNT(*) AS total_menores, COALESCE(SUM({$menor}), 0) AS total_menores_monto
                FROM registro_sendero_menores rsm
                LEFT JOIN sendero_inversiones si2 ON si2.id = rsm.inversion_id
                GROUP BY rsm.registro_id
            ) m ON m.registro_id = rs.id
            LEFT JOIN contabilidad_registro_pagos crp ON crp.registro_id = rs.id
            LEFT JOIN contabilidad_metodo_pago mp ON mp.id = crp.metodo_pago_id
            {$joinCargado}
            WHERE rs.sendero_id = ? AND rs.estado = 'registrado'{$filtroIds}
            ORDER BY COALESCE(u.nombre, rs.manual_nombre) ASC, COALESCE(u.apellido, rs.manual_apellido) ASC, rs.id ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $filas = [];
        $usuarios = [];
        $personas = [];
        $creditoPersonas = ic_creditos_personas_listo($conn);
        while ($fila = mysqli_fetch_assoc($res)) {
            $fila['dueno'] = ic_dueno((int) $fila['usuario_id'], (int) $fila['persona_id'], $creditoPersonas);
            $filas[] = $fila;
            if ((int) $fila['usuario_id'] > 0) {
                $usuarios[(int) $fila['usuario_id']] = true;
            } elseif ($fila['dueno'] !== '') {
                $personas[(int) $fila['persona_id']] = true;
            }
        }
        mysqli_stmt_close($stmt);

        $creditos = ic_creditos_de_duenos($conn, array_keys($usuarios), array_keys($personas));
        $hoy = date('Y-m-d');

        foreach ($filas as &$fila) {
            $registroId = (int) $fila['registro_id'];
            [$usable, $yaUsado] = ic_credito_para_registro($creditos[$fila['dueno']] ?? [], $registroId);
            $tipo = in_array($fila['estado_financiero'], ['cortesia', 'exento'], true) ? $fila['estado_financiero'] : 'automatico';
            // Miembro de la directiva: su propia participacion no se cobra (sus menores si), aunque la inscripcion tenga inversion.
            $esDirectiva = (int) ($fila['es_directiva'] ?? 0) === 1;
            $valor = ($esDirectiva ? 0 : ic_cents($fila['inversion_monto'])) + ic_cents($fila['total_menores_monto']);
            $entrada = [
                'tipo' => $tipo,
                'asistio' => (int) $fila['asistio'] === 1,
                'descuento' => ic_cents($fila['descuento_autorizado']),
                'credito' => ic_cents($fila['credito_aplicado']),
                'recibido' => ic_cents($fila['monto_pagado']) + ic_cents($fila['monto_devuelto']),
                'a_credito' => ic_cents($fila['credito_generado']),
                'devuelto' => ic_cents($fila['monto_devuelto']),
                'cargado' => ic_cents($fila['monto_cargado']),
                'cargado_a' => (int) $fila['cargado_usuario_id'],
                'metodo_id' => (int) ($fila['metodo_pago_id'] ?? 0),
                'fecha' => (string) ($fila['fecha_pago'] ?? ''),
            ];
            $contexto = [
                'valor' => $valor,
                // Puede usar o recibir credito quien tiene cuenta o ficha de persona sin cuenta.
                'registrado' => $fila['dueno'] !== '',
                'credito_usable' => $usable,
                'generado_ya_usado' => $yaUsado,
                'sendero_paso' => (string) $fila['fecha_sendero'] < $hoy,
                // Miembro de la directiva: su participacion no se cobra (los menores si).
                'directiva' => $esDirectiva,
                'hoy' => $hoy,
                'recibido_guardado' => $entrada['recibido'],
            ];
            $calculo = ic_calcular($entrada, $contexto);
            // En la lista solo se marcan los problemas de dinero; un metodo de pago
            // vacio en cobros viejos no impide verlos.
            $calculo['revisar'] = array_values(array_filter(
                $calculo['errores'],
                static fn(array $e): bool => !in_array($e['campo'], ['metodo', 'fecha'], true)
            ));
            $fila['entrada'] = $entrada;
            $fila['contexto'] = $contexto;
            $fila['calculo'] = $calculo;
            $fila['nota'] = (string) $fila['nota'];
        }
        unset($fila);

        return $filas;
    }

    // Cargo de un cobro a otro usuario (actualizacion de cargos por cobrar).
    function ic_cargado_listo(mysqli $conn): bool
    {
        static $listo = null;
        if ($listo === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM contabilidad_registro_pagos LIKE 'cargado_usuario_id'");
            $listo = $res && mysqli_num_rows($res) === 1;
        }
        return $listo;
    }

    // La inscripcion puede apuntar a una ficha de persona sin cuenta (actualizacion 014).
    function ic_tiene_personas(mysqli $conn): bool
    {
        static $existe = null;
        if ($existe === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM registros_senderos LIKE 'persona_sin_cuenta_id'");
            $existe = $res && mysqli_num_rows($res) === 1;
        }
        return $existe;
    }

    // Un credito puede pertenecer a una persona sin cuenta (actualizacion 015).
    function ic_creditos_personas_listo(mysqli $conn): bool
    {
        static $existe = null;
        if ($existe === null) {
            $res = mysqli_query($conn, "SHOW COLUMNS FROM usuario_creditos LIKE 'persona_sin_cuenta_id'");
            $existe = $res && mysqli_num_rows($res) === 1;
        }
        return $existe;
    }

    /** Dueno de los creditos de una inscripcion: 'u<usuario>', 'p<persona sin cuenta>' o '' (no puede tener). */
    function ic_dueno(int $usuarioId, int $personaId, bool $creditoPersonas): string
    {
        if ($usuarioId > 0) {
            return 'u' . $usuarioId;
        }
        return $creditoPersonas && $personaId > 0 ? 'p' . $personaId : '';
    }

    /**
     * Creditos de los duenos indicados: ['u12' => [credito_id => [...]], 'p21' => [...]],
     * con lo aplicado por cada inscripcion.
     */
    function ic_creditos_de_duenos(mysqli $conn, array $usuarioIds, array $personaIds = []): array
    {
        $usuarioIds = array_values(array_filter(array_map('intval', $usuarioIds)));
        $personaIds = ic_creditos_personas_listo($conn) ? array_values(array_filter(array_map('intval', $personaIds))) : [];
        $condiciones = [];
        if ($usuarioIds) {
            $condiciones[] = 'uc.usuario_id IN (' . implode(',', $usuarioIds) . ')';
        }
        if ($personaIds) {
            $condiciones[] = '(uc.usuario_id IS NULL AND uc.persona_sin_cuenta_id IN (' . implode(',', $personaIds) . '))';
        }
        if (!$condiciones) {
            return [];
        }
        $donde = implode(' OR ', $condiciones);
        $columnaPersona = $personaIds || ic_creditos_personas_listo($conn) ? 'uc.persona_sin_cuenta_id' : 'NULL';
        $claveDe = static fn(array $c): string => !empty($c['usuario_id']) ? 'u' . (int) $c['usuario_id'] : 'p' . (int) $c['persona_id'];

        $creditos = [];
        $res = mysqli_query(
            $conn,
            "SELECT uc.id, uc.usuario_id, {$columnaPersona} AS persona_id, uc.registro_origen_id, uc.monto, uc.estado
             FROM usuario_creditos uc WHERE {$donde}"
        );
        while ($res && $c = mysqli_fetch_assoc($res)) {
            $creditos[$claveDe($c)][(int) $c['id']] = [
                'id' => (int) $c['id'],
                'origen' => (int) ($c['registro_origen_id'] ?? 0),
                'monto' => ic_cents($c['monto']),
                'estado' => (string) $c['estado'],
                'aplicaciones' => [],
            ];
        }
        $res = mysqli_query(
            $conn,
            "SELECT a.credito_id, a.registro_id, a.monto, uc.usuario_id, {$columnaPersona} AS persona_id
             FROM contabilidad_credito_aplicaciones a
             INNER JOIN usuario_creditos uc ON uc.id = a.credito_id
             WHERE {$donde}"
        );
        while ($res && $a = mysqli_fetch_assoc($res)) {
            $clave = $claveDe($a);
            $credito = (int) $a['credito_id'];
            if (isset($creditos[$clave][$credito])) {
                $creditos[$clave][$credito]['aplicaciones'][(int) $a['registro_id']] = ic_cents($a['monto']);
            }
        }
        return $creditos;
    }

    // Lo que queda libre de un credito sin contar lo que usa la inscripcion indicada.
    function ic_credito_libre(array $credito, int $registroId): int
    {
        if (!in_array($credito['estado'], ['activo', 'usado'], true)) {
            return 0;
        }
        $usadoPorOtros = 0;
        foreach ($credito['aplicaciones'] as $registro => $monto) {
            if ($registro !== $registroId) {
                $usadoPorOtros += $monto;
            }
        }
        return max(0, $credito['monto'] - $usadoPorOtros);
    }

    /**
     * [credito que esta inscripcion puede usar, lo ya usado del credito que ella genero].
     * No puede usar el credito que ella misma genero.
     */
    function ic_credito_para_registro(array $creditosUsuario, int $registroId): array
    {
        $usable = 0;
        $yaUsado = 0;
        foreach ($creditosUsuario as $credito) {
            if ($credito['origen'] === $registroId) {
                foreach ($credito['aplicaciones'] as $registro => $monto) {
                    if ($registro !== $registroId) {
                        $yaUsado += $monto;
                    }
                }
                continue;
            }
            $usable += ic_credito_libre($credito, $registroId);
        }
        return [$usable, $yaUsado];
    }

    function ic_totales(array $filas): array
    {
        $t = [
            'inscritos' => 0, 'asistieron' => 0, 'no_asistieron' => 0, 'con_pago' => 0,
            'esperado' => 0, 'recibido' => 0, 'devuelto' => 0, 'credito_aplicado' => 0, 'credito_generado' => 0,
            'descuento' => 0, 'retenido' => 0, 'ingreso' => 0, 'por_cobrar' => 0, 'pendiente' => 0, 'revisar' => 0, 'cargado' => 0,
            'grupos' => ['debe' => 0, 'pendiente' => 0, 'al_dia' => 0, 'no_asistio' => 0],
        ];
        foreach ($filas as $fila) {
            $c = $fila['calculo'];
            $personas = 1 + (int) $fila['total_menores'];
            $t['inscritos'] += $personas;
            $t['asistieron'] += $c['asistio'] ? $personas : 0;
            $t['no_asistieron'] += $c['modo'] === 'no_asistio' ? $personas : 0;
            $t['con_pago'] += ($c['recibido'] > 0 || $c['credito'] > 0) ? 1 : 0;
            if ($c['tipo'] === 'automatico') {
                $t['esperado'] += $c['debe_pagar'];
            }
            $t['recibido'] += $c['recibido'];
            $t['devuelto'] += $c['devuelto'];
            $t['credito_aplicado'] += $c['credito'];
            $t['credito_generado'] += $c['a_credito'];
            $t['descuento'] += $c['descuento'];
            $t['cargado'] += $c['cargado'];
            $t['retenido'] += $c['retenido'];
            $t['ingreso'] += $c['ingreso'];
            if ($c['modo'] === 'asistio') {
                $t['por_cobrar'] += $c['saldo'];
            } elseif ($c['modo'] === 'pendiente') {
                $t['pendiente'] += $c['saldo'];
            }
            $t['revisar'] += $c['revisar'] ? 1 : 0;
            $t['grupos'][ic_grupo($c)]++;
        }
        return $t;
    }

    function ic_render_resumen(array $t): string
    {
        $h = 'sg_h';
        $kpi = static function (string $titulo, string $valor, string $nota, string $clase = '') use ($h): string {
            return '<article class="ing-kpi ' . $clase . '"><span>' . $h($titulo) . '</span><strong>' . $h($valor) . '</strong>'
                . ($nota !== '' ? '<small>' . $h($nota) . '</small>' : '') . '</article>';
        };
        $html = '<div class="ing-kpis">';
        $html .= $kpi('Esperado', ic_dinero($t['esperado']), 'Valor de todas las inscripciones, menos descuentos, cortesias y exentos');
        $html .= $kpi('Ingreso del sendero', ic_dinero($t['ingreso']), 'Efectivo retenido + credito usado', 'is-main');
        $html .= $kpi('Por cobrar', ic_dinero($t['por_cobrar']), 'Asistieron y aun deben', $t['por_cobrar'] > 0 ? 'is-alert' : '');
        $html .= $kpi('Recibido', ic_dinero($t['recibido']), $t['devuelto'] > 0 ? 'Devuelto: ' . ic_dinero($t['devuelto']) : 'Efectivo y transferencias');
        $html .= $kpi('Creditos', ic_dinero($t['credito_aplicado']) . ' usados', 'Generados: ' . ic_dinero($t['credito_generado']));
        $html .= '</div>';

        $detalle = [
            ['Inscritos (con menores)', (string) $t['inscritos']],
            ['Asistieron', (string) $t['asistieron']],
            ['No asistieron', (string) $t['no_asistieron']],
            ['Con algun pago', (string) $t['con_pago']],
            ['Descuentos', ic_dinero($t['descuento'])],
            ['Cargado a otros usuarios (por cobrar en Balances)', ic_dinero($t['cargado'])],
            ['Efectivo retenido', ic_dinero($t['retenido'])],
            ['Devuelto', ic_dinero($t['devuelto'])],
            ['Credito usado', ic_dinero($t['credito_aplicado'])],
            ['Credito generado', ic_dinero($t['credito_generado'])],
            ['Pendiente (aun no ocurre)', ic_dinero($t['pendiente'])],
        ];
        $html .= '<details class="ing-detalle"><summary>Ver detalle del sendero</summary><dl>';
        foreach ($detalle as [$titulo, $valor]) {
            $html .= '<div><dt>' . $h($titulo) . '</dt><dd>' . $h($valor) . '</dd></div>';
        }
        $html .= '</dl></details>';
        return $html;
    }

    // Datos que el panel de cobro necesita para abrir una fila (centavos).
    function ic_datos_panel(array $fila): array
    {
        $e = $fila['entrada'];
        $c = $fila['contexto'];
        $detalle = !empty($c['directiva']) ? 'Directiva: sin cobro propio' : $fila['inversion_nombre'] . ' ' . ic_dinero(ic_cents($fila['inversion_monto']));
        if ((int) $fila['total_menores'] > 0) {
            $detalle .= ' + ' . (int) $fila['total_menores'] . ' menor(es) ' . ic_dinero(ic_cents($fila['total_menores_monto']));
        }
        return [
            'registro_id' => (int) $fila['registro_id'],
            'nombre' => $fila['nombre'],
            'detalle' => $detalle,
            'directiva' => !empty($c['directiva']),
            'version' => (string) $fila['version'],
            'tipo' => $e['tipo'],
            'asistio' => $e['asistio'],
            'descuento' => $e['descuento'],
            'credito' => $e['credito'],
            'recibido' => $e['recibido'],
            'a_credito' => $e['a_credito'],
            'devuelto' => $e['devuelto'],
            'cargado' => $e['cargado'],
            'cargado_a' => $e['cargado_a'],
            'metodo_id' => $e['metodo_id'],
            'fecha' => $e['fecha'],
            'nota' => $fila['nota'],
            'valor' => $c['valor'],
            'registrado' => $c['registrado'],
            'credito_usable' => $c['credito_usable'],
            'generado_ya_usado' => $c['generado_ya_usado'],
            'sendero_paso' => $c['sendero_paso'],
            'recibido_guardado' => $c['recibido_guardado'],
            'hoy' => $c['hoy'],
        ];
    }

    function ic_render_fila(array $fila, array $catalogo): string
    {
        $h = 'sg_h';
        $c = $fila['calculo'];
        $rid = (int) $fila['registro_id'];
        $estado = $c['estado'];
        $estadoNombre = $catalogo[$estado]['nombre'] ?? sg_financial_status_label($estado);
        $estadoColor = $catalogo[$estado]['color'] ?? '#64748B';
        $grupo = ic_grupo($c);
        $buscar = $fila['nombre'] . ' ' . $fila['usuario'] . ' ' . $fila['telefono'] . ' ' . $fila['inversion_nombre'];

        $contacto = array_filter([$fila['usuario'] !== '' ? '@' . $fila['usuario'] : 'Inscripcion manual', $fila['telefono']]);
        $inversion = $fila['inversion_nombre'] . ((int) $fila['total_menores'] > 0 ? ' · ' . (int) $fila['total_menores'] . ' menor(es)' : '');

        // Columna "A pagar".
        $aPagar = '<strong>' . $h(ic_dinero($c['debe_pagar'])) . '</strong>';
        if ($c['tipo'] !== 'automatico') {
            $aPagar .= '<small>' . $h($c['tipo'] === 'cortesia' ? 'Cortesia' : 'Exento') . ' · valor ' . $h(ic_dinero($c['valor'])) . '</small>';
        } elseif ($c['descuento'] > 0) {
            $aPagar .= '<small>Valor ' . $h(ic_dinero($c['valor'])) . ' − desc. ' . $h(ic_dinero($c['descuento'])) . '</small>';
        }

        if ($c['cargado'] > 0) {
            $aPagar .= '<small>Cargado a ' . $h(trim((string) ($fila['cargado_nombre'] ?? '')) ?: 'otro usuario') . ': ' . $h(ic_dinero($c['cargado'])) . '</small>';
        }

        // Columna "Como pago": solo lo que tiene valor.
        $partes = [];
        if ($c['recibido'] > 0) {
            $partes[] = ['Recibido', $c['recibido'], ''];
        }
        if ($c['credito'] > 0) {
            $partes[] = ['Uso credito', $c['credito'], 'is-credit'];
        }
        if ($c['a_credito'] > 0) {
            $partes[] = ['Paso a credito', $c['a_credito'], 'is-credit'];
        }
        if ($c['devuelto'] > 0) {
            $partes[] = ['Devuelto', $c['devuelto'], 'is-refund'];
        }
        if ($c['cargado'] > 0) {
            $partes[] = ['A cuenta de otro usuario', $c['cargado'], 'is-credit'];
        }
        $comoPago = '';
        foreach ($partes as [$titulo, $monto, $clase]) {
            $comoPago .= '<span class="ing-chip ' . $clase . '">' . $h($titulo) . ' <b>' . $h(ic_dinero($monto)) . '</b></span>';
        }
        if ($comoPago === '') {
            $comoPago = '<span class="ing-muted">Sin pagos</span>';
        }

        // Columna "Balance".
        if ($c['tipo'] !== 'automatico') {
            $balance = '<span class="ing-balance is-ok">Sin cobro</span>';
        } elseif ($c['modo'] === 'no_asistio') {
            $balance = '<span class="ing-balance is-muted">No asistio</span>';
        } elseif ($c['saldo'] > 0) {
            $balance = '<span class="ing-balance ' . ($c['modo'] === 'asistio' ? 'is-debt' : 'is-pending') . '">'
                . ($c['modo'] === 'asistio' ? 'Debe ' : 'Falta ') . $h(ic_dinero($c['saldo'])) . '</span>';
        } else {
            $balance = '<span class="ing-balance is-ok">Al dia</span>';
        }

        $revisar = '';
        if ($c['revisar']) {
            $mensajes = implode(' ', array_map(static fn(array $e): string => $e['mensaje'], $c['revisar']));
            $revisar = '<span class="ing-revisar" title="' . $h($mensajes) . '"><i data-feather="alert-triangle"></i> Revisar</span>';
        }
        $tieneCobro = $c['recibido'] > 0 || $c['credito'] > 0 || $c['descuento'] > 0 || $c['tipo'] !== 'automatico';
        // Pago rapido: el valor completo en un clic, solo a quien no tiene nada registrado
        // (misma condicion que la accion cobro_completo del servidor).
        $pagoRapido = '';
        if ($c['tipo'] === 'automatico' && $c['recibido'] === 0 && $c['credito'] === 0 && $c['debe_pagar'] > 0) {
            $pagoRapido = '<button type="button" class="ing-btn is-primary" data-pago-rapido title="Registrar el pago completo con el metodo elegido arriba">'
                . '<i data-feather="check"></i>Pagar ' . $h(ic_dinero($c['debe_pagar'])) . '</button>';
        }

        $datos = json_encode(ic_datos_panel($fila), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return '<tr data-fila data-registro="' . $rid . '" data-grupo="' . $h($grupo) . '" data-revisar="' . ($c['revisar'] ? '1' : '0') . '"'
            . ' data-buscar="' . $h($buscar) . '" data-cobro="' . $h($datos) . '">'
            . '<td class="ing-col-asis"><label class="ing-switch" title="Asistio"><input type="checkbox" data-asistencia'
            . ($c['asistio'] ? ' checked' : '') . ' aria-label="Asistio ' . $h($fila['nombre']) . '"><span></span></label></td>'
            . '<td class="ing-col-persona"><strong>' . $h($fila['nombre']) . '</strong><small>' . $h(implode(' · ', $contacto)) . '</small>'
            . '<small>' . $h($inversion) . '</small></td>'
            . '<td class="ing-col-num" data-label="A pagar">' . $aPagar . '</td>'
            . '<td class="ing-col-pago" data-label="Como pago"><div class="ing-chips">' . $comoPago . '</div></td>'
            . '<td class="ing-col-balance" data-label="Balance">' . $balance . '</td>'
            . '<td class="ing-col-estado" data-label="Estado"><span class="ing-estado" style="--estado: ' . $h($estadoColor) . '">' . $h($estadoNombre) . '</span>' . $revisar . '</td>'
            . '<td class="ing-col-accion"><div class="ing-botones">' . $pagoRapido
            . ($pagoRapido !== ''
                // Junto al pago rapido el detalle va solo con icono para que la fila quepa.
                ? '<button type="button" class="ing-btn is-icono" data-abrir-cobro title="Detalle: descuento, credito, devolucion, cortesia" aria-label="Detalle del cobro de ' . $h($fila['nombre']) . '"><i data-feather="sliders"></i></button>'
                : '<button type="button" class="ing-btn" data-abrir-cobro title="Abrir el cobro con todas las opciones"><i data-feather="' . ($tieneCobro ? 'edit-3' : 'sliders') . '"></i>' . ($tieneCobro ? 'Editar' : 'Detalle') . '</button>')
            . '</div></td>'
            . '</tr>';
    }

    /**
     * Guarda el cobro de una inscripcion. $datos llega del panel (montos en pesos).
     * Lanza IngresoValidacionException con el mensaje para el administrador.
     */
    function ic_guardar(mysqli $conn, int $senderoId, int $registroId, array $datos, int $adminId): array
    {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT rs.id, rs.usuario_id, rs.sendero_id FROM registros_senderos rs
             WHERE rs.id = ? AND rs.sendero_id = ? AND rs.estado = 'registrado' FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $registroId, $senderoId);
        mysqli_stmt_execute($stmt);
        $registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$registro) {
            throw new IngresoValidacionException('Esa inscripcion ya no esta activa en este sendero. Recarga la pagina.');
        }
        $usuarioId = (int) ($registro['usuario_id'] ?? 0);
        $personaId = 0;
        if ($usuarioId <= 0 && ic_tiene_personas($conn)) {
            $res = mysqli_query($conn, "SELECT COALESCE(persona_sin_cuenta_id, 0) AS p FROM registros_senderos WHERE id = " . (int) $registroId);
            $personaId = (int) (($res ? mysqli_fetch_assoc($res) : null)['p'] ?? 0);
        }

        // Bloquea el cobro y los creditos del usuario mientras se calcula.
        $stmt = mysqli_prepare($conn, "SELECT updated_at FROM contabilidad_registro_pagos WHERE registro_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $registroId);
        mysqli_stmt_execute($stmt);
        $pagoActual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $versionActual = (string) ($pagoActual['updated_at'] ?? '');
        if (array_key_exists('version', $datos) && (string) $datos['version'] !== $versionActual) {
            throw new IngresoValidacionException('Otra persona cambio este cobro mientras lo editabas. Cierra el panel y vuelve a abrirlo.');
        }
        if ($usuarioId > 0) {
            mysqli_query($conn, "SELECT id FROM usuario_creditos WHERE usuario_id = " . $usuarioId . " FOR UPDATE");
        } elseif ($personaId > 0 && ic_creditos_personas_listo($conn)) {
            mysqli_query($conn, "SELECT id FROM usuario_creditos WHERE persona_sin_cuenta_id = " . $personaId . " FOR UPDATE");
        }

        $filas = ic_cargar($conn, $senderoId, [$registroId]);
        if (!$filas) {
            throw new IngresoValidacionException('No se encontro la inscripcion.');
        }
        $fila = $filas[0];

        $entrada = [
            'tipo' => (string) ($datos['tipo'] ?? 'automatico'),
            'asistio' => !empty($datos['asistio']),
            'descuento' => ic_cents($datos['descuento'] ?? 0),
            'credito' => ic_cents($datos['credito'] ?? 0),
            'recibido' => ic_cents($datos['recibido'] ?? 0),
            'a_credito' => ic_cents($datos['a_credito'] ?? 0),
            'devuelto' => ic_cents($datos['devuelto'] ?? 0),
            'cargado' => ic_cents($datos['cargado'] ?? 0),
            'cargado_a' => (int) ($datos['cargado_a'] ?? 0),
            'metodo_id' => (int) ($datos['metodo_id'] ?? 0),
            'fecha' => (string) ($datos['fecha'] ?? ''),
        ];
        $calculo = ic_calcular($entrada, $fila['contexto']);
        if ($calculo['errores']) {
            $e = new IngresoValidacionException($fila['nombre'] . ': ' . $calculo['errores'][0]['mensaje']);
            $e->errores = $calculo['errores'];
            throw $e;
        }

        $metodoNombre = '';
        if ($calculo['metodo_id'] > 0) {
            $stmt = mysqli_prepare($conn, "SELECT nombre FROM contabilidad_metodo_pago WHERE id = ? AND activo = 1");
            mysqli_stmt_bind_param($stmt, 'i', $calculo['metodo_id']);
            mysqli_stmt_execute($stmt);
            $metodoNombre = (string) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['nombre'] ?? '');
            mysqli_stmt_close($stmt);
            if ($metodoNombre === '') {
                throw new IngresoValidacionException($fila['nombre'] . ': el metodo de pago elegido no esta activo.');
            }
        }

        if (($calculo['cargado'] > 0 || $fila['entrada']['cargado'] > 0) && !ic_cargado_listo($conn)) {
            throw new IngresoValidacionException('Falta aplicar la actualizacion pendiente del sistema para cargar un cobro a otro usuario.');
        }
        if ($calculo['cargado'] > 0 && $calculo['cargado_a'] === (int) $usuarioId) {
            throw new IngresoValidacionException($fila['nombre'] . ': no se puede cargar su propio cobro a si mismo; usa un cobro normal.');
        }

        $creditoPrincipal = ic_aplicar_credito($conn, $fila, $calculo['credito'], $senderoId, $adminId);
        ic_generar_credito($conn, $fila, $calculo, $senderoId, $adminId);

        $nota = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($datos['nota'] ?? ''))), 0, 255);
        $pagado = $calculo['pagado'] ? 1 : 0;
        $estado = $calculo['estado'];
        $esperado = ic_decimal($calculo['tipo'] === 'exento' ? 0 : $calculo['valor']);
        $montoPagado = ic_decimal($calculo['monto_pagado']);
        $creditoAplicado = ic_decimal($calculo['credito']);
        $descuento = ic_decimal($calculo['descuento']);
        $saldo = ic_decimal($calculo['saldo']);
        $creditoGenerado = ic_decimal($calculo['a_credito']);
        $retenido = ic_decimal($calculo['retenido']);
        $devuelto = ic_decimal($calculo['devuelto']);
        $fecha = $calculo['fecha'] !== '' ? $calculo['fecha'] : null;
        $metodoId = $calculo['metodo_id'] > 0 ? $calculo['metodo_id'] : null;

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO contabilidad_registro_pagos
                (registro_id, sendero_id, pagado, estado_financiero, monto_esperado, monto_pagado, credito_aplicado,
                 descuento_autorizado, saldo_pendiente, credito_id, credito_generado, monto_retenido, monto_devuelto,
                 fecha_pago, metodo_pago, metodo_pago_id, nota)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                sendero_id = VALUES(sendero_id), pagado = VALUES(pagado), estado_financiero = VALUES(estado_financiero),
                monto_esperado = VALUES(monto_esperado), monto_pagado = VALUES(monto_pagado),
                credito_aplicado = VALUES(credito_aplicado), descuento_autorizado = VALUES(descuento_autorizado),
                saldo_pendiente = VALUES(saldo_pendiente), credito_id = VALUES(credito_id),
                credito_generado = VALUES(credito_generado), monto_retenido = VALUES(monto_retenido),
                monto_devuelto = VALUES(monto_devuelto), fecha_pago = VALUES(fecha_pago),
                metodo_pago = VALUES(metodo_pago), metodo_pago_id = VALUES(metodo_pago_id), nota = VALUES(nota)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'iiissssssisssssis',
            $registroId,
            $senderoId,
            $pagado,
            $estado,
            $esperado,
            $montoPagado,
            $creditoAplicado,
            $descuento,
            $saldo,
            $creditoPrincipal,
            $creditoGenerado,
            $retenido,
            $devuelto,
            $fecha,
            $metodoNombre,
            $metodoId,
            $nota
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('No se pudo guardar el cobro: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        if (ic_cargado_listo($conn)) {
            $obligacionId = ic_sincronizar_cargo($conn, $fila, $calculo, $senderoId, $registroId, $adminId);
            $montoCargado = ic_decimal($calculo['cargado']);
            $cargadoA = $calculo['cargado'] > 0 ? $calculo['cargado_a'] : null;
            $stmt = mysqli_prepare($conn, 'UPDATE contabilidad_registro_pagos SET monto_cargado = ?, cargado_usuario_id = ?, obligacion_id = ? WHERE registro_id = ?');
            mysqli_stmt_bind_param($stmt, 'siii', $montoCargado, $cargadoA, $obligacionId, $registroId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $asistio = $calculo['asistio'] ? 1 : 0;
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE registros_senderos
             SET asistio = ?,
                 fecha_asistencia = CASE WHEN ? = 1 THEN COALESCE(fecha_asistencia, NOW()) ELSE NULL END,
                 asistencia_marcada_por = CASE WHEN ? = 1 THEN COALESCE(asistencia_marcada_por, ?) ELSE NULL END
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'iiiii', $asistio, $asistio, $asistio, $adminId, $registroId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return ['nombre' => $fila['nombre'], 'calculo' => $calculo];
    }

    /**
     * Mantiene la cuenta por cobrar (Balances) del monto cargado a otro usuario: la crea, la actualiza o la anula.
     * Devuelve su id o null si ya no hay nada cargado. Una cuenta que ya tiene cobros no se cambia.
     */
    function ic_sincronizar_cargo(mysqli $conn, array $fila, array $calculo, int $senderoId, int $registroId, int $adminId): ?int
    {
        require_once __DIR__ . '/balances_servicio.php';
        try {
            $previo = bal_one($conn, 'SELECT obligacion_id FROM contabilidad_registro_pagos WHERE registro_id = ?', [$registroId]);
            $obligacionId = (int) ($previo['obligacion_id'] ?? 0);
            $obligacion = $obligacionId > 0 ? bal_one($conn, 'SELECT * FROM fin_obligaciones WHERE id = ? AND anulada = 0 FOR UPDATE', [$obligacionId]) : [];
            $monto = (int) $calculo['cargado'];

            if ($monto <= 0) {
                if ($obligacion) {
                    if (bal_obligation_paid($conn, $obligacionId, 'ingreso') !== 0) {
                        throw new IngresoValidacionException($fila['nombre'] . ': la cuenta por cobrar a otro usuario ya tiene cobros. Revierte sus cobros en Balances antes de quitarla.');
                    }
                    bal_query($conn, 'UPDATE fin_obligaciones SET anulada = 1 WHERE id = ?', [$obligacionId]);
                    bal_audit($conn, $adminId, 'anular', 'obligacion', $obligacionId, $obligacion, ['motivo' => 'cobro de sendero sin cargo a otro usuario']);
                }
                return null;
            }

            $tercero = bal_tercero_formulario($conn, ['cargar_usuario' => 1, 'usuario_id' => $calculo['cargado_a']], $adminId, true);
            $sendero = bal_one($conn, 'SELECT nombre FROM senderos WHERE id = ?', [$senderoId]);
            $concepto = mb_substr('Sendero ' . ($sendero['nombre'] ?? '') . ': ' . $fila['nombre'], 0, 200);

            if ($obligacion) {
                $cambia = bal_cents($obligacion['monto']) !== $monto || (int) $obligacion['tercero_id'] !== (int) $tercero['id'];
                if ($cambia && bal_obligation_paid($conn, $obligacionId, 'ingreso') !== 0) {
                    throw new IngresoValidacionException($fila['nombre'] . ': la cuenta por cobrar a otro usuario ya tiene cobros; no se puede cambiar el monto ni el usuario.');
                }
                if ($cambia) {
                    bal_query($conn, 'UPDATE fin_obligaciones SET concepto = ?, tercero_id = ?, monto = ?, sendero_id = ? WHERE id = ?',
                        [$concepto, $tercero['id'], bal_decimal($monto), $senderoId, $obligacionId]);
                    bal_audit($conn, $adminId, 'editar', 'obligacion', $obligacionId, $obligacion, ['tercero_id' => $tercero['id'], 'monto' => bal_decimal($monto)]);
                }
                return $obligacionId;
            }

            $tipo = bal_one($conn, "SELECT id FROM fin_tipos WHERE naturaleza = 'ingreso' AND activo = 1 ORDER BY (nombre LIKE '%senderos%') DESC, id LIMIT 1");
            if (!$tipo) {
                throw new IngresoValidacionException('No hay un tipo de ingreso activo en Balances para crear la cuenta por cobrar.');
            }
            if (!bal_usuario_sendero_listo($conn)) {
                throw new IngresoValidacionException('Falta aplicar la actualizacion pendiente del sistema para cargar un cobro a otro usuario.');
            }
            bal_query($conn, 'INSERT INTO fin_obligaciones (concepto,naturaleza,tercero_id,tipo_id,monto,vencimiento,creado_por,sendero_id) VALUES (?,?,?,?,?,?,?,?)',
                [$concepto, 'ingreso', $tercero['id'], $tipo['id'], bal_decimal($monto), date('Y-m-d'), $adminId, $senderoId]);
            $nuevo = (int) $conn->insert_id;
            bal_audit($conn, $adminId, 'crear', 'obligacion', $nuevo, [], ['concepto' => $concepto, 'monto' => bal_decimal($monto), 'tercero_id' => $tercero['id'], 'sendero_id' => $senderoId, 'origen' => 'cobro_sendero_cargado']);
            return $nuevo;
        } catch (InvalidArgumentException $e) {
            throw new IngresoValidacionException($fila['nombre'] . ': ' . $e->getMessage());
        }
    }

    /** Lo que se envia a ic_guardar() para volver a guardar el cobro tal como esta (montos en pesos). */
    function ic_datos_desde_fila(array $fila): array
    {
        $e = $fila['entrada'];
        return [
            'version' => (string) $fila['version'],
            'tipo' => $e['tipo'],
            'asistio' => $e['asistio'],
            'descuento' => ic_decimal($e['descuento']),
            'credito' => ic_decimal($e['credito']),
            'recibido' => ic_decimal($e['recibido']),
            'a_credito' => ic_decimal($e['a_credito']),
            'devuelto' => ic_decimal($e['devuelto']),
            'cargado' => ic_decimal($e['cargado']),
            'cargado_a' => $e['cargado_a'],
            'metodo_id' => $e['metodo_id'],
            'fecha' => $e['fecha'],
            'nota' => $fila['nota'],
        ];
    }

    /**
     * Cambia la asistencia de una inscripcion recalculando su cobro con las mismas reglas
     * de Ingresos por sendero. Lanza IngresoValidacionException si el cobro no cuadra con la
     * nueva asistencia (ej.: se le devolvio todo y ahora se marca que asistio).
     */
    function ic_cambiar_asistencia(mysqli $conn, int $senderoId, int $registroId, bool $asistio, int $adminId): void
    {
        $filas = ic_cargar($conn, $senderoId, [$registroId]);
        if (!$filas) {
            throw new IngresoValidacionException('Inscripcion #' . $registroId . ' no encontrada.');
        }
        $datos = ic_datos_desde_fila($filas[0]);
        $datos['asistio'] = $asistio;
        ic_guardar($conn, $senderoId, $registroId, $datos, $adminId);
    }

    function ic_movimiento(mysqli $conn, int $creditoId, int $registroId, int $senderoId, string $tipo, int $centavos, string $nota, int $adminId): void
    {
        $monto = ic_decimal($centavos);
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO usuario_credito_movimientos (credito_id, registro_id, sendero_id, tipo, monto, nota, creado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'iiisssi', $creditoId, $registroId, $senderoId, $tipo, $monto, $nota, $adminId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Saldo de un credito = monto - lo aplicado en todas las inscripciones.
    function ic_recalcular_saldo(mysqli $conn, int $creditoId): void
    {
        mysqli_query(
            $conn,
            "UPDATE usuario_creditos uc
             LEFT JOIN (SELECT credito_id, SUM(monto) AS aplicado FROM contabilidad_credito_aplicaciones
                        WHERE credito_id = {$creditoId} GROUP BY credito_id) a ON a.credito_id = uc.id
             SET uc.saldo_disponible = CASE WHEN uc.estado IN ('anulado', 'vencido') THEN 0
                                            ELSE GREATEST(uc.monto - COALESCE(a.aplicado, 0), 0) END,
                 uc.estado = CASE WHEN uc.estado IN ('anulado', 'vencido') THEN uc.estado
                                  WHEN uc.monto - COALESCE(a.aplicado, 0) > 0.004 THEN 'activo' ELSE 'usado' END
             WHERE uc.id = {$creditoId}"
        );
    }

    /**
     * Reparte el credito usado entre los creditos del usuario (primero uno que alcance solo,
     * si no, del mas antiguo al mas nuevo) y deja un movimiento por cada diferencia.
     * Devuelve el id del credito principal (o null).
     */
    function ic_aplicar_credito(mysqli $conn, array $fila, int $credito, int $senderoId, int $adminId): ?int
    {
        $registroId = (int) $fila['registro_id'];
        $dueno = (string) ($fila['dueno'] ?? '');

        $anteriores = [];
        $res = mysqli_query($conn, "SELECT credito_id, monto FROM contabilidad_credito_aplicaciones WHERE registro_id = {$registroId}");
        while ($res && $a = mysqli_fetch_assoc($res)) {
            $anteriores[(int) $a['credito_id']] = ic_cents($a['monto']);
        }
        if (array_sum($anteriores) === $credito) {
            // Mismo monto que ya estaba: no se mueve nada.
            if (!$anteriores) {
                return null;
            }
            arsort($anteriores);
            return (int) array_key_first($anteriores);
        }

        $nuevos = [];
        if ($credito > 0 && $dueno !== '') {
            $creditos = ($dueno[0] === 'u'
                ? ic_creditos_de_duenos($conn, [(int) substr($dueno, 1)])
                : ic_creditos_de_duenos($conn, [], [(int) substr($dueno, 1)]))[$dueno] ?? [];
            uasort($creditos, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
            $libres = [];
            foreach ($creditos as $c) {
                if ($c['origen'] === $registroId) {
                    continue;
                }
                $libre = ic_credito_libre($c, $registroId);
                if ($libre > 0) {
                    $libres[$c['id']] = $libre;
                }
            }
            foreach ($libres as $id => $libre) {
                if ($libre >= $credito) {
                    $nuevos[$id] = $credito;
                    break;
                }
            }
            if (!$nuevos) {
                $pendiente = $credito;
                foreach ($libres as $id => $libre) {
                    if ($pendiente <= 0) {
                        break;
                    }
                    $toma = min($libre, $pendiente);
                    $nuevos[$id] = $toma;
                    $pendiente -= $toma;
                }
                if ($pendiente > 0) {
                    throw new IngresoValidacionException($fila['nombre'] . ': no tiene credito suficiente disponible.');
                }
            }
        }

        mysqli_query($conn, "DELETE FROM contabilidad_credito_aplicaciones WHERE registro_id = {$registroId}");
        $stmt = mysqli_prepare($conn, "INSERT INTO contabilidad_credito_aplicaciones (registro_id, credito_id, monto) VALUES (?, ?, ?)");
        foreach ($nuevos as $id => $monto) {
            $montoDecimal = ic_decimal($monto);
            mysqli_stmt_bind_param($stmt, 'iis', $registroId, $id, $montoDecimal);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);

        $tocados = array_unique(array_merge(array_keys($anteriores), array_keys($nuevos)));
        foreach ($tocados as $id) {
            $diferencia = ($nuevos[$id] ?? 0) - ($anteriores[$id] ?? 0);
            if ($diferencia > 0) {
                ic_movimiento($conn, $id, $registroId, $senderoId, 'aplicacion', $diferencia, "Credito usado en la inscripcion #{$registroId}", $adminId);
            } elseif ($diferencia < 0) {
                ic_movimiento($conn, $id, $registroId, $senderoId, 'ajuste', -$diferencia, "Se devuelve al credito lo que habia usado la inscripcion #{$registroId}", $adminId);
            }
            ic_recalcular_saldo($conn, $id);
        }

        if (!$nuevos) {
            return null;
        }
        arsort($nuevos);
        return (int) array_key_first($nuevos);
    }

    // Crea, ajusta o anula el credito que genera esta inscripcion con lo que se pasa a credito.
    function ic_generar_credito(mysqli $conn, array $fila, array $calculo, int $senderoId, int $adminId): void
    {
        $registroId = (int) $fila['registro_id'];
        $dueno = (string) ($fila['dueno'] ?? '');
        $nuevo = $calculo['a_credito'];

        $res = mysqli_query($conn, "SELECT id, monto, estado FROM usuario_creditos WHERE registro_origen_id = {$registroId} ORDER BY id LIMIT 1 FOR UPDATE");
        $existente = $res ? mysqli_fetch_assoc($res) : null;

        $motivo = ($calculo['asistio'] ? 'Excedente pagado' : 'Pago sin asistencia') . " en el sendero #{$senderoId}";
        if ($calculo['retenido'] > 0) {
            $motivo .= '. Retenido ' . ic_dinero($calculo['retenido']);
        }
        if ($calculo['devuelto'] > 0) {
            $motivo .= '. Devuelto ' . ic_dinero($calculo['devuelto']);
        }

        if (!$existente) {
            if ($nuevo <= 0 || $dueno === '') {
                return;
            }
            $monto = ic_decimal($nuevo);
            // El credito queda en la cuenta del usuario o en la ficha de la persona sin cuenta.
            $columnaDueno = $dueno[0] === 'u' ? 'usuario_id' : 'persona_sin_cuenta_id';
            $duenoId = (int) substr($dueno, 1);
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO usuario_creditos ({$columnaDueno}, registro_origen_id, sendero_origen_id, monto, saldo_disponible, estado, motivo, creado_por)
                 VALUES (?, ?, ?, ?, ?, 'activo', ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'iiisssi', $duenoId, $registroId, $senderoId, $monto, $monto, $motivo, $adminId);
            mysqli_stmt_execute($stmt);
            $creditoId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            ic_movimiento($conn, $creditoId, $registroId, $senderoId, 'creacion', $nuevo, $motivo, $adminId);
            return;
        }

        $creditoId = (int) $existente['id'];
        $anterior = $existente['estado'] === 'anulado' ? 0 : ic_cents($existente['monto']);
        if ($anterior === $nuevo) {
            return;
        }
        if ($nuevo <= 0) {
            mysqli_query($conn, "UPDATE usuario_creditos SET estado = 'anulado', saldo_disponible = 0 WHERE id = {$creditoId}");
            ic_movimiento($conn, $creditoId, $registroId, $senderoId, 'anulacion', $anterior, "Credito anulado desde el cobro de la inscripcion #{$registroId}", $adminId);
            return;
        }
        $monto = ic_decimal($nuevo);
        $stmt = mysqli_prepare($conn, "UPDATE usuario_creditos SET monto = ?, estado = 'activo', motivo = ?, creado_por = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssii', $monto, $motivo, $adminId, $creditoId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        ic_movimiento($conn, $creditoId, $registroId, $senderoId, 'ajuste', abs($nuevo - $anterior),
            "Credito cambiado de " . ic_dinero($anterior) . " a " . ic_dinero($nuevo) . " desde el cobro de la inscripcion #{$registroId}", $adminId);
        ic_recalcular_saldo($conn, $creditoId);
    }
}
