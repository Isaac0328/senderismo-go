// ================= VARIABLES GLOBALES GALERÍA =================
let galleryImages = [];
let currentGalleryIndex = 0;
let galleryModal = null;
let galleryModalImage = null;
let isAnimating = false;

// Contador (ÚNICO)
let counterCurrentEl = null;
let counterTotalEl = null;

// ================= FUNCIONES GALERÍA (GLOBALES) =================

// Inicializar galería
function initGallery() {
    galleryImages = Array.from(document.querySelectorAll(".image-hover img"))
        .map((img) => img.getAttribute("src"))
        .filter(Boolean);

    galleryModal = document.getElementById("galleryModal");
    galleryModalImage = document.getElementById("galleryImage");

    // Contador
    counterCurrentEl = document.getElementById("currentIndex");
    counterTotalEl = document.getElementById("totalImages");

    // Set total una vez
    if (counterTotalEl) counterTotalEl.textContent = String(galleryImages.length);

    preloadGalleryImages();
}

function preloadGalleryImages() {
    galleryImages.forEach((src) => {
        const img = new Image();
        img.src = src;
    });
}

function updateGalleryCounter() {
    if (!counterCurrentEl || !counterTotalEl) return;
    counterCurrentEl.textContent = String(currentGalleryIndex + 1);
    counterTotalEl.textContent = String(galleryImages.length);
}

// Abrir galería
function openGallery(index) {
    if (isAnimating) return;
    if (!galleryImages.length) return;
    isAnimating = true;

    if (!galleryModal || !galleryModalImage) initGallery();

    if (index < 0 || index >= galleryImages.length) index = 0;
    currentGalleryIndex = index;

    galleryModalImage.src = galleryImages[currentGalleryIndex];
    galleryModalImage.style.opacity = "0";
    galleryModalImage.style.transform = "scale(0.8)";

    // Mostrar modal como flex (centra contenido)
    galleryModal.classList.remove("hidden");
    galleryModal.classList.add("flex");

    // Contador
    updateGalleryCounter();

    // Reflow
    void galleryModal.offsetWidth;

    setTimeout(() => {
        galleryModal.classList.add("modal-open");
        galleryModalImage.style.opacity = "1";
        galleryModalImage.style.transform = "scale(1)";

        document.body.style.overflow = "hidden";
        document.documentElement.style.overflow = "hidden";

        isAnimating = false;
    }, 10);
}

// Cerrar galería
function closeGallery() {
    if (isAnimating) return;
    isAnimating = true;

    galleryModal.classList.remove("modal-open");
    galleryModalImage.style.opacity = "0";
    galleryModalImage.style.transform = "scale(0.8)";

    setTimeout(() => {
        galleryModal.classList.add("hidden");
        galleryModal.classList.remove("flex");

        document.body.style.overflow = "";
        document.documentElement.style.overflow = "";

        galleryModalImage.style.opacity = "1";
        galleryModalImage.style.transform = "scale(1)";

        isAnimating = false;
    }, 400);
}

// Cambiar imagen
function changeImage(direction) {
    if (isAnimating) return;
    isAnimating = true;

    galleryModalImage.style.opacity = "0";
    galleryModalImage.style.transform = `scale(0.9) translateX(${direction === 1 ? "-20px" : "20px"})`;

    setTimeout(() => {
        if (direction === 1) {
            currentGalleryIndex = (currentGalleryIndex + 1) % galleryImages.length;
        } else {
            currentGalleryIndex = (currentGalleryIndex - 1 + galleryImages.length) % galleryImages.length;
        }

        galleryModalImage.src = galleryImages[currentGalleryIndex];

        // Actualizar contador
        updateGalleryCounter();

        galleryModalImage.style.transform = `scale(1.1) translateX(${direction === 1 ? "20px" : "-20px"})`;
        void galleryModalImage.offsetWidth;

        setTimeout(() => {
            galleryModalImage.style.opacity = "1";
            galleryModalImage.style.transform = "scale(1) translateX(0)";
            isAnimating = false;
        }, 10);
    }, 300);
}

function nextImage() {
    changeImage(1);
}

function prevImage() {
    changeImage(-1);
}

// ================= EVENTOS =================
document.addEventListener("DOMContentLoaded", function () {
    initGallery();

    // Scroll suave
    const smoothScrollLinks = document.querySelectorAll('a.smooth-scroll, a[href^="#"]:not([href="#"])');
    smoothScrollLinks.forEach((link) => {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            const targetId = this.getAttribute("href");
            if (targetId === "#") return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                const navbarHeight = document.querySelector("nav")?.offsetHeight || 80;
                const targetPosition = targetElement.offsetTop - navbarHeight;
                window.scrollTo({ top: targetPosition, behavior: "smooth" });
            }
        });
    });

    // Animación tarjetas
    const cards = document.querySelectorAll(".hover-card");
    cards.forEach((card) => {
        card.addEventListener("mouseenter", function () {
            this.style.transform = "translateY(-10px)";
            this.style.boxShadow = "0 20px 40px rgba(0,0,0,0.1)";
        });

        card.addEventListener("mouseleave", function () {
            this.style.transform = "translateY(0)";
            this.style.boxShadow = "";
        });

        card.addEventListener("click", function () {
            this.style.transform = "scale(0.98)";
            setTimeout(() => (this.style.transform = ""), 150);
        });
    });

    // Click en thumbs de galería
    const galleryItems = document.querySelectorAll(".image-hover");
    galleryItems.forEach((item, index) => {
        const img = item.querySelector("img");

        item.addEventListener("mouseenter", function () {
            if (!isAnimating) {
                img.style.transform = "scale(1.05)";
                this.style.transform = "translateY(-5px)";
            }
        });

        item.addEventListener("mouseleave", function () {
            if (!isAnimating) {
                img.style.transform = "scale(1)";
                this.style.transform = "translateY(0)";
            }
        });

        item.addEventListener("click", function (e) {
            e.preventDefault();

            this.style.transform = "scale(0.95)";
            setTimeout(() => (this.style.transform = ""), 200);

            setTimeout(() => openGallery(index), 150);
        });
    });

    // Modal events
    if (galleryModal) {
        // Nota: como ahora tienes un overlay DIV dentro, esto cierra al hacer click en el fondo
        galleryModal.addEventListener("click", (e) => {
            // Si haces click en el contenedor principal o en el overlay (primer hijo)
            if (!isAnimating && (e.target === galleryModal || e.target === galleryModal.firstElementChild)) {
                closeGallery();
            }
        });

        document.addEventListener("keydown", (e) => {
            if (!galleryModal || galleryModal.classList.contains("hidden") || isAnimating) return;

            if (e.key === "Escape") closeGallery();
            if (e.key === "ArrowRight" || e.key === " ") {
                e.preventDefault();
                nextImage();
            }
            if (e.key === "ArrowLeft") {
                e.preventDefault();
                prevImage();
            }
        });

        // Swipe móvil
        let touchStartX = 0;
        let touchEndX = 0;

        galleryModal.addEventListener(
            "touchstart",
            (e) => (touchStartX = e.changedTouches[0].screenX),
            { passive: true }
        );

        galleryModal.addEventListener("click", (e) => {
            if (isAnimating) return;
            if (
                e.target.closest('button') ||
                e.target.id === 'galleryImage' || 
                e.target.closest('#galleryImage')                
            ) {
                return;
            }
            closeGallery();
        });
    }

    // Aparición al scroll
    const observer = new IntersectionObserver(
        (entries) => entries.forEach((entry) => entry.isIntersecting && entry.target.classList.add("animate-fade-in")),
        { threshold: 0.1 }
    );

    document.querySelectorAll("section").forEach((section) => observer.observe(section));
});

// Exponer globales
window.openGallery = openGallery;
window.closeGallery = closeGallery;
window.nextImage = nextImage;
window.prevImage = prevImage;
