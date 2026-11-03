document.addEventListener('DOMContentLoaded', function () {
  // ================= MENÚ MÓVIL =================
  const menuBtn = document.getElementById('menuBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  function safeFeatherReplace() {
    if (typeof feather !== 'undefined') feather.replace();
  }

  function setMobileMenuState(isOpen) {
    if (!menuBtn || !mobileMenu) return;

    mobileMenu.classList.toggle('hidden', !isOpen);
    mobileMenu.classList.toggle('is-open', isOpen);
    menuBtn.classList.toggle('is-open', isOpen);
    menuBtn.setAttribute('aria-expanded', String(isOpen));
    menuBtn.setAttribute('aria-label', isOpen ? 'Cerrar menu' : 'Abrir menu');
  }

  window.toggleMobileMenu = function (event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    setMobileMenuState(mobileMenu ? mobileMenu.classList.contains('hidden') : false);
    return false;
  };

  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', function (event) {
      if (event.defaultPrevented) return;
      event.preventDefault();
      event.stopPropagation();
      setMobileMenuState(mobileMenu.classList.contains('hidden'));
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        setMobileMenuState(false);
      });
    });
  }

  const notices = document.querySelector('[data-notifications]');
  if (notices) {
    const toggle = notices.querySelector('[data-notifications-toggle]');
    const panel = notices.querySelector('[data-notifications-panel]');
    const badge = notices.querySelector('[data-notifications-badge]');
    const count = notices.querySelector('[data-notifications-count]');
    const items = [...notices.querySelectorAll('[data-notification-item]')];
    const empty = notices.querySelector('[data-notifications-empty]');
    const error = notices.querySelector('[data-notifications-error]');
    const allButton = notices.querySelector('[data-notifications-read-all]');
    let filter = 'all';

    const update = () => {
      let shown = 0;
      const unread = items.filter(item => item.dataset.read === '0').length;
      items.forEach(item => {
        item.hidden = (filter === 'unread' && item.dataset.read === '1') ||
          (filter === 'important' && item.dataset.important !== '1');
        if (!item.hidden) shown++;
      });
      empty.hidden = shown > 0;
      badge.hidden = unread === 0;
      badge.textContent = unread > 99 ? '99+' : String(unread);
      toggle.setAttribute('aria-label', `Notificaciones, ${unread} sin leer`);
      count.textContent = `${unread} sin leer`;
      if (allButton) allButton.disabled = unread === 0;
    };
    const setOpen = (open) => {
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
      if (open) {
        setMobileMenuState(false);
        update();
      }
    };
    const mark = async (action, key = '') => {
      error.hidden = true;
      const form = new URLSearchParams({action, key, csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''});
      const response = await fetch(notices.dataset.endpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
        body: form.toString()
      });
      if (!response.ok || !(await response.json()).ok) throw new Error('No se pudo guardar la lectura');
      items.filter(item => action === 'read_all' || item.dataset.key === key).forEach(item => {
        item.dataset.read = '1';
        item.classList.add('is-read');
        item.querySelector('[data-notification-read]')?.remove();
      });
      update();
    };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    notices.querySelector('[data-notifications-close]').addEventListener('click', () => {
      setOpen(false);
      toggle.focus();
    });
    notices.querySelectorAll('[data-notifications-filter]').forEach(button => button.addEventListener('click', () => {
      filter = button.dataset.notificationsFilter;
      notices.querySelectorAll('[data-notifications-filter]').forEach(tab => tab.setAttribute('aria-pressed', String(tab === button)));
      update();
    }));
    notices.querySelectorAll('[data-notification-read]').forEach(button => button.addEventListener('click', async () => {
      button.disabled = true;
      try { await mark('read', button.closest('[data-notification-item]').dataset.key); }
      catch (_) { button.disabled = false; error.hidden = false; }
    }));
    allButton?.addEventListener('click', async () => {
      allButton.disabled = true;
      try { await mark('read_all'); }
      catch (_) { allButton.disabled = false; error.hidden = false; }
    });
    notices.querySelectorAll('[data-notification-link]').forEach(link => link.addEventListener('click', async (event) => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
      event.preventDefault();
      const destination = link.href;
      const item = link.closest('[data-notification-item]');
      if (item.dataset.read === '0') {
        try { await mark('read', item.dataset.key); } catch (_) {}
      }
      window.location.assign(destination);
    }));
    document.addEventListener('click', event => {
      if (!notices.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !panel.hidden) {
        setOpen(false);
        toggle.focus();
      }
    });
    update();
  }

  // ================= MENÚ DESPLEGABLE DE USUARIO =================
  const userDropdownBtn = document.querySelector('.user-dropdown-btn');
  const userDropdownMenu = document.querySelector('.user-dropdown-menu');

  if (userDropdownBtn && userDropdownMenu) {
    let dropdownTimeout;

    userDropdownBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      userDropdownMenu.classList.toggle('hidden');
      userDropdownMenu.classList.toggle('opacity-0');
      userDropdownMenu.classList.toggle('invisible');
    });

    userDropdownBtn.addEventListener('mouseenter', function () {
      clearTimeout(dropdownTimeout);
      userDropdownMenu.classList.remove('hidden', 'opacity-0', 'invisible');
    });

    userDropdownBtn.addEventListener('mouseleave', function () {
      dropdownTimeout = setTimeout(() => {
        userDropdownMenu.classList.add('opacity-0', 'invisible');
        setTimeout(() => {
          userDropdownMenu.classList.add('hidden');
        }, 300);
      }, 300);
    });

    userDropdownMenu.addEventListener('mouseenter', function () {
      clearTimeout(dropdownTimeout);
    });

    userDropdownMenu.addEventListener('mouseleave', function () {
      dropdownTimeout = setTimeout(() => {
        userDropdownMenu.classList.add('opacity-0', 'invisible');
        setTimeout(() => {
          userDropdownMenu.classList.add('hidden');
        }, 300);
      }, 300);
    });

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function (e) {
      if (!userDropdownBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
        userDropdownMenu.classList.add('opacity-0', 'invisible');
        setTimeout(() => {
          userDropdownMenu.classList.add('hidden');
        }, 300);
      }
    });

    // Cerrar dropdown al hacer clic en un enlace
    userDropdownMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        userDropdownMenu.classList.add('opacity-0', 'invisible');
        setTimeout(() => {
          userDropdownMenu.classList.add('hidden');
        }, 300);
      });
    });
  }

  // ================= MENÚ DESPLEGABLE DE SENDEROS =================
  const senderosDropdown = document.querySelector('.nav-dropdown');
  const senderosToggle = document.querySelector('.nav-dropdown-toggle');

  if (senderosDropdown && senderosToggle) {
    senderosToggle.addEventListener('click', function (e) {
      if (window.innerWidth >= 768) {
        e.preventDefault();
        senderosDropdown.classList.toggle('is-open');
      }
    });

    document.addEventListener('click', function (e) {
      if (!senderosDropdown.contains(e.target)) {
        senderosDropdown.classList.remove('is-open');
      }
    });
  }

  // ================= SCROLL EFFECT EN NAVBAR =================
  const navbar = document.querySelector('nav');
  let lastScrollTop = 0;

  if (navbar) {
    window.addEventListener('scroll', function () {
      const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

      // Evitar comportamiento raro en el tope
      if (scrollTop <= 0) {
        navbar.style.transform = 'translateY(0)';
        lastScrollTop = 0;
        navbar.classList.remove('navbar-scrolled');
        return;
      }

      if (scrollTop > 100) {
        navbar.classList.add('navbar-scrolled');
      } else {
        navbar.classList.remove('navbar-scrolled');
      }

      // Ocultar/mostrar navbar al hacer scroll
      if (scrollTop > lastScrollTop && scrollTop > 200) {
        // Scroll hacia abajo
        navbar.style.transform = 'translateY(-100%)';
        navbar.style.transition = 'transform 0.3s ease';
      } else {
        // Scroll hacia arriba
        navbar.style.transform = 'translateY(0)';
      }

      lastScrollTop = scrollTop;
    });
  }

  // ================= INICIALIZAR FEATHER ICONS =================
  safeFeatherReplace();

  // ================= PREVENIR CIERRE ACCIDENTAL / ESCAPE =================
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    // Cerrar dropdown usuario
    if (userDropdownMenu && !userDropdownMenu.classList.contains('hidden')) {
      userDropdownMenu.classList.add('opacity-0', 'invisible');
      setTimeout(() => {
        userDropdownMenu.classList.add('hidden');
      }, 300);
    }

    // Cerrar menú móvil
    if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
      setMobileMenuState(false);
    }
  });

  // ================= RESPONSIVE BEHAVIOR =================
  function handleResize() {
    const isMobile = window.innerWidth < 768;

    if (isMobile && userDropdownMenu && !userDropdownMenu.classList.contains('hidden')) {
      userDropdownMenu.classList.add('hidden', 'opacity-0', 'invisible');
    }

    // Ajustar padding del navbar en móvil (safe-area)
    if (navbar) {
      if (isMobile) {
        navbar.style.paddingTop = 'env(safe-area-inset-top)';
      } else {
        navbar.style.paddingTop = '';
      }
    }
  }

  window.addEventListener('resize', handleResize);
  handleResize(); // Ejecutar al cargar
});

// ================= FUNCIÓN PARA CERRAR MENÚ MÓVIL (GLOBAL) =================
window.closeMobileMenu = function () {
  const mobileMenu = document.getElementById('mobileMenu');
  const menuBtn = document.getElementById('menuBtn');

  if (mobileMenu) {
    mobileMenu.classList.add('hidden');
    mobileMenu.classList.remove('is-open');
  }

  if (menuBtn) {
    menuBtn.classList.remove('is-open');
    menuBtn.setAttribute('aria-expanded', 'false');
    menuBtn.setAttribute('aria-label', 'Abrir menu');
  }
};

// ================= FUNCIÓN PARA TOGGLE MENÚ USUARIO (GLOBAL) =================
window.toggleUserMenu = function () {
  const userDropdownMenu = document.querySelector('.user-dropdown-menu');

  if (userDropdownMenu) {
    const isHidden = userDropdownMenu.classList.contains('hidden');

    if (isHidden) {
      userDropdownMenu.classList.remove('hidden');
      setTimeout(() => {
        userDropdownMenu.classList.remove('opacity-0', 'invisible');
      }, 10);
    } else {
      userDropdownMenu.classList.add('opacity-0', 'invisible');
      setTimeout(() => {
        userDropdownMenu.classList.add('hidden');
      }, 300);
    }
  }
};
