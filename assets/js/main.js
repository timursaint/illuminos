/* =====================================================================
   Illuminos — общие клиентские скрипты
   Без фреймворков: нативные функции, каждая отвечает за свой блок.
   ===================================================================== */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    initHeader();
    initBurger();
    initUserMenu();
    initLiveSearch();
    initToasts();
    initPlayer();
    initFavorites();
    initSeasons();
    initFaq();
    initPageLoader();
    initCart();
});

/* Шапка получает фон после прокрутки */
function initHeader() {
    const header = document.getElementById('siteHeader');
    if (!header) return;
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

/* Мобильное меню */
function initBurger() {
    const burger = document.getElementById('burger');
    const nav = document.getElementById('mainNav');
    if (!burger || !nav) return;

    burger.addEventListener('click', () => {
        const open = nav.classList.toggle('is-open');
        burger.classList.toggle('is-active', open);
        burger.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('is-locked', open);
    });
}

/* Выпадающее меню пользователя */
function initUserMenu() {
    const menu = document.getElementById('userMenu');
    if (!menu) return;
    const toggle = menu.querySelector('.user-menu__toggle');
    if (!toggle) return;

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = menu.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            menu.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') menu.classList.remove('is-open');
    });
}

/* Живой поиск: запрос к api/search.php с задержкой 250 мс */
function initLiveSearch() {
    const input = document.getElementById('searchInput');
    const box = document.getElementById('searchResults');
    if (!input || !box) return;

    let timer = null;
    let controller = null;

    const hide = () => {
        box.hidden = true;
        box.innerHTML = '';
    };

    input.addEventListener('input', () => {
        const query = input.value.trim();
        clearTimeout(timer);

        if (query.length < 2) {
            hide();
            return;
        }

        timer = setTimeout(async () => {
            if (controller) controller.abort();
            controller = new AbortController();

            try {
                const response = await fetch(`${window.APP.api}/search.php?q=${encodeURIComponent(query)}`, {
                    signal: controller.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                renderSearch(data.items || []);
            } catch (error) {
                if (error.name !== 'AbortError') hide();
            }
        }, 250);
    });

    function renderSearch(items) {
        if (!items.length) {
            box.innerHTML = '<div class="search__empty">Ничего не найдено. Попробуйте другой запрос.</div>';
            box.hidden = false;
            return;
        }

        box.innerHTML = items.map((item) => `
            <a class="search-result" href="${item.url}">
                <img class="search-result__img" src="${item.poster}" alt="">
                <span>
                    <span class="search-result__title">${item.title}</span>
                    <span class="search-result__meta">${item.meta}</span>
                </span>
            </a>
        `).join('');
        box.hidden = false;
    }

    document.addEventListener('click', (event) => {
        if (!box.contains(event.target) && event.target !== input) hide();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') hide();
    });
}

/* Уведомления исчезают через 4,5 секунды */
function initToasts() {
    document.querySelectorAll('.toast').forEach((toast) => {
        setTimeout(() => {
            toast.classList.add('is-hiding');
            setTimeout(() => toast.remove(), 320);
        }, 4500);
    });
}

/* Модальное окно плеера: кнопки с data-player-src */
function initPlayer() {
    const modal = document.getElementById('playerModal');
    const video = document.getElementById('playerVideo');
    const caption = document.getElementById('playerCaption');
    if (!modal || !video) return;

    const open = (src, text) => {
        video.src = src;
        caption.textContent = text || '';
        modal.hidden = false;
        document.body.classList.add('is-locked');
        video.play().catch(() => {});
    };

    const close = () => {
        video.pause();
        video.removeAttribute('src');
        video.load();
        modal.hidden = true;
        document.body.classList.remove('is-locked');
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-player-src]');
        if (trigger) {
            event.preventDefault();
            open(trigger.dataset.playerSrc, trigger.dataset.playerCaption);
            return;
        }
        if (event.target.closest('[data-close-player]')) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) close();
    });
}

/* Избранное: сердечко на карточке и на странице тайтла */
function initFavorites() {
    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-favorite]');
        if (!btn) return;
        event.preventDefault();

        if (!window.APP_USER) {
            window.location.href = `${window.APP.base}/login.php`;
            return;
        }

        const titleId = btn.dataset.favorite;
        try {
            const response = await fetch(`${window.APP.api}/favorite.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ title_id: titleId }),
            });
            const data = await response.json();
            if (!data.ok) {
                showToast(data.message || 'Не удалось обновить избранное', 'error');
                return;
            }

            btn.classList.toggle('is-active', data.active);
            btn.setAttribute('aria-pressed', String(data.active));
            btn.title = data.active ? 'Убрать из избранного' : 'Добавить в избранное';
            if (btn.dataset.label === 'full') {
                const text = btn.querySelector('.js-fav-text');
                if (text) text.textContent = data.active ? 'В избранном' : 'В избранное';
            }
            showToast(data.active ? 'Добавлено в избранное' : 'Удалено из избранного', 'success');
        } catch (error) {
            showToast('Не удалось обновить избранное', 'error');
        }
    });
}

/* Аккордеон сезонов */
function initSeasons() {
    document.querySelectorAll('.season__toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const season = button.closest('.season');
            if (!season) return;
            const open = season.classList.toggle('is-open');
            button.setAttribute('aria-expanded', String(open));
        });
    });
}

/* FAQ на странице тарифов */
function initFaq() {
    document.querySelectorAll('.faq__q').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('.faq__item');
            if (item) item.classList.toggle('is-open');
        });
    });
}

/* Тонкая полоса загрузки при переходах по ссылкам */
function initPageLoader() {
    const loader = document.getElementById('pageLoader');
    if (!loader) return;

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href') || '';
        if (link.target || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        if (link.hasAttribute('data-player-src') || link.hasAttribute('data-favorite')) return;
        loader.classList.add('is-active');
    });

    window.addEventListener('pageshow', () => loader.classList.remove('is-active'));
}

/* ----------------------- Корзина покупки фильмов ----------------------- */
function initCart() {
    const setBadge = (count) => {
        const badge = document.getElementById('cartBadge');
        if (!badge) return;
        badge.textContent = count;
        badge.hidden = !count;
    };

    const money = (value) => Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 }) + ' ₽';

    const request = async (payload) => {
        const response = await fetch(`${window.APP.api}/cart.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!data.ok && data.loginUrl) {
            window.location.href = data.loginUrl;
            return null;
        }
        return data;
    };

    /* Пересчёт сумм на странице корзины */
    const refreshCartPage = () => {
        const items = document.querySelectorAll('[data-cart-item]');
        const positions = document.getElementById('cartPositions');
        if (!positions) return;

        if (!items.length) {
            window.location.reload();
            return;
        }

        let total = 0;
        let count = 0;
        items.forEach((item) => {
            const price = Number(item.dataset.price);
            const quantity = Number(item.dataset.quantity);
            total += price * quantity;
            count += quantity;

            const line = item.querySelector('[data-line-total]');
            if (line) line.textContent = money(price * quantity);
        });

        positions.textContent = items.length;
        const units = document.getElementById('cartUnits');
        if (units) units.textContent = count;
        const totalNode = document.getElementById('cartTotal');
        const grandTotal = document.getElementById('cartGrandTotal');
        if (totalNode) totalNode.textContent = money(total);
        if (grandTotal) grandTotal.textContent = money(total);
    };

    document.addEventListener('click', async (event) => {
        /* Добавление из карточки или со страницы тайтла */
        const addButton = event.target.closest('[data-cart-add]');
        if (addButton) {
            event.preventDefault();
            const data = await request({ action: 'add', title_id: addButton.dataset.cartAdd });
            if (!data) return;
            if (!data.ok) {
                showToast(data.message || 'Не удалось добавить в корзину', 'error');
                return;
            }
            setBadge(data.count);
            showToast(data.message, 'success');
            addButton.classList.add('is-active');
            return;
        }

        /* Удаление позиции */
        const removeButton = event.target.closest('[data-cart-remove]');
        if (removeButton) {
            const item = removeButton.closest('[data-cart-item]');
            if (!item) return;
            const data = await request({ action: 'remove', title_id: item.dataset.titleId });
            if (!data) return;
            setBadge(data.count);
            item.remove();
            refreshCartPage();
            showToast(data.message, 'success');
            return;
        }

        /* Изменение количества */
        const stepButton = event.target.closest('[data-qty-plus], [data-qty-minus]');
        if (stepButton) {
            const item = stepButton.closest('[data-cart-item]');
            if (!item) return;
            const valueNode = item.querySelector('[data-qty-value]');
            let quantity = Number(valueNode.textContent);
            quantity += stepButton.hasAttribute('data-qty-plus') ? 1 : -1;
            quantity = Math.max(1, Math.min(5, quantity));

            const data = await request({ action: 'update', title_id: item.dataset.titleId, quantity });
            if (!data) return;
            valueNode.textContent = quantity;
            item.dataset.quantity = quantity;
            setBadge(data.count);
            refreshCartPage();
            return;
        }

        /* Очистка корзины */
        if (event.target.closest('#cartClear')) {
            const data = await request({ action: 'clear' });
            if (!data) return;
            setBadge(0);
            window.location.reload();
        }
    });
}

/* Универсальное всплывающее сообщение (для AJAX-действий) */
function showToast(text, type = 'success') {
    let container = document.getElementById('toasts');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toasts';
        container.className = 'toasts';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.textContent = text;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('is-hiding');
        setTimeout(() => toast.remove(), 320);
    }, 4500);
}

window.showToast = showToast;
