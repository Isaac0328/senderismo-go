document.addEventListener('DOMContentLoaded', function () {
    const shell = document.querySelector('.admin-shell');
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const openButton = document.querySelector('[data-admin-sidebar-toggle]');
    const overlay = document.querySelector('[data-admin-sidebar-close]');
    const toggles = document.querySelectorAll('[data-admin-nav-toggle]');
    const sidebarPreferenceKey = 'sgAdminSidebarCollapsed';

    const isDesktop = () => window.innerWidth > 1180;

    const closeSidebar = () => {
        if (!sidebar || !overlay) return;
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
    };

    const syncDesktopSidebar = () => {
        if (!shell) return;

        if (isDesktop()) {
            closeSidebar();
            shell.classList.toggle(
                'is-sidebar-collapsed',
                localStorage.getItem(sidebarPreferenceKey) === '1'
            );
            return;
        }

        shell.classList.remove('is-sidebar-collapsed');
    };

    if (openButton && sidebar && overlay && shell) {
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

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const submenu = toggle.nextElementSibling;
            const expanded = toggle.getAttribute('aria-expanded') === 'true';

            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            if (submenu) {
                submenu.hidden = expanded;
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
            if (openButton) {
                openButton.setAttribute('aria-expanded', 'false');
            }
        }
    });

    syncDesktopSidebar();
    window.addEventListener('resize', syncDesktopSidebar);
});
