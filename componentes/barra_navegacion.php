<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/recordar_sesion.php';
sg_restaurar_sesion_recordada();

$isLoggedIn = isset($_SESSION['usuario_id']);
$userName = $_SESSION['usuario_nombre'] ?? '';
$userRole = $_SESSION['usuario_rol'] ?? '';
$userInitial = $userName ? strtoupper(substr($userName, 0, 1)) : '';
$userAvatar = '';

if ($isLoggedIn && !empty($_SESSION['usuario_id'])) {
    $connAvatar = $conn ?? null;

    if (!$connAvatar instanceof mysqli) {
        $conexionPath = __DIR__ . '/../bd/conexion.php';
        if (is_file($conexionPath)) {
            require $conexionPath;
            $connAvatar = $conn ?? null;
        }
    }

    if ($connAvatar instanceof mysqli) {
        $uidAvatar = (int) $_SESSION['usuario_id'];
        $stmtAvatar = mysqli_prepare($connAvatar, "SELECT imagen_perfil FROM detalles_usuarios WHERE usuario_id = ? LIMIT 1");
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
    }
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
                <a href="<?= BASE_URL ?>pantallas/inicio.php" class="flex items-center gap-3">
                    <img src="<?= BASE_URL ?>imagenes/logo/logo_sg.png" alt="Senderismo Go!"
                        class="h-12 sm:h-14 md:h-16 w-auto">
                </a>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex absolute left-1/2 -translate-x-1/2">
                <ul class="flex items-center space-x-8">
                    <li><a href="<?= BASE_URL ?>pantallas/inicio.php" class="nav-link">Inicio</a></li>
                    <li><a href="<?= BASE_URL ?>pantallas/nosotros.php" class="nav-link">Nosotros</a></li>
                    <li class="nav-dropdown">
                        <a href="<?= BASE_URL ?>pantallas/senderos.php" class="nav-link nav-dropdown-toggle">
                            Senderos
                            <i data-feather="chevron-down"></i>
                        </a>
                        <div class="nav-dropdown-menu">
                            <a href="<?= BASE_URL ?>pantallas/senderos.php">
                                <i data-feather="calendar"></i>
                                <span>Proximos</span>
                            </a>
                            <a href="<?= BASE_URL ?>pantallas/senderos_visitados.php">
                                <i data-feather="check-circle"></i>
                                <span>Visitados</span>
                            </a>
                        </div>
                    </li>
                    <li><a href="<?= BASE_URL ?>pantallas/contacto.php" class="nav-link">Contacto</a></li>
                </ul>
            </div>

            <!-- Zona derecha -->
            <div class="flex items-center gap-3 md:gap-4">

                <?php if ($isLoggedIn): ?>
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
                                <a href="<?= BASE_URL ?>pantallas/mi_perfil.php" class="dropdown-item">
                                    <i data-feather="user"></i>
                                    <span>Mi perfil</span>
                                </a>

                                <?php if (($_SESSION['usuario_rol_id'] ?? 0) == 1): ?>
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
                        <a href="<?= BASE_URL ?>pantallas/inicio_sesion.php" class="btn">
                            Iniciar Sesión
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

            <a href="<?= BASE_URL ?>pantallas/inicio.php" class="mobile-nav-link"><i
                    data-feather="home"></i><span>Inicio</span></a>
            <a href="<?= BASE_URL ?>pantallas/nosotros.php" class="mobile-nav-link"><i
                    data-feather="users"></i><span>Nosotros</span></a>
            <a href="<?= BASE_URL ?>pantallas/senderos.php" class="mobile-nav-link"><i
                    data-feather="map"></i><span>Proximos senderos</span></a>
            <a href="<?= BASE_URL ?>pantallas/senderos_visitados.php" class="mobile-nav-link"><i
                    data-feather="check-circle"></i><span>Senderos visitados</span></a>
            <a href="<?= BASE_URL ?>pantallas/contacto.php" class="mobile-nav-link"><i
                    data-feather="mail"></i><span>Contacto</span></a>

            <?php if ($isLoggedIn): ?>
                <div class="border-t pt-2 mt-2">
                    <a href="<?= BASE_URL ?>pantallas/mi_perfil.php" class="mobile-nav-link">
                        <i data-feather="user"></i><span>Mi perfil</span>
                    </a>

                    <?php if (($_SESSION['usuario_rol_id'] ?? 0) == 1): ?>
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
                    <a href="<?= BASE_URL ?>pantallas/inicio_sesion.php" class="mobile-nav-link justify-center">
                        <i data-feather="log-in"></i><span>Iniciar Sesión</span>
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
