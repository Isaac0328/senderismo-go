document.addEventListener('DOMContentLoaded', function () {
  // ================= MENÚ MÓVIL =================
  const menuBtn = document.getElementById('menuBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  const icon = menuBtn ? menuBtn.querySelector('i') : null;

  function safeFeatherReplace() {
    if (typeof feather !== 'undefined') feather.replace();
  }

  if (menuBtn && mobileMenu && icon) {
    menuBtn.addEventListener('click', function () {
      mobileMenu.classList.toggle('hidden');

      icon.setAttribute(
        'data-feather',
        mobileMenu.classList.contains('hidden') ? 'menu' : 'x'
      );

      safeFeatherReplace();
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileMenu.classList.add('hidden');
        icon.setAttribute('data-feather', 'menu');
        safeFeatherReplace();
      });
    });
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

  // ================= MENÃš DESPLEGABLE DE SENDEROS =================
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
      mobileMenu.classList.add('hidden');
      if (icon) icon.setAttribute('data-feather', 'menu');
      safeFeatherReplace();
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
  const icon = menuBtn ? menuBtn.querySelector('i') : null;

  if (mobileMenu) {
    mobileMenu.classList.add('hidden');
  }

  if (icon) {
    icon.setAttribute('data-feather', 'menu');
    if (typeof feather !== 'undefined') {
      feather.replace();
    }
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
