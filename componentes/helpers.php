<?php

if (!function_exists('sg_h')) {
    function sg_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sg_clean_text')) {
    function sg_clean_text(string $value, int $max = 255): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return substr($value, 0, $max);
    }
}

if (!function_exists('sg_only_digits')) {
    function sg_only_digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}

if (!function_exists('sg_contains_digits')) {
    function sg_contains_digits(string $value): bool
    {
        return preg_match('/\d/u', $value) === 1;
    }
}

if (!function_exists('sg_is_digits_between')) {
    function sg_is_digits_between(string $value, int $min = 10, int $max = 15): bool
    {
        return preg_match('/^\d{' . $min . ',' . $max . '}$/', $value) === 1;
    }
}

if (!function_exists('sg_fecha')) {
    function sg_fecha(?string $fecha, bool $conHora = false, string $fallback = 'Sin fecha'): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return $fallback;
        }

        $time = strtotime($fecha);
        return $time ? date($conHora ? 'd/m/Y h:i A' : 'd/m/Y', $time) : $fallback;
    }
}

if (!function_exists('sg_fecha_visual_a_sql')) {
    function sg_fecha_visual_a_sql(string $fechaIso, string $fechaVisual = ''): string
    {
        $fechaVisual = trim($fechaVisual);
        if ($fechaVisual !== '') {
            $dt = DateTime::createFromFormat('d/m/Y', $fechaVisual);
            $errores = DateTime::getLastErrors();
            $sinErrores = $errores === false || ((int) ($errores['warning_count'] ?? 0) === 0 && (int) ($errores['error_count'] ?? 0) === 0);

            return $dt && $sinErrores ? $dt->format('Y-m-d') : '';
        }

        $fechaIso = trim($fechaIso);
        if ($fechaIso === '') {
            return '';
        }

        $dt = DateTime::createFromFormat('Y-m-d', $fechaIso);
        $errores = DateTime::getLastErrors();
        $sinErrores = $errores === false || ((int) ($errores['warning_count'] ?? 0) === 0 && (int) ($errores['error_count'] ?? 0) === 0);

        return $dt && $sinErrores ? $dt->format('Y-m-d') : '';
    }
}

if (!function_exists('sg_money')) {
    function sg_money($value, string $fallback = 'RD$ 0.00'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return 'RD$ ' . number_format((float) $value, 2);
    }
}

if (!function_exists('sg_minutes_parts')) {
    function sg_minutes_parts(int|string|null $minutos): array
    {
        $total = max(0, (int) ($minutos ?? 0));
        return [intdiv($total, 60), $total % 60];
    }
}

if (!function_exists('sg_time_label')) {
    function sg_time_label(int|string|null $minutos, string $fallback = 'Tiempo pendiente'): string
    {
        if ($minutos === null || $minutos === '') {
            return $fallback;
        }

        [$horas, $mins] = sg_minutes_parts($minutos);
        if ($horas > 0 && $mins > 0) {
            return $horas . ' h ' . $mins . ' min';
        }

        if ($horas > 0) {
            return $horas . ' h';
        }

        return $mins . ' min';
    }
}

if (!function_exists('sg_slugify')) {
    function sg_slugify(string $text, string $fallback = 'item'): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $converted !== false ? $converted : $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        return $text !== '' ? $text : $fallback;
    }
}

if (!function_exists('sg_financial_status_defaults')) {
    function sg_financial_status_defaults(): array
    {
        return [
            'pendiente' => ['nombre' => 'Pendiente', 'descripcion' => 'El sendero aun no ocurre y no hay cobertura registrada.', 'color' => '#B7791F', 'orden' => 10, 'activo' => 1],
            'pagado' => ['nombre' => 'Pagado', 'descripcion' => 'El monto pagado cubre el valor esperado.', 'color' => '#167447', 'orden' => 20, 'activo' => 1],
            'parcial' => ['nombre' => 'Parcial', 'descripcion' => 'Existe cobertura, pero aun queda balance.', 'color' => '#B7791F', 'orden' => 30, 'activo' => 1],
            'credito_aplicado' => ['nombre' => 'Credito aplicado', 'descripcion' => 'El credito a favor cubre el valor esperado.', 'color' => '#2563EB', 'orden' => 40, 'activo' => 1],
            'pagado_con_credito' => ['nombre' => 'Pagado con credito', 'descripcion' => 'El pago combina efectivo y credito a favor.', 'color' => '#2563EB', 'orden' => 50, 'activo' => 1],
            'descuento' => ['nombre' => 'Pagado con descuento', 'descripcion' => 'El valor queda cubierto usando un descuento.', 'color' => '#7C3AED', 'orden' => 60, 'activo' => 1],
            'deuda' => ['nombre' => 'Debe pagar', 'descripcion' => 'La persona asistio y aun tiene balance pendiente.', 'color' => '#B42318', 'orden' => 70, 'activo' => 1],
            'cortesia' => ['nombre' => 'Cortesia', 'descripcion' => 'Excepcion manual sin cobro.', 'color' => '#0F766E', 'orden' => 80, 'activo' => 1],
            'exento' => ['nombre' => 'Exento', 'descripcion' => 'Excepcion manual sin valor esperado.', 'color' => '#475569', 'orden' => 90, 'activo' => 1],
            'no_asistio_sin_pago' => ['nombre' => 'No asistio / sin pago', 'descripcion' => 'El sendero paso sin asistencia ni cobertura.', 'color' => '#64748B', 'orden' => 100, 'activo' => 1],
        ];
    }
}

if (!function_exists('sg_financial_status_catalog')) {
    function sg_financial_status_catalog(?mysqli $conn = null): array
    {
        $catalog = sg_financial_status_defaults();
        if (!$conn) {
            return $catalog;
        }

        $res = mysqli_query($conn, 'SELECT codigo, nombre, descripcion, color, orden, activo FROM contabilidad_estado_financiero ORDER BY orden ASC, codigo ASC');
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $codigo = (string) ($row['codigo'] ?? '');
                if (isset($catalog[$codigo])) {
                    $catalog[$codigo] = [
                        'nombre' => (string) $row['nombre'],
                        'descripcion' => (string) ($row['descripcion'] ?? ''),
                        'color' => (string) $row['color'],
                        'orden' => (int) $row['orden'],
                        'activo' => (int) $row['activo'],
                    ];
                }
            }
            mysqli_free_result($res);
        }
        uasort($catalog, static fn(array $a, array $b): int => ($a['orden'] <=> $b['orden']));
        return $catalog;
    }
}

if (!function_exists('sg_financial_statuses')) {
    function sg_financial_statuses(?mysqli $conn = null): array
    {
        $labels = [];
        foreach (sg_financial_status_catalog($conn) as $codigo => $data) {
            $labels[$codigo] = $data['nombre'];
        }
        return $labels;
    }
}

if (!function_exists('sg_financial_status_derive')) {
    function sg_financial_status_derive(
        bool $asistio,
        bool $pagado,
        float $esperado,
        float $montoPagado,
        float $creditoAplicado,
        float $descuento,
        ?string $fechaSendero = null,
        ?string $fechaActual = null
    ): string {
        $esperado = max(0, $esperado);
        $montoPagado = $pagado ? max(0, $montoPagado) : 0;
        $creditoAplicado = max(0, $creditoAplicado);
        $descuento = max(0, $descuento);
        $cubierto = $montoPagado + $creditoAplicado + $descuento;

        if ($esperado <= 0) {
            return 'cortesia';
        }
        if ($cubierto + 0.00001 >= $esperado) {
            if ($creditoAplicado > 0 && $montoPagado <= 0 && $descuento <= 0) {
                return 'credito_aplicado';
            }
            if ($creditoAplicado > 0) {
                return 'pagado_con_credito';
            }
            if ($descuento > 0) {
                return 'descuento';
            }
            return 'pagado';
        }
        if ($cubierto > 0) {
            return 'parcial';
        }
        if ($asistio) {
            return 'deuda';
        }

        $fechaActual = $fechaActual ?: date('Y-m-d');
        if ($fechaSendero !== null && $fechaSendero !== '' && $fechaSendero < $fechaActual) {
            return 'no_asistio_sin_pago';
        }

        return 'pendiente';
    }
}

if (!function_exists('sg_financial_status_label')) {
    function sg_financial_status_label(?string $status, ?mysqli $conn = null): string
    {
        $statuses = sg_financial_statuses($conn);
        $status = (string) $status;
        return $statuses[$status] ?? 'Pendiente';
    }
}

if (!function_exists('sg_financial_status_default')) {
    function sg_financial_status_default(bool $pagado, bool $asistio): string
    {
        if ($pagado) {
            return 'pagado';
        }

        return $asistio ? 'deuda' : 'no_asistio_sin_pago';
    }
}

if (!function_exists('sg_uploaded_files_array')) {
    function sg_uploaded_files_array(string $field): array
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
            return [];
        }

        $files = [];
        $count = count($_FILES[$field]['name']);
        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'name' => $_FILES[$field]['name'][$i],
                'type' => $_FILES[$field]['type'][$i],
                'tmp_name' => $_FILES[$field]['tmp_name'][$i],
                'error' => $_FILES[$field]['error'][$i],
                'size' => $_FILES[$field]['size'][$i],
            ];
        }

        return $files;
    }
}

if (!function_exists('sg_save_uploaded_image')) {
    function sg_save_uploaded_image(array $file, string $relativeFolder, string $prefix, int $maxBytes = 4194304): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo cargar una imagen.');
        }

        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Cada imagen debe pesar maximo 4 MB.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp) || getimagesize($tmp) === false) {
            throw new RuntimeException('El archivo cargado no es una imagen valida.');
        }

        $mime = mime_content_type($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Solo se permiten imagenes JPG, PNG o WEBP.');
        }

        $relativeFolder = trim($relativeFolder, '/\\');
        $folder = dirname(__DIR__) . '/' . str_replace('\\', '/', $relativeFolder);
        if (!is_dir($folder) && !mkdir($folder, 0775, true)) {
            throw new RuntimeException('No se pudo crear la carpeta de imagenes.');
        }

        $filename = sg_slugify($prefix, 'imagen') . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $dest = $folder . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('No se pudo guardar la imagen cargada.');
        }

        return $relativeFolder . '/' . $filename;
    }
}

if (!function_exists('sg_pendiente_sin_pago_por_sendero')) {
    // Lo que deben las inscripciones activas que aun no tienen fila en Ingresos.
    // Sigue la misma regla que sg_financial_status_derive: cuenta quien asistio
    // (deuda) o quien va a un sendero que aun no se realiza (pendiente); quien no
    // asistio a un sendero pasado no debe nada.
    // Devuelve [sendero_id => ['monto' => float, 'cuentas' => int]].
    function sg_pendiente_sin_pago_por_sendero(mysqli $conn): array
    {
        $pendientes = [];
        $result = mysqli_query($conn, "
            SELECT rs.sendero_id,
                   SUM(COALESCE(si.monto, 0) + COALESCE(m.monto, 0)) AS monto,
                   COUNT(*) AS cuentas
            FROM registros_senderos rs
            INNER JOIN senderos s ON s.id = rs.sendero_id
            LEFT JOIN sendero_inversiones si ON si.id = rs.inversion_id
            LEFT JOIN (
                SELECT rm.registro_id, SUM(mi.monto) AS monto
                FROM registro_sendero_menores rm
                LEFT JOIN sendero_inversiones mi ON mi.id = rm.inversion_id
                GROUP BY rm.registro_id
            ) m ON m.registro_id = rs.id
            LEFT JOIN contabilidad_registro_pagos p ON p.registro_id = rs.id
            WHERE rs.estado = 'registrado'
              AND p.id IS NULL
              AND (rs.asistio = 1 OR s.fecha_sendero >= CURDATE())
              AND COALESCE(si.monto, 0) + COALESCE(m.monto, 0) > 0
            GROUP BY rs.sendero_id
        ");
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $pendientes[(int) $row['sendero_id']] = ['monto' => (float) $row['monto'], 'cuentas' => (int) $row['cuentas']];
        }
        return $pendientes;
    }
}
