<?php
// Factura en PDF de un cobro pendiente de sendero (Por cobrar). Se arma con los datos de la inscripcion
// (los mismos que usa Ingresos por sendero), la identidad del sitio y la tarjeta de pago.

if (!function_exists('ic_cargar')) {
    require_once __DIR__ . '/ingresos_cobro.php';
}
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/configuracion_sitio.php';

if (!function_exists('factura_limpiar_texto')) {
    // El PDF no dibuja emojis ni simbolos raros: se quitan de los nombres (por ejemplo, el arbol del sendero).
    function factura_limpiar_texto(?string $texto): string
    {
        $texto = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2190}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u', '', (string) $texto) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    }
}

if (!function_exists('factura_nombre')) {
    // Los nombres escritos todo en mayusculas se muestran con formato normal (Michael Dario Rodriguez).
    function factura_nombre(string $nombre): string
    {
        return $nombre !== '' && $nombre === mb_strtoupper($nombre, 'UTF-8') ? mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8') : $nombre;
    }
}

if (!function_exists('factura_dinero')) {
    function factura_dinero(int $centavos): string
    {
        return 'RD$ ' . number_format($centavos / 100, 2);
    }
}

if (!function_exists('factura_sendero_datos')) {
    // Datos de la factura de una inscripcion, o null si no existe o no esta activa.
    function factura_sendero_datos(mysqli $conn, int $registroId): ?array
    {
        $stmt = mysqli_prepare($conn, "SELECT sendero_id FROM registros_senderos WHERE id = ? AND estado = 'registrado' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $registroId);
        mysqli_stmt_execute($stmt);
        $registro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$registro) {
            return null;
        }
        $senderoId = (int) $registro['sendero_id'];
        $filas = ic_cargar($conn, $senderoId, [$registroId]);
        if (!$filas) {
            return null;
        }
        $fila = $filas[0];
        $calculo = $fila['calculo'];

        $stmt = mysqli_prepare($conn, 'SELECT nombre, fecha_sendero, lugar FROM senderos WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $senderoId);
        mysqli_stmt_execute($stmt);
        $sendero = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['nombre' => 'Sendero', 'fecha_sendero' => null, 'lugar' => ''];
        mysqli_stmt_close($stmt);

        $pago = null;
        $existe = mysqli_query($conn, "SHOW TABLES LIKE 'tarjeta_pago'");
        if ($existe && mysqli_num_rows($existe) === 1) {
            $res = mysqli_query($conn, 'SELECT * FROM tarjeta_pago WHERE activo = 1 ORDER BY id DESC LIMIT 1');
            $pago = $res ? (mysqli_fetch_assoc($res) ?: null) : null;
        }

        return [
            'numero' => 'F-' . str_pad((string) $registroId, 6, '0', STR_PAD_LEFT),
            'registro_id' => $registroId,
            'cliente' => factura_nombre(factura_limpiar_texto($fila['nombre'])) ?: 'Participante',
            'telefono' => trim((string) $fila['telefono']),
            'email' => trim((string) $fila['email']),
            'sendero' => factura_limpiar_texto($sendero['nombre']),
            'fecha_sendero' => $sendero['fecha_sendero'],
            'lugar' => factura_limpiar_texto($sendero['lugar'] ?? ''),
            'inversion' => factura_limpiar_texto($fila['inversion_nombre']),
            'inversion_monto' => ic_cents($fila['inversion_monto']),
            'menores' => (int) $fila['total_menores'],
            'menores_monto' => ic_cents($fila['total_menores_monto']),
            'descuento' => (int) $calculo['descuento'],
            'credito' => (int) $calculo['credito'],
            'pagado' => (int) $calculo['retenido'],
            'saldo' => (int) $calculo['saldo'],
            'pago' => $pago,
            'sitio' => sg_site_config($conn),
            'contacto' => sg_site_contact($conn),
        ];
    }
}

if (!function_exists('factura_logo')) {
    // Logo de la factura (sobre fondo verde, sin transparencia para que cualquier PDF lo dibuje bien).
    function factura_logo(): string
    {
        $ruta = dirname(__DIR__) . '/imagenes/logo/logo_factura.png';
        return is_file($ruta) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($ruta)) : '';
    }
}

if (!function_exists('factura_sendero_html')) {
    // $opciones: fecha_emision (Y-m-d), vencimiento (Y-m-d o ''), nota (texto opcional).
    function factura_sendero_html(array $d, array $opciones): string
    {
        $h = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $fecha = static fn(?string $f): string => $f ? date('d/m/Y', strtotime($f)) : '';
        $saldada = $d['saldo'] <= 0;
        $nombreSitio = factura_limpiar_texto($d['sitio']['nombre_sitio'] ?? 'Senderismo Go') ?: 'Senderismo Go';
        $eslogan = factura_limpiar_texto($d['sitio']['eslogan'] ?? '');
        $contacto = $d['contacto'];
        $pago = $d['pago'];
        $primerNombre = explode(' ', $d['cliente'])[0];
        $logo = factura_logo();

        // Lineas del detalle.
        $lineas = [];
        if ($d['inversion_monto'] > 0) {
            $lineas[] = ['Participación en ' . $d['sendero'], $d['inversion'] !== '' && $d['inversion'] !== 'Sin inversion' ? 'Inversión: ' . $d['inversion'] : '', $d['inversion_monto']];
        } elseif ($d['menores_monto'] <= 0) {
            $lineas[] = ['Participación en ' . $d['sendero'], '', 0];
        }
        if ($d['menores_monto'] > 0) {
            $lineas[] = ['Menores de edad (' . $d['menores'] . ')', 'Participación de menores en ' . $d['sendero'], $d['menores_monto']];
        }
        $ajustes = [];
        if ($d['descuento'] > 0) { $ajustes[] = ['Descuento', -$d['descuento']]; }
        if ($d['credito'] > 0) { $ajustes[] = ['Crédito aplicado', -$d['credito']]; }
        if ($d['pagado'] > 0) { $ajustes[] = ['Pagos recibidos', -$d['pagado']]; }
        $subtotal = $d['inversion_monto'] + $d['menores_monto'];

        // Mensaje de pago.
        $mensaje = $saldada
            ? 'Esta factura se encuentra saldada. ¡Gracias por tu pago, ' . $primerNombre . '!'
            : 'Hola ' . $primerNombre . ', gracias por ser parte de ' . $nombreSitio . '. Para completar tu participación, realiza el pago de '
                . factura_dinero($d['saldo']) . ' a la cuenta indicada'
                . ($pago && trim((string) $pago['telefono_comprobante']) !== '' ? ' y envía el comprobante al ' . trim($pago['telefono_comprobante']) : ' y envíanos el comprobante')
                . '.';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2a24; }
    table { border-collapse: collapse; }
    .band { background: #123d27; width: 100%; }
    .band td { padding: 20px 40px; vertical-align: middle; }
    .titulo { color: #ffffff; font-size: 30px; font-weight: bold; letter-spacing: 3px; text-align: right; }
    .meta { color: #cfe3d6; font-size: 11px; text-align: right; line-height: 1.7; }
    .acento { height: 5px; background: #d6212d; }
    .contenido { padding: 26px 40px 0 40px; }
    .caja { background: #f3f7f4; border-left: 4px solid #1f6b3f; padding: 12px 16px; vertical-align: top; }
    .etiqueta { font-size: 9px; font-weight: bold; letter-spacing: 1.5px; color: #1f6b3f; text-transform: uppercase; margin-bottom: 5px; }
    .nombre { font-size: 15px; font-weight: bold; color: #123d27; margin-bottom: 3px; }
    .tenue { color: #5f6d64; }
    .items { width: 100%; margin-top: 22px; }
    .items th { background: #123d27; color: #ffffff; font-size: 10px; letter-spacing: 1px; text-transform: uppercase; padding: 9px 12px; text-align: left; }
    .items td { padding: 10px 12px; border-bottom: 1px solid #dfe8e2; vertical-align: top; }
    .items .der { text-align: right; }
    .totales { width: 100%; margin-top: 4px; }
    .totales td { padding: 6px 12px; text-align: right; }
    .total-final td { background: #123d27; color: #ffffff; font-size: 15px; font-weight: bold; padding: 11px 14px; }
    .pago { width: 100%; margin-top: 24px; background: #fff7f0; border: 1px solid #f0d9c3; }
    .pago td { padding: 14px 18px; vertical-align: top; }
    .pago h3 { margin: 0 0 6px 0; font-size: 13px; color: #9a3412; }
    .datos td { padding: 2px 0; font-size: 11px; }
    .datos td.k { width: 120px; color: #5f6d64; }
    .nota { margin-top: 14px; font-size: 10px; color: #5f6d64; line-height: 1.5; }
    .pie { position: fixed; left: 0; right: 0; bottom: 0; background: #123d27; color: #cfe3d6; text-align: center; padding: 12px 40px; font-size: 10px; }
    .pie strong { color: #ffffff; }
    .sello { font-size: 10px; font-weight: bold; letter-spacing: 1px; text-align: center; padding: 5px 14px; }
</style>
</head>
<body>
<table class="band"><tr>
    <td style="width: 46%;"><?php if ($logo !== ''): ?><img src="<?= $logo ?>" style="height: 82px;" alt="<?= $h($nombreSitio) ?>"><?php else: ?><span style="color:#fff;font-size:22px;font-weight:bold;"><?= $h($nombreSitio) ?></span><?php endif; ?></td>
    <td>
        <div class="titulo">FACTURA</div>
        <div class="meta">No. <strong style="color:#ffffff;"><?= $h($d['numero']) ?></strong><br>
        Fecha de emisión: <?= $h($fecha($opciones['fecha_emision'])) ?><?php if (!empty($opciones['vencimiento'])): ?><br>Vence: <?= $h($fecha($opciones['vencimiento'])) ?><?php endif; ?></div>
    </td>
</tr></table>
<div class="acento"></div>

<div class="contenido">
    <table style="width:100%;"><tr>
        <td class="caja" style="width: 50%;">
            <div class="etiqueta">Facturado a</div>
            <div class="nombre"><?= $h($d['cliente']) ?></div>
            <?php if ($d['telefono'] !== ''): ?><div class="tenue">Tel.: <?= $h($d['telefono']) ?></div><?php endif; ?>
            <?php if ($d['email'] !== ''): ?><div class="tenue"><?= $h($d['email']) ?></div><?php endif; ?>
        </td>
        <td style="width: 3%;"></td>
        <td class="caja" style="width: 47%;">
            <div class="etiqueta">Sendero</div>
            <div class="nombre"><?= $h($d['sendero']) ?></div>
            <?php if ($d['fecha_sendero']): ?><div class="tenue">Fecha del sendero: <strong style="color:#1f2a24;"><?= $h($fecha($d['fecha_sendero'])) ?></strong></div><?php endif; ?>
            <?php if ($d['lugar'] !== ''): ?><div class="tenue">Lugar: <?= $h($d['lugar']) ?></div><?php endif; ?>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr><th>Descripción</th><th class="der" style="width: 120px;">Monto</th></tr></thead>
        <tbody>
        <?php foreach ($lineas as [$titulo, $detalle, $monto]): ?>
            <tr><td><strong><?= $h($titulo) ?></strong><?php if ($detalle !== ''): ?><div class="tenue" style="margin-top:3px;"><?= $h($detalle) ?></div><?php endif; ?></td>
            <td class="der"><?= $h(factura_dinero($monto)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <table class="totales">
        <tr><td class="tenue">Subtotal</td><td style="width:120px;"><?= $h(factura_dinero($subtotal)) ?></td></tr>
        <?php foreach ($ajustes as [$texto, $monto]): ?>
            <tr><td class="tenue"><?= $h($texto) ?></td><td style="width:120px;">- <?= $h(factura_dinero(abs($monto))) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total-final"><td><?= $saldada ? 'SALDO' : 'TOTAL A PAGAR' ?></td><td style="width:120px;"><?= $h(factura_dinero($d['saldo'])) ?></td></tr>
    </table>

    <table class="pago"><tr><td>
        <h3><?= $saldada ? 'Pago recibido' : 'Cómo pagar' ?></h3>
        <div style="margin-bottom: 8px; line-height: 1.5;"><?= $h($mensaje) ?></div>
        <?php if (!$saldada && $pago): ?>
        <table class="datos">
            <?php foreach ([['Banco', $pago['banco']], ['Tipo de cuenta', $pago['tipo_cuenta']], ['Número de cuenta', $pago['cuenta']], ['A nombre de', $pago['nombre']], ['Cédula', $pago['cedula']]] as [$clave, $valor]): ?>
                <?php if (trim((string) $valor) !== ''): ?><tr><td class="k"><?= $h($clave) ?></td><td><strong><?= $h($valor) ?></strong></td></tr><?php endif; ?>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </td></tr></table>

    <?php if (!$saldada && $pago && trim((string) $pago['nota_importante']) !== ''): ?>
        <div class="nota"><strong>Importante:</strong> <?= $h($pago['nota_importante']) ?></div>
    <?php endif; ?>
    <?php if (trim((string) ($opciones['nota'] ?? '')) !== ''): ?>
        <div class="nota"><strong>Nota:</strong> <?= nl2br($h($opciones['nota'])) ?></div>
    <?php endif; ?>
</div>

<div class="pie">
    <strong><?= $h($nombreSitio) ?></strong><?= $eslogan !== '' ? ' - ' . $h($eslogan) : '' ?><br>
    <?= $h(implode('  |  ', array_filter([$contacto['telefono'] ?? '', $contacto['email'] ?? '', $contacto['instagram'] ?? '', $contacto['ubicacion'] ?? '']))) ?>
</div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('factura_sendero_pdf')) {
    function factura_sendero_pdf(string $html): \Dompdf\Dompdf
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Falta instalar las dependencias del PDF.');
        }
        if (!extension_loaded('gd')) {
            throw new InvalidArgumentException('Falta la extension GD de PHP para generar el PDF. En XAMPP: quita el ; de extension=gd en php.ini y reinicia Apache.');
        }
        require_once $autoload;
        $opciones = new \Dompdf\Options();
        $opciones->set('isHtml5ParserEnabled', true);
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('defaultFont', 'Helvetica');
        $opciones->setChroot(realpath(dirname(__DIR__)));
        $dompdf = new \Dompdf\Dompdf($opciones);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        return $dompdf;
    }
}
