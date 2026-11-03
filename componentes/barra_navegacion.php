<?php
require_once __DIR__ . '/../configuracion.php';
require_once __DIR__ . '/rutas.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/recordar_sesion.php';
sg_restaurar_sesion_recordada();
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/configuracion_sitio.php';

$isLoggedIn = isset($_SESSION['usuario_id']);
$userName = $_SESSION['usuario_nombre'] ?? '';
$userRole = $_SESSION['usuario_rol'] ?? '';
$userInitial = $userName ? strtoupper(substr($userName, 0, 1)) : '';
$userAvatar = '';
$canAccessAdmin = false;
$navNotifications = ['items' => [], 'no_leidas' => 0, 'ready' => false];
$navConnection = $conn ?? null;
$navOwnConnection = false;

if (!$navConnection instanceof mysqli) {
    $navConnection = sg_site_open_connection();
    $navOwnConnection = $navConnection instanceof mysqli;
}

$navSiteConfig = sg_site_config($navConnection);
$navMenuItems = sg_site_menu($navConnection, true);
$navTopItems = [];
$navChildren = [];
foreach ($navMenuItems as $navItem) {
    $parentCode = trim((string) ($navItem['parent_codigo'] ?? ''));
    if ($parentCode === '') {
        $navTopItems[] = $navItem;
    } else {
        $navChildren[$parentCode][] = $navItem;
    }
}

$navLogo = sg_site_asset((string) ($navSiteConfig['logo_header'] ?? ''));
$navSiteName = trim((string) ($navSiteConfig['nombre_sitio'] ?? '')) ?: 'Plataforma';
$navLoginText = trim((string) ($navSiteConfig['login_texto'] ?? 'Iniciar sesion')) ?: 'Iniciar sesion';

if ($isLoggedIn && !empty($_SESSION['usuario_id'])) {
    if ($navConnection instanceof mysqli) {
        $uidAvatar = (int) $_SESSION['usuario_id'];
        $stmtAvatar = mysqli_prepare($navConnection, "SELECT imagen_perfil FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
        if ($stmtAvatar) {
            mysqli_stmt_bind_param($stmtAvatar, 'i', $uidAvatar);
            mysqli_stmt_execute($stmtAvatar);
            $avatarRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAvatar)) ?: [];
            mysqli_stmt_close($stmtAvatar);
            $avatarPath = trim((string) ($avatarRow['imagen_perfil'] ?? ''));
            if ($avatarPath !== '') {
                $userAvatar = preg_match('/^https?:\/\//i', $avatarPath)
                    ? $avatarPath
                    : BASE_URL . ltrim($avatarPath, '/');
            }
        }

        $roleId = (int) ($_SESSION['usuario_rol_id'] ?? 0);
        $canAccessAdmin = sg_is_admin_role($roleId) || count(sg_session_permissions($navConnection)) > 0;

        require_once __DIR__ . '/notificaciones.php';
        $navNotifications = sg_notificaciones_resumen($navConnection, $uidAvatar, sg_notificaciones_permisos($navConnection));
    }
}

if ($navOwnConnection && $navConnection instanceof mysqli) {
    mysqli_close($navConnection);
}

if (!function_exists('nav_avatar_html')) {
    function nav_avatar_html(string $avatar, string $initial, string $class = ''): string
    {
        $classAttr = trim('nav-user-avatar ' . $class);
        if ($avatar !== '') {
            return '<span class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '"><img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt=""></span>';
        }
        return '<span class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
?>

<nav class="fixed top-0 left-0 w-full z-50">
    <div class="w-full px-4 sm:px-6 lg:px-10">
        <div class="flex items-center justify-between h-16 md:h-20">

            <!-- Logo -->
            <div class="flex items-center">
                <a href="<?= htmlspecialchars(sg_clean_url('pantallas/inicio.php'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center gap-3">
                    <?php if ($navLogo !== ''): ?>
                        <img src="<?= htmlspecialchars($navLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($navSiteName, ENT_QUOTES, 'UTF-8') ?>"
                            class="h-12 sm:h-14 md:h-16 w-auto">
                    <?php else: ?>
                        <span class="nav-site-wordmark"><?= htmlspecialchars($navSiteName, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex absolute left-1/2 -translate-x-1/2">
                <ul class="flex items-center space-x-8">
                    <?php foreach ($navTopItems as $navItem): ?>
                        <?php
                        $navCode = (string) ($navItem['codigo'] ?? '');
                        $navLabel = (string) ($navItem['etiqueta'] ?? '');
                        $navRoute = sg_clean_url((string) ($navItem['ruta'] ?? ''));
                        $children = $navChildren[$navCode] ?? [];
                        ?>
                        <?php if (!empty($children)): ?>
                            <li class="nav-dropdown">
                                <a href="<?= htmlspecialchars($navRoute, ENT_QUOTES, 'UTF-8') ?>" class="nav-link nav-dropdown-toggle">
                                    <?= htmlspecialchars($navLabel) ?>
                                    <i data-feather="chevron-down"></i>
                                </a>
                                <div class="nav-dropdown-menu">
                                    <?php foreach ($children as $child): ?>
                                        <a href="<?= htmlspecialchars(sg_clean_url((string) $child['ruta']), ENT_QUOTES, 'UTF-8') ?>">
                                            <i data-feather="<?= htmlspecialchars((string) ($child['icono'] ?: 'circle'), ENT_QUOTES, 'UTF-8') ?>"></i>
                                            <span><?= htmlspecialchars((string) $child['etiqueta']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </li>
                        <?php else: ?>
                            <li><a href="<?= htmlspecialchars($navRoute, ENT_QUOTES, 'UTF-8') ?>" class="nav-link"><?= htmlspecialchars($navLabel) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Zona derecha -->
            <div class="flex items-center gap-3 md:gap-4">

                <?php if ($isLoggedIn): ?>
                    <div class="nav-notifications" data-notifications data-endpoint="<?= BASE_URL ?>procesos/proceso_notificaciones.php">
                        <button type="button" class="nav-notifications-trigger" data-notifications-toggle aria-label="Notificaciones, <?= (int) $navNotifications['no_leidas'] ?> sin leer" aria-expanded="false" aria-controls="nav-notifications-panel" title="Notificaciones">
                            <i data-feather="bell" aria-hidden="true"></i><span class="nav-notifications-fallback" aria-hidden="true">&#128276;</span>
                            <span class="nav-notifications-badge" data-notifications-badge <?= $navNotifications['no_leidas'] ? '' : 'hidden' ?>><?= $navNotifications['no_leidas'] > 99 ? '99+' : (int) $navNotifications['no_leidas'] ?></span>
                        </button>
                        <div class="nav-notifications-panel" id="nav-notifications-panel" data-notifications-panel hidden>
                            <div class="nav-notifications-head"><div><small>Tu actividad</small><h2>Notificaciones</h2></div><button type="button" data-notifications-close aria-label="Cerrar notificaciones" title="Cerrar"><i data-feather="x" aria-hidden="true"></i><span aria-hidden="true">&times;</span></button></div>
                            <div class="nav-notifications-tabs" role="group" aria-label="Filtrar notificaciones">
                                <button type="button" data-notifications-filter="all" aria-pressed="true">Todas</button>
                                <button type="button" data-notifications-filter="unread" aria-pressed="false">No leidas</button>
                                <button type="button" data-notifications-filter="important" aria-pressed="false">Importantes</button>
                            </div>
                            <div class="nav-notifications-list" data-notifications-list>
                                <?php foreach ($navNotifications['items'] as $navNotice): ?>
                                    <article class="nav-notification-item <?= $navNotice['leida'] ? 'is-read' : '' ?>" data-notification-item data-key="<?= htmlspecialchars($navNotice['clave'], ENT_QUOTES, 'UTF-8') ?>" data-important="<?= $navNotice['importante'] ? '1' : '0' ?>" data-read="<?= $navNotice['leida'] ? '1' : '0' ?>">
                                        <span class="nav-notification-icon"><i data-feather="<?= htmlspecialchars($navNotice['icono'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i><span aria-hidden="true"><?= htmlspecialchars(mb_substr($navNotice['titulo'], 0, 1), ENT_QUOTES, 'UTF-8') ?></span></span>
                                        <div class="nav-notification-content"><strong><?= htmlspecialchars($navNotice['titulo'], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($navNotice['detalle'], ENT_QUOTES, 'UTF-8') ?></p><a href="<?= htmlspecialchars($navNotice['url'], ENT_QUOTES, 'UTF-8') ?>" data-notification-link>Ver detalle <i data-feather="arrow-right" aria-hidden="true"></i><span aria-hidden="true">&rarr;</span></a></div>
                                        <?php if (!$navNotice['leida'] && $navNotifications['ready']): ?><button type="button" class="nav-notification-read" data-notification-read aria-label="Marcar como leida: <?= htmlspecialchars($navNotice['titulo'], ENT_QUOTES, 'UTF-8') ?>" title="Marcar como leida"><i data-feather="check" aria-hidden="true"></i><span aria-hidden="true">&#10003;</span></button><?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                                <p class="nav-notifications-empty" data-notifications-empty hidden>No hay avisos en este filtro.</p>
                            </div>
                            <p class="nav-notifications-error" data-notifications-error role="status" hidden>No se pudo actualizar. Intenta nuevamente.</p>
                            <div class="nav-notifications-foot"><span data-notifications-count><?= (int) $navNotifications['no_leidas'] ?> sin leer</span><?php if ($navNotifications['ready']): ?><button type="button" data-notifications-read-all <?= $navNotifications['no_leidas'] ? '' : 'disabled' ?>>Marcar todas como leidas</button><?php endif; ?></div>
                        </div>
                    </div>

                    <!-- Usuario logueado (desktop) -->
                    <div class="hidden md:block relative">
                        <button class="user-dropdown-btn flex items-center gap-2 px-4 py-2 rounded-full">
                            <?= nav_avatar_html($userAvatar, $userInitial, 'desktop-avatar') ?>
                            <?php $firstName = trim(explode(' ', trim($userName))[0] ?? ''); ?>
                            <span class="font-medium"><?= htmlspecialchars($firstName ?: 'Usuario') ?></span>
                            <i data-feather="chevron-down" class="w-4 h-4"></i>
                        </button>

                        <!-- Dropdown -->
                        <div class="user-dropdown-menu absolute right-0 mt-2 w-56 rounded-xl hidden opacity-0 invisible">
                            <div class="dropdown-user-header">
                                <p class="dropdown-user-name">
                                    <?= htmlspecialchars($userName) ?>
                                </p>
                                <span class="dropdown-user-role">
                                    <?= htmlspecialchars($userRole) ?>
                                </span>
                            </div>

                            <div class="p-2">
                                <a href="<?= htmlspecialchars(sg_clean_url('pantallas/mi_perfil.php'), ENT_QUOTES, 'UTF-8') ?>" class="dropdown-item">
                                    <i data-feather="user"></i>
                                    <span>Mi perfil</span>
                                </a>

                                <?php if ($canAccessAdmin): ?>
                                    <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="dropdown-item">
                                        <i data-feather="settings"></i>
                                        <span>Administración</span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="p-2 border-t border-white/10">
                                <a href="<?= BASE_URL ?>sesion/cerrar_sesion.php" class="dropdown-item text-red-600">
                                    <i data-feather="log-out"></i>
                                    <span>Cerrar Sesión</span>
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Login desktop -->
                    <div class="hidden md:block">
                        <a href="<?= htmlspecialchars(sg_clean_url('pantallas/inicio_sesion.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn">
                            <?= htmlspecialchars($navLoginText) ?>
                        </a>
                    </div>

                <?php endif; ?>

                <!-- Botón menú mobile -->
                <div class="mobile-menu-button-wrap">
                    <button id="menuBtn" class="nav-menu-button" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileMenu" onclick="return window.toggleMobileMenu(event);">
                        <i data-feather="menu"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden md:hidden">
        <div class="px-4 pt-2 pb-3 space-y-1">
            <?php if ($isLoggedIn): ?>
                <div class="mobile-user-card">
                    <?= nav_avatar_html($userAvatar, $userInitial, 'mobile-user-avatar') ?>
                    <div>
                        <strong><?= htmlspecialchars($userName ?: 'Usuario') ?></strong>
                        <span><?= htmlspecialchars($userRole ?: 'Sesion activa') ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($navTopItems as $navItem): ?>
                <?php
                $navCode = (string) ($navItem['codigo'] ?? '');
                $children = $navChildren[$navCode] ?? [];
                $mobileItems = !empty($children) ? $children : [$navItem];
                ?>
                <?php foreach ($mobileItems as $mobileItem): ?>
                    <a href="<?= htmlspecialchars(BASE_URL . ltrim((string) $mobileItem['ruta'], '/'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link">
                        <i data-feather="<?= htmlspecialchars((string) ($mobileItem['icono'] ?: 'circle'), ENT_QUOTES, 'UTF-8') ?>"></i>
                        <span><?= htmlspecialchars((string) $mobileItem['etiqueta']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if ($isLoggedIn): ?>
                <div class="border-t pt-2 mt-2">
                    <a href="<?= htmlspecialchars(sg_clean_url('pantallas/mi_perfil.php'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link">
                        <i data-feather="user"></i><span>Mi perfil</span>
                    </a>

                    <?php if ($canAccessAdmin): ?>
                        <a href="<?= BASE_URL ?>pantallas/panel_administrativo.php" class="mobile-nav-link">
                            <i data-feather="settings"></i><span>Administración</span>
                        </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>sesion/cerrar_sesion.php" class="mobile-nav-link text-red-600">
                        <i data-feather="log-out"></i><span>Cerrar Sesión</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="border-t pt-2 mt-2">
                    <a href="<?= htmlspecialchars(sg_clean_url('pantallas/inicio_sesion.php'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link justify-center">
                        <i data-feather="log-in"></i><span><?= htmlspecialchars($navLoginText) ?></span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
    window.toggleMobileMenu = window.toggleMobileMenu || function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        var menu = document.getElementById('mobileMenu');
        var button = document.getElementById('menuBtn');
        if (!menu || !button) return false;

        var isOpen = menu.classList.contains('hidden');
        menu.classList.toggle('hidden', !isOpen);
        menu.classList.toggle('is-open', isOpen);
        button.classList.toggle('is-open', isOpen);
        button.setAttribute('aria-expanded', String(isOpen));
        button.setAttribute('aria-label', isOpen ? 'Cerrar menu' : 'Abrir menu');
        return false;
    };
</script>
