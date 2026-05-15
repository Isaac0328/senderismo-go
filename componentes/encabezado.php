<?php
require_once __DIR__ . '/../configuracion.php';
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
            echo "<link rel='stylesheet' href='" . BASE_URL . ltrim($css, '/') . "'>\n";
        }
    }
    ?>

</head>

<body class="bg-stone-50 font-sans antialiased">