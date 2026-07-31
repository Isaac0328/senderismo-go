<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/configuracion_sitio.php';

function asset_url(string $asset): string
{
    $asset = ltrim($asset, '/');
    $path = __DIR__ . '/../' . $asset;
    $version = file_exists($path) ? '?v=' . filemtime($path) : '';

    return BASE_URL . $asset . $version;
}

$siteHeaderConnection = $conn ?? null;
$siteHeaderOwnConnection = false;
if (!$siteHeaderConnection instanceof mysqli) {
    $siteHeaderConnection = sg_site_open_connection();
    $siteHeaderOwnConnection = $siteHeaderConnection instanceof mysqli;
}

$siteHeaderConfig = sg_site_config($siteHeaderConnection);
if ($siteHeaderOwnConnection && $siteHeaderConnection instanceof mysqli) {
    mysqli_close($siteHeaderConnection);
}

$siteName = trim((string) ($siteHeaderConfig['nombre_sitio'] ?? 'Senderismo Go!')) ?: 'Senderismo Go!';
$resolvedPageTitle = isset($pageTitle) && trim((string) $pageTitle) !== ''
    ? (string) $pageTitle
    : $siteName;
$resolvedPageTitle = preg_replace('/Senderismo\s*Go!?/iu', $siteName, $resolvedPageTitle) ?: $resolvedPageTitle;
$siteDescription = trim((string) ($siteHeaderConfig['meta_descripcion'] ?? ''));
$siteShareImage = sg_site_asset((string) ($siteHeaderConfig['imagen_compartir'] ?? ''));
$siteFavicon = sg_site_asset((string) ($siteHeaderConfig['favicon'] ?? ''));
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

    <title><?= htmlspecialchars($resolvedPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($siteDescription !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8') ?>">
        <meta property="og:description" content="<?= htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= htmlspecialchars($resolvedPageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <?php if ($siteShareImage !== ''): ?>
        <meta property="og:image" content="<?= htmlspecialchars($siteShareImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($siteFavicon !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>

    <?php
    if (isset($cssFiles)) {
        foreach ($cssFiles as $css) {
            echo "<link rel='stylesheet' href='" . asset_url($css) . "'>\n";
        }
    }
    ?>
    <link rel="stylesheet" href="<?= asset_url('css/pie_pagina.css') ?>">

    <?php
    include_once __DIR__ . '/tema_colores.php';
    sg_imprimir_tema_css();
    ?>

</head>

<body class="bg-stone-50 font-sans antialiased">
