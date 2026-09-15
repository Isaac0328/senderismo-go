<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/rutas.php';
require_once __DIR__ . '/configuracion_sitio.php';

if (sg_site_public_footer_enabled()):
    $footerConnection = sg_site_open_connection();

    $footerConfig = sg_site_config($footerConnection);
    $footerMenu = sg_site_menu($footerConnection, true);
    $footerContact = sg_site_contact($footerConnection);
    $footerLogo = sg_site_asset((string) ($footerConfig['logo_footer'] ?: $footerConfig['logo_header']));
    $footerWhatsappDigits = preg_replace('/\D+/', '', (string) ($footerContact['whatsapp'] ?? ''));
    $footerInstagramUrl = trim((string) ($footerContact['instagram_url'] ?? ''));
    if ($footerConnection instanceof mysqli) {
        mysqli_close($footerConnection);
    }
?>
<footer class="public-footer">
    <div class="public-footer-inner">
        <?php if (!empty($footerConfig['footer_mostrar_identidad'])): ?>
            <section class="public-footer-brand" aria-label="Identidad del sitio">
                <?php if (!empty($footerConfig['footer_mostrar_logo']) && $footerLogo !== ''): ?>
                    <img src="<?= htmlspecialchars($footerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $footerConfig['nombre_sitio'], ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <div>
                    <strong><?= htmlspecialchars((string) $footerConfig['nombre_sitio']) ?></strong>
                    <?php if (!empty($footerConfig['footer_mostrar_descripcion']) && trim((string) $footerConfig['footer_descripcion']) !== ''): ?>
                        <p><?= nl2br(htmlspecialchars((string) $footerConfig['footer_descripcion'])) ?></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($footerConfig['footer_mostrar_enlaces'])): ?>
            <section class="public-footer-links" aria-label="Enlaces del pie de pagina">
                <h2><?= htmlspecialchars((string) $footerConfig['footer_enlaces_titulo']) ?></h2>
                <div>
                    <?php foreach ($footerMenu as $footerItem): ?>
                        <?php if (trim((string) ($footerItem['parent_codigo'] ?? '')) !== '') continue; ?>
                        <a href="<?= htmlspecialchars(sg_clean_url((string) $footerItem['ruta']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $footerItem['etiqueta']) ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($footerConfig['footer_mostrar_contacto'])): ?>
            <section class="public-footer-contact">
                <h2><?= htmlspecialchars((string) $footerConfig['footer_contacto_titulo']) ?></h2>
                <div class="public-footer-contact-list">
                    <?php if (!empty($footerConfig['footer_mostrar_ubicacion']) && trim((string) $footerContact['ubicacion']) !== ''): ?>
                        <span><i data-feather="map-pin"></i><?= htmlspecialchars((string) $footerContact['ubicacion']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($footerConfig['footer_mostrar_telefono']) && trim((string) $footerContact['telefono']) !== ''): ?>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/[^\d+]/', '', (string) $footerContact['telefono']), ENT_QUOTES, 'UTF-8') ?>"><i data-feather="phone"></i><?= htmlspecialchars((string) $footerContact['telefono']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($footerConfig['footer_mostrar_email']) && trim((string) $footerContact['email']) !== ''): ?>
                        <a href="mailto:<?= htmlspecialchars((string) $footerContact['email'], ENT_QUOTES, 'UTF-8') ?>"><i data-feather="mail"></i><?= htmlspecialchars((string) $footerContact['email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($footerConfig['footer_mostrar_whatsapp']) && $footerWhatsappDigits !== ''): ?>
                        <a href="https://wa.me/<?= htmlspecialchars($footerWhatsappDigits, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i data-feather="message-circle"></i>WhatsApp</a>
                    <?php endif; ?>
                    <?php if (!empty($footerConfig['footer_mostrar_instagram']) && $footerInstagramUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($footerInstagramUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i data-feather="instagram"></i><?= htmlspecialchars((string) ($footerContact['instagram'] ?: 'Instagram')) ?></a>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
    <?php if (!empty($footerConfig['footer_mostrar_copyright'])): ?>
        <div class="public-footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars((string) $footerConfig['nombre_corto']) ?>.</span>
            <span><?= htmlspecialchars((string) $footerConfig['footer_copyright']) ?></span>
        </div>
    <?php endif; ?>
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
