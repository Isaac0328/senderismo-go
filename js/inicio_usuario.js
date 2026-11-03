document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.inicio-personalizado');
    if (!root) return;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let paused = false;
    try { paused = localStorage.getItem('sg-home-motion-paused') === '1'; } catch (_) { /* Storage is optional. */ }
    const motionButton = root.querySelector('[data-motion-toggle]');
    const motionAllowed = () => !paused && !reducedMotion.matches;
    function syncMotion() {
        root.classList.toggle('home-motion-off', !motionAllowed());
        if (motionButton) {
            motionButton.setAttribute('aria-pressed', String(paused || reducedMotion.matches));
            motionButton.setAttribute('aria-label', motionAllowed() ? 'Pausar animaciones' : 'Activar animaciones');
            motionButton.title = reducedMotion.matches ? 'Movimiento reducido por preferencia del dispositivo' : motionButton.getAttribute('aria-label');
            motionButton.disabled = reducedMotion.matches;
            motionButton.innerHTML = window.feather ? feather.icons[motionAllowed() ? 'pause' : 'play'].toSvg({ 'aria-hidden': 'true' }) : (motionAllowed() ? '||' : '>');
        }
    }
    if (motionButton) {
        motionButton.hidden = false;
        motionButton.addEventListener('click', () => {
            paused = !paused;
            try { localStorage.setItem('sg-home-motion-paused', paused ? '1' : '0'); } catch (_) { /* Keep the setting for this page. */ }
            syncMotion();
        });
    }
    reducedMotion.addEventListener('change', syncMotion);
    syncMotion();
    root.classList.add('home-enhanced');
    root.querySelectorAll('.home-level').forEach((level, index) => {
        level.style.setProperty('--home-level-order', index);
    });

    const animateProgress = element => {
        const target = Number(element.dataset.progress);
        if (!Number.isFinite(target) || target <= 0 || !motionAllowed()) return;
        element.value = 0;
        const start = performance.now();
        function tick(now) {
            const t = motionAllowed() ? Math.min(1, (now - start) / 850) : 1;
            element.value = target * (1 - Math.pow(1 - t, 3));
            if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    };

    root.querySelectorAll('[data-home-media]').forEach(frame => {
        const image = frame.querySelector('img');
        if (!image) return;
        const finish = () => frame.classList.remove('is-media-loading');
        frame.classList.add('is-media-loading');
        if (image.complete && image.naturalWidth > 0) {
            requestAnimationFrame(finish);
        } else {
            image.addEventListener('load', finish, { once: true });
            image.addEventListener('error', finish, { once: true });
        }
    });

    const animateCount = element => {
        const target = Number(element.dataset.count);
        const decimals = Number(element.dataset.decimals || 0);
        const format = value => value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        if (!motionAllowed() || target === 0) return;
        const start = performance.now();
        function tick(now) {
            const t = motionAllowed() ? Math.min(1, (now - start) / 900) : 1;
            element.textContent = format(target * (1 - Math.pow(1 - t, 3)));
            if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    };
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('home-revealed');
                entry.target.querySelectorAll('[data-count]').forEach(animateCount);
                entry.target.querySelectorAll('[data-progress]').forEach(animateProgress);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });
        root.querySelectorAll('[data-reveal]').forEach(element => observer.observe(element));
    }

    const gallery = root.querySelector('[data-home-gallery]');
    if (gallery) {
        const slides = Array.from(gallery.querySelectorAll('[data-home-slide]'));
        let index = 0;
        let changing = false;
        const show = step => {
            if (changing || slides.length < 2) return;
            const current = slides[index];
            const nextIndex = (index + step + slides.length) % slides.length;
            const next = slides[nextIndex];
            if (!motionAllowed()) {
                current.hidden = true;
                next.hidden = false;
                index = nextIndex;
                gallery.querySelector('[data-gallery-position]').textContent = `${index + 1} / ${slides.length}`;
                return;
            }
            changing = true;
            next.hidden = false;
            current.classList.add('is-leaving');
            next.classList.add('is-entering');
            requestAnimationFrame(() => {
                current.classList.add('is-leaving-active');
                next.classList.add('is-entering-active');
            });
            window.setTimeout(() => {
                current.hidden = true;
                current.classList.remove('is-leaving', 'is-leaving-active');
                next.classList.remove('is-entering', 'is-entering-active');
                index = nextIndex;
                changing = false;
            }, 460);
            gallery.querySelector('[data-gallery-position]').textContent = `${nextIndex + 1} / ${slides.length}`;
        };
        if (slides.length > 1) {
            gallery.querySelector('.home-gallery-controls').hidden = false;
            gallery.querySelectorAll('[data-gallery-step]').forEach(button => button.addEventListener('click', () => show(Number(button.dataset.galleryStep))));
            gallery.addEventListener('keydown', event => {
                if (!event.target.closest('.home-gallery-controls')) return;
                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                    event.preventDefault();
                    show(event.key === 'ArrowRight' ? 1 : -1);
                }
            });
        }
    }

    root.querySelectorAll('.home-level details').forEach(detail => {
        detail.addEventListener('toggle', () => {
            if (!detail.open) return;
            root.querySelectorAll('.home-level details[open]').forEach(other => { if (other !== detail) other.open = false; });
        });
    });

    const explorer = root.querySelector('[data-route-explorer]');
    if (explorer) {
        const tabs = Array.from(explorer.querySelectorAll('[data-route-tab]'));
        const panels = Array.from(explorer.querySelectorAll('[data-route-panel]'));
        const search = explorer.querySelector('[data-route-search]');
        const province = explorer.querySelector('[data-route-province]');
        let active = explorer.dataset.defaultTab;
        const batchSize = () => window.matchMedia('(max-width: 600px)').matches ? 3 : 6;
        let visibleLimit = batchSize();
        const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
        function filter() {
            const panel = panels.find(item => item.dataset.routePanel === active);
            const cards = Array.from(panel.querySelectorAll('[data-route-card]'));
            const term = normalize(search.value);
            let count = 0;
            cards.forEach(card => {
                const matches = normalize(card.dataset.search).includes(term) && (!province.value || card.dataset.province === province.value);
                card.hidden = !matches || count >= visibleLimit;
                if (matches) count++;
            });
            const shown = Math.min(count, visibleLimit);
            explorer.querySelector('[data-result-count]').textContent = `${shown < count ? shown + ' de ' : ''}${count} ${count === 1 ? 'ruta' : 'rutas'}`;
            explorer.querySelector('[data-no-results]').hidden = count !== 0 || cards.length === 0;
            explorer.querySelector('[data-more-routes]').hidden = shown >= count;
        }
        function activate(value, focus = false) {
            active = value;
            visibleLimit = batchSize();
            tabs.forEach(tab => {
                const selected = tab.dataset.routeTab === active;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                if (selected && focus) tab.focus();
            });
            panels.forEach(panel => {
                panel.hidden = panel.dataset.routePanel !== active;
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', `home-tab-${panel.dataset.routePanel}`);
                panel.tabIndex = 0;
            });
            filter();
        }
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab.dataset.routeTab));
            tab.addEventListener('keydown', event => {
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                if (next === undefined) return;
                event.preventDefault();
                activate(tabs[next].dataset.routeTab, true);
            });
        });
        explorer.querySelector('.home-tabs').hidden = false;
        explorer.querySelector('.home-route-filters').hidden = false;
        explorer.querySelector('form').addEventListener('submit', event => event.preventDefault());
        const resetLimitAndFilter = () => { visibleLimit = batchSize(); filter(); };
        search.addEventListener('input', resetLimitAndFilter);
        province.addEventListener('change', resetLimitAndFilter);
        explorer.querySelector('[data-reset-filters]').addEventListener('click', () => { search.value = ''; province.value = ''; resetLimitAndFilter(); search.focus(); });
        explorer.querySelector('[data-show-more]').addEventListener('click', () => {
            const panel = panels.find(item => item.dataset.routePanel === active);
            const previouslyVisible = new Set(panel.querySelectorAll('[data-route-card]:not([hidden])'));
            visibleLimit += batchSize();
            filter();
            // Keep keyboard navigation at the first newly revealed route.
            const next = Array.from(panel.querySelectorAll('[data-route-card]:not([hidden])')).find(card => !previouslyVisible.has(card));
            next?.querySelector('a')?.focus({ preventScroll: true });
        });
        activate(active);
    }

    const dialog = document.querySelector('[data-share-dialog]');
    const shareTrigger = root.querySelector('[data-open-share]');
    if (dialog && shareTrigger && typeof dialog.showModal === 'function') {
        const text = dialog.querySelector('textarea');
        const status = dialog.querySelector('[data-share-status]');
        shareTrigger.hidden = false;
        shareTrigger.addEventListener('click', () => { status.textContent = ''; dialog.showModal(); });
        dialog.querySelector('[data-close-share]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            const rect = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
        });
        dialog.addEventListener('close', () => shareTrigger.focus());
        const nativeShare = dialog.querySelector('[data-native-share]');
        if (navigator.share) {
            nativeShare.hidden = false;
            nativeShare.addEventListener('click', async () => {
                try { await navigator.share({ title: 'Mi pasaporte senderista', text: text.value }); }
                catch (error) { if (error.name !== 'AbortError') status.textContent = 'No se pudo compartir. Puedes copiar el texto.'; }
            });
        }
        dialog.querySelector('[data-copy-share]').addEventListener('click', async () => {
            try {
                if (!navigator.clipboard) throw new Error('Clipboard unavailable');
                await navigator.clipboard.writeText(text.value);
                status.textContent = 'Texto copiado. Tu aventura merece compartirse.';
            } catch (_) {
                text.focus();
                text.select();
                status.textContent = 'Seleccionamos el texto para que puedas copiarlo.';
            }
        });
    }
});
