// ================= INICIALIZACIÓN FEATHER ICONS =================
document.addEventListener('DOMContentLoaded', function () {
    // Inicializar feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Reemplazar icons periódicamente por si hay carga dinámica
    setInterval(() => {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }, 1000);

    // ================= SCROLL SUAVE PARA ENLACES INTERNOS =================
    const internalLinks = document.querySelectorAll('a[href^="#"]:not([href="#"])');

    internalLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);

            if (targetElement) {
                const navbarHeight = document.querySelector('nav')?.offsetHeight || 80;
                const targetPosition = targetElement.offsetTop - navbarHeight;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ================= ANIMACIONES AL SCROLL =================
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Animaciones específicas por clase
                if (entry.target.classList.contains('animate-fade-left')) {
                    entry.target.classList.add('animated');
                }
                if (entry.target.classList.contains('animate-fade-right')) {
                    entry.target.classList.add('animated');
                }
                if (entry.target.classList.contains('animate-fade-up')) {
                    entry.target.classList.add('animated');
                }
                if (entry.target.classList.contains('animate-scale-up')) {
                    entry.target.classList.add('animated');
                }
                if (entry.target.classList.contains('animate-team-card')) {
                    entry.target.classList.add('animated');
                }
                if (entry.target.classList.contains('animate-text-up')) {
                    entry.target.classList.add('animated');
                }

                // Una vez animado, dejar de observar
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observar todos los elementos con clases de animación
    const animatedElements = document.querySelectorAll(
        '.animate-fade-left, .animate-fade-right, .animate-fade-up, .animate-scale-up, .animate-team-card, .animate-text-up'
    );

    animatedElements.forEach(element => {
        // Agregar estilo inicial para las animaciones
        if (!element.classList.contains('animated')) {
            observer.observe(element);
        }
    });

    // ================= VIDEO MODAL =================
    const videoModal = document.getElementById('videoModal');
    const playButton = document.getElementById('playVideo');
    const closeVideoButton = document.getElementById('closeVideo');
    const videoFrame = document.getElementById('videoFrame');

    if (playButton && videoModal) {
        playButton.addEventListener('click', function () {
            // Mostrar modal con animación
            videoModal.classList.remove('hidden');
            videoModal.classList.add('flex');

            // Forzar reflow para activar animación CSS
            void videoModal.offsetWidth;

            // Reproducir video automáticamente
            const currentSrc = videoFrame.src;
            videoFrame.src = currentSrc.includes('autoplay=1') ? currentSrc : currentSrc + '&autoplay=1';

            // Bloquear scroll
            document.body.style.overflow = 'hidden';
        });
    }

    if (closeVideoButton && videoModal) {
        closeVideoButton.addEventListener('click', function () {
            // Pausar video
            videoFrame.src = videoFrame.src.replace('&autoplay=1', '');

            // Ocultar modal con animación
            videoModal.classList.add('hidden');
            videoModal.classList.remove('flex');

            // Restaurar scroll
            document.body.style.overflow = '';
        });
    }

    // Cerrar modal al hacer click fuera
    if (videoModal) {
        videoModal.addEventListener('click', function (e) {
            if (e.target === videoModal) {
                // Pausar video
                videoFrame.src = videoFrame.src.replace('&autoplay=1', '');

                // Ocultar modal
                videoModal.classList.add('hidden');
                videoModal.classList.remove('flex');
                document.body.style.overflow = '';
            }
        });
    }

    // Cerrar modal con tecla Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && videoModal && !videoModal.classList.contains('hidden')) {
            videoFrame.src = videoFrame.src.replace('&autoplay=1', '');
            videoModal.classList.add('hidden');
            videoModal.classList.remove('flex');
            document.body.style.overflow = '';
        }
    });

    // ================= ANIMACIÓN DE TARJETAS AL HOVER =================
    const teamCards = document.querySelectorAll('.team-card');

    teamCards.forEach(card => {
        card.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-10px)';
            this.style.boxShadow = '0 20px 40px rgba(0,0,0,0.15)';
        });

        card.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.08)';
        });
    });

    const mvCards = document.querySelectorAll('.mv-card');

    mvCards.forEach(card => {
        card.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-15px)';
            this.style.boxShadow = '0 20px 40px rgba(0,0,0,0.12)';
        });

        card.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.08)';
        });
    });

    // ================= CONTADORES ANIMADOS (OPCIONAL) =================
    const statBoxes = document.querySelectorAll('.stat-box span');

    const animateCounter = (element, target) => {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current);
            }
        }, 30);
    };

    // Observar cuando los contadores están en vista
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                const target = parseInt(element.textContent.replace('+', '').replace('%', ''));

                if (!isNaN(target)) {
                    animateCounter(element, target);
                }

                counterObserver.unobserve(element);
            }
        });
    }, { threshold: 0.5 });

    statBoxes.forEach(box => {
        counterObserver.observe(box);
    });

    // ================= EFECTO PARALLAX EN HERO =================
    const heroSection = document.getElementById('nosotros-hero');

    if (heroSection) {
        window.addEventListener('scroll', function () {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;

            const bgImage = heroSection.querySelector('img');
            if (bgImage) {
                bgImage.style.transform = `translateY(${rate}px)`;
            }
        });
    }

    // ================= ANIMACIÓN DE ICONOS EN LOGROS =================
    const logroIcons = document.querySelectorAll('.logro-icon');

    logroIcons.forEach(icon => {
        icon.addEventListener('mouseenter', function () {
            this.style.transform = 'rotateY(180deg)';
        });

        icon.addEventListener('mouseleave', function () {
            this.style.transform = 'rotateY(0deg)';
        });
    });

    // ================= INICIALIZAR TODAS LAS ANIMACIONES AL CARGAR =================
    window.addEventListener('load', function () {
        // Forzar re-render para activar animaciones
        setTimeout(() => {
            animatedElements.forEach(element => {
                element.style.animationPlayState = 'running';
            });
        }, 500);
    });
});

// ================= FUNCIÓN PARA REPRODUCIR VIDEO (GLOBAL) =================
window.playVideo = function () {
    const videoModal = document.getElementById('videoModal');
    const videoFrame = document.getElementById('videoFrame');

    if (videoModal && videoFrame) {
        videoModal.classList.remove('hidden');
        videoModal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        const currentSrc = videoFrame.src;
        if (!currentSrc.includes('autoplay=1')) {
            videoFrame.src = currentSrc + '&autoplay=1';
        }
    }
};

// ================= FUNCIÓN PARA CERRAR VIDEO (GLOBAL) =================
window.closeVideo = function () {
    const videoModal = document.getElementById('videoModal');
    const videoFrame = document.getElementById('videoFrame');

    if (videoModal && videoFrame) {
        videoFrame.src = videoFrame.src.replace('&autoplay=1', '');
        videoModal.classList.add('hidden');
        videoModal.classList.remove('flex');
        document.body.style.overflow = '';
    }
};