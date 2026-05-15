<?php
require_once __DIR__ . '/../configuracion.php';

// Cargar JS específicos de cada página
if (isset($jsFiles)) {
    foreach ($jsFiles as $js) {
        echo "<script src='" . BASE_URL . ltrim($js, '/') . "' defer></script>\n";
    }
}
?>

<script>
    feather.replace();
</script>

</body>

</html>