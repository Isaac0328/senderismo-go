document.addEventListener('DOMContentLoaded', function () {
    /* ================= FEATHER ICONS ================= */
    function safeFeatherReplace() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    safeFeatherReplace();

    /* ================= EXPLORADOR DE PRÓXIMOS SENDEROS ================= */
    const explorer = document.querySelector('[data-senderos-explorer]');
    if (explorer) {
        const tools = explorer.querySelector('.senderos-explorer-tools');
        const search = explorer.querySelector('[data-senderos-search]');
        const difficulty = explorer.querySelector('[data-senderos-difficulty]');
        const province = explorer.querySelector('[data-senderos-province]');
        const cards = Array.from(explorer.querySelectorAll('[data-sendero-card]'));
        const count = explorer.querySelector('[data-senderos-count]');
        const empty = explorer.querySelector('[data-senderos-empty]');
        const reset = explorer.querySelector('[data-senderos-reset]');
        const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

        const filterCards = () => {
            const term = normalize(search.value);
            let visible = 0;
            cards.forEach((card) => {
                const matches = normalize(card.dataset.search || '').includes(term)
                    && (!difficulty.value || card.dataset.difficulty === difficulty.value)
                    && (!province.value || card.dataset.province === province.value);
                card.hidden = !matches;
                if (matches) visible += 1;
            });
            count.textContent = `${visible} ${visible === 1 ? 'sendero disponible' : 'senderos disponibles'}`;
            empty.hidden = visible !== 0;
        };

        tools.hidden = false;
        tools.addEventListener('submit', (event) => event.preventDefault());
        search.addEventListener('input', filterCards);
        difficulty.addEventListener('change', filterCards);
        province.addEventListener('change', filterCards);
        reset.addEventListener('click', () => {
            search.value = '';
            difficulty.value = '';
            province.value = '';
            filterCards();
            search.focus();
        });
        filterCards();
    }

    /* ================= ANIMACIÓN DE ENTRADA ================= */
    const animatedItems = document.querySelectorAll(
        '.sendero-card, .visitados-banner, .calendar-card, .section-heading'
    );

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('is-visible');
                }, index * 80);
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.12
    });

    animatedItems.forEach(item => {
        item.classList.add('fade-up-init');
        observer.observe(item);
    });

    /* ================= CALENDARIO - TITULO DEL MES ================= */
    const monthTitle = document.getElementById('calendarMonthTitle');
    const prevBtn = document.getElementById('prevMonth');
    const nextBtn = document.getElementById('nextMonth');

    const monthNames = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    let currentDate = new Date();

    function updateCalendarTitle() {
        if (!monthTitle) return;

        const month = monthNames[currentDate.getMonth()];
        const year = currentDate.getFullYear();

        monthTitle.textContent = `${month} ${year}`;
    }

    updateCalendarTitle();

    /* ================= NAVEGACIÓN VISUAL DEL MES ================= */
    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            currentDate.setMonth(currentDate.getMonth() - 1);
            updateCalendarTitle();
            pulseCalendar();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            currentDate.setMonth(currentDate.getMonth() + 1);
            updateCalendarTitle();
            pulseCalendar();
        });
    }

    function pulseCalendar() {
        const calendarCard = document.querySelector('.calendar-card');
        if (!calendarCard) return;

        calendarCard.classList.remove('calendar-pulse');
        void calendarCard.offsetWidth;
        calendarCard.classList.add('calendar-pulse');
    }

    /* ================= EFECTO HOVER PARA CARDS VACÍAS ================= */
    const senderoCards = document.querySelectorAll('.sendero-card');

    senderoCards.forEach(card => {
        card.addEventListener('mousemove', function (e) {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            card.style.setProperty('--mouse-x', `${x}px`);
            card.style.setProperty('--mouse-y', `${y}px`);
        });
    });

    /* ================= LINK DESACTIVADO POR AHORA ================= */
    const disabledLinks = document.querySelectorAll('.sendero-card-link[href="#"]');

    disabledLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
        });
    });
});
