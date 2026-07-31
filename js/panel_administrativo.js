(function () {
    'use strict';

    function initAdminPanel() {
        const shell = document.querySelector('.admin-shell');
        const sidebar = document.querySelector('[data-admin-sidebar]');
        const openButton = document.querySelector('[data-admin-sidebar-toggle]');
        const overlay = document.querySelector('[data-admin-sidebar-close]');
        const sidebarPreferenceKey = 'sgAdminSidebarCollapsed';
        const isDesktop = () => window.innerWidth > 1180;

        if (!shell || shell.dataset.adminReady === '1') return;
        shell.dataset.adminReady = '1';

        const closeSidebar = () => {
            if (!sidebar || !overlay) return;
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-visible');
        };

        const syncDesktopSidebar = () => {
            if (isDesktop()) {
                closeSidebar();
                const collapsed = localStorage.getItem(sidebarPreferenceKey) === '1';
                shell.classList.toggle('is-sidebar-collapsed', collapsed);
                if (openButton) openButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                return;
            }

            shell.classList.remove('is-sidebar-collapsed');
        };

        if (openButton && sidebar && overlay) {
            openButton.addEventListener('click', () => {
                if (isDesktop()) {
                    const collapsed = shell.classList.toggle('is-sidebar-collapsed');
                    localStorage.setItem(sidebarPreferenceKey, collapsed ? '1' : '0');
                    openButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    return;
                }

                sidebar.classList.add('is-open');
                overlay.classList.add('is-visible');
                openButton.setAttribute('aria-expanded', 'true');
            });

            overlay.addEventListener('click', () => {
                closeSidebar();
                openButton.setAttribute('aria-expanded', 'false');
            });
        }

        shell.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-admin-nav-toggle]');
            if (!toggle || !shell.contains(toggle)) return;

            const submenu = toggle.nextElementSibling;
            if (!submenu || !submenu.classList.contains('admin-nav-submenu')) return;

            const shouldOpen = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            submenu.hidden = !shouldOpen;
            submenu.classList.toggle('is-open', shouldOpen);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            closeSidebar();
            if (openButton) openButton.setAttribute('aria-expanded', 'false');
        });

        syncDesktopSidebar();
        window.addEventListener('resize', syncDesktopSidebar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminPanel, { once: true });
    } else {
        initAdminPanel();
    }
})();
