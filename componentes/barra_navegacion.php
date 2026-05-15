<?php
require_once __DIR__ . '/../configuracion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['usuario_id']);
$userName = $_SESSION['usuario_nombre'] ?? '';
$userRole = $_SESSION['usuario_rol'] ?? '';
$userInitial = $userName ? strtoupper(substr($userName, 0, 1)) : '';
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
                            <div class="w-8 h-8 rounded-full flex items-center justify-center">
                                <?= $userInitial ?>
                            </div>
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

                    <!-- Logout mobile -->
                    <div class="md:hidden">
                        <a href="<?= BASE_URL ?>sesion/cerrar_sesion.php" class="mobile-nav-link text-red-600">
                            <i data-feather="log-out"></i>
                            <span>Salir</span>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Login desktop -->
                    <div class="hidden md:block">
                        <a href="<?= BASE_URL ?>pantallas/inicio_sesion.php" class="btn">
                            Iniciar Sesión
                        </a>
                    </div>

                    <!-- Login mobile -->
                    <div class="md:hidden">
                        <a href="<?= BASE_URL ?>pantallas/inicio_sesion.php" class="mobile-nav-link">
                            <i data-feather="log-in"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Botón menú mobile -->
                <div class="mobile-menu-button-wrap">
                    <button id="menuBtn" class="nav-menu-button" type="button" aria-label="Abrir menu">
                        <i data-feather="menu"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden md:hidden">
        <div class="px-4 pt-2 pb-3 space-y-1">
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
