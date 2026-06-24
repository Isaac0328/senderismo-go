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

    <?php if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
    (function () {
        var pingUrl = '<?= asset_url('procesos/ping_sesion.php') ?>';
        var intervalMs = 4 * 60 * 1000;

        function pingSession() {
            fetch(pingUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            }).catch(function () {});
        }

        window.setInterval(pingSession, intervalMs);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                pingSession();
            }
        });
    })();
    <?php endif; ?>

    feather.replace();
</script>

</body>

</html>
