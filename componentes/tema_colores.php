<?php
if (!function_exists('sg_paletas_colores')) {
    function sg_paletas_colores(): array
    {
        return [
            'senderismo' => [
                'nombre' => 'Tema predeterminado',
                'primary' => '#255f38',
                'primary_dark' => '#102617',
                'accent' => '#e10600',
                'accent_dark' => '#b90000',
                'background' => '#f3f6ef',
                'surface' => '#ffffff',
                'text' => '#111111',
                'muted' => '#5f6d64',
                'line' => 'rgba(20, 27, 23, 0.12)',
            ],
            'montana' => [
                'nombre' => 'Montana Serena',
                'primary' => '#2f5d50',
                'primary_dark' => '#17332b',
                'accent' => '#c5652d',
                'accent_dark' => '#9c461b',
                'background' => '#eef3ed',
                'surface' => '#ffffff',
                'text' => '#16211d',
                'muted' => '#64736b',
                'line' => 'rgba(22, 33, 29, 0.13)',
            ],
            'oceano' => [
                'nombre' => 'Oceano Vivo',
                'primary' => '#176b87',
                'primary_dark' => '#0b3442',
                'accent' => '#d64545',
                'accent_dark' => '#a82323',
                'background' => '#edf6f8',
                'surface' => '#ffffff',
                'text' => '#102027',
                'muted' => '#5e7178',
                'line' => 'rgba(16, 32, 39, 0.13)',
            ],
            'tierra' => [
                'nombre' => 'Tierra Calida',
                'primary' => '#6f5e2f',
                'primary_dark' => '#332a14',
                'accent' => '#c2410c',
                'accent_dark' => '#8f2e08',
                'background' => '#f4f0e8',
                'surface' => '#ffffff',
                'text' => '#241c12',
                'muted' => '#756c5d',
                'line' => 'rgba(36, 28, 18, 0.13)',
            ],
        ];
    }
}

if (!function_exists('sg_hex_color')) {
    function sg_hex_color(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }
}

if (!function_exists('sg_tema_activo')) {
    function sg_tema_activo(): array
    {
        $paletas = sg_paletas_colores();
        $tema = array_merge(['tema' => 'senderismo'], $paletas['senderismo']);

        $connTema = $GLOBALS['conn'] ?? null;
        $debeCerrarConexion = false;

        if (!$connTema instanceof mysqli) {
            $connTema = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $debeCerrarConexion = $connTema instanceof mysqli;
        }

        if (!$connTema) {
            return $tema;
        }
        mysqli_set_charset($connTema, 'utf8mb4');

        $res = @mysqli_query($connTema, "SELECT * FROM configuracion_tema WHERE id = 1 LIMIT 1");
        $row = $res ? (mysqli_fetch_assoc($res) ?: []) : [];
        if ($debeCerrarConexion) {
            mysqli_close($connTema);
        }

        $temaKey = (string) ($row['tema'] ?? 'senderismo');
        if (isset($paletas[$temaKey])) {
            return array_merge(['tema' => $temaKey], $paletas[$temaKey]);
        }

        return [
            'tema' => 'personalizado',
            'nombre' => 'Personalizado',
            'primary' => sg_hex_color($row['primary_color'] ?? null, $paletas['senderismo']['primary']),
            'primary_dark' => sg_hex_color($row['primary_dark_color'] ?? null, $paletas['senderismo']['primary_dark']),
            'accent' => sg_hex_color($row['accent_color'] ?? null, $paletas['senderismo']['accent']),
            'accent_dark' => sg_hex_color($row['accent_dark_color'] ?? null, $paletas['senderismo']['accent_dark']),
            'background' => sg_hex_color($row['background_color'] ?? null, $paletas['senderismo']['background']),
            'surface' => sg_hex_color($row['surface_color'] ?? null, $paletas['senderismo']['surface']),
            'text' => sg_hex_color($row['text_color'] ?? null, $paletas['senderismo']['text']),
            'muted' => sg_hex_color($row['muted_color'] ?? null, $paletas['senderismo']['muted']),
            'line' => 'rgba(20, 27, 23, 0.12)',
        ];
    }
}

if (!function_exists('sg_imprimir_tema_css')) {
    function sg_imprimir_tema_css(): void
    {
        $sgTema = sg_tema_activo();
        ?>
        <style id="sg-theme-vars">
            /* Solo los valores de la paleta activa. El resto de variables se calcula
               en css/global.css y las reglas del tema estan en css/tema.css. */
            :root {
                --app-primary: <?= htmlspecialchars($sgTema['primary']) ?>;
                --app-primary-dark: <?= htmlspecialchars($sgTema['primary_dark']) ?>;
                --app-accent: <?= htmlspecialchars($sgTema['accent']) ?>;
                --app-accent-dark: <?= htmlspecialchars($sgTema['accent_dark']) ?>;
                --app-bg: <?= htmlspecialchars($sgTema['background']) ?>;
                --app-surface: <?= htmlspecialchars($sgTema['surface']) ?>;
                --app-text: <?= htmlspecialchars($sgTema['text']) ?>;
                --app-muted: <?= htmlspecialchars($sgTema['muted']) ?>;
                --app-line: <?= htmlspecialchars($sgTema['line']) ?>;
            }
        </style>
        <?php
    }
}
