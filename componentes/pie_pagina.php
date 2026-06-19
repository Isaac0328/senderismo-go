<?php
require_once __DIR__ . '/../configuracion.php';

// Cargar JS específicos de cada página
if (isset($jsFiles)) {
    foreach ($jsFiles as $js) {
        echo "<script src='" . asset_url($js) . "' defer></script>\n";
    }
}
?>

<script>
    (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta || !meta.content) return;

        document.querySelectorAll('form').forEach(function (form) {
            var method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method !== 'POST' || form.querySelector('input[name="csrf_token"]')) return;

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = meta.content;
            form.appendChild(input);
        });
    })();

    feather.replace();
</script>

</body>

</html>
