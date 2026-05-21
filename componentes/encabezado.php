<?php
require_once __DIR__ . '/../configuracion.php';

function asset_url(string $asset): string
{
    $asset = ltrim($asset, '/');
    $path = __DIR__ . '/../' . $asset;
    $version = file_exists($path) ? '?v=' . filemtime($path) : '';

    return BASE_URL . $asset . $version;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo isset($pageTitle)
            ? $pageTitle
            : 'Senderismo GO!'; ?>
    </title>

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

</head>

<body class="bg-stone-50 font-sans antialiased">
