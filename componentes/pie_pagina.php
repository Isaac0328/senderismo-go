<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/configuracion_sitio.php';

if (sg_site_public_footer_enabled()):
    $footerConnection = sg_site_open_connection();

    $footerConfig = sg_site_config($footerConnection);
    $footerMenu = sg_site_menu($footerConnection, true);
    $footerContact = sg_site_contact($footerConnection);
    $footerLogo = sg_site_asset((string) ($footerConfig['logo_footer'] ?: $footerConfig['logo_header']));
    $footerWhatsappDigits = preg_replace('/\D+/', '', (string) ($footerContact['whatsapp'] ?? ''));
    if ($footerConnection instanceof mysqli) {
        mysqli_close($footerConnection);
    }
?>
<footer class="public-footer">
    <div class="public-footer-inner">
        <section class="public-footer-brand" aria-label="Identidad del sitio">
            <?php if ($footerLogo !== ''): ?>
                <img src="<?= htmlspecialchars($footerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $footerConfig['nombre_sitio'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <div>
                <strong><?= htmlspecialchars((string) $footerConfig['nombre_sitio']) ?></strong>
                <?php if (trim((string) $footerConfig['footer_descripcion']) !== ''): ?>
                    <p><?= nl2br(htmlspecialchars((string) $footerConfig['footer_descripcion'])) ?></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="public-footer-links" aria-label="Enlaces del pie de pagina">
            <h2><?= htmlspecialchars((string) $footerConfig['footer_enlaces_titulo']) ?></h2>
            <div>
                <?php foreach ($footerMenu as $footerItem): ?>
                    <?php if (trim((string) ($footerItem['parent_codigo'] ?? '')) !== '') continue; ?>
                    <a href="<?= htmlspecialchars(BASE_URL . ltrim((string) $footerItem['ruta'], '/'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $footerItem['etiqueta']) ?></a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="public-footer-contact">
            <h2><?= htmlspecialchars((string) $footerConfig['footer_contacto_titulo']) ?></h2>
            <div class="public-footer-contact-list">
                <?php if (trim((string) $footerContact['ubicacion']) !== ''): ?>
                    <span><i data-feather="map-pin"></i><?= htmlspecialchars((string) $footerContact['ubicacion']) ?></span>
                <?php endif; ?>
                <?php if (trim((string) $footerContact['telefono']) !== ''): ?>
                    <a href="tel:<?= htmlspecialchars(preg_replace('/[^\d+]/', '', (string) $footerContact['telefono']), ENT_QUOTES, 'UTF-8') ?>"><i data-feather="phone"></i><?= htmlspecialchars((string) $footerContact['telefono']) ?></a>
                <?php endif; ?>
                <?php if (trim((string) $footerContact['email']) !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars((string) $footerContact['email'], ENT_QUOTES, 'UTF-8') ?>"><i data-feather="mail"></i><?= htmlspecialchars((string) $footerContact['email']) ?></a>
                <?php endif; ?>
                <?php if ($footerWhatsappDigits !== ''): ?>
                    <a href="https://wa.me/<?= htmlspecialchars($footerWhatsappDigits, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i data-feather="message-circle"></i>WhatsApp</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="public-footer-bottom">
        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars((string) $footerConfig['nombre_corto']) ?>.</span>
        <span><?= htmlspecialchars((string) $footerConfig['footer_copyright']) ?></span>
    </div>
</footer>
<?php endif; ?>
<?php

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
