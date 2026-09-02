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

if (!function_exists('sg_financial_statuses')) {
    function sg_financial_statuses(): array
    {
        return [
            'pendiente' => 'Pendiente',
            'pagado' => 'Pagado',
            'parcial' => 'Parcial',
            'credito_aplicado' => 'Credito aplicado',
            'descuento' => 'Pagado con descuento',
            'deuda' => 'Debe pagar',
            'cortesia' => 'Cortesia',
            'exento' => 'Exento',
            'no_asistio_sin_pago' => 'No asistio / sin pago',
        ];
    }
}

if (!function_exists('sg_financial_status_label')) {
    function sg_financial_status_label(?string $status): string
    {
        $statuses = sg_financial_statuses();
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
